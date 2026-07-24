<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * GitHub updates via Plugin Update Checker (public repository — no license token).
 *
 * @param string $plugin_file Main plugin file.
 */
function hangar_connect_register_github_updates($plugin_file) {
    if (function_exists('barbas_update_guard_plugin_details')) {
        barbas_update_guard_plugin_details(
            $plugin_file,
            'hangar-connect',
            'https://github.com/Barbas-Digital/hangar-connect'
        );
    }

    if (function_exists('barbas_update_load_puc_library')) {
        if (!barbas_update_load_puc_library($plugin_file)) {
            return;
        }
    } else {
        $library = plugin_dir_path($plugin_file) . 'lib/plugin-update-checker/plugin-update-checker.php';
        if (!is_readable($library)) {
            return;
        }
        require_once $library;
    }

    if (!class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory', false)) {
        return;
    }

    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/Barbas-Digital/hangar-connect',
        $plugin_file,
        'hangar-connect'
    );

    if (function_exists('barbas_update_remember_puc')) {
        barbas_update_remember_puc($plugin_file, $updateChecker);
    }

    // Public repo: do not setAuthentication().

    if (is_object($updateChecker) && method_exists($updateChecker, 'getVcsApi')) {
        $vcsApi = $updateChecker->getVcsApi();
        if (is_object($vcsApi) && method_exists($vcsApi, 'enableReleaseAssets')) {
            $vcsApi->enableReleaseAssets('/^hangar-connect\.zip$/i');
        }
    }

    if (is_object($updateChecker) && method_exists($updateChecker, 'addResultFilter')) {
        $updateChecker->addResultFilter(
            static function ($info) {
                if (is_object($info)) {
                    $info->slug = 'hangar-connect';
                    $info->homepage = 'https://github.com/Barbas-Digital/hangar-connect';
                    hangar_connect_apply_brand_icons($info);
                }
                return $info;
            }
        );
    }
}

/**
 * Brand kit icons for updates / plugin information.
 *
 * @param object $info Plugin info or update object.
 */
function hangar_connect_apply_brand_icons($info) {
    if (!is_object($info) || !defined('HANGAR_CONNECT_URL')) {
        return;
    }
    $base = HANGAR_CONNECT_URL . 'assets/';
    $info->icons = array(
        'svg'     => $base . 'icon.svg',
        '1x'      => $base . 'icon-128x128.png',
        '2x'      => $base . 'icon-256x256.png',
        'default' => $base . 'icon-128x128.png',
    );
}

/**
 * Ensure Ver detalhes / plugins_api also carries Hangar icons.
 *
 * @param mixed  $res    API result.
 * @param string $action Action name.
 * @param object $args   Request args.
 * @return mixed
 */
function hangar_connect_plugins_api_icons($res, $action, $args) {
    if ($action !== 'plugin_information' || !is_object($args) || empty($args->slug)) {
        return $res;
    }
    if ((string) $args->slug !== 'hangar-connect' || !is_object($res)) {
        return $res;
    }
    hangar_connect_apply_brand_icons($res);
    return $res;
}
add_filter('plugins_api_result', 'hangar_connect_plugins_api_icons', 20, 3);

add_action('plugins_loaded', function () {
    if (!defined('HANGAR_CONNECT_PLUGIN_FILE')) {
        return;
    }
    hangar_connect_register_github_updates(HANGAR_CONNECT_PLUGIN_FILE);
}, 20);
