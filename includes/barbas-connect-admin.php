<?php
/**
 * Admin UI — connections management (ManageWP Worker–like).
 */

defined('ABSPATH') || exit;

define('BARBAS_CONNECT_MENU_SLUG', 'barbas-connect');

/**
 * Register Settings → Barbas Connect.
 */
function barbas_connect_register_admin_menu() {
    add_options_page(
        __('Barbas Connect', 'barbas-connect'),
        __('Barbas Connect', 'barbas-connect'),
        'manage_options',
        BARBAS_CONNECT_MENU_SLUG,
        'barbas_connect_render_admin_page'
    );
}
add_action('admin_menu', 'barbas_connect_register_admin_menu');

/**
 * Enqueue admin assets only on our screen.
 *
 * @param string $hook Current admin hook.
 */
function barbas_connect_admin_enqueue($hook) {
    if ($hook !== 'settings_page_' . BARBAS_CONNECT_MENU_SLUG) {
        return;
    }

    $hub_css_deps = array();
    if (defined('BARBAS_UPDATE_PLUGIN_FILE')) {
        $hub_ver = defined('BARBAS_UPDATE_HUB_VERSION') ? BARBAS_UPDATE_HUB_VERSION : BARBAS_CONNECT_VERSION;
        wp_enqueue_style(
            'barbas-update-admin',
            plugins_url('assets/css/barbas-update-admin.css', BARBAS_UPDATE_PLUGIN_FILE),
            array(),
            $hub_ver
        );
        $hub_css_deps[] = 'barbas-update-admin';
    }

    wp_enqueue_style(
        'barbas-connect-admin',
        BARBAS_CONNECT_URL . 'assets/css/barbas-connect-admin.css',
        $hub_css_deps,
        BARBAS_CONNECT_VERSION
    );

    wp_enqueue_script(
        'barbas-connect-admin',
        BARBAS_CONNECT_URL . 'assets/js/barbas-connect-admin.js',
        array(),
        BARBAS_CONNECT_VERSION,
        true
    );

    wp_localize_script(
        'barbas-connect-admin',
        'barbasConnectAdmin',
        array(
            'copied'  => __('Copied!', 'barbas-connect'),
            'copyFail'=> __('Could not copy. Select the key and copy manually.', 'barbas-connect'),
        )
    );
}
add_action('admin_enqueue_scripts', 'barbas_connect_admin_enqueue');

/**
 * Whether the current request is the Barbas Connect settings screen.
 *
 * @return bool
 */
function barbas_connect_is_admin_page() {
    if (!is_admin()) {
        return false;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && isset($screen->id) && $screen->id === 'settings_page_' . BARBAS_CONNECT_MENU_SLUG) {
        return true;
    }

    // Early fallback before get_current_screen() is available.
    return isset($_GET['page']) && sanitize_key(wp_unslash((string) $_GET['page'])) === BARBAS_CONNECT_MENU_SLUG;
}

/**
 * Remove third-party admin notices on the Connect screen (Barbas Update hub pattern).
 * Own notices are rendered inline in barbas_connect_render_admin_page().
 */
function barbas_connect_suppress_third_party_notices() {
    if (!barbas_connect_is_admin_page()) {
        return;
    }

    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');
    remove_all_actions('network_admin_notices');
    remove_all_actions('user_admin_notices');
}
add_action('in_admin_header', 'barbas_connect_suppress_third_party_notices', 999);
add_action('admin_head', 'barbas_connect_suppress_third_party_notices', 1);

/**
 * Plugin row action links.
 *
 * @param string[] $links Existing links.
 * @return string[]
 */
function barbas_connect_plugin_action_links($links) {
    $url = admin_url('options-general.php?page=' . BARBAS_CONNECT_MENU_SLUG);
    array_unshift(
        $links,
        '<a href="' . esc_url($url) . '">' . esc_html__('Connections', 'barbas-connect') . '</a>'
    );

    if (function_exists('barbas_update_settings_url')) {
        array_unshift(
            $links,
            '<a href="' . esc_url(barbas_update_settings_url('connect')) . '">' . esc_html__('License', 'barbas-connect') . '</a>'
        );
    }

    return $links;
}

/**
 * Register action links after constants exist.
 */
function barbas_connect_register_plugin_action_links() {
    if (!defined('BARBAS_CONNECT_PLUGIN_FILE')) {
        return;
    }
    add_filter(
        'plugin_action_links_' . plugin_basename(BARBAS_CONNECT_PLUGIN_FILE),
        'barbas_connect_plugin_action_links'
    );
}
add_action('plugins_loaded', 'barbas_connect_register_plugin_action_links', 15);

/**
 * Handle admin-post actions (generate / rotate / disconnect).
 */
function barbas_connect_handle_admin_actions() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to manage connections.', 'barbas-connect'));
    }

    check_admin_referer('barbas_connect_admin', '_barbas_connect_nonce');

    $action = isset($_POST['barbas_connect_action'])
        ? sanitize_key(wp_unslash((string) $_POST['barbas_connect_action']))
        : '';

    $redirect = admin_url('options-general.php?page=' . BARBAS_CONNECT_MENU_SLUG);
    $notice = 'ok';

    switch ($action) {
        case 'generate':
            if (!empty(barbas_connect_get_all_connections())) {
                $redirect = add_query_arg(
                    array(
                        'bc_notice' => 'error',
                        'bc_msg'    => rawurlencode(
                            __('This site already has a connection. Disconnect it before pairing with another Central.', 'barbas-connect')
                        ),
                    ),
                    $redirect
                );
                wp_safe_redirect($redirect);
                exit;
            }
            $label = isset($_POST['connection_label'])
                ? sanitize_text_field(wp_unslash((string) $_POST['connection_label']))
                : '';
            $result = barbas_connect_create_connection($label);
            if (is_wp_error($result)) {
                $notice = 'error';
                $redirect = add_query_arg(
                    array(
                        'bc_notice' => $notice,
                        'bc_msg'    => rawurlencode($result->get_error_message()),
                    ),
                    $redirect
                );
            } else {
                $redirect = add_query_arg(
                    array(
                        'bc_notice' => 'generated',
                        'bc_id'     => $result['connection']['id'],
                    ),
                    $redirect
                );
            }
            break;

        case 'rotate':
            $id = isset($_POST['connection_id'])
                ? sanitize_key(wp_unslash((string) $_POST['connection_id']))
                : '';
            $result = barbas_connect_rotate_connection($id);
            if (is_wp_error($result)) {
                $redirect = add_query_arg(
                    array(
                        'bc_notice' => 'error',
                        'bc_msg'    => rawurlencode($result->get_error_message()),
                    ),
                    $redirect
                );
            } else {
                $redirect = add_query_arg(
                    array(
                        'bc_notice' => 'rotated',
                        'bc_id'     => $id,
                    ),
                    $redirect
                );
            }
            break;

        case 'disconnect':
            $id = isset($_POST['connection_id'])
                ? sanitize_key(wp_unslash((string) $_POST['connection_id']))
                : '';
            $result = barbas_connect_delete_connection($id);
            $notice = is_wp_error($result) ? 'error' : 'disconnected';
            $args = array('bc_notice' => $notice);
            if (is_wp_error($result)) {
                $args['bc_msg'] = rawurlencode($result->get_error_message());
            }
            $redirect = add_query_arg($args, $redirect);
            break;

        case 'disconnect_all':
            barbas_connect_delete_all_connections();
            $redirect = add_query_arg(array('bc_notice' => 'disconnected_all'), $redirect);
            break;

        case 'dismiss_key':
            $id = isset($_POST['connection_id'])
                ? sanitize_key(wp_unslash((string) $_POST['connection_id']))
                : '';
            if ($id !== '') {
                barbas_connect_clear_revealed_key($id);
            }
            break;

        default:
            $redirect = add_query_arg(array('bc_notice' => 'error'), $redirect);
            break;
    }

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_barbas_connect_action', 'barbas_connect_handle_admin_actions');

/**
 * Format unix time for admin display (locale-aware).
 *
 * pt_BR uses DD/MM/YYYY HH:MM (e.g. 20/07/2026 18:56).
 *
 * @param int $ts Timestamp.
 * @return string
 */
function barbas_connect_format_time($ts) {
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
function barbas_connect_status_label($status) {
    switch ($status) {
        case 'connected':
            return __('Connected', 'barbas-connect');
        case 'pending':
            return __('Pending pairing', 'barbas-connect');
        default:
            return __('Unknown', 'barbas-connect');
    }
}

/**
 * Render admin page.
 */
function barbas_connect_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $connections = barbas_connect_get_all_connections();
    $notice = isset($_GET['bc_notice']) ? sanitize_key(wp_unslash((string) $_GET['bc_notice'])) : '';
    $focus_id = isset($_GET['bc_id']) ? sanitize_key(wp_unslash((string) $_GET['bc_id'])) : '';
    $error_msg = isset($_GET['bc_msg']) ? sanitize_text_field(rawurldecode(wp_unslash((string) $_GET['bc_msg']))) : '';

    $health_url = rest_url(BARBAS_CONNECT_REST_NS . '/health');

    echo '<div class="wrap barbas-connect-wrap">';
    echo '<div class="barbas-connect-app">';

    echo '<header class="barbas-connect-header">';
    echo '<div class="barbas-connect-header__copy">';
    echo '<h1>' . esc_html__('Barbas Connect', 'barbas-connect') . '</h1>';
    echo '<p class="barbas-connect-lead">' . esc_html__(
        'Connect this site to Barbas Central. Generate a pairing key, paste it in Barbas Central, then manage or revoke connections here.',
        'barbas-connect'
    ) . '</p>';
    echo '</div>';
    echo '<div class="barbas-connect-header__meta">';
    echo '<span class="barbas-connect-pill">' . esc_html(sprintf(/* translators: %s: version */ __('v%s', 'barbas-connect'), BARBAS_CONNECT_VERSION)) . '</span>';
    echo '</div>';
    echo '</header>';

    if ($notice !== '') {
        $class = ($notice === 'error') ? 'notice-error' : 'notice-success';
        $text = '';
        switch ($notice) {
            case 'generated':
                $text = __('New pairing key created. Copy it now — it will not be shown again.', 'barbas-connect');
                break;
            case 'rotated':
                $text = __('Pairing key rotated. Copy the new key now.', 'barbas-connect');
                break;
            case 'disconnected':
                $text = __('Connection disconnected.', 'barbas-connect');
                break;
            case 'disconnected_all':
                $text = __('All connections disconnected.', 'barbas-connect');
                break;
            case 'error':
                $text = $error_msg !== '' ? $error_msg : __('Something went wrong.', 'barbas-connect');
                break;
            default:
                $text = '';
        }
        if ($text !== '') {
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible barbas-connect-notice"><p>' . esc_html($text) . '</p></div>';
        }
    }

    // Reveal panel for newly generated/rotated key.
    if ($focus_id !== '') {
        $revealed = barbas_connect_peek_revealed_key($focus_id);
        if ($revealed !== '') {
            echo '<section class="barbas-connect-card barbas-connect-card--reveal" aria-live="polite">';
            echo '<h2>' . esc_html__('Your pairing key', 'barbas-connect') . '</h2>';
            echo '<p>' . esc_html__(
                'Copy this key into Barbas Central. For security it is shown only once.',
                'barbas-connect'
            ) . '</p>';
            echo '<div class="barbas-connect-key-row">';
            echo '<code class="barbas-connect-key" id="barbas-connect-pairing-key">' . esc_html($revealed) . '</code>';
            echo '<button type="button" class="button button-primary barbas-connect-copy" data-target="barbas-connect-pairing-key">' . esc_html__('Copy', 'barbas-connect') . '</button>';
            echo '</div>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="barbas-connect-inline-form">';
            echo '<input type="hidden" name="action" value="barbas_connect_action" />';
            wp_nonce_field('barbas_connect_admin', '_barbas_connect_nonce');
            echo '<input type="hidden" name="barbas_connect_action" value="dismiss_key" />';
            echo '<input type="hidden" name="connection_id" value="' . esc_attr($focus_id) . '" />';
            echo '<button type="submit" class="button-link">' . esc_html__('Hide key', 'barbas-connect') . '</button>';
            echo '</form>';
            echo '</section>';
        }
    }

    // Status card.
    echo '<section class="barbas-connect-card">';
    echo '<h2>' . esc_html__('Site status', 'barbas-connect') . '</h2>';
    echo '<dl class="barbas-connect-status-grid">';
    echo '<div><dt>' . esc_html__('Site URL', 'barbas-connect') . '</dt><dd><code class="barbas-connect-status-value">' . esc_html(home_url('/')) . '</code></dd></div>';
    echo '<div><dt>' . esc_html__('Health endpoint', 'barbas-connect') . '</dt><dd><code class="barbas-connect-status-value">' . esc_html($health_url) . '</code></dd></div>';
    echo '<div><dt>' . esc_html__('Activity Reports', 'barbas-connect') . '</dt><dd>';
    echo barbas_connect_activity_reports_available()
        ? esc_html__('Available', 'barbas-connect')
        : esc_html__('Not installed / inactive', 'barbas-connect');
    echo '</dd></div>';
    echo '<div><dt>' . esc_html__('Connected to Central', 'barbas-connect') . '</dt><dd>';
    echo barbas_connect_has_active_connection()
        ? esc_html__('Yes', 'barbas-connect')
        : esc_html__('No', 'barbas-connect');
    echo '</dd></div>';
    echo '</dl>';
    echo '</section>';

    // Generate card — only when no connection exists (one Central at a time).
    if (empty($connections)) {
        echo '<section class="barbas-connect-card">';
        echo '<h2>' . esc_html__('New connection', 'barbas-connect') . '</h2>';
        echo '<p>' . esc_html__(
            'Creates a pairing key for Barbas Central.',
            'barbas-connect'
        ) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="barbas-connect-form">';
        echo '<input type="hidden" name="action" value="barbas_connect_action" />';
        wp_nonce_field('barbas_connect_admin', '_barbas_connect_nonce');
        echo '<input type="hidden" name="barbas_connect_action" value="generate" />';
        echo '<p class="barbas-connect-field">';
        echo '<label for="barbas-connect-label">' . esc_html__('Label (optional)', 'barbas-connect') . '</label>';
        echo '<input type="text" class="regular-text" id="barbas-connect-label" name="connection_label" maxlength="80" placeholder="' . esc_attr__('e.g. Production', 'barbas-connect') . '" />';
        echo '</p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Generate pairing key', 'barbas-connect') . '</button></p>';
        echo '</form>';
        echo '</section>';
    }

    // Connections list.
    echo '<section class="barbas-connect-card">';
    echo '<div class="barbas-connect-card__head">';
    echo '<h2>' . esc_html__('Connections', 'barbas-connect') . '</h2>';
    if (!empty($connections)) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(' . wp_json_encode(__('Disconnect all connections? Pairing keys will stop working.', 'barbas-connect')) . ');">';
        echo '<input type="hidden" name="action" value="barbas_connect_action" />';
        wp_nonce_field('barbas_connect_admin', '_barbas_connect_nonce');
        echo '<input type="hidden" name="barbas_connect_action" value="disconnect_all" />';
        echo '<button type="submit" class="button">' . esc_html__('Disconnect all', 'barbas-connect') . '</button>';
        echo '</form>';
    }
    echo '</div>';

    if (empty($connections)) {
        echo '<p class="barbas-connect-empty">' . esc_html__('No connections yet. Generate a pairing key to get started.', 'barbas-connect') . '</p>';
    } else {
        echo '<table class="widefat striped barbas-connect-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Label', 'barbas-connect') . '</th>';
        echo '<th>' . esc_html__('Status', 'barbas-connect') . '</th>';
        echo '<th>' . esc_html__('Created', 'barbas-connect') . '</th>';
        echo '<th>' . esc_html__('Last seen', 'barbas-connect') . '</th>';
        echo '<th>' . esc_html__('Actions', 'barbas-connect') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($connections as $row) {
            $status = $row['status'];
            echo '<tr>';
            echo '<td data-label="' . esc_attr__('Label', 'barbas-connect') . '">';
            echo esc_html($row['label'] !== '' ? $row['label'] : __('(no label)', 'barbas-connect'));
            echo '<div class="barbas-connect-muted"><code>' . esc_html($row['id']) . '</code></div>';
            echo '</td>';
            echo '<td data-label="' . esc_attr__('Status', 'barbas-connect') . '">';
            echo '<span class="barbas-connect-status barbas-connect-status--' . esc_attr($status) . '">' . esc_html(barbas_connect_status_label($status)) . '</span>';
            echo '</td>';
            echo '<td data-label="' . esc_attr__('Created', 'barbas-connect') . '">' . esc_html(barbas_connect_format_time($row['created_at'])) . '</td>';
            echo '<td data-label="' . esc_attr__('Last seen', 'barbas-connect') . '">' . esc_html(barbas_connect_format_time($row['last_seen_at'])) . '</td>';
            echo '<td class="barbas-connect-actions" data-label="' . esc_attr__('Actions', 'barbas-connect') . '">';

            // Hide rotate while connected — one site = one Central; regenerating confuses the paired link.
            if ($status !== 'connected') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="barbas-connect-inline-form">';
                echo '<input type="hidden" name="action" value="barbas_connect_action" />';
                wp_nonce_field('barbas_connect_admin', '_barbas_connect_nonce');
                echo '<input type="hidden" name="barbas_connect_action" value="rotate" />';
                echo '<input type="hidden" name="connection_id" value="' . esc_attr($row['id']) . '" />';
                echo '<button type="submit" class="button">' . esc_html__('Generate new key', 'barbas-connect') . '</button>';
                echo '</form>';
            }

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="barbas-connect-inline-form" onsubmit="return confirm(' . wp_json_encode(__('Disconnect this connection?', 'barbas-connect')) . ');">';
            echo '<input type="hidden" name="action" value="barbas_connect_action" />';
            wp_nonce_field('barbas_connect_admin', '_barbas_connect_nonce');
            echo '<input type="hidden" name="barbas_connect_action" value="disconnect" />';
            echo '<input type="hidden" name="connection_id" value="' . esc_attr($row['id']) . '" />';
            echo '<button type="submit" class="button">' . esc_html__('Disconnect', 'barbas-connect') . '</button>';
            echo '</form>';

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
    echo '</section>';

    if (function_exists('barbas_update_render_brand_footer')) {
        barbas_update_render_brand_footer();
    }

    echo '</div></div>';
}
