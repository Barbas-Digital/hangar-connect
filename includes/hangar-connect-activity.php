<?php
/**
 * Hangar Connect activity + WP user directory (HMAC-protected REST).
 *
 * Reads WP Activity Log (WSAL) tables natively — no Activity Reports dependency.
 * Without WSAL, user directory still works; reports return a clear readiness error.
 */

defined('ABSPATH') || exit;

/**
 * Load native WSAL engine once.
 */
function hangar_connect_wsal_load_engine() {
    static $loaded = false;
    if ($loaded) {
        return function_exists('hangar_wsal_tables') && function_exists('hangar_wsal_build_report');
    }
    $loaded = true;
    $base   = HANGAR_CONNECT_DIR . 'includes/wsal/';
    foreach (array('events.php', 'data.php', 'analytics.php', 'report.php') as $file) {
        $path = $base . $file;
        if (is_readable($path)) {
            require_once $path;
        }
    }
    return function_exists('hangar_wsal_tables') && function_exists('hangar_wsal_build_report');
}

/**
 * Whether WP Activity Log plugin appears active.
 *
 * @return bool
 */
function hangar_connect_wsal_plugin_active() {
    if (defined('WSAL_BASE_NAME') || defined('WSAL_BASE_FILE_NAME') || defined('WSAL_VERSION')) {
        return true;
    }
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    foreach (array('wp-security-audit-log/wp-security-audit-log.php') as $file) {
        if (is_plugin_active($file)) {
            return true;
        }
        if (is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($file)) {
            return true;
        }
    }
    return false;
}

/**
 * Whether WSAL DB tables are readable.
 *
 * @return bool
 */
function hangar_connect_wsal_tables_ready() {
    if (!hangar_connect_wsal_load_engine()) {
        return false;
    }
    return (bool) hangar_wsal_tables();
}

/**
 * Aggregated WSAL readiness for Hangar + admin UI.
 *
 * @return array{plugin_active:bool,tables_ready:bool,ready:bool,code:string,message:string}
 */
function hangar_connect_wsal_status() {
    hangar_connect_wsal_load_engine();
    $plugin = hangar_connect_wsal_plugin_active();
    $tables = hangar_connect_wsal_tables_ready();
    if ($plugin && $tables) {
        return array(
            'plugin_active' => true,
            'tables_ready'  => true,
            'ready'         => true,
            'code'          => 'ok',
            'message'       => 'WP Activity Log is active and tables are available.',
        );
    }
    if (!$plugin && !$tables) {
        return array(
            'plugin_active' => false,
            'tables_ready'  => false,
            'ready'         => false,
            'code'          => 'wsal_missing',
            'message'       => 'WP Activity Log is not installed or not active. Install and activate it to collect productivity logs.',
        );
    }
    if (!$plugin && $tables) {
        return array(
            'plugin_active' => false,
            'tables_ready'  => true,
            'ready'         => false,
            'code'          => 'wsal_plugin_inactive',
            'message'       => 'WSAL tables found, but the WP Activity Log plugin is not active. New events will not be recorded until it is activated.',
        );
    }
    return array(
        'plugin_active' => true,
        'tables_ready'  => false,
        'ready'         => false,
        'code'          => 'wsal_tables_missing',
        'message'       => 'WP Activity Log is active, but its database tables were not found yet. Open WP Activity Log once so tables can be created.',
    );
}

/**
 * Missing / not-ready WSAL response for report endpoints.
 *
 * @param array<string,mixed>|null $status Optional precomputed status.
 * @return WP_REST_Response
 */
function hangar_connect_wsal_not_ready_response($status = null) {
    if (!is_array($status)) {
        $status = hangar_connect_wsal_status();
    }
    $code = isset($status['code']) ? (string) $status['code'] : 'wsal_missing';
    $http = ($code === 'wsal_tables_missing') ? 503 : 501;
    return new WP_REST_Response(
        array(
            'ok'      => false,
            'code'    => $code,
            'message' => isset($status['message']) ? (string) $status['message'] : 'WP Activity Log is required for productivity reports.',
            'ready'   => false,
            'wsal'    => $status,
        ),
        $http
    );
}

/**
 * Resolve report subject from email or username.
 *
 * @param string $user_param Email or username.
 * @return string Username (or original).
 */
function hangar_connect_activity_resolve_username($user_param) {
    $user_param = trim((string) $user_param);
    if ($user_param === '') {
        return '';
    }

    if (is_email($user_param)) {
        $by_email = get_user_by('email', $user_param);
        if ($by_email instanceof WP_User) {
            return (string) $by_email->user_login;
        }
    }

    $by_login = get_user_by('login', $user_param);
    if ($by_login instanceof WP_User) {
        return (string) $by_login->user_login;
    }

    return $user_param;
}

/**
 * Whether a WP user exists for the given email or username.
 *
 * @param string $user_param Email or username.
 * @return bool
 */
function hangar_connect_activity_user_exists($user_param) {
    $user_param = trim((string) $user_param);
    if ($user_param === '') {
        return false;
    }
    if (is_email($user_param)) {
        return (bool) get_user_by('email', $user_param);
    }
    return (bool) get_user_by('login', $user_param);
}

/**
 * Enrich a known-user row with WP email / display name.
 *
 * @param object $row Known user row from hangar_wsal_known_users().
 * @return array<string, mixed>
 */
function hangar_connect_activity_enrich_user($row) {
    $username = isset($row->username) ? (string) $row->username : '';
    $user_id  = isset($row->user_id) ? (int) $row->user_id : 0;
    $events   = isset($row->n) ? (int) $row->n : 0;

    $wp_user = null;
    if ($user_id > 0) {
        $wp_user = get_user_by('id', $user_id);
    }
    if (!$wp_user && $username !== '') {
        $wp_user = get_user_by('login', $username);
    }

    return array(
        'username'     => $username,
        'user_id'      => $user_id,
        'email'        => $wp_user instanceof WP_User ? (string) $wp_user->user_email : '',
        'display_name' => $wp_user instanceof WP_User
            ? (string) $wp_user->display_name
            : ($username !== '' ? $username : __('Unknown User', 'hangar-connect')),
        'events'       => $events,
        'active'       => $wp_user instanceof WP_User,
    );
}

/**
 * GET /activity/users and /wp/users — WordPress users (HMAC).
 *
 * Always lists WP users via get_users(). When WSAL tables exist, merges event counts.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function hangar_connect_rest_activity_users(WP_REST_Request $request) {
    unset($request);

    $by_key = array();
    $wsal   = hangar_connect_wsal_status();

    $wp_users = get_users(
        array(
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'fields'  => array('ID', 'user_login', 'user_email', 'display_name'),
        )
    );
    foreach ((array) $wp_users as $u) {
        if (!is_object($u)) {
            continue;
        }
        $uid = (int) $u->ID;
        $by_key['id:' . $uid] = array(
            'username'     => (string) $u->user_login,
            'user_id'      => $uid,
            'email'        => (string) $u->user_email,
            'display_name' => (string) $u->display_name,
            'events'       => 0,
            'source'       => 'wp',
            'active'       => true,
        );
    }

    $wsal_enrichment = false;
    if (!empty($wsal['tables_ready']) && hangar_connect_wsal_load_engine() && function_exists('hangar_wsal_known_users')) {
        $wsal_enrichment = true;
        foreach ((array) hangar_wsal_known_users() as $row) {
            if (!is_object($row)) {
                continue;
            }
            $enriched = hangar_connect_activity_enrich_user($row);
            $uid      = (int) $enriched['user_id'];
            $key      = $uid > 0 ? ('id:' . $uid) : ('login:' . strtolower((string) $enriched['username']));
            if (isset($by_key[$key])) {
                $by_key[$key]['events'] = (int) $enriched['events'];
                $by_key[$key]['source'] = 'wp+wsal';
                $by_key[$key]['active'] = true;
            } else {
                $enriched['source'] = 'wsal';
                $enriched['active'] = !empty($enriched['active']);
                $by_key[$key]       = $enriched;
            }
        }
    }

    $users = array_values($by_key);
    usort(
        $users,
        static function ($a, $b) {
            $ea = isset($a['events']) ? (int) $a['events'] : 0;
            $eb = isset($b['events']) ? (int) $b['events'] : 0;
            if ($ea !== $eb) {
                return $eb <=> $ea;
            }
            return strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
        }
    );

    return new WP_REST_Response(
        array(
            'ok'             => true,
            'ready'          => true,
            'bridge_version' => HANGAR_CONNECT_VERSION,
            'site_url'       => home_url('/'),
            'site_name'      => get_bloginfo('name'),
            'users'          => $users,
            'users_count'    => count($users),
            'source'         => $wsal_enrichment ? 'wp+wsal' : 'wp',
            'wsal'           => $wsal,
            'activity_ready' => !empty($wsal['ready']) && !empty($wsal['tables_ready']),
        ),
        200
    );
}

/**
 * GET /activity/report — build productivity report for one user/period.
 *
 * Query: user (email or username), from (Y-m-d), to (Y-m-d), format=json|html|csv
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function hangar_connect_rest_activity_report(WP_REST_Request $request) {
    $wsal = hangar_connect_wsal_status();
    if (empty($wsal['tables_ready'])) {
        return hangar_connect_wsal_not_ready_response($wsal);
    }
    if (!hangar_connect_wsal_load_engine()) {
        return new WP_REST_Response(
            array(
                'ok'      => false,
                'code'    => 'wsal_engine_unavailable',
                'message' => 'Hangar Connect WSAL engine could not be loaded.',
                'ready'   => false,
                'wsal'    => $wsal,
            ),
            500
        );
    }

    $user_param = sanitize_text_field((string) $request->get_param('user'));
    $from       = sanitize_text_field((string) $request->get_param('from'));
    $to         = sanitize_text_field((string) $request->get_param('to'));
    $format     = sanitize_key((string) $request->get_param('format'));
    if ($format === '') {
        $format = 'json';
    }

    if ($user_param === '') {
        return new WP_Error(
            'missing_user',
            'Query param "user" (email or username) is required.',
            array('status' => 400)
        );
    }

    if (!hangar_connect_activity_user_exists($user_param) && is_email($user_param)) {
        return new WP_REST_Response(
            array(
                'ok'      => false,
                'code'    => 'user_not_found',
                'message' => 'No WordPress user with that email on this site.',
                'ready'   => true,
                'wsal'    => $wsal,
            ),
            404
        );
    }

    $username = hangar_connect_activity_resolve_username($user_param);
    $from_ts  = hangar_wsal_date_to_ts($from);
    $to_ts    = hangar_wsal_date_to_ts($to, true);

    if (null === $from_ts && $from !== '') {
        $from_ts = strtotime($from . ' 00:00:00');
    }
    if (null === $to_ts && $to !== '') {
        $to_ts = strtotime($to . ' 23:59:59');
    }

    list($analysis, $meta, $events, $details) = hangar_wsal_build_report($username, $from_ts, $to_ts);

    $events_count = is_array($events) ? count($events) : 0;
    $totals       = isset($analysis['totals']) && is_array($analysis['totals']) ? $analysis['totals'] : array();

    if ($format === 'html') {
        // Hangar SaaS renders the full multi-site template; Connect returns analysis JSON payload.
        return new WP_REST_Response(
            array(
                'ok'             => true,
                'ready'          => true,
                'bridge_version' => HANGAR_CONNECT_VERSION,
                'format'         => 'html',
                'site_url'       => home_url('/'),
                'site_name'      => get_bloginfo('name'),
                'events_count'   => $events_count,
                'totals'         => $totals,
                'meta'           => $meta,
                'analysis'       => $analysis,
                'html'           => '',
                'wsal'           => $wsal,
            ),
            200
        );
    }

    if ($format === 'csv') {
        $csv_rows   = array();
        $csv_rows[] = array(
            'Date/Time',
            'User',
            'Role',
            'IP',
            'Severity',
            'Action',
            'Object',
            'Type',
            'Event ID',
            'Detail',
        );
        foreach ((array) $events as $r) {
            if (!is_object($r)) {
                continue;
            }
            $oid    = isset($r->id) ? (int) $r->id : 0;
            $detail = '';
            if ($oid && is_array($details) && isset($details[$oid]) && is_array($details[$oid])) {
                foreach (array('PostTitle', 'FileName', 'PluginName') as $k) {
                    if (!empty($details[$oid][$k])) {
                        $detail = (string) $details[$oid][$k];
                        break;
                    }
                }
            }
            $label = function_exists('hangar_wsal_event_label')
                ? hangar_wsal_event_label($r->alert_id ?? 0, $r->event_type ?? '', $r->object ?? '')
                : '';
            $csv_rows[] = array(
                isset($r->created_on) ? wp_date('Y-m-d H:i:s', (int) $r->created_on) : '',
                isset($r->username) ? (string) $r->username : '',
                isset($r->user_roles) ? (string) $r->user_roles : '',
                isset($r->client_ip) ? (string) $r->client_ip : '',
                isset($r->severity) && function_exists('hangar_wsal_severity_label')
                    ? hangar_wsal_severity_label($r->severity)
                    : (isset($r->severity) ? (string) $r->severity : ''),
                $label,
                isset($r->object) ? (string) $r->object : '',
                isset($r->event_type) ? (string) $r->event_type : '',
                isset($r->alert_id) ? (string) $r->alert_id : '',
                $detail,
            );
        }

        return new WP_REST_Response(
            array(
                'ok'             => true,
                'ready'          => true,
                'bridge_version' => HANGAR_CONNECT_VERSION,
                'format'         => 'csv',
                'site_url'       => home_url('/'),
                'site_name'      => get_bloginfo('name'),
                'events_count'   => $events_count,
                'csv'            => $csv_rows,
                'wsal'           => $wsal,
            ),
            200
        );
    }

    $analysis_out = $analysis;
    if (isset($analysis_out['heatmap']) && is_array($analysis_out['heatmap']) && count($analysis_out['heatmap']) > 500) {
        $analysis_out['heatmap'] = array_slice($analysis_out['heatmap'], 0, 500);
    }

    return new WP_REST_Response(
        array(
            'ok'             => true,
            'ready'          => true,
            'bridge_version' => HANGAR_CONNECT_VERSION,
            'format'         => 'json',
            'site_url'       => home_url('/'),
            'site_name'      => get_bloginfo('name'),
            'events_count'   => $events_count,
            'totals'         => $totals,
            'analysis'       => $analysis_out,
            'meta'           => $meta,
            'wsal'           => $wsal,
        ),
        200
    );
}
