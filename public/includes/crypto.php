<?php

function getAppKey(): string {
    $raw = $_ENV['APP_KEY'] ?? getenv('APP_KEY') ?? '';

    if (empty($raw)) {
        throw new RuntimeException('APP_KEY is not set in environment.');
    }

    // Remove surrounding quotes if present (from INI parsing)
    if ((str_starts_with($raw, '"') && str_ends_with($raw, '"')) ||
        (str_starts_with($raw, "'") && str_ends_with($raw, "'"))) {
        $raw = substr($raw, 1, -1);
    }

    // Strip the "base64:" prefix if present
    if (str_starts_with($raw, 'base64:')) {
        $key = base64_decode(substr($raw, 7));
    } else {
        $key = $raw;
    }

    if (strlen($key) !== 32) {
        throw new RuntimeException('APP_KEY must be exactly 32 bytes.');
    }

    return $key;
}

function encryptMessage(string $plaintext): string {
    $key = getAppKey();
    $iv = openssl_random_pseudo_bytes(16); // AES block size is 16 bytes
    
    $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed: ' . openssl_error_string());
    }

    // Store IV + ciphertext together, base64-encoded for DB storage
    return base64_encode($iv . $ciphertext);
}

function decryptMessage(string $stored): string {
    try {
        $key = getAppKey();
        $decoded = base64_decode($stored, true);

        if ($decoded === false || strlen($decoded) < 16) {
            return '[Message could not be decrypted - invalid format]';
        }

        $iv = substr($decoded, 0, 16); // First 16 bytes are the IV
        $ciphertext = substr($decoded, 16); // Rest is the ciphertext

        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            return '[Message could not be decrypted - invalid key or corrupted]';
        }

        return $plaintext;
    } catch (Exception $e) {
        error_log("decryptMessage error: " . $e->getMessage());
        return '[Message could not be decrypted - ' . $e->getMessage() . ']';
    }
}

