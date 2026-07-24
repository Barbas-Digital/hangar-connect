<?php
/**
 * Activity Reports bridge for Hangar (HMAC-protected REST).
 *
 * Real implementation: calls wsalr_known_users() / wsalr_build_report() from
 * Barbas Activity Reports (not a 501 stub).
 */

defined('ABSPATH') || exit;

/**
 * Resolve Activity Reports plugin directory.
 *
 * @return string Absolute path with trailing slash, or empty.
 */
function hangar_connect_activity_reports_dir() {
    if (defined('WSALR_DIR') && is_string(WSALR_DIR) && WSALR_DIR !== '') {
        return trailingslashit(WSALR_DIR);
    }
    if (defined('BARBAS_ACTIVITY_REPORTS_PLUGIN_FILE')) {
        return trailingslashit(plugin_dir_path(BARBAS_ACTIVITY_REPORTS_PLUGIN_FILE));
    }

    $candidates = array(
        WP_PLUGIN_DIR . '/barbas-activity-reports/',
        WP_PLUGIN_DIR . '/barbas-activity-reports-main/',
    );
    foreach ($candidates as $dir) {
        if (is_readable($dir . 'barbas-activity-reports.php')) {
            return trailingslashit($dir);
        }
    }
    return '';
}

/**
 * Ensure Activity Reports helpers are loaded (same include order as AR bootstrap).
 *
 * @return bool
 */
function hangar_connect_activity_ensure_loaded() {
    if (!hangar_connect_activity_reports_available()) {
        return false;
    }
    if (function_exists('wsalr_known_users') && function_exists('wsalr_build_report') && function_exists('wsalr_tables')) {
        return true;
    }

    $dir = hangar_connect_activity_reports_dir();
    if ($dir === '') {
        return false;
    }

    $files = array(
        'includes/events.php',
        'includes/data.php',
        'includes/analytics.php',
        'includes/report.php',
        'includes/admin.php',
    );
    foreach ($files as $rel) {
        $file = $dir . $rel;
        if (is_readable($file)) {
            require_once $file;
        }
    }

    return function_exists('wsalr_known_users')
        && function_exists('wsalr_build_report')
        && function_exists('wsalr_tables');
}

/**
 * Missing plugin response.
 *
 * @return WP_REST_Response
 */
function hangar_connect_activity_missing_response() {
    return new WP_REST_Response(
        array(
            'ok'      => false,
            'code'    => 'activity_reports_missing',
            'message' => 'Productivity report engine is not available on this site yet. Install Activity Reports as an optional shortcut, or wait for Hangar native collection.',
            'ready'   => false,
        ),
        501
    );
}

/**
 * Enrich a known-user row with WP email / display name.
 *
 * @param object $row Known user row from wsalr_known_users().
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
        'display_name' => $wp_user instanceof WP_User ? (string) $wp_user->display_name : $username,
        'events'       => $events,
    );
}

/**
 * Resolve report subject from email or username.
 *
 * @param string $user_param Email or username.
 * @return string Username (or original) for wsalr_build_report.
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
 * GET /activity/users and /wp/users — WordPress users (HMAC).
 *
 * Always lists WP users via get_users(). If Activity Reports is installed,
 * merges WSAL activity counts as an optional enrichment shortcut.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function hangar_connect_rest_activity_users(WP_REST_Request $request) {
    unset($request);

    $by_key = array();

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
        );
    }

    $ar_enrichment = false;
    if (hangar_connect_activity_reports_available() && hangar_connect_activity_ensure_loaded() && function_exists('wsalr_tables') && wsalr_tables() && function_exists('wsalr_known_users')) {
        $ar_enrichment = true;
        foreach ((array) wsalr_known_users() as $row) {
            if (!is_object($row)) {
                continue;
            }
            $enriched = hangar_connect_activity_enrich_user($row);
            $uid      = (int) $enriched['user_id'];
            $key      = $uid > 0 ? ('id:' . $uid) : ('login:' . strtolower((string) $enriched['username']));
            if (isset($by_key[$key])) {
                $by_key[$key]['events'] = (int) $enriched['events'];
                $by_key[$key]['source'] = 'wp+ar';
            } else {
                $enriched['source'] = 'ar';
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
            'source'         => $ar_enrichment ? 'wp+ar' : 'wp',
            'ar_shortcut'    => $ar_enrichment,
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
    if (!hangar_connect_activity_reports_available()) {
        return hangar_connect_activity_missing_response();
    }
    if (!hangar_connect_activity_ensure_loaded()) {
        return new WP_REST_Response(
            array(
                'ok'      => false,
                'code'    => 'activity_bridge_unavailable',
                'message' => 'Activity Reports helpers could not be loaded. Update Hangar Connect and Activity Reports.',
                'ready'   => false,
            ),
            500
        );
    }

    if (!wsalr_tables()) {
        return new WP_REST_Response(
            array(
                'ok'      => false,
                'code'    => 'wsal_tables_missing',
                'message' => 'WP Activity Log tables not found on this site.',
                'ready'   => false,
            ),
            503
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

    $username = hangar_connect_activity_resolve_username($user_param);
    $from_ts  = function_exists('wsalr_date_to_ts') ? wsalr_date_to_ts($from) : null;
    $to_ts    = function_exists('wsalr_date_to_ts') ? wsalr_date_to_ts($to, true) : null;

    if (null === $from_ts && $from !== '') {
        $from_ts = strtotime($from . ' 00:00:00');
    }
    if (null === $to_ts && $to !== '') {
        $to_ts = strtotime($to . ' 23:59:59');
    }

    list($analysis, $meta, $events, $details) = wsalr_build_report($username, $from_ts, $to_ts);

    $events_count = is_array($events) ? count($events) : 0;
    $totals       = isset($analysis['totals']) && is_array($analysis['totals']) ? $analysis['totals'] : array();

    if ($format === 'html' && function_exists('wsalr_render_report_html')) {
        $html = wsalr_render_report_html($analysis, $meta);
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
                'html'           => $html,
            ),
            200
        );
    }

    if ($format === 'csv') {
        $csv_rows = array();
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
            if ($oid && is_array($details) && isset($details[ $oid ]) && is_array($details[ $oid ])) {
                foreach (array('PostTitle', 'FileName', 'PluginName') as $k) {
                    if (!empty($details[ $oid ][ $k ])) {
                        $detail = (string) $details[ $oid ][ $k ];
                        break;
                    }
                }
            }
            $csv_rows[] = array(
                isset($r->created_on) ? wp_date('Y-m-d H:i:s', (int) $r->created_on) : '',
                isset($r->username) ? (string) $r->username : '',
                isset($r->user_roles) ? (string) $r->user_roles : '',
                isset($r->client_ip) ? (string) $r->client_ip : '',
                isset($r->severity) ? (string) $r->severity : '',
                '',
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
            ),
            200
        );
    }

    // Strip bulky raw events from default JSON (Central merges summaries).
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
        ),
        200
    );
}
