<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Criptografia de licenças em repouso (AES-256-CBC + salts do WordPress).
 * Compatível com PHP 7.4+.
 *
 * Sem OpenSSL / random_bytes: NÃO grava plaintext — encrypt retorna false.
 * Tokens legados em texto claro ainda são lidos e re-criptografados na leitura.
 */

if (!function_exists('barbas_update_token_is_encrypted')) {
    function barbas_update_token_is_encrypted($value) {
        return is_string($value) && strpos($value, 'barbas1:') === 0;
    }
}

if (!function_exists('barbas_update_get_encryption_key')) {
    function barbas_update_get_encryption_key() {
        static $key = null;

        if ($key !== null) {
            return $key;
        }

        if (!function_exists('wp_salt')) {
            return '';
        }

        $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);
        return $key;
    }
}

if (!function_exists('barbas_update_encrypt_token')) {
    /**
     * @param string $plaintext Token em claro.
     * @return string|false Payload `barbas1:…` ou false se não for possível cifrar.
     */
    function barbas_update_encrypt_token($plaintext) {
        $plaintext = is_string($plaintext) ? trim($plaintext) : '';
        if ($plaintext === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt') || !function_exists('random_bytes')) {
            return false;
        }

        $key = barbas_update_get_encryption_key();
        if ($key === '') {
            return false;
        }

        try {
            $iv = random_bytes(16);
        } catch (Exception $e) {
            return false;
        }

        $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return false;
        }

        return 'barbas1:' . base64_encode($iv . $cipher);
    }
}

if (!function_exists('barbas_update_decrypt_token')) {
    function barbas_update_decrypt_token($stored) {
        if (!is_string($stored) || $stored === '') {
            return '';
        }

        if (!barbas_update_token_is_encrypted($stored)) {
            return trim($stored);
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $payload = base64_decode(substr($stored, 8), true);
        if ($payload === false || strlen($payload) < 17) {
            return '';
        }

        $key = barbas_update_get_encryption_key();
        if ($key === '') {
            return '';
        }

        $iv = substr($payload, 0, 16);
        $cipher = substr($payload, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return is_string($plain) ? trim($plain) : '';
    }
}

if (!function_exists('barbas_update_store_token_option')) {
    /**
     * @return bool True se gravou cifrado; false se OpenSSL indisponível ou falhou.
     */
    function barbas_update_store_token_option($option_key, $plaintext) {
        $plaintext = is_string($plaintext) ? trim($plaintext) : '';
        if ($plaintext === '') {
            return false;
        }

        $encrypted = barbas_update_encrypt_token($plaintext);
        if ($encrypted === false || $encrypted === '') {
            return false;
        }

        return (bool) update_option($option_key, $encrypted, false);
    }
}

if (!function_exists('barbas_update_read_token_option')) {
    function barbas_update_read_token_option($option_key) {
        $raw = get_option($option_key, '');
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $plain = barbas_update_decrypt_token($raw);
        if ($plain !== '' && !barbas_update_token_is_encrypted($raw)) {
            // Migra legado plaintext → cifrado (só se OpenSSL ok).
            barbas_update_store_token_option($option_key, $plain);
        }

        return $plain;
    }
}
