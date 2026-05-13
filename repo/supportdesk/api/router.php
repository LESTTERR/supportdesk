<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/crypto.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    sendJson(['success' => true]);
}

try {
    dispatchRequest($pdo, $method, getRouteSegments());
} catch (Throwable $e) {
    sendJson(['success' => false, 'error' => 'Server error'], 500);
}

function dispatchRequest(PDO $pdo, string $method, array $segments): void
{
    if ($segments === ['auth', 'register'] && $method === 'POST') {
        registerUser($pdo);
    }

    if ($segments === ['auth', 'login'] && $method === 'POST') {
        loginUser($pdo);
    }

    if ($segments === ['users', 'profile']) {
        if ($method === 'GET') {
            getProfile($pdo);
        }
        if ($method === 'PUT' || $method === 'PATCH') {
            updateProfile($pdo);
        }
        methodNotAllowed();
    }

    if (($segments[0] ?? '') === 'tickets') {
        routeTickets($pdo, $method, $segments);
    }

    if ($segments === ['admin', 'tickets'] && $method === 'GET') {
        requireAdmin($pdo);
        sendJson(['success' => true, 'tickets' => listTickets($pdo, null)]);
    }

    if ($segments === ['reports', 'ticket-status'] && $method === 'GET') {
        requireAdmin($pdo);
        ticketStatusReport($pdo);
    }

    if ($segments === ['reports', 'response-time'] && $method === 'GET') {
        requireAdmin($pdo);
        responseTimeReport($pdo);
    }

    sendJson(['success' => false, 'error' => 'Endpoint not found'], 404);
}

function routeTickets(PDO $pdo, string $method, array $segments): void
{
    $currentUser = requireAuthenticated($pdo);

    if (count($segments) === 1) {
        if ($method === 'GET') {
            $tickets = isStaff($currentUser) ? listTickets($pdo, null) : listTickets($pdo, (int) $currentUser['id']);
            sendJson(['success' => true, 'tickets' => $tickets]);
        }
        if ($method === 'POST') {
            createTicket($pdo, $currentUser);
        }
        methodNotAllowed();
    }

    if (count($segments) === 2 && ctype_digit($segments[1])) {
        if ($method !== 'GET') {
            methodNotAllowed();
        }
        $ticket = fetchTicket($pdo, (int) $segments[1]);
        if (!$ticket) {
            sendJson(['success' => false, 'error' => 'Ticket not found'], 404);
        }
        ensureCanViewTicket($currentUser, $ticket);
        sendJson(['success' => true, 'ticket' => $ticket]);
    }

    if (count($segments) === 3 && $segments[1] === 'user' && ctype_digit($segments[2]) && $method === 'GET') {
        $userId = (int) $segments[2];
        if (!isStaff($currentUser) && $userId !== (int) $currentUser['id']) {
            sendJson(['success' => false, 'error' => 'Forbidden'], 403);
        }
        sendJson(['success' => true, 'tickets' => listTickets($pdo, $userId)]);
    }

    if (count($segments) === 3 && ctype_digit($segments[1]) && $segments[2] === 'status') {
        if ($method !== 'PUT' && $method !== 'PATCH') {
            methodNotAllowed();
        }
        updateTicketStatus($pdo, $currentUser, (int) $segments[1]);
    }

    if (count($segments) === 3 && ctype_digit($segments[1]) && $segments[2] === 'reply') {
        if ($method !== 'POST') {
            methodNotAllowed();
        }
        addTicketReply($pdo, $currentUser, (int) $segments[1]);
    }

    sendJson(['success' => false, 'error' => 'Endpoint not found'], 404);
}

function registerUser(PDO $pdo): void
{
    $data = readBody();
    $name = trim((string) ($data['name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');
    $phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
    $address = isset($data['address']) ? trim((string) $data['address']) : null;

    if ($name === '' || $email === '' || $password === '') {
        sendJson(['success' => false, 'error' => 'Name, email, and password are required'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(['success' => false, 'error' => 'Please enter a valid email address'], 400);
    }
    if (strlen($password) < 6) {
        sendJson(['success' => false, 'error' => 'Password must be at least 6 characters'], 400);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJson(['success' => false, 'error' => 'User with this email already exists'], 409);
    }

    [$phoneEnc, $phoneIv, $phoneTag] = encryptSensitive($phone);
    [$addressEnc, $addressIv, $addressTag] = encryptSensitive($address);

    $stmt = $pdo->prepare("
        INSERT INTO users
            (name, email, password, role, phone_enc, phone_iv, phone_tag, address_enc, address_iv, address_tag)
        VALUES
            (?, ?, ?, 'customer', ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $name,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $phoneEnc,
        $phoneIv,
        $phoneTag,
        $addressEnc,
        $addressIv,
        $addressTag,
    ]);

    $user = loadUser($pdo, (int) $pdo->lastInsertId());
    setSessionUser($user);
    sendJson(['success' => true, 'user' => publicUser($user)], 201);
}

function loginUser(PDO $pdo): void
{
    $data = readBody();
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');

    if ($email === '' || $password === '') {
        sendJson(['success' => false, 'error' => 'Email and password are required'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        sendJson(['success' => false, 'error' => 'Invalid credentials'], 401);
    }

    setSessionUser($user);
    sendJson(['success' => true, 'user' => publicUser($user)]);
}

function getProfile(PDO $pdo): void
{
    $user = requireAuthenticated($pdo);
    sendJson(['success' => true, 'user' => publicUser($user, true)]);
}

function updateProfile(PDO $pdo): void
{
    $user = requireAuthenticated($pdo);
    $data = readBody();
    $sets = [];
    $params = [];

    if (array_key_exists('name', $data)) {
        $name = trim((string) $data['name']);
        if ($name === '') {
            sendJson(['success' => false, 'error' => 'Name cannot be empty'], 400);
        }
        $sets[] = 'name = ?';
        $params[] = $name;
    }

    if (array_key_exists('email', $data)) {
        $email = strtolower(trim((string) $data['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJson(['success' => false, 'error' => 'Please enter a valid email address'], 400);
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            sendJson(['success' => false, 'error' => 'User with this email already exists'], 409);
        }
        $sets[] = 'email = ?';
        $params[] = $email;
    }

    if (array_key_exists('phone', $data)) {
        [$enc, $iv, $tag] = encryptSensitive(trim((string) $data['phone']));
        $sets[] = 'phone_enc = ?';
        $sets[] = 'phone_iv = ?';
        $sets[] = 'phone_tag = ?';
        array_push($params, $enc, $iv, $tag);
    }

    if (array_key_exists('address', $data)) {
        [$enc, $iv, $tag] = encryptSensitive(trim((string) $data['address']));
        $sets[] = 'address_enc = ?';
        $sets[] = 'address_iv = ?';
        $sets[] = 'address_tag = ?';
        array_push($params, $enc, $iv, $tag);
    }

    if (!$sets) {
        sendJson(['success' => false, 'error' => 'No profile changes provided'], 400);
    }

    $params[] = $user['id'];
    $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($params);

    $updated = loadUser($pdo, (int) $user['id']);
    setSessionUser($updated);
    sendJson(['success' => true, 'user' => publicUser($updated, true)]);
}

function createTicket(PDO $pdo, array $currentUser): void
{
    $data = readBody();
    $subject = trim((string) ($data['subject'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $priority = normalizePriority((string) ($data['priority'] ?? 'medium'));
    $categoryId = resolveCategoryId($pdo, $data);
    $requesterId = (int) $currentUser['id'];

    if (isStaff($currentUser) && isset($data['user_id'])) {
        $requesterId = (int) $data['user_id'];
        if (!userExists($pdo, $requesterId)) {
            sendJson(['success' => false, 'error' => 'Requester user not found'], 404);
        }
    }

    if ($subject === '' || $description === '') {
        sendJson(['success' => false, 'error' => 'Subject and description are required'], 400);
    }
    if ($priority === '') {
        sendJson(['success' => false, 'error' => 'Invalid priority'], 400);
    }

    $stmt = $pdo->prepare("
        INSERT INTO tickets (user_id, category_id, subject, description, priority, status)
        VALUES (?, ?, ?, ?, ?, 'open')
    ");
    $stmt->execute([$requesterId, $categoryId, $subject, $description, $priority]);

    $ticket = fetchTicket($pdo, (int) $pdo->lastInsertId());
    sendJson(['success' => true, 'ticket' => $ticket], 201);
}

function updateTicketStatus(PDO $pdo, array $currentUser, int $ticketId): void
{
    if (!isStaff($currentUser)) {
        sendJson(['success' => false, 'error' => 'Only agents and admins can update ticket status'], 403);
    }

    $ticket = fetchTicket($pdo, $ticketId);
    if (!$ticket) {
        sendJson(['success' => false, 'error' => 'Ticket not found'], 404);
    }

    $data = readBody();
    $status = normalizeStatus((string) ($data['status'] ?? ''));
    if ($status === '') {
        sendJson(['success' => false, 'error' => 'Invalid status'], 400);
    }

    $stmt = $pdo->prepare('UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $ticketId]);

    sendJson(['success' => true, 'ticket' => fetchTicket($pdo, $ticketId)]);
}

function addTicketReply(PDO $pdo, array $currentUser, int $ticketId): void
{
    $ticket = fetchTicket($pdo, $ticketId);
    if (!$ticket) {
        sendJson(['success' => false, 'error' => 'Ticket not found'], 404);
    }
    ensureCanViewTicket($currentUser, $ticket);

    $data = readBody();
    $message = trim((string) ($data['message'] ?? ''));
    if ($message === '') {
        sendJson(['success' => false, 'error' => 'Reply message is required'], 400);
    }

    $stmt = $pdo->prepare('INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)');
    $stmt->execute([$ticketId, $currentUser['id'], $message]);
    $pdo->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?')->execute([$ticketId]);

    sendJson(['success' => true, 'ticket' => fetchTicket($pdo, $ticketId)], 201);
}

function listTickets(PDO $pdo, ?int $userId): array
{
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
    ";
    $params = [];

    if ($userId !== null) {
        $sql .= ' WHERE t.user_id = ?';
        $params[] = $userId;
    }

    $sql .= ' ORDER BY t.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
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

function ticketStatusReport(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT status, COUNT(*) AS total FROM tickets GROUP BY status");
    $totals = [
        'open' => 0,
        'in_progress' => 0,
        'resolved' => 0,
        'closed' => 0,
    ];

    foreach ($stmt->fetchAll() as $row) {
        $totals[$row['status']] = (int) $row['total'];
    }

    sendJson(['success' => true, 'status_counts' => $totals]);
}

function responseTimeReport(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS responded_tickets,
            ROUND(AVG(TIMESTAMPDIFF(MINUTE, t.created_at, first_reply.first_agent_reply_at)), 2) AS average_minutes
        FROM tickets t
        JOIN (
            SELECT tr.ticket_id, MIN(tr.created_at) AS first_agent_reply_at
            FROM ticket_replies tr
            JOIN users u ON tr.user_id = u.id
            WHERE u.role IN ('agent', 'admin')
            GROUP BY tr.ticket_id
        ) first_reply ON first_reply.ticket_id = t.id
    ");
    $row = $stmt->fetch() ?: ['responded_tickets' => 0, 'average_minutes' => null];

    sendJson([
        'success' => true,
        'responded_tickets' => (int) $row['responded_tickets'],
        'average_minutes' => $row['average_minutes'] === null ? null : (float) $row['average_minutes'],
    ]);
}

function getRouteSegments(): array
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $path = str_replace('\\', '/', $path);
    $apiMarker = '/api/';
    $position = strpos($path, $apiMarker);
    $route = $position === false ? trim($path, '/') : substr($path, $position + strlen($apiMarker));
    $route = trim($route, '/');

    if ($route === 'router.php' || $route === '') {
        return [];
    }

    return array_values(array_filter(explode('/', $route), static fn ($part) => $part !== ''));
}

function readBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (is_array($data)) {
        return $data;
    }

    return $_POST ?: [];
}

function requireAuthenticated(PDO $pdo): array
{
    if (!isset($_SESSION['user']['id'])) {
        sendJson(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    $user = loadUser($pdo, (int) $_SESSION['user']['id']);
    if (!$user) {
        unset($_SESSION['user']);
        sendJson(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    return $user;
}

function requireAdmin(PDO $pdo): array
{
    $user = requireAuthenticated($pdo);
    if (($user['role'] ?? '') !== 'admin') {
        sendJson(['success' => false, 'error' => 'Admin access required'], 403);
    }
    return $user;
}

function loadUser(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function userExists(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn() > 0;
}

function publicUser(array $user, bool $includeProfileFields = false): array
{
    $payload = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'created_at' => $user['created_at'] ?? null,
    ];

    if ($includeProfileFields) {
        $payload['phone'] = decryptField($user, 'phone');
        $payload['address'] = decryptField($user, 'address');
    }

    return $payload;
}

function decryptField(array $user, string $field): ?string
{
    try {
        return decryptSensitive(
            $user[$field . '_enc'] ?? null,
            $user[$field . '_iv'] ?? null,
            $user[$field . '_tag'] ?? null
        );
    } catch (Throwable $e) {
        return null;
    }
}

function setSessionUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

function resolveCategoryId(PDO $pdo, array $data): ?int
{
    if (isset($data['category_id']) && $data['category_id'] !== '') {
        $categoryId = (int) $data['category_id'];
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $stmt->execute([$categoryId]);
        return $stmt->fetch() ? $categoryId : null;
    }

    $category = trim((string) ($data['category'] ?? ''));
    if ($category === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ?');
    $stmt->execute([$category]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function ensureCanViewTicket(array $currentUser, array $ticket): void
{
    if (!isStaff($currentUser) && (int) $ticket['user_id'] !== (int) $currentUser['id']) {
        sendJson(['success' => false, 'error' => 'Forbidden'], 403);
    }
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

function normalizeStatus(string $status): string
{
    $status = strtolower(trim(str_replace(' ', '_', $status)));
    return in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true) ? $status : '';
}

function methodNotAllowed(): void
{
    sendJson(['success' => false, 'error' => 'Method not allowed'], 405);
}

function sendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}
?>
