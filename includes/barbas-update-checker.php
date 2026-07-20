<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * GitHub updates via Plugin Update Checker (PUC loaded once by the hub).
 *
 * @return string
 */
function barbas_connect_get_update_token() {
    if (function_exists('barbas_update_get_token_for_plugin')) {
        return barbas_update_get_token_for_plugin('connect');
    }
    return '';
}

/**
 * Register GitHub release updates for private repo.
 *
 * @param string $plugin_file Main plugin file.
 */
function barbas_connect_register_github_updates($plugin_file) {
    if (function_exists('barbas_update_guard_plugin_details')) {
        barbas_update_guard_plugin_details(
            $plugin_file,
            'barbas-connect',
            'https://github.com/Barbas-Digital/barbas-connect'
        );
    }

    $token = barbas_connect_get_update_token();
    if ($token === '') {
        return;
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
        'barbas-connect'
    );

    if (function_exists('barbas_update_remember_puc')) {
        barbas_update_remember_puc($plugin_file, $updateChecker);
    }

    if (is_object($updateChecker) && method_exists($updateChecker, 'setAuthentication')) {
        $updateChecker->setAuthentication($token);
    }

    if (is_object($updateChecker) && method_exists($updateChecker, 'getUniqueName')) {
        add_filter(
            $updateChecker->getUniqueName('manual_check_message'),
            'barbas_connect_manual_check_message',
            10,
            2
        );
    }

    if (is_object($updateChecker) && method_exists($updateChecker, 'getVcsApi')) {
        $vcsApi = $updateChecker->getVcsApi();
        if (is_object($vcsApi) && method_exists($vcsApi, 'enableReleaseAssets')) {
            $vcsApi->enableReleaseAssets('/^barbas-connect\.zip$/i');
        }
    }

    if (is_object($updateChecker) && method_exists($updateChecker, 'addResultFilter')) {
        $updateChecker->addResultFilter(
            static function ($info) {
                if (is_object($info)) {
                    $info->slug = 'barbas-connect';
                    $info->homepage = 'https://github.com/Barbas-Digital/barbas-connect';
                }
                return $info;
            }
        );
    }
}

/**
 * @param string $message Message.
 * @param string $status  Status.
 * @return string
 */
function barbas_connect_manual_check_message($message, $status) {
    if ($status !== 'error' || !function_exists('barbas_update_settings_url')) {
        return $message;
    }

    $url = esc_url(barbas_update_settings_url('connect'));
    return sprintf(
        /* translators: %s: settings URL */
        __('Could not check for updates. Configure the license under <a href="%s">Barbas Update → Connect</a> (private repository).', 'barbas-connect'),
        $url
    );
}

add_action('plugins_loaded', function () {
    if (!defined('BARBAS_CONNECT_PLUGIN_FILE')) {
        return;
    }
    barbas_connect_register_github_updates(BARBAS_CONNECT_PLUGIN_FILE);
}, 20);
