<?php
/**
 * Connection storage for Barbas Connect pairing keys.
 *
 * Pairing secrets are encrypted at rest (hub crypto when available).
 * Plaintext is never logged. Hub license tokens are unrelated.
 */

defined('ABSPATH') || exit;

/** Option key (autoload=false). */
define('BARBAS_CONNECT_CONNECTIONS_OPTION', 'barbas_connect_connections');

/**
 * Transient prefix for one-time plaintext reveal after generate/rotate.
 *
 * @param string $connection_id Connection id.
 * @return string
 */
function barbas_connect_reveal_transient_key($connection_id) {
    return 'barbas_connect_reveal_' . sanitize_key($connection_id);
}

/**
 * @return array<int, array<string, mixed>>
 */
function barbas_connect_get_all_connections() {
    $raw = get_option(BARBAS_CONNECT_CONNECTIONS_OPTION, array());
    if (!is_array($raw)) {
        return array();
    }

    $out = array();
    foreach ($raw as $row) {
        if (!is_array($row) || empty($row['id'])) {
            continue;
        }
        $out[] = barbas_connect_normalize_connection($row);
    }

    return $out;
}

/**
 * @param array<string, mixed> $row Raw row.
 * @return array<string, mixed>
 */
function barbas_connect_normalize_connection($row) {
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
function barbas_connect_save_all_connections($connections) {
    $clean = array();
    foreach ((array) $connections as $row) {
        if (!is_array($row) || empty($row['id'])) {
            continue;
        }
        $clean[] = barbas_connect_normalize_connection($row);
    }

    $existing = get_option(BARBAS_CONNECT_CONNECTIONS_OPTION, null);
    if (null === $existing) {
        return add_option(BARBAS_CONNECT_CONNECTIONS_OPTION, $clean, '', false);
    }

    return update_option(BARBAS_CONNECT_CONNECTIONS_OPTION, $clean, false);
}

/**
 * @param string $connection_id Connection id.
 * @return array<string, mixed>|null
 */
function barbas_connect_get_connection($connection_id) {
    $connection_id = sanitize_key($connection_id);
    foreach (barbas_connect_get_all_connections() as $row) {
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
function barbas_connect_encrypt_secret($plaintext) {
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
function barbas_connect_decrypt_secret($stored) {
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
function barbas_connect_generate_pairing_key() {
    try {
        $bytes = random_bytes(32);
    } catch (Exception $e) {
        $bytes = wp_generate_password(32, true, true);
        return 'bc_' . rtrim(strtr(base64_encode(hash('sha256', $bytes, true)), '+/', '-_'), '=');
    }

    return 'bc_' . rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/**
 * Create a new pending connection; returns plaintext key once.
 *
 * @param string $label Optional label.
 * @return array{connection:array<string,mixed>,pairing_key:string}|WP_Error
 */
function barbas_connect_create_connection($label = '') {
    $plaintext = barbas_connect_generate_pairing_key();
    $encrypted = barbas_connect_encrypt_secret($plaintext);
    if ($encrypted === '') {
        return new WP_Error(
            'barbas_connect_encrypt_failed',
            __('Could not store the pairing key securely (OpenSSL required).', 'barbas-connect')
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

    $all = barbas_connect_get_all_connections();
    $all[] = $row;
    if (!barbas_connect_save_all_connections($all)) {
        return new WP_Error(
            'barbas_connect_save_failed',
            __('Could not save the connection.', 'barbas-connect')
        );
    }

    set_transient(barbas_connect_reveal_transient_key($id), $plaintext, 10 * MINUTE_IN_SECONDS);

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
function barbas_connect_rotate_connection($connection_id) {
    $connection_id = sanitize_key($connection_id);
    $all = barbas_connect_get_all_connections();
    $found = false;
    $updated = null;
    $plaintext = barbas_connect_generate_pairing_key();
    $encrypted = barbas_connect_encrypt_secret($plaintext);
    if ($encrypted === '') {
        return new WP_Error(
            'barbas_connect_encrypt_failed',
            __('Could not store the pairing key securely (OpenSSL required).', 'barbas-connect')
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
            'barbas_connect_not_found',
            __('Connection not found.', 'barbas-connect')
        );
    }

    if (!barbas_connect_save_all_connections($all)) {
        return new WP_Error(
            'barbas_connect_save_failed',
            __('Could not save the connection.', 'barbas-connect')
        );
    }

    set_transient(barbas_connect_reveal_transient_key($connection_id), $plaintext, 10 * MINUTE_IN_SECONDS);

    return array(
        'connection'  => $updated,
        'pairing_key' => $plaintext,
    );
}

/**
 * @param string $connection_id Connection id.
 * @return true|WP_Error
 */
function barbas_connect_delete_connection($connection_id) {
    $connection_id = sanitize_key($connection_id);
    $all = barbas_connect_get_all_connections();
    $next = array();
    $found = false;
    foreach ($all as $row) {
        if ($row['id'] === $connection_id) {
            $found = true;
            delete_transient(barbas_connect_reveal_transient_key($connection_id));
            continue;
        }
        $next[] = $row;
    }

    if (!$found) {
        return new WP_Error(
            'barbas_connect_not_found',
            __('Connection not found.', 'barbas-connect')
        );
    }

    barbas_connect_save_all_connections($next);
    return true;
}

/**
 * Remove every connection.
 *
 * @return int Number removed.
 */
function barbas_connect_delete_all_connections() {
    $all = barbas_connect_get_all_connections();
    foreach ($all as $row) {
        delete_transient(barbas_connect_reveal_transient_key($row['id']));
    }
    barbas_connect_save_all_connections(array());
    return count($all);
}

/**
 * One-time reveal of plaintext pairing key (admin only, after generate/rotate).
 *
 * @param string $connection_id Connection id.
 * @return string Empty if not available.
 */
function barbas_connect_peek_revealed_key($connection_id) {
    $key = get_transient(barbas_connect_reveal_transient_key($connection_id));
    return is_string($key) ? $key : '';
}

/**
 * Clear one-time reveal (after copy / dismiss).
 *
 * @param string $connection_id Connection id.
 */
function barbas_connect_clear_revealed_key($connection_id) {
    delete_transient(barbas_connect_reveal_transient_key($connection_id));
}

/**
 * Public-safe connection summary (no secrets).
 *
 * @param array<string, mixed> $row Connection row.
 * @return array<string, mixed>
 */
function barbas_connect_public_connection_summary($row) {
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
function barbas_connect_has_active_connection() {
    foreach (barbas_connect_get_all_connections() as $row) {
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
function barbas_connect_get_connection_secret($connection_id) {
    $row = barbas_connect_get_connection($connection_id);
    if ($row === null) {
        return '';
    }
    return barbas_connect_decrypt_secret($row['secret']);
}

/**
 * Complete Central pairing: match plaintext key to a pending connection.
 *
 * @param string $pairing_key Plaintext key (bc_...).
 * @return array<string,mixed>|WP_Error Public connection row on success.
 */
function barbas_connect_complete_pairing($pairing_key) {
    $pairing_key = is_string($pairing_key) ? trim($pairing_key) : '';
    if ($pairing_key === '' || strpos($pairing_key, 'bc_') !== 0) {
        return new WP_Error(
            'barbas_connect_pair_invalid',
            __('Invalid pairing key.', 'barbas-connect'),
            array('status' => 400)
        );
    }

    $all = barbas_connect_get_all_connections();
    $matched_id = '';
    foreach ($all as $row) {
        if (($row['status'] ?? '') === 'connected') {
            continue;
        }
        $secret = barbas_connect_decrypt_secret($row['secret']);
        if ($secret !== '' && hash_equals($secret, $pairing_key)) {
            $matched_id = $row['id'];
            break;
        }
    }

    if ($matched_id === '') {
        return new WP_Error(
            'barbas_connect_pair_not_found',
            __('No pending connection matches this pairing key.', 'barbas-connect'),
            array('status' => 404)
        );
    }

    barbas_connect_touch_connection($matched_id);
    barbas_connect_clear_revealed_key($matched_id);

    $updated = barbas_connect_get_connection($matched_id);
    if ($updated === null) {
        return new WP_Error(
            'barbas_connect_pair_failed',
            __('Pairing failed.', 'barbas-connect'),
            array('status' => 500)
        );
    }

    return barbas_connect_public_connection_summary($updated);
}

/**
 * Mark connection as connected / touch last_seen.
 *
 * @param string $connection_id Connection id.
 * @return void
 */
function barbas_connect_touch_connection($connection_id) {
    $connection_id = sanitize_key($connection_id);
    $all = barbas_connect_get_all_connections();
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
        barbas_connect_save_all_connections($all);
    }
}

/**
 * Whether Activity Reports plugin appears available.
 *
 * @return bool
 */
function barbas_connect_activity_reports_available() {
    if (defined('BARBAS_ACTIVITY_REPORTS_VERSION') || defined('WSALR_VERSION')) {
        return true;
    }
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    return is_plugin_active('barbas-activity-reports/barbas-activity-reports.php');
}
