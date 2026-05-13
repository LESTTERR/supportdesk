<?php
require_once __DIR__ . '/../config/security.php';

function encryptSensitive(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [null, null, null];
    }

    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($value, 'aes-256-gcm', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv, $tag);

    if ($ciphertext === false) {
        throw new RuntimeException('Sensitive data encryption failed');
    }

    return [
        base64_encode($ciphertext),
        base64_encode($iv),
        base64_encode($tag),
    ];
}

function decryptSensitive(?string $ciphertext, ?string $iv, ?string $tag): ?string
{
    if ($ciphertext === null || $iv === null || $tag === null || $ciphertext === '' || $iv === '' || $tag === '') {
        return null;
    }

    $decodedCiphertext = base64_decode($ciphertext, true);
    $decodedIv = base64_decode($iv, true);
    $decodedTag = base64_decode($tag, true);

    if ($decodedCiphertext === false || $decodedIv === false || $decodedTag === false) {
        throw new RuntimeException('Sensitive data is not valid base64');
    }

    $plaintext = openssl_decrypt(
        $decodedCiphertext,
        'aes-256-gcm',
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $decodedIv,
        $decodedTag
    );

    if ($plaintext === false) {
        throw new RuntimeException('Sensitive data decryption failed');
    }

    return $plaintext;
}
?>
