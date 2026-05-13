<?php
$encryptionKeyHex = getenv('ENCRYPTION_KEY_HEX') ?: 'd486c4e84557756a7a316a1d35247194cc9193a96675487c3281e35e9d040377';

if (!preg_match('/^[a-f0-9]{64}$/i', $encryptionKeyHex)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid encryption key configuration. ENCRYPTION_KEY_HEX must be 64 hex characters.'
    ]);
    exit;
}

define('ENCRYPTION_KEY', hex2bin($encryptionKeyHex));
?>
