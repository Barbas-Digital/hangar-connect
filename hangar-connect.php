<?php
/*
Plugin Name: Hangar Connect
Plugin URI: https://github.com/Barbas-Digital/hangar-connect
Description: Site agent for Hangar: secure REST API, pairing keys, and productivity reports via WP Activity Log.
Version: 0.2.12
Requires at least: 5.8
Requires PHP: 7.4
Author: Guilherme Souza
Author URI: https://www.barbas.digital
Licence: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: hangar-connect
Domain Path: languages
*/

if (!defined('ABSPATH')) {
    exit;
}

define('HANGAR_CONNECT_PLUGIN_FILE', __FILE__);
define('HANGAR_CONNECT_VERSION', '0.2.12');
define('HANGAR_CONNECT_DIR', plugin_dir_path(__FILE__));
define('HANGAR_CONNECT_URL', plugin_dir_url(__FILE__));
define('HANGAR_CONNECT_REST_NS', 'hangar-connect/v1');

$hangar_connect_base = plugin_dir_path(__FILE__);

// Pairing secrets use OpenSSL helpers (no Barbas Update license hub required).
if (is_readable($hangar_connect_base . 'includes/barbas-update-crypto.php')) {
    require_once $hangar_connect_base . 'includes/barbas-update-crypto.php';
}

// If another Barbas plugin already hosts the Update hub, register as candidate only
// (crypto / PUC helpers). Connect itself does not require a license tab.
if (is_readable($hangar_connect_base . 'includes/barbas-update-bootstrap.php')) {
    require_once $hangar_connect_base . 'includes/barbas-update-bootstrap.php';
    if (function_exists('barbas_update_register_hub_candidate')) {
        barbas_update_register_hub_candidate(__FILE__);
    }
    if (function_exists('barbas_update_guard_plugin_details')) {
        barbas_update_guard_plugin_details(
            __FILE__,
            'hangar-connect',
            'https://github.com/Barbas-Digital/hangar-connect'
        );
    }
}

$hangar_connect_includes = array(
    'includes/hangar-connect-migrate.php',
    'includes/barbas-plugin-list-i18n.php',
    'includes/barbas-readme-i18n.php',
    'includes/barbas-update-checker.php',
    'includes/hangar-connect-connections.php',
    'includes/hangar-connect-hmac.php',
    'includes/hangar-connect-activity.php',
    'includes/hangar-connect-rest.php',
    'includes/hangar-connect-admin.php',
);

foreach ($hangar_connect_includes as $hangar_connect_file) {
    $hangar_connect_path = $hangar_connect_base . $hangar_connect_file;
    if (is_readable($hangar_connect_path)) {
        require_once $hangar_connect_path;
    }
}

if (!function_exists('hangar_connect_missing_files_notice')) {
    /**
     * Admin notice when core includes are missing (broken zip extract).
     */
    function hangar_connect_missing_files_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>Hangar Connect:</strong> ';
        echo esc_html__(
            'Missing files in includes/ or lib/. Delete the plugin folder and install via Plugins â†’ Add New â†’ Upload Plugin (hangar-connect.zip).',
            'hangar-connect'
        );
        echo '</p></div>';
    }
}

if (!is_readable($hangar_connect_base . 'includes/hangar-connect-rest.php')) {
    add_action('admin_notices', 'hangar_connect_missing_files_notice');
}

/**
 * Load plugin textdomain.
 */
function hangar_connect_load_textdomain() {
    load_plugin_textdomain(
        'hangar-connect',
        false,
        dirname(plugin_basename(HANGAR_CONNECT_PLUGIN_FILE)) . '/languages'
    );
}
add_action('init', 'hangar_connect_load_textdomain', 0);
add_action('plugins_loaded', 'hangar_connect_load_textdomain', 0);

/**
 * Activation: ensure option shape exists (autoload false).
 */
function hangar_connect_activate() {
    if (false === get_option('hangar_connect_connections', false)) {
        add_option('hangar_connect_connections', array(), '', false);
    }
}
register_activation_hook(__FILE__, 'hangar_connect_activate');
