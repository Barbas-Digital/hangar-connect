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

if (!function_exists('barbas_activity_reports_readme_i18n_sections')) {
    function barbas_activity_reports_readme_i18n_sections() {
        $github = 'https://github.com/Barbas-Digital/barbas-activity-reports';
        $domain = 'barbas-activity-reports';

        return array(
            'description' => '<p>' . esc_html__('Barbas - Activity Reports adds productivity reports and filtered logs on top of WP Activity Log.', $domain) . '</p>'
                . '<ul>'
                . '<li>' . esc_html__('Requires WP Activity Log — cannot activate without it.', $domain) . '</li>'
                . '<li>' . esc_html__('Productivity report with sessions, timeline, and estimated active time.', $domain) . '</li>'
                . '<li>' . esc_html__('Log & Filters table with CSV export.', $domain) . '</li>'
                . '<li>' . esc_html__('Export report as HTML, PDF (browser print), or CSV.', $domain) . '</li>'
                . '<li>' . esc_html__('Visibility follows WP Activity Log privileges (“Only me” / “All administrators”).', $domain) . '</li>'
                . '<li>' . esc_html__('Menus live under WP Activity Log (above Upgrade), with Melapress-styled UI.', $domain) . '</li>'
                . '<li>' . esc_html__('Updates via Settings → Barbas Update (Activity Reports tab).', $domain) . '</li>'
                . '<li>' . esc_html__('Read-only — never writes to WP Activity Log tables.', $domain) . '</li>'
                . '</ul>'
                . '<p>' . sprintf(
                    /* translators: %s: GitHub link */
                    __('You\'re invited to visit %s for releases and contributions.', $domain),
                    '<a href="' . esc_url($github) . '" target="_blank" rel="noopener noreferrer">GitHub</a>'
                ) . '</p>',
            'installation' => '<ol>'
                . '<li>' . esc_html__('Install and activate WP Activity Log (one-click button if missing).', $domain) . '</li>'
                . '<li>' . esc_html__('Plugins → Add New → Upload Plugin → barbas-activity-reports.zip', $domain) . '</li>'
                . '<li>' . esc_html__('Activate the plugin.', $domain) . '</li>'
                . '<li>' . esc_html__('Settings → Barbas Update → save and validate your license.', $domain) . '</li>'
                . '</ol>'
                . '<p>' . esc_html__('Always install via WordPress (not hosting file manager only).', $domain) . '</p>',
            'frequently_asked_questions' => '<h4>' . esc_html__('Does it need WP Activity Log?', $domain) . '</h4>'
                . '<p>' . esc_html__('Yes — hard dependency. Without WP Activity Log active, this plugin will not activate. It only reads wsal_occurrences / wsal_metadata and never writes to those tables.', $domain) . '</p>'
                . '<h4>' . esc_html__('Where do I add the license?', $domain) . '</h4>'
                . '<p>' . esc_html__('Settings → Barbas Update → Activity Reports tab, or the License link on the Plugins list.', $domain) . '</p>'
                . '<p><code>define(\'BARBAS_UPDATE_TOKEN_ACTIVITY_REPORTS\', \'...\');</code> ' . esc_html__('in wp-config.php', $domain) . '</p>'
                . '<h4>' . esc_html__('How do I update?', $domain) . '</h4>'
                . '<p>' . esc_html__('With a saved license: Dashboard → Updates → Check for updates.', $domain) . '</p>'
                . '<h4>' . esc_html__('Who can see Activity Reports?', $domain) . '</h4>'
                . '<p>' . esc_html__('The same people who can see WP Activity Log. Visibility follows WP Activity Log → Settings → Restrict plugin access (“Only me” / “All administrators”), including extra log viewers on multisite.', $domain) . '</p>'
                . '<h4>' . esc_html__('Can I share a report publicly?', $domain) . '</h4>'
                . '<p>' . esc_html__('Yes. From the report page you can create encrypted public links, optionally with a password. Password-protected links ask for the password on every visit.', $domain) . '</p>',
        );
    }

    barbas_readme_i18n_register('barbas-activity-reports', 'barbas_activity_reports_readme_i18n_sections');
}
