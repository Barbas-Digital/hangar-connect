<?php
/**
 * Connection storage for Hangar Connect pairing keys.
 *
 * Pairing secrets are encrypted at rest (hub crypto when available).
 * Plaintext is never logged. Hub license tokens are unrelated.
 */

defined('ABSPATH') || exit;

/** Option key (autoload=false). */
define('HANGAR_CONNECT_CONNECTIONS_OPTION', 'hangar_connect_connections');

/**
 * Transient prefix for one-time plaintext reveal after generate/rotate.
 *
 * @param string $connection_id Connection id.
 * @return string
 */
function hangar_connect_reveal_transient_key($connection_id) {
    return 'hangar_connect_reveal_' . sanitize_key($connection_id);
}

/**
 * @return array<int, array<string, mixed>>
 */
function hangar_connect_get_all_connections() {
    $raw = get_option(HANGAR_CONNECT_CONNECTIONS_OPTION, array());
    if (!is_array($raw)) {
        return array();
    }

    $out = array();
    foreach ($raw as $row) {
        if (!is_array($row) || empty($row['id'])) {
            continue;
        }
        $out[] = hangar_connect_normalize_connection($row);
    }

    return $out;
}

/**
 * @param array<string, mixed> $row Raw row.
 * @return array<string, mixed>
 */
function hangar_connect_normalize_connection($row) {
    return array(
        'id'           => (string) $row['id'],
        'label'        => isset($row['label']) ? (string) $row['label'] : '',
        'status'       => isset($row['status']) ? sanitize_key((string) $row['status']) : 'pending',
        'created_at'   => isset($row['created_at']) ? (int) $row['created_at'] : 0,
        'connected_at' => isset($row['connected_at']) ? (int) $row['connected_at'] : 0,
        'last_seen_at' => isset($row['last_seen_at']) ? (int) $row['last_seen_at'] : 0,
        'secret'       => isset($row['secret']) ? (string) $row['secret'] : '',
    );
}

/**
 * Persist connections list (never autoload).
 *
 * @param array<int, array<string, mixed>> $connections Rows.
 * @return bool
 */
function hangar_connect_save_all_connections($connections) {
    $clean = array();
    foreach ((array) $connections as $row) {
        if (!is_array($row) || empty($row['id'])) {
            continue;
        }
        $clean[] = hangar_connect_normalize_connection($row);
    }

    $existing = get_option(HANGAR_CONNECT_CONNECTIONS_OPTION, null);
    if (null === $existing) {
        return add_option(HANGAR_CONNECT_CONNECTIONS_OPTION, $clean, '', false);
    }

    return update_option(HANGAR_CONNECT_CONNECTIONS_OPTION, $clean, false);
}

/**
 * @param string $connection_id Connection id.
 * @return array<string, mixed>|null
 */
function hangar_connect_get_connection($connection_id) {
    $connection_id = sanitize_key($connection_id);
    foreach (hangar_connect_get_all_connections() as $row) {
        if ($row['id'] === $connection_id) {
            return $row;
        }
    }
    return null;
}

/**
 * Encrypt secret for storage.
 *
 * @param string $plaintext Pairing secret.
 * @return string Encrypted or empty on failure.
 */
function hangar_connect_encrypt_secret($plaintext) {
    $plaintext = is_string($plaintext) ? trim($plaintext) : '';
    if ($plaintext === '') {
        return '';
    }

    if (function_exists('barbas_update_encrypt_token')) {
        $enc = barbas_update_encrypt_token($plaintext);
        if (is_string($enc) && $enc !== '') {
            return $enc;
        }
    }

    // Fallback: never store plaintext if OpenSSL path failed.
    return '';
}

/**
 * Decrypt stored secret.
 *
 * @param string $stored Encrypted payload.
 * @return string
 */
function hangar_connect_decrypt_secret($stored) {
    if (!is_string($stored) || $stored === '') {
        return '';
    }

    if (function_exists('barbas_update_decrypt_token')) {
        return barbas_update_decrypt_token($stored);
    }

    return '';
}

/**
 * Generate a high-entropy pairing key (URL-safe).
 *
 * @return string
 */
function hangar_connect_generate_pairing_key() {
    try {
        $bytes = random_bytes(32);
    } catch (Exception $e) {
        $bytes = wp_generate_password(32, true, true);
        return 'hc_' . rtrim(strtr(base64_encode(hash('sha256', $bytes, true)), '+/', '-_'), '=');
    }

    return 'hc_' . rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/**
 * Create a new pending connection; returns plaintext key once.
 *
 * @param string $label Optional label.
 * @return array{connection:array<string,mixed>,pairing_key:string}|WP_Error
 */
function hangar_connect_create_connection($label = '') {
    if (!empty(hangar_connect_get_all_connections())) {
        return new WP_Error(
            'hangar_connect_already_connected',
            __('This site already has a connection. Disconnect it before pairing with another Hangar.', 'hangar-connect')
        );
    }

    $plaintext = hangar_connect_generate_pairing_key();
    $encrypted = hangar_connect_encrypt_secret($plaintext);
    if ($encrypted === '') {
        return new WP_Error(
            'hangar_connect_encrypt_failed',
            __('Could not store the pairing key securely (OpenSSL required).', 'hangar-connect')
        );
    }

    $label = sanitize_text_field((string) $label);
    if ($label === '') {
        $label = sanitize_text_field((string) get_bloginfo('name'));
    }

    $id = 'c_' . strtolower(wp_generate_password(12, false, false));
    $row = array(
        'id'           => $id,
        'label'        => $label,
        'status'       => 'pending',
        'created_at'   => time(),
        'connected_at' => 0,
        'last_seen_at' => 0,
        'secret'       => $encrypted,
    );

    $all = hangar_connect_get_all_connections();
    $all[] = $row;
    if (!hangar_connect_save_all_connections($all)) {
        return new WP_Error(
            'hangar_connect_save_failed',
            __('Could not save the connection.', 'hangar-connect')
        );
    }

    set_transient(hangar_connect_reveal_transient_key($id), $plaintext, 10 * MINUTE_IN_SECONDS);

    return array(
        'connection'  => $row,
        'pairing_key' => $plaintext,
    );
}

/**
 * Rotate secret for an existing connection; returns new plaintext once.
 *
 * @param string $connection_id Connection id.
 * @return array{connection:array<string,mixed>,pairing_key:string}|WP_Error
 */
function hangar_connect_rotate_connection($connection_id) {
    $connection_id = sanitize_key($connection_id);
    $all = hangar_connect_get_all_connections();
    $found = false;
    $updated = null;
    $plaintext = hangar_connect_generate_pairing_key();
    $encrypted = hangar_connect_encrypt_secret($plaintext);
    if ($encrypted === '') {
        return new WP_Error(
            'hangar_connect_encrypt_failed',
            __('Could not store the pairing key securely (OpenSSL required).', 'hangar-connect')
        );
    }

    foreach ($all as $i => $row) {
        if ($row['id'] !== $connection_id) {
            continue;
        }
        $all[ $i ]['secret'] = $encrypted;
        $all[ $i ]['status'] = 'pending';
        $all[ $i ]['connected_at'] = 0;
        $updated = $all[ $i ];
        $found = true;
        break;
    }

    if (!$found || $updated === null) {
        return new WP_Error(
            'hangar_connect_not_found',
            __('Connection not found.', 'hangar-connect')
        );
    }

    if (!hangar_connect_save_all_connections($all)) {
        return new WP_Error(
            'hangar_connect_save_failed',
            __('Could not save the connection.', 'hangar-connect')
        );
    }

    set_transient(hangar_connect_reveal_transient_key($connection_id), $plaintext, 10 * MINUTE_IN_SECONDS);

    return array(
        'connection'  => $updated,
        'pairing_key' => $plaintext,
    );
}

/**
 * @param string $connection_id Connection id.
 * @return true|WP_Error
 */
function hangar_connect_delete_connection($connection_id) {
    $connection_id = sanitize_key($connection_id);
    $all = hangar_connect_get_all_connections();
    $next = array();
    $found = false;
    foreach ($all as $row) {
        if ($row['id'] === $connection_id) {
            $found = true;
            delete_transient(hangar_connect_reveal_transient_key($connection_id));
            continue;
        }
        $next[] = $row;
    }

    if (!$found) {
        return new WP_Error(
            'hangar_connect_not_found',
            __('Connection not found.', 'hangar-connect')
        );
    }

    hangar_connect_save_all_connections($next);
    return true;
}

/**
 * Remove every connection.
 *
 * @return int Number removed.
 */
function hangar_connect_delete_all_connections() {
    $all = hangar_connect_get_all_connections();
    foreach ($all as $row) {
        delete_transient(hangar_connect_reveal_transient_key($row['id']));
    }
    hangar_connect_save_all_connections(array());
    return count($all);
}

/**
 * One-time reveal of plaintext pairing key (admin only, after generate/rotate).
 *
 * @param string $connection_id Connection id.
 * @return string Empty if not available.
 */
function hangar_connect_peek_revealed_key($connection_id) {
    $key = get_transient(hangar_connect_reveal_transient_key($connection_id));
    return is_string($key) ? $key : '';
}

/**
 * Clear one-time reveal (after copy / dismiss).
 *
 * @param string $connection_id Connection id.
 */
function hangar_connect_clear_revealed_key($connection_id) {
    delete_transient(hangar_connect_reveal_transient_key($connection_id));
}

/**
 * Public-safe connection summary (no secrets).
 *
 * @param array<string, mixed> $row Connection row.
 * @return array<string, mixed>
 */
function hangar_connect_public_connection_summary($row) {
    return array(
        'id'           => $row['id'],
        'label'        => $row['label'],
        'status'       => $row['status'],
        'created_at'   => $row['created_at'],
        'connected_at' => $row['connected_at'],
        'last_seen_at' => $row['last_seen_at'],
    );
}

/**
 * Whether at least one connection is marked connected.
 *
 * @return bool
 */
function hangar_connect_has_active_connection() {
    foreach (hangar_connect_get_all_connections() as $row) {
        if (($row['status'] ?? '') === 'connected') {
            return true;
        }
    }
    // Pending keys still count as "has pairing material" for health.connected bool:
    // health uses "connected" only for established links; pending = false.
    return false;
}

/**
 * Resolve plaintext secret for HMAC (never log).
 *
 * @param string $connection_id Connection id.
 * @return string
 */
function hangar_connect_get_connection_secret($connection_id) {
    $row = hangar_connect_get_connection($connection_id);
    if ($row === null) {
        return '';
    }
    return hangar_connect_decrypt_secret($row['secret']);
}

/**
 * Complete Hangar pairing: match plaintext key to a pending connection.
 *
 * @param string $pairing_key Plaintext key (bc_...).
 * @return array<string,mixed>|WP_Error Public connection row on success.
 */
function hangar_connect_complete_pairing($pairing_key) {
    $pairing_key = is_string($pairing_key) ? trim($pairing_key) : '';
    if ($pairing_key === '' || (strpos($pairing_key, 'hc_') !== 0 && strpos($pairing_key, 'bc_') !== 0)) {
        return new WP_Error(
            'hangar_connect_pair_invalid',
            __('Invalid pairing key.', 'hangar-connect'),
            array('status' => 400)
        );
    }

    $all = hangar_connect_get_all_connections();

    // One Hangar at a time: reject pairing a second connection while one is active.
    // Rotate sets status back to pending, so re-pair of the same slot still works.
    if (hangar_connect_has_active_connection()) {
        return new WP_Error(
            'hangar_connect_already_paired',
            __('This site is already paired with Hangar. Disconnect before pairing again.', 'hangar-connect'),
            array('status' => 409)
        );
    }

    $matched_id = '';
    foreach ($all as $row) {
        if (($row['status'] ?? '') === 'connected') {
            continue;
        }
        $secret = hangar_connect_decrypt_secret($row['secret']);
        if ($secret !== '' && hash_equals($secret, $pairing_key)) {
            $matched_id = $row['id'];
            break;
        }
    }

    if ($matched_id === '') {
        return new WP_Error(
            'hangar_connect_pair_not_found',
            __('No pending connection matches this pairing key.', 'hangar-connect'),
            array('status' => 404)
        );
    }

    hangar_connect_touch_connection($matched_id);
    hangar_connect_clear_revealed_key($matched_id);

    $updated = hangar_connect_get_connection($matched_id);
    if ($updated === null) {
        return new WP_Error(
            'hangar_connect_pair_failed',
            __('Pairing failed.', 'hangar-connect'),
            array('status' => 500)
        );
    }

    return hangar_connect_public_connection_summary($updated);
}

/**
 * Mark connection as connected / touch last_seen.
 *
 * @param string $connection_id Connection id.
 * @return void
 */
function hangar_connect_touch_connection($connection_id) {
    $connection_id = sanitize_key($connection_id);
    $all = hangar_connect_get_all_connections();
    $changed = false;
    foreach ($all as $i => $row) {
        if ($row['id'] !== $connection_id) {
            continue;
        }
        $all[ $i ]['last_seen_at'] = time();
        if (($row['status'] ?? '') !== 'connected') {
            $all[ $i ]['status'] = 'connected';
            $all[ $i ]['connected_at'] = time();
        }
        $changed = true;
        break;
    }
    if ($changed) {
        hangar_connect_save_all_connections($all);
    }
}

/**
 * @deprecated 0.2.4 Activity Reports is never required. Use hangar_connect_wsal_status().
 *
 * @return bool Always false.
 */
function hangar_connect_activity_reports_available() {
    return false;
}
