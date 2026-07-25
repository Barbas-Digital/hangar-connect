<?php
/**
 * Admin UI — connections management (ManageWP Worker–like).
 */

defined('ABSPATH') || exit;

define('HANGAR_CONNECT_MENU_SLUG', 'hangar-connect');

/**
 * Register Settings → Hangar Connect.
 */
function hangar_connect_register_admin_menu() {
    $title = function_exists('hangar_connect_display_name')
        ? hangar_connect_display_name()
        : __('Hangar Connect', 'hangar-connect');
    add_options_page(
        $title,
        $title,
        'manage_options',
        HANGAR_CONNECT_MENU_SLUG,
        'hangar_connect_render_admin_page'
    );
}
add_action('admin_menu', 'hangar_connect_register_admin_menu');

/**
 * Enqueue admin assets only on our screen.
 *
 * @param string $hook Current admin hook.
 */
function hangar_connect_admin_enqueue($hook) {
    if ($hook !== 'settings_page_' . HANGAR_CONNECT_MENU_SLUG) {
        return;
    }

    wp_enqueue_style(
        'hangar-connect-admin',
        HANGAR_CONNECT_URL . 'assets/css/hangar-connect-admin.css',
        array(),
        HANGAR_CONNECT_VERSION
    );

    wp_enqueue_script(
        'hangar-connect-admin',
        HANGAR_CONNECT_URL . 'assets/js/hangar-connect-admin.js',
        array(),
        HANGAR_CONNECT_VERSION,
        true
    );

    wp_localize_script(
        'hangar-connect-admin',
        'barbasConnectAdmin',
        array(
            'copied'  => __('Copied!', 'hangar-connect'),
            'copyFail'=> __('Could not copy. Select the key and copy manually.', 'hangar-connect'),
        )
    );
}
add_action('admin_enqueue_scripts', 'hangar_connect_admin_enqueue');

/**
 * Whether the current request is the Hangar Connect settings screen.
 *
 * @return bool
 */
function hangar_connect_is_admin_page() {
    if (!is_admin()) {
        return false;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && isset($screen->id) && $screen->id === 'settings_page_' . HANGAR_CONNECT_MENU_SLUG) {
        return true;
    }

    // Early fallback before get_current_screen() is available.
    return isset($_GET['page']) && sanitize_key(wp_unslash((string) $_GET['page'])) === HANGAR_CONNECT_MENU_SLUG;
}

/**
 * Remove third-party admin notices on the Connect screen (Barbas Update hub pattern).
 * Own notices are rendered inline in hangar_connect_render_admin_page().
 */
function hangar_connect_suppress_third_party_notices() {
    if (!hangar_connect_is_admin_page()) {
        return;
    }

    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');
    remove_all_actions('network_admin_notices');
    remove_all_actions('user_admin_notices');
}
add_action('in_admin_header', 'hangar_connect_suppress_third_party_notices', 999);
add_action('admin_head', 'hangar_connect_suppress_third_party_notices', 1);

/**
 * Plugin row action links.
 *
 * @param string[] $links Existing links.
 * @return string[]
 */
function hangar_connect_plugin_action_links($links) {
    $url = admin_url('options-general.php?page=' . HANGAR_CONNECT_MENU_SLUG);
    array_unshift(
        $links,
        '<a href="' . esc_url($url) . '">' . esc_html__('Connections', 'hangar-connect') . '</a>'
    );

    return $links;
}

/**
 * Register action links after constants exist.
 */
function hangar_connect_register_plugin_action_links() {
    if (!defined('HANGAR_CONNECT_PLUGIN_FILE')) {
        return;
    }
    add_filter(
        'plugin_action_links_' . plugin_basename(HANGAR_CONNECT_PLUGIN_FILE),
        'hangar_connect_plugin_action_links'
    );
}
add_action('plugins_loaded', 'hangar_connect_register_plugin_action_links', 15);

/**
 * Store a one-shot admin flash (notice + optional focus id / error message).
 *
 * @param string $notice Notice key.
 * @param string $focus_id Connection id for key reveal.
 * @param string $msg Error message (plain text).
 */
function hangar_connect_set_flash($notice, $focus_id = '', $msg = '') {
    $uid = get_current_user_id();
    if ($uid <= 0) {
        return;
    }
    set_transient(
        'hangar_connect_flash_' . $uid,
        array(
            'notice' => sanitize_key((string) $notice),
            'id'     => sanitize_key((string) $focus_id),
            'msg'    => sanitize_text_field((string) $msg),
        ),
        120
    );
}

/**
 * Consume flash once (deleted after read).
 *
 * @return array{notice:string,id:string,msg:string}|null
 */
function hangar_connect_consume_flash() {
    $uid = get_current_user_id();
    if ($uid <= 0) {
        return null;
    }
    $key = 'hangar_connect_flash_' . $uid;
    $data = get_transient($key);
    delete_transient($key);
    if (!is_array($data)) {
        return null;
    }
    return array(
        'notice' => isset($data['notice']) ? sanitize_key((string) $data['notice']) : '',
        'id'     => isset($data['id']) ? sanitize_key((string) $data['id']) : '',
        'msg'    => isset($data['msg']) ? sanitize_text_field((string) $data['msg']) : '',
    );
}

/**
 * Handle admin-post actions (generate / rotate / disconnect).
 * Always redirects to a clean admin URL; flash carries notice/key id (no bc_* query args).
 */
function hangar_connect_handle_admin_actions() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to manage connections.', 'hangar-connect'));
    }

    check_admin_referer('hangar_connect_admin', '_hangar_connect_nonce');

    $action = isset($_POST['hangar_connect_action'])
        ? sanitize_key(wp_unslash((string) $_POST['hangar_connect_action']))
        : '';

    $redirect = admin_url('options-general.php?page=' . HANGAR_CONNECT_MENU_SLUG);

    switch ($action) {
        case 'generate':
            if (!empty(hangar_connect_get_all_connections())) {
                hangar_connect_set_flash(
                    'error',
                    '',
                    __('This site already has a connection. Disconnect it before pairing with another Hangar.', 'hangar-connect')
                );
                wp_safe_redirect($redirect);
                exit;
            }
            $label = isset($_POST['connection_label'])
                ? sanitize_text_field(wp_unslash((string) $_POST['connection_label']))
                : '';
            $result = hangar_connect_create_connection($label);
            if (is_wp_error($result)) {
                hangar_connect_set_flash('error', '', $result->get_error_message());
            } else {
                hangar_connect_set_flash('generated', $result['connection']['id'], '');
            }
            break;

        case 'rotate':
            $id = isset($_POST['connection_id'])
                ? sanitize_key(wp_unslash((string) $_POST['connection_id']))
                : '';
            $result = hangar_connect_rotate_connection($id);
            if (is_wp_error($result)) {
                hangar_connect_set_flash('error', '', $result->get_error_message());
            } else {
                hangar_connect_set_flash('rotated', $id, '');
            }
            break;

        case 'disconnect':
            $id = isset($_POST['connection_id'])
                ? sanitize_key(wp_unslash((string) $_POST['connection_id']))
                : '';
            $result = hangar_connect_delete_connection($id);
            if (is_wp_error($result)) {
                hangar_connect_set_flash('error', '', $result->get_error_message());
            } else {
                hangar_connect_set_flash('disconnected');
            }
            break;

        case 'disconnect_all':
            hangar_connect_delete_all_connections();
            hangar_connect_set_flash('disconnected_all');
            break;

        case 'dismiss_key':
            $id = isset($_POST['connection_id'])
                ? sanitize_key(wp_unslash((string) $_POST['connection_id']))
                : '';
            if ($id !== '') {
                hangar_connect_clear_revealed_key($id);
            }
            break;

        default:
            hangar_connect_set_flash('error');
            break;
    }

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_hangar_connect_action', 'hangar_connect_handle_admin_actions');

/**
 * Format unix time for admin display (locale-aware).
 *
 * pt_BR uses DD/MM/YYYY HH:MM (e.g. 20/07/2026 18:56).
 *
 * @param int $ts Timestamp.
 * @return string
 */
function hangar_connect_format_time($ts) {
    $ts = (int) $ts;
    if ($ts <= 0) {
        return '—';
    }

    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    if (is_string($locale) && strpos($locale, 'pt_') === 0) {
        return wp_date('d/m/Y H:i', $ts);
    }

    $format = trim((string) get_option('date_format') . ' ' . (string) get_option('time_format'));
    if ($format === '') {
        $format = 'Y-m-d H:i';
    }

    return wp_date($format, $ts);
}

/**
 * Status badge label.
 *
 * @param string $status Status key.
 * @return string
 */
function hangar_connect_status_label($status) {
    switch ($status) {
        case 'connected':
            return __('Connected', 'hangar-connect');
        case 'pending':
            return __('Pending', 'hangar-connect');
        default:
            return __('Unknown', 'hangar-connect');
    }
}

/**
 * Render admin page.
 */
function hangar_connect_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $connections = hangar_connect_get_all_connections();
    $flash = hangar_connect_consume_flash();
    $notice = $flash ? $flash['notice'] : '';
    $focus_id = $flash ? $flash['id'] : '';
    $error_msg = $flash ? $flash['msg'] : '';
    // Legacy query-arg flash (pre-0.1.12) — still honor once, then JS strips the URL.
    if ($notice === '' && isset($_GET['bc_notice'])) {
        $notice = sanitize_key(wp_unslash((string) $_GET['bc_notice']));
    }
    if ($focus_id === '' && isset($_GET['bc_id'])) {
        $focus_id = sanitize_key(wp_unslash((string) $_GET['bc_id']));
    }
    if ($error_msg === '' && isset($_GET['bc_msg'])) {
        $error_msg = sanitize_text_field(rawurldecode(wp_unslash((string) $_GET['bc_msg'])));
    }

    $health_url = rest_url(HANGAR_CONNECT_REST_NS . '/health');

    echo '<div class="wrap hangar-connect-wrap">';
    echo '<div class="hangar-connect-app">';

    echo '<header class="hangar-connect-header">';
    echo '<div class="hangar-connect-header__brand">';
    echo '<h1 class="hangar-connect-title-sr">' . esc_html__('Hangar Connect', 'hangar-connect') . '</h1>';
    echo '<div class="hangar-connect-header__lockup">';
    echo '<img class="hangar-connect-logo" src="' . esc_url(HANGAR_CONNECT_URL . 'assets/img/hangar-connect-light.svg') . '" alt="' . esc_attr__('Hangar Connect', 'hangar-connect') . '" width="280" height="44" decoding="async" />';
    echo '<span class="hangar-connect-pill">' . esc_html(sprintf(/* translators: %s: version */ __('v%s', 'hangar-connect'), HANGAR_CONNECT_VERSION)) . '</span>';
    echo '</div>';
    echo '<p class="hangar-connect-lead">';
    echo esc_html__('Connect this site to Hangar.', 'hangar-connect');
    echo '<br />';
    echo esc_html__(
        'Generate a pairing key, paste it in Hangar, then manage or revoke connections here.',
        'hangar-connect'
    );
    echo '</p>';
    echo '</div>';
    echo '</header>';

    if ($notice !== '') {
        $class = ($notice === 'error') ? 'notice-error' : 'notice-success';
        $text = '';
        switch ($notice) {
            case 'generated':
                $text = __('New pairing key created. Copy it now — it will not be shown again.', 'hangar-connect');
                break;
            case 'rotated':
                $text = __('Pairing key rotated. Copy the new key now.', 'hangar-connect');
                break;
            case 'disconnected':
                $text = __('Connection disconnected.', 'hangar-connect');
                break;
            case 'disconnected_all':
                $text = __('All connections disconnected.', 'hangar-connect');
                break;
            case 'error':
                $text = $error_msg !== '' ? $error_msg : __('Something went wrong.', 'hangar-connect');
                break;
            default:
                $text = '';
        }
        if ($text !== '') {
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible hangar-connect-notice"><p>' . esc_html($text) . '</p></div>';
        }
    }

    // Reveal panel for newly generated/rotated key.
    if ($focus_id !== '') {
        $revealed = hangar_connect_peek_revealed_key($focus_id);
        if ($revealed !== '') {
            echo '<section class="hangar-connect-card hangar-connect-card--reveal" aria-live="polite">';
            echo '<h2>' . esc_html__('Your pairing key', 'hangar-connect') . '</h2>';
            echo '<p>' . esc_html__(
                'Copy this key into Hangar. For security it is shown only once.',
                'hangar-connect'
            ) . '</p>';
            echo '<div class="hangar-connect-key-row">';
            echo '<code class="hangar-connect-key" id="hangar-connect-pairing-key">' . esc_html($revealed) . '</code>';
            echo '<button type="button" class="button button-primary hangar-connect-copy" data-target="hangar-connect-pairing-key">' . esc_html__('Copy', 'hangar-connect') . '</button>';
            echo '</div>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hangar-connect-inline-form">';
            echo '<input type="hidden" name="action" value="hangar_connect_action" />';
            wp_nonce_field('hangar_connect_admin', '_hangar_connect_nonce');
            echo '<input type="hidden" name="hangar_connect_action" value="dismiss_key" />';
            echo '<input type="hidden" name="connection_id" value="' . esc_attr($focus_id) . '" />';
            echo '<button type="submit" class="button-link">' . esc_html__('Hide key', 'hangar-connect') . '</button>';
            echo '</form>';
            echo '</section>';
        }
    }

    // Status card (includes compact WP Activity Log readiness).
    $wsal = function_exists('hangar_connect_wsal_status') ? hangar_connect_wsal_status() : array(
        'ready'         => false,
        'tables_ready'  => false,
        'plugin_active' => false,
        'code'          => 'wsal_missing',
        'message'       => '',
    );
    $wsal_ok = !empty($wsal['plugin_active']) && !empty($wsal['tables_ready']);
    $wsal_plugin_active = !empty($wsal['plugin_active']);

    echo '<section class="hangar-connect-card">';
    echo '<h2>' . esc_html__('Site status', 'hangar-connect') . '</h2>';
    echo '<dl class="hangar-connect-status-grid">';
    echo '<div><dt>' . esc_html__('Site URL', 'hangar-connect') . '</dt><dd><code class="hangar-connect-status-value">' . esc_html(home_url('/')) . '</code></dd></div>';
    echo '<div><dt>' . esc_html__('Health endpoint', 'hangar-connect') . '</dt><dd><code class="hangar-connect-status-value">' . esc_html($health_url) . '</code></dd></div>';
    echo '</dl>';

    echo '<div class="hangar-connect-wsal' . ($wsal_ok ? ' is-ok' : ' is-warn') . '">';
    echo '<div class="hangar-connect-wsal__body">';
    echo '<span class="hangar-connect-wsal__label">' . esc_html__('WP Activity Log', 'hangar-connect') . '</span>';
    if ($wsal_ok) {
        echo '<p class="hangar-connect-wsal__msg">' . esc_html__(
            'Active for productivity reports.',
            'hangar-connect'
        ) . '</p>';
    } else {
        $wsal_msg = '';
        if (!empty($wsal['code'])) {
            switch ((string) $wsal['code']) {
                case 'wsal_tables_missing':
                    $wsal_msg = __('Plugin is active, but log tables were not found yet. Open WP Activity Log once so tables can be created.', 'hangar-connect');
                    break;
                case 'wsal_plugin_inactive':
                    $wsal_msg = __('Log tables were found, but the plugin is not active. Activate WP Activity Log to keep recording events.', 'hangar-connect');
                    break;
                default:
                    $wsal_msg = __('Install and activate WP Activity Log to enable productivity reports.', 'hangar-connect');
                    break;
            }
        }
        echo '<p class="hangar-connect-wsal__msg">' . esc_html($wsal_msg) . '</p>';
        $install_url = self_admin_url('plugin-install.php?s=wp-activity-log&tab=search&type=term');
        echo '<p class="hangar-connect-wsal__action"><a href="' . esc_url($install_url) . '">' . esc_html__('Find WP Activity Log', 'hangar-connect') . '</a></p>';
    }
    echo '</div>';
    if ($wsal_ok) {
        $badge = __('Active', 'hangar-connect');
    } elseif ($wsal_plugin_active) {
        $badge = __('Setup needed', 'hangar-connect');
    } else {
        $badge = __('Inactive', 'hangar-connect');
    }
    echo '<span class="hangar-connect-wsal__badge">' . esc_html($badge) . '</span>';
    echo '</div>';
    echo '</section>';

    // Generate card — only when no connection exists (one Hangar at a time).
    if (empty($connections)) {
        echo '<section class="hangar-connect-card">';
        echo '<h2>' . esc_html__('New connection', 'hangar-connect') . '</h2>';
        echo '<p>' . esc_html__(
            'Creates a pairing key for Hangar.',
            'hangar-connect'
        ) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hangar-connect-form">';
        echo '<input type="hidden" name="action" value="hangar_connect_action" />';
        wp_nonce_field('hangar_connect_admin', '_hangar_connect_nonce');
        echo '<input type="hidden" name="hangar_connect_action" value="generate" />';
        echo '<p class="hangar-connect-field">';
        echo '<label for="hangar-connect-label">' . esc_html__('Label (optional)', 'hangar-connect') . '</label>';
        echo '<input type="text" class="regular-text" id="hangar-connect-label" name="connection_label" maxlength="80" placeholder="' . esc_attr__('e.g. Production', 'hangar-connect') . '" />';
        echo '</p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Generate pairing key', 'hangar-connect') . '</button></p>';
        echo '</form>';
        echo '</section>';
    }

    // Connections list.
    echo '<section class="hangar-connect-card">';
    echo '<div class="hangar-connect-card__head">';
    echo '<h2>' . esc_html__('Connections', 'hangar-connect') . '</h2>';
    if (!empty($connections)) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(' . wp_json_encode(__('Disconnect all connections? Pairing keys will stop working.', 'hangar-connect')) . ');">';
        echo '<input type="hidden" name="action" value="hangar_connect_action" />';
        wp_nonce_field('hangar_connect_admin', '_hangar_connect_nonce');
        echo '<input type="hidden" name="hangar_connect_action" value="disconnect_all" />';
        echo '<button type="submit" class="button">' . esc_html__('Disconnect all', 'hangar-connect') . '</button>';
        echo '</form>';
    }
    echo '</div>';

    if (empty($connections)) {
        echo '<p class="hangar-connect-empty">' . esc_html__('No connections yet. Generate a pairing key to get started.', 'hangar-connect') . '</p>';
    } else {
        echo '<table class="widefat striped hangar-connect-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Label', 'hangar-connect') . '</th>';
        echo '<th>' . esc_html__('Created', 'hangar-connect') . '</th>';
        echo '<th>' . esc_html__('Last seen', 'hangar-connect') . '</th>';
        echo '<th>' . esc_html__('Actions', 'hangar-connect') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($connections as $row) {
            $status = $row['status'];
            echo '<tr>';
            echo '<td data-label="' . esc_attr__('Label', 'hangar-connect') . '">';
            echo '<span class="hangar-connect-label-row">';
            echo '<span class="hangar-connect-label-text">' . esc_html($row['label'] !== '' ? $row['label'] : __('(no label)', 'hangar-connect')) . '</span>';
            echo '<span class="hangar-connect-status hangar-connect-status--' . esc_attr($status) . '">' . esc_html(hangar_connect_status_label($status)) . '</span>';
            echo '</span>';
            echo '<code class="hangar-connect-conn-id" title="' . esc_attr__('Connection ID', 'hangar-connect') . '">' . esc_html($row['id']) . '</code>';
            echo '</td>';
            echo '<td data-label="' . esc_attr__('Created', 'hangar-connect') . '">' . esc_html(hangar_connect_format_time($row['created_at'])) . '</td>';
            echo '<td data-label="' . esc_attr__('Last seen', 'hangar-connect') . '">' . esc_html(hangar_connect_format_time($row['last_seen_at'])) . '</td>';
            echo '<td class="hangar-connect-actions" data-label="' . esc_attr__('Actions', 'hangar-connect') . '">';

            // Hide rotate while connected — one site = one Hangar; regenerating confuses the paired link.
            if ($status !== 'connected') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hangar-connect-inline-form">';
                echo '<input type="hidden" name="action" value="hangar_connect_action" />';
                wp_nonce_field('hangar_connect_admin', '_hangar_connect_nonce');
                echo '<input type="hidden" name="hangar_connect_action" value="rotate" />';
                echo '<input type="hidden" name="connection_id" value="' . esc_attr($row['id']) . '" />';
                echo '<button type="submit" class="button">' . esc_html__('Generate new key', 'hangar-connect') . '</button>';
                echo '</form>';
            }

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hangar-connect-inline-form" onsubmit="return confirm(' . wp_json_encode(__('Disconnect this connection?', 'hangar-connect')) . ');">';
            echo '<input type="hidden" name="action" value="hangar_connect_action" />';
            wp_nonce_field('hangar_connect_admin', '_hangar_connect_nonce');
            echo '<input type="hidden" name="hangar_connect_action" value="disconnect" />';
            echo '<input type="hidden" name="connection_id" value="' . esc_attr($row['id']) . '" />';
            echo '<button type="submit" class="button">' . esc_html__('Disconnect', 'hangar-connect') . '</button>';
            echo '</form>';

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
    echo '</section>';

    hangar_connect_render_brand_footer();

    echo '</div></div>';
}

/**
 * Vendor footer — same Barbas Digital block as the rest of the suite.
 */
function hangar_connect_render_brand_footer() {
    if (function_exists('barbas_update_render_brand_footer')) {
        barbas_update_render_brand_footer();
        return;
    }

    $logo = HANGAR_CONNECT_URL . 'assets/img/logo-black.svg';
    $site = 'https://www.barbas.digital';
    echo '<footer class="barbas-update-footer">';
    echo '<a href="' . esc_url($site) . '" class="barbas-update-footer__logo-link" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Barbas Digital', 'hangar-connect') . '">';
    echo '<img src="' . esc_url($logo) . '" alt="" class="barbas-update-footer__logo" width="36" height="63" decoding="async" />';
    echo '</a>';
    echo '<div class="barbas-update-footer__copy">';
    echo '<p class="barbas-update-footer__name"><a href="' . esc_url($site) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Barbas Digital', 'hangar-connect') . '</a></p>';
    echo '<p class="barbas-update-footer__tagline">' . esc_html__('The brand behind the most impactful websites on the Internet.', 'hangar-connect') . '</p>';
    echo '</div>';
    echo '</footer>';
}
