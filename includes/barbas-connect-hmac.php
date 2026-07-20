<?php
/**
 * HMAC request verification scaffold for Barbas Connect REST.
 *
 * Headers:
 * - X-Barbas-Connect-Id: connection id
 * - X-Barbas-Connect-Timestamp: unix seconds
 * - X-Barbas-Connect-Nonce: unique request nonce
 * - X-Barbas-Connect-Signature: hex HMAC-SHA256 of canonical string
 *
 * Canonical string:
 *   timestamp + "\n" + nonce + "\n" + METHOD + "\n" + path_with_query + "\n" + sha256_hex(body)
 */

defined('ABSPATH') || exit;

/** Allowed clock skew (seconds). */
define('BARBAS_CONNECT_HMAC_SKEW', 300);

/**
 * Build canonical string for signing.
 *
 * @param string $timestamp Unix timestamp string.
 * @param string $nonce     Request nonce.
 * @param string $method    HTTP method.
 * @param string $path      Request path including query (no host).
 * @param string $body      Raw body.
 * @return string
 */
function barbas_connect_hmac_canonical($timestamp, $nonce, $method, $path, $body) {
    $body_hash = hash('sha256', is_string($body) ? $body : '');
    return implode(
        "\n",
        array(
            (string) $timestamp,
            (string) $nonce,
            strtoupper((string) $method),
            (string) $path,
            $body_hash,
        )
    );
}

/**
 * Sign a canonical string.
 *
 * @param string $canonical Canonical payload.
 * @param string $secret    Pairing secret (plaintext).
 * @return string Hex digest.
 */
function barbas_connect_hmac_sign($canonical, $secret) {
    return hash_hmac('sha256', $canonical, $secret);
}

/**
 * Read HMAC headers from current request.
 *
 * @return array{id:string,timestamp:string,nonce:string,signature:string}
 */
function barbas_connect_hmac_read_headers() {
    $get = static function ($name) {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[ $key ])) {
            return sanitize_text_field(wp_unslash((string) $_SERVER[ $key ]));
        }
        // Some stacks expose via getallheaders.
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $h => $v) {
                    if (strtolower((string) $h) === strtolower($name)) {
                        return sanitize_text_field((string) $v);
                    }
                }
            }
        }
        return '';
    };

    return array(
        'id'        => $get('X-Barbas-Connect-Id'),
        'timestamp' => $get('X-Barbas-Connect-Timestamp'),
        'nonce'     => $get('X-Barbas-Connect-Nonce'),
        'signature' => $get('X-Barbas-Connect-Signature'),
    );
}

/**
 * Path used for signing (REST route path + query).
 *
 * @param WP_REST_Request $request Request.
 * @return string
 */
function barbas_connect_hmac_request_path(WP_REST_Request $request) {
    $route = $request->get_route();
    $uri   = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    if ($uri !== '') {
        $parts = wp_parse_url($uri);
        $path  = isset($parts['path']) ? $parts['path'] : '';
        $query = isset($parts['query']) ? $parts['query'] : '';
        if ($path !== '') {
            return $query !== '' ? ($path . '?' . $query) : $path;
        }
    }
    return '/wp-json' . $route;
}

/**
 * Whether nonce was already used (replay protection).
 *
 * @param string $connection_id Connection id.
 * @param string $nonce         Nonce.
 * @return bool True if replay.
 */
function barbas_connect_hmac_nonce_seen($connection_id, $nonce) {
    $key = 'barbas_connect_nonce_' . md5($connection_id . '|' . $nonce);
    if (get_transient($key)) {
        return true;
    }
    set_transient($key, 1, BARBAS_CONNECT_HMAC_SKEW * 2);
    return false;
}

/**
 * Verify HMAC for a REST request. On success, touches the connection.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function barbas_connect_hmac_verify_request(WP_REST_Request $request) {
    $headers = barbas_connect_hmac_read_headers();

    if ($headers['id'] === '' || $headers['timestamp'] === '' || $headers['nonce'] === '' || $headers['signature'] === '') {
        return new WP_Error(
            'barbas_connect_hmac_missing',
            __('Missing Barbas Connect authentication headers.', 'barbas-connect'),
            array('status' => 401)
        );
    }

    if (!ctype_digit($headers['timestamp'])) {
        return new WP_Error(
            'barbas_connect_hmac_timestamp',
            __('Invalid timestamp.', 'barbas-connect'),
            array('status' => 401)
        );
    }

    $ts = (int) $headers['timestamp'];
    $now = time();
    if (abs($now - $ts) > BARBAS_CONNECT_HMAC_SKEW) {
        return new WP_Error(
            'barbas_connect_hmac_skew',
            __('Request timestamp outside allowed window.', 'barbas-connect'),
            array('status' => 401)
        );
    }

    if (strlen($headers['nonce']) < 8 || strlen($headers['nonce']) > 128) {
        return new WP_Error(
            'barbas_connect_hmac_nonce',
            __('Invalid nonce.', 'barbas-connect'),
            array('status' => 401)
        );
    }

    if (barbas_connect_hmac_nonce_seen($headers['id'], $headers['nonce'])) {
        return new WP_Error(
            'barbas_connect_hmac_replay',
            __('Nonce already used.', 'barbas-connect'),
            array('status' => 401)
        );
    }

    $secret = barbas_connect_get_connection_secret($headers['id']);
    if ($secret === '') {
        return new WP_Error(
            'barbas_connect_hmac_unknown',
            __('Unknown connection.', 'barbas-connect'),
            array('status' => 401)
        );
    }

    $body = $request->get_body();
    $canonical = barbas_connect_hmac_canonical(
        $headers['timestamp'],
        $headers['nonce'],
        $request->get_method(),
        barbas_connect_hmac_request_path($request),
        $body
    );
    $expected = barbas_connect_hmac_sign($canonical, $secret);

    if (!hash_equals($expected, strtolower($headers['signature'])) && !hash_equals($expected, $headers['signature'])) {
        return new WP_Error(
            'barbas_connect_hmac_invalid',
            __('Invalid signature.', 'barbas-connect'),
            array('status' => 401)
        );
    }

    barbas_connect_touch_connection($headers['id']);
    return true;
}

/**
 * REST permission callback: require valid HMAC.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function barbas_connect_rest_permission_hmac(WP_REST_Request $request) {
    return barbas_connect_hmac_verify_request($request);
}
