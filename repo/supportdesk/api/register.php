<?php
header('Content-Type: application/json');
session_start();
require_once '../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$role = $data['role'] ?? 'agent';

// Basic validation
if (!$name || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name, email, and password are required']);
    exit;
}

// Check if user already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'User with this email already exists']);
    exit;
}

// Hash password
$hash = password_hash($password, PASSWORD_DEFAULT);

// Insert new user
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
try {
    $stmt->execute([$name, $email, $hash, $role]);
    $userId = $pdo->lastInsertId();
    
    // Set session
    $_SESSION['user'] = [
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'role' => $role
    ];
    
    echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed']);
}
?>
