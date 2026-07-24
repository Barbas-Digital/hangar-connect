<?php
/**
 * REST API for Hangar Connect (own namespace only — never /wp/v2).
 */

defined('ABSPATH') || exit;

/**
 * Register routes.
 */
function hangar_connect_register_rest_routes() {
    $ns = HANGAR_CONNECT_REST_NS;

    register_rest_route(
        $ns,
        '/health',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'hangar_connect_rest_health',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        $ns,
        '/pair',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'hangar_connect_rest_pair',
            'permission_callback' => '__return_true',
            'args'                => array(
                'pairing_key' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        )
    );

    register_rest_route(
        $ns,
        '/capabilities',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'hangar_connect_rest_capabilities',
            'permission_callback' => 'hangar_connect_rest_permission_hmac',
        )
    );

    register_rest_route(
        $ns,
        '/activity/users',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'hangar_connect_rest_activity_users',
            'permission_callback' => 'hangar_connect_rest_permission_hmac',
        )
    );

    register_rest_route(
        $ns,
        '/activity/report',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'hangar_connect_rest_activity_report',
            'permission_callback' => 'hangar_connect_rest_permission_hmac',
            'args'                => array(
                'user'   => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'from'   => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'to'     => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'format' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'json',
                    'sanitize_callback' => 'sanitize_key',
                ),
            ),
        )
    );
}
add_action('rest_api_init', 'hangar_connect_register_rest_routes');

/**
 * Capability map advertised to Central.
 *
 * @return array<string, bool>
 */
function hangar_connect_capabilities_map() {
    $ar     = hangar_connect_activity_reports_available();
    $loaded = $ar && function_exists('hangar_connect_activity_ensure_loaded')
        ? hangar_connect_activity_ensure_loaded()
        : false;
    return array(
        'activity_reports'       => $ar,
        'activity_users'         => $ar && $loaded,
        'activity_report'        => $ar && $loaded,
        'activity_bridge_ready'  => $ar && $loaded,
        'activity_bridge_version'=> HANGAR_CONNECT_VERSION,
    );
}

/**
 * GET /health — public discovery (no secrets).
 *
 * @return WP_REST_Response
 */
function hangar_connect_rest_health() {
    $connections = hangar_connect_get_all_connections();
    $pending = 0;
    $connected = 0;
    foreach ($connections as $row) {
        if (($row['status'] ?? '') === 'connected') {
            $connected++;
        } elseif (($row['status'] ?? '') === 'pending') {
            $pending++;
        }
    }

    return new WP_REST_Response(
        array(
            'ok'           => true,
            'plugin'       => 'hangar-connect',
            'version'      => HANGAR_CONNECT_VERSION,
            'site_url'     => home_url('/'),
            'site_name'    => get_bloginfo('name'),
            'connected'    => hangar_connect_has_active_connection(),
            'connections'  => array(
                'total'     => count($connections),
                'pending'   => $pending,
                'connected' => $connected,
            ),
            'capabilities' => hangar_connect_capabilities_map(),
        ),
        200
    );
}

/**
 * POST /pair — one-time handshake with Central using plaintext pairing key.
 *
 * Body JSON: { "pairing_key": "bc_..." }
 * Returns connection_id for subsequent HMAC calls (secret = pairing key).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function hangar_connect_rest_pair(WP_REST_Request $request) {
    $key = (string) $request->get_param('pairing_key');
    if ($key === '' && $request->get_json_params()) {
        $json = $request->get_json_params();
        if (is_array($json) && isset($json['pairing_key'])) {
            $key = sanitize_text_field((string) $json['pairing_key']);
        }
    }

    $result = hangar_connect_complete_pairing($key);
    if (is_wp_error($result)) {
        return $result;
    }

    return new WP_REST_Response(
        array(
            'ok'            => true,
            'connection_id' => $result['id'],
            'site_url'      => home_url('/'),
            'site_name'     => get_bloginfo('name'),
        ),
        200
    );
}

/**
 * GET /capabilities — HMAC protected.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function hangar_connect_rest_capabilities(WP_REST_Request $request) {
    unset($request);
    return new WP_REST_Response(
        array(
            'ok'           => true,
            'capabilities' => hangar_connect_capabilities_map(),
            'version'      => HANGAR_CONNECT_VERSION,
        ),
        200
    );
}

// Activity users + report callbacks live in hangar-connect-activity.php.
