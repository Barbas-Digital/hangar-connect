<?php
defined('ABSPATH') or die('No script kiddies please!');

if (!function_exists('barbas_readme_i18n_register')) {
    function barbas_readme_i18n_register($slug, $callback) {
        if (!isset($GLOBALS['barbas_readme_i18n_callbacks']) || !is_array($GLOBALS['barbas_readme_i18n_callbacks'])) {
            $GLOBALS['barbas_readme_i18n_callbacks'] = array();
        }
        $GLOBALS['barbas_readme_i18n_callbacks'][$slug] = $callback;

        if (!has_filter('plugins_api_result', 'barbas_readme_i18n_filter_api')) {
            add_filter('plugins_api_result', 'barbas_readme_i18n_filter_api', 10, 3);
        }
    }

    function barbas_readme_i18n_get_callbacks() {
        return isset($GLOBALS['barbas_readme_i18n_callbacks']) && is_array($GLOBALS['barbas_readme_i18n_callbacks'])
            ? $GLOBALS['barbas_readme_i18n_callbacks']
            : array();
    }

    function barbas_readme_i18n_is_pt_locale() {
        $locale = function_exists('get_user_locale') ? get_user_locale() : get_locale();
        return strpos($locale, 'pt') === 0;
    }

    function barbas_readme_i18n_normalize_faq_section($result) {
        if (!is_object($result) || empty($result->sections) || !is_array($result->sections)) {
            return $result;
        }

        if (!empty($result->sections['frequently_asked_questions'])) {
            $result->sections['faq'] = $result->sections['frequently_asked_questions'];
            unset($result->sections['frequently_asked_questions']);
        }

        return $result;
    }

    function barbas_readme_i18n_filter_api($result, $action, $args) {
        if ($action !== 'plugin_information' || !is_object($result) || empty($result->sections)) {
            return $result;
        }

        // Always map FAQ key so the modal tab appears (EN + PT).
        $result = barbas_readme_i18n_normalize_faq_section($result);

        if (!barbas_readme_i18n_is_pt_locale()) {
            return $result;
        }

        $slug = isset($args->slug) ? $args->slug : '';
        $callbacks = barbas_readme_i18n_get_callbacks();
        if ($slug === '' || !isset($callbacks[$slug]) || !is_callable($callbacks[$slug])) {
            return $result;
        }

        $sections = call_user_func($callbacks[$slug]);
        if (!is_array($sections)) {
            return $result;
        }

        foreach ($sections as $key => $html) {
            // Changelog always comes from readme.txt in the GitHub release (stays in sync).
            if ($html === '' || $key === 'changelog') {
                continue;
            }
            if ($key === 'frequently_asked_questions') {
                $result->sections['faq'] = $html;
                continue;
            }
            if (isset($result->sections[$key])) {
                $result->sections[$key] = $html;
            }
        }

        return barbas_readme_i18n_normalize_faq_section($result);
    }
}

if (!function_exists('hangar_connect_readme_i18n_sections')) {
    /**
     * Portuguese sections for the plugin details modal (Ver detalhes).
     *
     * @return array<string, string>
     */
    function hangar_connect_readme_i18n_sections() {
        $github = 'https://github.com/Barbas-Digital/hangar-connect';
        $domain = 'hangar-connect';

        return array(
            'description' => '<p>' . esc_html__('Hangar Connect turns each WordPress site into a secure agent for Hangar.', $domain) . '</p>'
                . '<ul>'
                . '<li>' . esc_html__('Free plugin: public GitHub repository, no license token required.', $domain) . '</li>'
                . '<li>' . esc_html__('Connections screen: generate pairing key, copy once, rotate when pending, disconnect.', $domain) . '</li>'
                . '<li>' . esc_html__('Own REST namespace (/wp-json/hangar-connect/v1/) — never exposes generic /wp/v2.', $domain) . '</li>'
                . '<li>' . esc_html__('HMAC-protected capabilities and productivity reports via WP Activity Log.', $domain) . '</li>'
                . '<li>' . esc_html__('Updates from public GitHub Releases (hangar-connect.zip).', $domain) . '</li>'
                . '</ul>'
                . '<p>' . sprintf(
                    /* translators: %s: GitHub link */
                    __('You\'re invited to visit %s for releases and contributions.', $domain),
                    '<a href="' . esc_url($github) . '" target="_blank" rel="noopener noreferrer">GitHub</a>'
                ) . '</p>',
            'installation' => '<ol>'
                . '<li>' . esc_html__('Plugins → Add New → Upload Plugin → hangar-connect.zip', $domain) . '</li>'
                . '<li>' . esc_html__('Activate the plugin.', $domain) . '</li>'
                . '<li>' . esc_html__('Settings → Hangar Connect → Generate pairing key', $domain) . '</li>'
                . '<li>' . esc_html__('Pair the site from Hangar using that key.', $domain) . '</li>'
                . '</ol>'
                . '<p>' . esc_html__('Always install via WordPress (not hosting file manager only).', $domain) . '</p>',
            'frequently_asked_questions' => '<h4>' . esc_html__('Do I need a Barbas Update license?', $domain) . '</h4>'
                . '<p>' . esc_html__('No. Hangar Connect is free. Updates use the public GitHub repository.', $domain) . '</p>'
                . '<h4>' . esc_html__('Where is the health endpoint?', $domain) . '</h4>'
                . '<p>' . esc_html__('GET /wp-json/hangar-connect/v1/health — public discovery (version, site URL, connected flag, capabilities). No secrets.', $domain) . '</p>'
                . '<h4>' . esc_html__('Does it dump WP Activity Log data?', $domain) . '</h4>'
                . '<p>' . esc_html__('HMAC endpoints expose summarized productivity data from WP Activity Log (users + Logbook) for Hangar. No raw WSAL admin dump.', $domain) . '</p>',
        );
    }
}
