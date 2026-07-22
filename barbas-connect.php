<?php
/*
Plugin Name: Barbas Connect
Plugin URI: https://github.com/Barbas-Digital/barbas-connect
Description: Site agent for Barbas Central: secure REST API, pairing keys, and Activity Reports bridge. Updates via Settings -> Barbas Update.
Version: 0.1.12
Requires at least: 5.8
Requires PHP: 7.4
Author: Guilherme Souza
Author URI: https://www.barbas.digital
Licence: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: barbas-connect
Domain Path: languages
*/

if (!defined('ABSPATH')) {
    exit;
}

define('BARBAS_CONNECT_PLUGIN_FILE', __FILE__);
define('BARBAS_CONNECT_VERSION', '0.1.12');
define('BARBAS_CONNECT_DIR', plugin_dir_path(__FILE__));
define('BARBAS_CONNECT_URL', plugin_dir_url(__FILE__));
define('BARBAS_CONNECT_REST_NS', 'barbas-connect/v1');

$barbas_connect_base = plugin_dir_path(__FILE__);

if (is_readable($barbas_connect_base . 'includes/barbas-update-bootstrap.php')) {
    require_once $barbas_connect_base . 'includes/barbas-update-bootstrap.php';
    barbas_update_bootstrap(__FILE__);
}

$barbas_connect_includes = array(
    'includes/barbas-plugin-list-i18n.php',
    'includes/barbas-readme-i18n.php',
    'includes/barbas-update-tab.php',
    'includes/barbas-update-checker.php',
    'includes/barbas-connect-connections.php',
    'includes/barbas-connect-hmac.php',
    'includes/barbas-connect-activity.php',
    'includes/barbas-connect-rest.php',
    'includes/barbas-connect-admin.php',
);

foreach ($barbas_connect_includes as $barbas_connect_file) {
    $barbas_connect_path = $barbas_connect_base . $barbas_connect_file;
    if (is_readable($barbas_connect_path)) {
        require_once $barbas_connect_path;
    }
}

if (!function_exists('barbas_connect_missing_files_notice')) {
    /**
     * Admin notice when core includes are missing (broken zip extract).
     */
    function barbas_connect_missing_files_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>Barbas Connect:</strong> ';
        echo esc_html__(
            'Missing files in includes/ or lib/. Delete the plugin folder and install via Plugins → Add New → Upload Plugin (barbas-connect.zip).',
            'barbas-connect'
        );
        echo '</p></div>';
    }
}

if (!is_readable($barbas_connect_base . 'includes/barbas-connect-rest.php')) {
    add_action('admin_notices', 'barbas_connect_missing_files_notice');
}

/**
 * Load plugin textdomain.
 */
function barbas_connect_load_textdomain() {
    load_plugin_textdomain(
        'barbas-connect',
        false,
        dirname(plugin_basename(BARBAS_CONNECT_PLUGIN_FILE)) . '/languages'
    );
}
add_action('init', 'barbas_connect_load_textdomain', 0);
add_action('plugins_loaded', 'barbas_connect_load_textdomain', 0);

/**
 * Activation: ensure option shape exists (autoload false).
 */
function barbas_connect_activate() {
    if (false === get_option('barbas_connect_connections', false)) {
        add_option('barbas_connect_connections', array(), '', false);
    }
}
register_activation_hook(__FILE__, 'barbas_connect_activate');
