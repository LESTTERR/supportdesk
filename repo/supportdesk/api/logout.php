<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json');
session_destroy();
echo json_encode(['success' => true]);
?>
