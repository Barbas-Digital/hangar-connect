<?php
defined('ABSPATH') or die('No script kiddies please!');

if (!function_exists('hangar_connect_display_name')) {
    /**
     * Plugin display name (WP Plugins list / admin). Default Hangar Connect.
     * White-label: filter hangar_connect_display_name → "{company} Connect".
     *
     * @return string
     */
    function hangar_connect_display_name() {
        $default = __('Hangar Connect', 'hangar-connect');
        /**
         * Filter the Connect plugin display name (e.g. "{Company} Connect" under WL).
         *
         * @param string $name Default Hangar Connect.
         */
        return (string) apply_filters('hangar_connect_display_name', $default);
    }
}

if (!function_exists('hangar_connect_filter_plugins_list')) {
    /**
     * Keep Plugins list Name/Description consistent (i18n + future WL).
     *
     * @param array $plugins Plugins list.
     * @return array
     */
    function hangar_connect_filter_plugins_list($plugins) {
        if (!is_array($plugins) || !defined('HANGAR_CONNECT_PLUGIN_FILE')) {
            return $plugins;
        }

        $basename = plugin_basename(HANGAR_CONNECT_PLUGIN_FILE);
        if (!isset($plugins[ $basename ])) {
            return $plugins;
        }

        if (function_exists('hangar_connect_load_textdomain')) {
            hangar_connect_load_textdomain();
        }

        $plugins[ $basename ]['Name'] = hangar_connect_display_name();
        $plugins[ $basename ]['Description'] = __(
            'Site agent for Hangar: secure REST API, pairing keys, and bridge stubs for Activity Reports.',
            'hangar-connect'
        );

        return $plugins;
    }

    add_filter('all_plugins', 'hangar_connect_filter_plugins_list');
}

if (function_exists('barbas_readme_i18n_register')) {
    barbas_readme_i18n_register(
        'hangar-connect',
        static function () {
            return array();
        }
    );
}
