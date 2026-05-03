<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    if ($id) {
        $stmt = $pdo->prepare("SELECT t.*, u.name as user_name, u.email as user_email, c.name as category_name FROM tickets t JOIN users u ON t.user_id = u.id LEFT JOIN categories c ON t.category_id = c.id WHERE t.id = ?");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();

        if ($ticket) {
            $ticket['attachments'] = [];
            try {
                $attachStmt = $pdo->prepare("SELECT id, file_name, file_path, file_size, mime_type, uploaded_at FROM attachments WHERE ticket_id = ? ORDER BY uploaded_at DESC");
                $attachStmt->execute([$id]);
                $ticket['attachments'] = $attachStmt->fetchAll();
            } catch (Exception $e) {
                // attachments table may not exist yet
            }
            echo json_encode($ticket);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Ticket not found']);
        }
        exit;
    }

    $sql = "SELECT t.*, u.name as user_name, u.email as user_email, c.name as category_name FROM tickets t JOIN users u ON t.user_id = u.id LEFT JOIN categories c ON t.category_id = c.id WHERE 1=1";
    $params = [];

    if ($status && $status !== 'all') {
        $status = str_replace(' ', '_', $status);
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }
    if ($search) {
        $sql .= " AND (t.subject LIKE ? OR t.id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $like = "%$search%";
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    $sql .= " ORDER BY t.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    echo json_encode($tickets);
}
elseif ($method === 'POST') {
    // Multipart form data
    $user_name = $_POST['full_name'] ?? '';
    $user_email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $category_name = $_POST['category'] ?? '';
    $priority = strtolower($_POST['priority'] ?? 'medium');
    $description = $_POST['description'] ?? '';

    if (!$subject || !$description || !$user_name || !$user_email) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Check if user exists, if not create as customer
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$user_email]);
        $user = $stmt->fetch();
        if (!$user) {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, '', 'customer')");
            $stmt->execute([$user_name, $user_email]);
            $user_id = $pdo->lastInsertId();
        } else {
            $user_id = $user['id'];
        }

        // Get category_id
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$category_name]);
        $category = $stmt->fetch();
        $category_id = $category ? $category['id'] : null;

        $stmt = $pdo->prepare("INSERT INTO tickets (user_id, category_id, subject, description, priority, status) VALUES (?, ?, ?, ?, ?, 'open')");
        $stmt->execute([$user_id, $category_id, $subject, $description, $priority]);
        $ticketId = $pdo->lastInsertId();

        // Handle file uploads
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/ticket_' . $ticketId . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            foreach ($_FILES['attachments']['tmp_name'] as $i => $tmpName) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $origName = basename($_FILES['attachments']['name'][$i]);
                    $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
                    $filePath = $uploadDir . $safeName;
                    move_uploaded_file($tmpName, $filePath);

                    // Store in database
                    $sql = "INSERT INTO attachments (ticket_id, user_id, file_name, file_path, file_size, mime_type) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $ticketId,
                        $user_id,
                        $origName,
                        'uploads/ticket_' . $ticketId . '/' . $safeName,
                        $_FILES['attachments']['size'][$i],
                        $_FILES['attachments']['type'][$i]
                    ]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'ticket_id' => $ticketId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create ticket']);
    }
}
?>
