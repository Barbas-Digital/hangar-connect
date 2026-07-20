<?php
defined('ABSPATH') or die('No script kiddies please!');

if (!function_exists('barbas_connect_filter_plugins_list')) {
    /**
     * Keep Plugins list Name/Description consistent (i18n-ready).
     *
     * @param array $plugins Plugins list.
     * @return array
     */
    function barbas_connect_filter_plugins_list($plugins) {
        if (!is_array($plugins) || !defined('BARBAS_CONNECT_PLUGIN_FILE')) {
            return $plugins;
        }

        $basename = plugin_basename(BARBAS_CONNECT_PLUGIN_FILE);
        if (!isset($plugins[ $basename ])) {
            return $plugins;
        }

        if (function_exists('barbas_connect_load_textdomain')) {
            barbas_connect_load_textdomain();
        }

        $plugins[ $basename ]['Name'] = __('Barbas Connect', 'barbas-connect');
        $plugins[ $basename ]['Description'] = __(
            'Site agent for Barbas Console: secure REST API, pairing keys, and bridge stubs for Activity Reports.',
            'barbas-connect'
        );

        return $plugins;
    }

    add_filter('all_plugins', 'barbas_connect_filter_plugins_list');
}

if (function_exists('barbas_readme_i18n_register')) {
    barbas_readme_i18n_register(
        'barbas-connect',
        static function () {
            return array();
        }
    );
}
