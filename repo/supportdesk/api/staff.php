<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET' && $action === 'setup-status') {
        jsonResponse(['success' => true, 'has_admin' => adminExists($pdo)]);
    }

    if ($method === 'POST') {
        $data = readJsonBody();
        if (($data['action'] ?? '') === 'setup-admin') {
            setupFirstAdmin($pdo, $data);
        }
    }

    $currentUser = $_SESSION['user'] ?? null;
    if (!$currentUser || ($currentUser['role'] ?? '') !== 'admin') {
        jsonResponse(['success' => false, 'error' => 'Only admins can manage staff accounts'], 403);
    }

    if ($method === 'GET') {
        listStaff($pdo);
    } elseif ($method === 'POST') {
        $data = readJsonBody();
        createStaff($pdo, $data);
    } else {
        jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'Server error'], 500);
}

function setupFirstAdmin(PDO $pdo, array $data): void
{
    if (adminExists($pdo)) {
        jsonResponse(['success' => false, 'error' => 'An admin account already exists. Please sign in as admin.'], 409);
    }

    $user = createUser($pdo, $data, 'admin');
    session_regenerate_id(true);
    $_SESSION['user'] = $user;

    jsonResponse(['success' => true, 'user' => $user], 201);
}

function listStaff(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT id, name, email, role, created_at
        FROM users
        WHERE role IN ('agent', 'admin')
        ORDER BY role, name
    ");
    jsonResponse(['success' => true, 'staff' => $stmt->fetchAll()]);
}

function createStaff(PDO $pdo, array $data): void
{
    $role = $data['role'] ?? '';
    if (!in_array($role, ['agent', 'admin'], true)) {
        jsonResponse(['success' => false, 'error' => 'Choose agent or admin role'], 400);
    }

    $user = createUser($pdo, $data, $role);
    jsonResponse(['success' => true, 'staff_user' => $user], 201);
}

function createUser(PDO $pdo, array $data, string $role): array
{
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        jsonResponse(['success' => false, 'error' => 'Name, email, and password are required'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'error' => 'Please enter a valid email address'], 400);
    }

    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'error' => 'Password must be at least 6 characters'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'A user with this email already exists'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hash, $role]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'name' => $name,
        'email' => $email,
        'role' => $role,
    ];
}

function adminExists(PDO $pdo): bool
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    return (int) $stmt->fetchColumn() > 0;
}

function readJsonBody(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}
?>
