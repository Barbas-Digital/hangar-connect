<?php
/**
 * Hangar Connect — productivity report builder on top of native WSAL engine.
 */

defined('ABSPATH') || exit;

/**
 * Convert Y-m-d (site timezone) to UNIX timestamp (start or end of day).
 *
 * @param string $date Date string.
 * @param bool   $end  End of day when true.
 * @return float|null
 */
function hangar_wsal_date_to_ts($date, $end = false) {
    if (empty($date)) {
        return null;
    }
    $suffix = $end ? ' 23:59:59' : ' 00:00:00';
    $dt     = date_create($date . $suffix, wp_timezone());
    return $dt ? (float) $dt->getTimestamp() : null;
}

/**
 * Build full productivity analysis for one username and period.
 *
 * @param string     $user    WP username (exact).
 * @param float|null $from_ts Start timestamp.
 * @param float|null $to_ts   End timestamp.
 * @return array{0:array,1:array,2:array,3:array} analysis, meta, events, details
 */
function hangar_wsal_build_report($user, $from_ts, $to_ts) {
    $events = hangar_wsal_fetch_events(
        array(
            'user'       => $user,
            'user_exact' => true,
            'from_ts'    => $from_ts,
            'to_ts'      => $to_ts,
            'limit'      => 100000,
            'offset'     => 0,
        ),
        $total
    );

    $ids = array();
    foreach ($events as $e) {
        if (isset($e->id)) {
            $ids[] = (int) $e->id;
        }
    }
    $details  = hangar_wsal_fetch_metadata($ids, array('PostTitle', 'FileName', 'AttachmentName', 'FilePath', 'PluginName'));
    $analysis = hangar_wsal_analyze($events, $details);

    $analysis['compare'] = null;
    if (null !== $from_ts && null !== $to_ts && $to_ts > $from_ts) {
        $span      = (float) $to_ts - (float) $from_ts;
        $prev_to   = (float) $from_ts - 1;
        $prev_from = $prev_to - $span;
        $prev_events = hangar_wsal_fetch_events(
            array(
                'user'       => $user,
                'user_exact' => true,
                'from_ts'    => $prev_from,
                'to_ts'      => $prev_to,
                'limit'      => 100000,
                'offset'     => 0,
            ),
            $prev_total
        );
        $prev_ids = array();
        foreach ($prev_events as $e) {
            if (isset($e->id)) {
                $prev_ids[] = (int) $e->id;
            }
        }
        $prev_details  = hangar_wsal_fetch_metadata($prev_ids, array('PostTitle', 'FileName', 'AttachmentName', 'FilePath', 'PluginName'));
        $prev_analysis = hangar_wsal_analyze($prev_events, $prev_details);
        $cur_t  = $analysis['totals'];
        $prev_t = $prev_analysis['totals'];
        $delta  = static function ($now, $before) {
            $now    = (float) $now;
            $before = (float) $before;
            return array(
                'now'    => $now,
                'before' => $before,
                'diff'   => $now - $before,
                'pct'    => ($before > 0) ? round((($now - $before) / $before) * 100) : ($now > 0 ? 100 : 0),
            );
        };
        $analysis['compare'] = array(
            'from'     => wp_date('d/m/Y', (int) $prev_from),
            'to'       => wp_date('d/m/Y', (int) $prev_to),
            'work'     => $delta($cur_t['work'], $prev_t['work']),
            'active'   => $delta($cur_t['active_s'], $prev_t['active_s']),
            'events'   => $delta($cur_t['events'], $prev_t['events']),
            'sessions' => $delta($cur_t['sessions'], $prev_t['sessions']),
            'days'     => $delta($cur_t['days'], $prev_t['days']),
        );
    }

    $username = !empty($analysis['user']['username']) ? $analysis['user']['username'] : $user;
    $wp_user  = null;
    if (!empty($analysis['user']['user_id'])) {
        $wp_user = get_user_by('id', (int) $analysis['user']['user_id']);
    }
    if (!$wp_user && $username) {
        $wp_user = get_user_by('login', $username);
    }

    $meta = array(
        'display'     => $wp_user instanceof WP_User ? $wp_user->display_name : $username,
        'username'    => $username,
        'role'        => $analysis['user']['roles'],
        'email'       => $wp_user instanceof WP_User ? $wp_user->user_email : '',
        'site'        => wp_parse_url(home_url(), PHP_URL_HOST),
        'from'        => $from_ts ? wp_date('d/m/Y', (int) $from_ts) : '—',
        'to'          => $to_ts ? wp_date('d/m/Y', (int) $to_ts) : wp_date('d/m/Y'),
        'generated'   => wp_date('d/m/Y H:i'),
        'print'       => false,
        'from_ts'     => $from_ts,
        'to_ts'       => $to_ts,
        'detail_days' => 7,
        'avatar_data' => '',
        'avatar_url'  => ($wp_user instanceof WP_User) ? get_avatar_url($wp_user->ID, array('size' => 128)) : '',
    );

    return array($analysis, $meta, $events, $details);
}
