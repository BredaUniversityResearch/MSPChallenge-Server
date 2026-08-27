<?php

namespace App\Domain\Security;

/**
 * Symmetric authenticated encryption for individual entity fields stored in the database.
 *
 * Algorithm : XSalsa20-Poly1305 via sodium_crypto_secretbox (PHP built-in since 7.2).
 * Key       : 32-byte BLAKE2b hash of APP_SECRET (kernel.secret).
 * Format    : base64(nonce[24] ‖ ciphertext) — the random nonce is prepended so the same
 *             plaintext always produces a different ciphertext, making DB dumps safe.
 */
final class FieldEncryptor
{
    private string $key;

    public function __construct(#[\SensitiveParameter] string $appSecret)
    {
        // Derive a fixed-length symmetric key from APP_SECRET.
        // sodium_crypto_generichash is BLAKE2b; using an empty salt is fine here because
        // APP_SECRET itself is already the secret material.
        $this->key = sodium_crypto_generichash(
            $appSecret,
            '',
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES  // 32 bytes
        );
    }

    /**
     * Encrypt a field value. Returns null when $plaintext is null.
     * Each call produces a different ciphertext due to the random nonce.
     */
    public function encrypt(#[\SensitiveParameter] ?string $plaintext): ?string
    {
        if ($plaintext === null) {
            return null;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); // 24 bytes
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        // Store as URL-safe base64: nonce prepended to ciphertext
        return sodium_bin2base64($nonce . $ciphertext, SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    /**
     * Decrypt a previously encrypted field value. Returns null for null input or on any failure
     * (wrong key, corrupted data, etc.).
     */
    public function decrypt(?string $encoded): ?string
    {
        if ($encoded === null) {
            return null;
        }

        try {
            $raw = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (\Exception) {
            // Not valid base64 — treat as unencrypted legacy value and return as-is.
            // Remove this branch once all legacy plain-text values have been migrated.
            return $encoded;
        }

        if (strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            // Too short to contain a nonce + MAC — probably a plain-text legacy value.
            return $encoded;
        }

        $nonce      = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        // sodium_crypto_secretbox_open returns false if authentication fails (wrong key / tampered).
        return $plaintext === false ? null : $plaintext;
    }
}
