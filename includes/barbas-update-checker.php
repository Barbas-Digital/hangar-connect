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
            'https://github.com/Barbas-Digital/barbas-connect'
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
        'https://github.com/Barbas-Digital/barbas-connect',
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
                    $info->homepage = 'https://github.com/Barbas-Digital/barbas-connect';
                }
                return $info;
            }
        );
    }
}

add_action('plugins_loaded', function () {
    if (!defined('HANGAR_CONNECT_PLUGIN_FILE')) {
        return;
    }
    hangar_connect_register_github_updates(HANGAR_CONNECT_PLUGIN_FILE);
}, 20);
