<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user'])) {
    jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
}

$currentUser = $_SESSION['user'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        handleGet($pdo, $currentUser);
    } elseif ($method === 'POST') {
        handlePost($pdo, $currentUser);
    } elseif ($method === 'PATCH' || $method === 'PUT') {
        handleUpdate($pdo, $currentUser);
    } else {
        jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'Server error'], 500);
}

function handleGet(PDO $pdo, array $currentUser): void
{
    $action = $_GET['action'] ?? '';

    if ($action === 'agents') {
        if (!isStaff($currentUser)) {
            jsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $stmt = $pdo->query("
            SELECT id, name, email, role
            FROM users
            WHERE role IN ('agent', 'admin')
            ORDER BY name
        ");
        jsonResponse(['success' => true, 'agents' => $stmt->fetchAll()]);
    }

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $ticket = fetchTicket($pdo, $id);
        if (!$ticket) {
            jsonResponse(['success' => false, 'error' => 'Ticket not found'], 404);
        }
        if (!canViewTicket($currentUser, $ticket)) {
            jsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }
        jsonResponse($ticket);
    }

    $status = normalizeStatus($_GET['status'] ?? '', true);
    $search = trim($_GET['search'] ?? '');

    $sql = "
        SELECT
            t.*,
            requester.name AS user_name,
            requester.email AS user_email,
            assignee.name AS assignee_name,
            assignee.email AS assignee_email,
            c.name AS category_name
        FROM tickets t
        JOIN users requester ON t.user_id = requester.id
        LEFT JOIN users assignee ON t.assignee_id = assignee.id
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE 1 = 1
    ";
    $params = [];

    if (!isStaff($currentUser)) {
        $sql .= " AND t.user_id = ?";
        $params[] = $currentUser['id'];
    }

    if ($status !== '') {
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }

    if ($search !== '') {
        $like = "%$search%";
        $sql .= " AND (t.subject LIKE ? OR CAST(t.id AS CHAR) LIKE ? OR requester.name LIKE ? OR requester.email LIKE ? OR assignee.name LIKE ?)";
        array_push($params, $like, $like, $like, $like, $like);
    }

    $sql .= " ORDER BY t.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse($stmt->fetchAll());
}

function handlePost(PDO $pdo, array $currentUser): void
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $data = readJsonBody();
        if (($data['action'] ?? '') === 'reply') {
            addReply($pdo, $currentUser, $data);
        }
        jsonResponse(['success' => false, 'error' => 'Unsupported action'], 400);
    }

    createTicket($pdo, $currentUser);
}

function createTicket(PDO $pdo, array $currentUser): void
{
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $categoryName = trim($_POST['category'] ?? '');
    $priority = normalizePriority($_POST['priority'] ?? 'medium');
    $description = trim($_POST['description'] ?? '');

    if (!isStaff($currentUser)) {
        $fullName = $currentUser['name'];
        $email = $currentUser['email'];
    }

    if ($fullName === '' || $email === '' || $subject === '' || $description === '') {
        jsonResponse(['success' => false, 'error' => 'Name, email, subject, and description are required'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'error' => 'Please enter a valid email address'], 400);
    }

    if ($priority === '') {
        jsonResponse(['success' => false, 'error' => 'Invalid priority'], 400);
    }

    $pdo->beginTransaction();

    try {
        $requesterId = findOrCreateCustomer($pdo, $fullName, $email);
        $categoryId = findCategoryId($pdo, $categoryName);

        $stmt = $pdo->prepare("
            INSERT INTO tickets (user_id, category_id, subject, description, priority, status)
            VALUES (?, ?, ?, ?, ?, 'open')
        ");
        $stmt->execute([$requesterId, $categoryId, $subject, $description, $priority]);
        $ticketId = (int) $pdo->lastInsertId();

        saveAttachments($pdo, $ticketId, (int) $currentUser['id']);

        $pdo->commit();
        jsonResponse(['success' => true, 'ticket_id' => $ticketId], 201);
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'error' => 'Failed to create ticket'], 500);
    }
}

function addReply(PDO $pdo, array $currentUser, array $data): void
{
    $ticketId = (int) ($data['ticket_id'] ?? 0);
    $message = trim($data['message'] ?? '');

    if ($ticketId <= 0 || $message === '') {
        jsonResponse(['success' => false, 'error' => 'Ticket and message are required'], 400);
    }

    $ticket = fetchTicket($pdo, $ticketId);
    if (!$ticket) {
        jsonResponse(['success' => false, 'error' => 'Ticket not found'], 404);
    }
    if (!canViewTicket($currentUser, $ticket)) {
        jsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
    }

    $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$ticketId, $currentUser['id'], $message]);

    $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
    jsonResponse(['success' => true, 'ticket' => fetchTicket($pdo, $ticketId)], 201);
}

function handleUpdate(PDO $pdo, array $currentUser): void
{
    if (!isStaff($currentUser)) {
        jsonResponse(['success' => false, 'error' => 'Only agents and admins can update tickets'], 403);
    }

    $data = readJsonBody();
    $ticketId = (int) ($_GET['id'] ?? ($data['id'] ?? 0));
    if ($ticketId <= 0) {
        jsonResponse(['success' => false, 'error' => 'Ticket ID is required'], 400);
    }

    $ticket = fetchTicket($pdo, $ticketId);
    if (!$ticket) {
        jsonResponse(['success' => false, 'error' => 'Ticket not found'], 404);
    }

    $sets = [];
    $params = [];

    if (array_key_exists('status', $data)) {
        $status = normalizeStatus($data['status'] ?? '', false);
        if ($status === '') {
            jsonResponse(['success' => false, 'error' => 'Invalid status'], 400);
        }
        $sets[] = 'status = ?';
        $params[] = $status;
    }

    if (array_key_exists('priority', $data)) {
        $priority = normalizePriority($data['priority'] ?? '');
        if ($priority === '') {
            jsonResponse(['success' => false, 'error' => 'Invalid priority'], 400);
        }
        $sets[] = 'priority = ?';
        $params[] = $priority;
    }

    if (array_key_exists('assignee_id', $data)) {
        $assigneeId = $data['assignee_id'];
        if ($assigneeId === '' || $assigneeId === null) {
            $sets[] = 'assignee_id = NULL';
        } else {
            $assigneeId = (int) $assigneeId;
            if (!isAssignableUser($pdo, $assigneeId)) {
                jsonResponse(['success' => false, 'error' => 'Selected assignee is not an agent or admin'], 400);
            }
            $sets[] = 'assignee_id = ?';
            $params[] = $assigneeId;
        }
    }

    if (!$sets) {
        jsonResponse(['success' => false, 'error' => 'No changes provided'], 400);
    }

    $sets[] = 'updated_at = NOW()';
    $params[] = $ticketId;
    $stmt = $pdo->prepare('UPDATE tickets SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($params);

    jsonResponse(['success' => true, 'ticket' => fetchTicket($pdo, $ticketId)]);
}

function fetchTicket(PDO $pdo, int $ticketId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            t.*,
            requester.name AS user_name,
            requester.email AS user_email,
            assignee.name AS assignee_name,
            assignee.email AS assignee_email,
            c.name AS category_name
        FROM tickets t
        JOIN users requester ON t.user_id = requester.id
        LEFT JOIN users assignee ON t.assignee_id = assignee.id
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT id, file_name, file_path, file_size, mime_type, uploaded_at
        FROM attachments
        WHERE ticket_id = ?
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([$ticketId]);
    $ticket['attachments'] = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT tr.id, tr.message, tr.created_at, u.name AS user_name, u.email AS user_email, u.role AS user_role
        FROM ticket_replies tr
        JOIN users u ON tr.user_id = u.id
        WHERE tr.ticket_id = ?
        ORDER BY tr.created_at ASC
    ");
    $stmt->execute([$ticketId]);
    $ticket['replies'] = $stmt->fetchAll();

    return $ticket;
}

function saveAttachments(PDO $pdo, int $ticketId, int $uploadedBy): void
{
    if (empty($_FILES['attachments']) || !is_array($_FILES['attachments']['name'])) {
        return;
    }

    $allowedMimeTypes = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'application/pdf' => 'pdf',
    ];
    $maxSize = 10 * 1024 * 1024;
    $uploadDir = dirname(__DIR__) . '/uploads/ticket_' . $ticketId . '/';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Unable to create upload directory');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($_FILES['attachments']['tmp_name'] as $index => $tmpName) {
        if ($_FILES['attachments']['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($_FILES['attachments']['error'][$index] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed');
        }
        if ($_FILES['attachments']['size'][$index] > $maxSize) {
            throw new RuntimeException('File is too large');
        }

        $mimeType = $finfo->file($tmpName);
        if (!isset($allowedMimeTypes[$mimeType])) {
            throw new RuntimeException('File type not allowed');
        }

        $originalName = basename($_FILES['attachments']['name'][$index]);
        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $safeName = bin2hex(random_bytes(8)) . '_' . $cleanName;
        $absolutePath = $uploadDir . $safeName;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException('Unable to save uploaded file');
        }

        $relativePath = 'uploads/ticket_' . $ticketId . '/' . $safeName;
        $stmt = $pdo->prepare("
            INSERT INTO attachments (ticket_id, user_id, file_name, file_path, file_size, mime_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ticketId,
            $uploadedBy,
            $originalName,
            $relativePath,
            $_FILES['attachments']['size'][$index],
            $mimeType,
        ]);
    }
}

function findOrCreateCustomer(PDO $pdo, string $name, string $email): int
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        return (int) $user['id'];
    }

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, '', 'customer')");
    $stmt->execute([$name, $email]);
    return (int) $pdo->lastInsertId();
}

function findCategoryId(PDO $pdo, string $categoryName): ?int
{
    if ($categoryName === '') {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$categoryName]);
    $category = $stmt->fetch();

    return $category ? (int) $category['id'] : null;
}

function isAssignableUser(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role IN ('agent', 'admin')");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn() > 0;
}

function canViewTicket(array $currentUser, array $ticket): bool
{
    return isStaff($currentUser) || (int) $ticket['user_id'] === (int) $currentUser['id'];
}

function isStaff(array $user): bool
{
    return in_array($user['role'] ?? '', ['agent', 'admin'], true);
}

function normalizePriority(string $priority): string
{
    $priority = strtolower(trim($priority));
    return in_array($priority, ['low', 'medium', 'high', 'critical'], true) ? $priority : '';
}

function normalizeStatus(string $status, bool $allowAll): string
{
    $status = strtolower(trim(str_replace(' ', '_', $status)));
    if ($allowAll && ($status === '' || $status === 'all')) {
        return '';
    }
    return in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true) ? $status : '';
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}
?>
