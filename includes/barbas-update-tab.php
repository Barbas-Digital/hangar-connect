<?php
defined('ABSPATH') or die('No script kiddies please!');

if (!function_exists('barbas_connect_register_update_tab')) {
    /**
     * Register Barbas Update hub tab (tab_id: connect → BARBAS_UPDATE_TOKEN_CONNECT).
     *
     * @param array $tabs Existing tabs.
     * @return array
     */
    function barbas_connect_register_update_tab($tabs) {
        if (!defined('BARBAS_CONNECT_PLUGIN_FILE')) {
            return $tabs;
        }

        return barbas_update_register_tab($tabs, array(
            'id'          => 'connect',
            'label'       => __('Connect', 'barbas-connect'),
            'plugin'      => plugin_basename(BARBAS_CONNECT_PLUGIN_FILE),
            'github_repo' => 'Barbas-Digital/barbas-connect',
            'render'      => '__return_null',
        ));
    }

    add_filter('barbas_update_tabs', 'barbas_connect_register_update_tab');
}
