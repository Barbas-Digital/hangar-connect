<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Bootstrap Barbas Update (identical copy in each Barbas plugin).
 *
 * Hub load is deferred to plugins_loaded so the newest hub version among
 * active Barbas plugins wins (not whichever plugin file loads first).
 *
 * @param string $host_plugin_file Main plugin file path (__FILE__).
 */
if (!function_exists('barbas_update_probe_hub_version')) {
    /**
     * Read BARBAS_UPDATE_HUB_VERSION from a plugin's hub PHP without loading it.
     *
     * @param string $host_plugin_file Main plugin file.
     * @return string Version or "0".
     */
    function barbas_update_probe_hub_version($host_plugin_file) {
        $hub = plugin_dir_path($host_plugin_file) . 'includes/barbas-update-hub.php';
        if (!is_readable($hub)) {
            return '0';
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file probe.
        $chunk = file_get_contents($hub, false, null, 0, 8192);
        if (!is_string($chunk) || $chunk === '') {
            return '0';
        }
        if (preg_match("/define\\(\\s*'BARBAS_UPDATE_HUB_VERSION'\\s*,\\s*'([^']+)'\\s*\\)/", $chunk, $m)) {
            return (string) $m[1];
        }
        return '0';
    }
}

if (!function_exists('barbas_update_register_hub_candidate')) {
    /**
     * Register this plugin as a hub host candidate.
     *
     * @param string $host_plugin_file Main plugin file.
     */
    function barbas_update_register_hub_candidate($host_plugin_file) {
        if (!is_string($host_plugin_file) || $host_plugin_file === '') {
            return;
        }
        if (!isset($GLOBALS['barbas_update_hub_candidates']) || !is_array($GLOBALS['barbas_update_hub_candidates'])) {
            $GLOBALS['barbas_update_hub_candidates'] = array();
        }
        $key = plugin_basename($host_plugin_file);
        $GLOBALS['barbas_update_hub_candidates'][ $key ] = array(
            'file'    => $host_plugin_file,
            'version' => barbas_update_probe_hub_version($host_plugin_file),
        );
    }
}

if (!function_exists('barbas_update_pick_newest_hub_candidate')) {
    /**
     * @return array{file:string,version:string}|null
     */
    function barbas_update_pick_newest_hub_candidate() {
        $candidates = isset($GLOBALS['barbas_update_hub_candidates']) && is_array($GLOBALS['barbas_update_hub_candidates'])
            ? $GLOBALS['barbas_update_hub_candidates']
            : array();
        if (empty($candidates)) {
            return null;
        }

        $best = null;
        foreach ($candidates as $candidate) {
            if (!is_array($candidate) || empty($candidate['file'])) {
                continue;
            }
            $version = isset($candidate['version']) ? (string) $candidate['version'] : '0';
            if ($best === null || version_compare($version, $best['version'], '>')) {
                $best = array(
                    'file'    => (string) $candidate['file'],
                    'version' => $version,
                );
            }
        }

        return $best;
    }
}

if (!function_exists('barbas_update_load_textdomain')) {
    /**
     * Load barbas-update .mo from the hub host plugin (private, not on wp.org).
     * Prefer calling on init; safe to call again when locale changes.
     *
     * @param string $host_plugin_file Main plugin file path.
     */
    function barbas_update_load_textdomain($host_plugin_file) {
        if (!is_string($host_plugin_file) || $host_plugin_file === '') {
            return;
        }

        static $loaded_locale = null;
        $domain = 'barbas-update';
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        if ($loaded_locale === $locale) {
            return;
        }

        $rel = dirname(plugin_basename($host_plugin_file)) . '/languages';
        load_plugin_textdomain($domain, false, $rel);

        $mofile = plugin_dir_path($host_plugin_file) . 'languages/' . $domain . '-' . $locale . '.mo';
        if (is_readable($mofile)) {
            load_textdomain($domain, $mofile);
        }

        $loaded_locale = $locale;
    }
}

if (!function_exists('barbas_update_load_newest_hub')) {
    /**
     * Load crypto + hub from the candidate with the highest HUB_VERSION.
     */
    function barbas_update_load_newest_hub() {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        if (defined('BARBAS_UPDATE_HUB_LOADED')) {
            barbas_update_register_hooks();
            return;
        }

        $best = barbas_update_pick_newest_hub_candidate();
        if ($best === null) {
            return;
        }

        $host_plugin_file = $best['file'];

        if (!defined('BARBAS_UPDATE_PLUGIN_FILE')) {
            define('BARBAS_UPDATE_PLUGIN_FILE', $host_plugin_file);
        }

        barbas_update_load_textdomain($host_plugin_file);

        $includes_dir = plugin_dir_path($host_plugin_file) . 'includes/';

        $crypto = $includes_dir . 'barbas-update-crypto.php';
        if (is_readable($crypto)) {
            require_once $crypto;
        }

        // Helpers must load before hub (token constant names, suite license, etc.).
        // If the winning host is missing the file after a partial restore, try peers.
        $wpconfig = $includes_dir . 'barbas-update-wp-config.php';
        if (!is_readable($wpconfig)) {
            $candidates = isset($GLOBALS['barbas_update_hub_candidates']) && is_array($GLOBALS['barbas_update_hub_candidates'])
                ? $GLOBALS['barbas_update_hub_candidates']
                : array();
            foreach ($candidates as $candidate) {
                if (!is_array($candidate) || empty($candidate['file'])) {
                    continue;
                }
                $peer = plugin_dir_path($candidate['file']) . 'includes/barbas-update-wp-config.php';
                if (is_readable($peer)) {
                    $wpconfig = $peer;
                    break;
                }
            }
        }
        if (is_readable($wpconfig)) {
            require_once $wpconfig;
        }

        $hub = $includes_dir . 'barbas-update-hub.php';
        if (is_readable($hub)) {
            require_once $hub;
            // require_once can be a no-op if a partial early include happened.
            if (!defined('BARBAS_UPDATE_HUB_LOADED')) {
                require $hub;
            }
        }

        barbas_update_register_hooks();
    }
}

if (!function_exists('barbas_update_bootstrap')) {
    function barbas_update_bootstrap($host_plugin_file) {
        if (!is_string($host_plugin_file) || $host_plugin_file === '') {
            return;
        }

        barbas_update_register_hub_candidate($host_plugin_file);

        if (!has_action('plugins_loaded', 'barbas_update_load_newest_hub')) {
            add_action('plugins_loaded', 'barbas_update_load_newest_hub', 0);
        }

        // Late bootstrap (e.g. after plugins_loaded): load immediately.
        if (did_action('plugins_loaded')) {
            barbas_update_load_newest_hub();
        }
    }
}

if (!function_exists('barbas_update_register_hooks')) {
    function barbas_update_register_hooks() {
        static $registered = false;
        if ($registered) {
            return;
        }
        if (!defined('BARBAS_UPDATE_HUB_LOADED') || !function_exists('barbas_update_register_admin_menu')) {
            return;
        }
        $registered = true;

        $actions = array(
            array('admin_menu', 'barbas_update_register_admin_menu', 5),
            array('admin_notices', 'barbas_update_admin_notices', 10),
            // Remove notices de terceiros na página do hub (após outros plugins registrarem).
            array('in_admin_header', 'barbas_update_suppress_third_party_notices', 999),
            array('admin_head', 'barbas_update_suppress_third_party_notices', 1),
            array('admin_enqueue_scripts', 'barbas_update_enqueue_admin_assets', 10),
            array('admin_init', 'barbas_update_redirect_legacy_urls', 10),
            array('wp_ajax_barbas_update_validate_token', 'barbas_update_ajax_validate_token', 10),
            array('wp_ajax_barbas_update_save_token', 'barbas_update_ajax_save_token', 10),
            array('wp_ajax_barbas_update_load_panel', 'barbas_update_ajax_load_panel', 10),
            array('wp_ajax_barbas_update_force_check', 'barbas_update_ajax_force_check', 10),
            array('wp_ajax_barbas_update_plugins_status', 'barbas_update_ajax_plugins_status', 10),
            array('wp_ajax_barbas_update_install_plugin', 'barbas_update_ajax_install_plugin', 10),
        );

        foreach ($actions as $action) {
            list($hook, $callback, $priority) = $action;
            if (function_exists($callback) && false === has_action($hook, $callback)) {
                add_action($hook, $callback, $priority);
            }
        }

        if (function_exists('barbas_update_filter_plugin_information_changelog')
            && false === has_filter('plugins_api_result', 'barbas_update_filter_plugin_information_changelog')
        ) {
            add_filter('plugins_api_result', 'barbas_update_filter_plugin_information_changelog', 20, 3);
        }
    }
}

if (!function_exists('barbas_update_changelog_full_url')) {
    /**
     * Public/full changelog URL for the plugin details modal.
     * Prefer GitHub README #changelog (private repos need GitHub access).
     *
     * @param object $result plugins_api plugin_information result.
     * @return string
     */
    function barbas_update_changelog_full_url($result) {
        $homepage = '';
        if (is_object($result) && !empty($result->homepage) && is_string($result->homepage)) {
            $homepage = rtrim($result->homepage, '/');
        }
        if ($homepage === '' || strpos($homepage, 'github.com/') === false) {
            return '';
        }
        // Repo home renders README; heading "## Changelog" → #changelog.
        return $homepage . '#changelog';
    }
}

if (!function_exists('barbas_update_truncate_changelog_html')) {
    /**
     * Keep only the latest N version blocks in changelog HTML from readme.txt.
     *
     * @param string $html Changelog HTML.
     * @param int    $max  Max version headings to keep.
     * @param string $full_url Optional full changelog URL.
     * @return string
     */
    function barbas_update_truncate_changelog_html($html, $max = 10, $full_url = '') {
        if (!is_string($html) || $html === '') {
            return $html;
        }
        $max = max(1, (int) $max);

        // PUC/WordPress typically emits <h4>1.2.3</h4> per version.
        if (!preg_match_all('/<h4\b[^>]*>.*?<\/h4>/is', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $heads = $matches[0];
        if (count($heads) <= $max) {
            return $html;
        }

        $cut_at = (int) $heads[$max][1];
        $truncated = rtrim(substr($html, 0, $cut_at));

        if ($full_url !== '') {
            $note = sprintf(
                /* translators: 1: number of versions shown, 2: URL to full changelog */
                __('Showing the latest %1$d releases. <a href="%2$s" target="_blank" rel="noopener noreferrer">View the full changelog on GitHub</a>.', 'barbas-update'),
                $max,
                esc_url($full_url)
            );
        } else {
            $note = sprintf(
                /* translators: %d: number of versions shown */
                __('Showing the latest %d releases.', 'barbas-update'),
                $max
            );
        }

        return $truncated . '<p class="barbas-changelog-more">' . $note . '</p>';
    }
}

if (!function_exists('barbas_update_filter_plugin_information_changelog')) {
    /**
     * Limit plugin details modal changelog to the latest 10 versions.
     *
     * @param mixed  $result
     * @param string $action
     * @param object $args
     * @return mixed
     */
    function barbas_update_filter_plugin_information_changelog($result, $action, $args) {
        if ($action !== 'plugin_information' || !is_object($result) || empty($result->sections) || !is_array($result->sections)) {
            return $result;
        }
        if (empty($result->sections['changelog']) || !is_string($result->sections['changelog'])) {
            return $result;
        }

        $homepage = isset($result->homepage) ? (string) $result->homepage : '';
        $slug = isset($args->slug) ? (string) $args->slug : (isset($result->slug) ? (string) $result->slug : '');
        $is_barbas = (strpos($homepage, 'github.com/Barbas-Digital/') !== false)
            || ($slug !== '' && strpos($slug, 'barbas-') === 0)
            || ($slug === 'abler-api-vagas');
        if (!$is_barbas) {
            return $result;
        }

        $result->sections['changelog'] = barbas_update_truncate_changelog_html(
            $result->sections['changelog'],
            10,
            barbas_update_changelog_full_url($result)
        );

        return $result;
    }
}

if (!function_exists('barbas_update_guard_plugin_details')) {
    /**
     * Register plugin for correct "View details" (hub 2.2.21+ owns filters + local plugins_api).
     *
     * @param string $plugin_file Absolute main plugin file.
     * @param string $slug        PUC slug (e.g. barbas-uncode-core-fix).
     * @param string $homepage    Prefer GitHub repo URL.
     */
    function barbas_update_guard_plugin_details($plugin_file, $slug, $homepage = '') {
        if (!is_string($plugin_file) || $plugin_file === '' || !is_string($slug) || $slug === '') {
            return;
        }
        $slug = sanitize_key($slug);
        if ($slug === '') {
            return;
        }
        if ($homepage === '') {
            $homepage = 'https://github.com/Barbas-Digital/' . $slug;
        }

        // Prefer newest hub registry (survives Uncode hijacks + private-repo "Plugin not found").
        if (function_exists('barbas_update_register_plugin_details_guard')) {
            barbas_update_register_plugin_details_guard($plugin_file, $slug, $homepage);
            return;
        }

        if (!isset($GLOBALS['barbas_update_plugin_details']) || !is_array($GLOBALS['barbas_update_plugin_details'])) {
            $GLOBALS['barbas_update_plugin_details'] = array();
        }
        $GLOBALS['barbas_update_plugin_details'][ $slug ] = array(
            'file'     => $plugin_file,
            'slug'     => $slug,
            'homepage' => $homepage,
        );

        // Legacy fallback when an older hub is still the active host.
        static $legacy_hooks = false;
        if ($legacy_hooks) {
            return;
        }
        $legacy_hooks = true;

        add_filter(
            'all_plugins',
            static function ($plugins) {
                if (!is_array($plugins) || empty($GLOBALS['barbas_update_plugin_details'])) {
                    return $plugins;
                }
                foreach ($GLOBALS['barbas_update_plugin_details'] as $entry) {
                    $key = plugin_basename($entry['file']);
                    if (!isset($plugins[ $key ]) || !is_array($plugins[ $key ])) {
                        continue;
                    }
                    $plugins[ $key ]['slug'] = $entry['slug'];
                    $plugins[ $key ]['PluginURI'] = $entry['homepage'];
                }
                return $plugins;
            },
            PHP_INT_MAX
        );

        add_filter(
            'plugin_row_meta',
            static function ($plugin_meta, $plugin_file_rel, $plugin_data = array()) {
                if (!is_array($plugin_meta) || empty($GLOBALS['barbas_update_plugin_details'])) {
                    return $plugin_meta;
                }
                $entry = null;
                foreach ($GLOBALS['barbas_update_plugin_details'] as $candidate) {
                    if (plugin_basename($candidate['file']) === $plugin_file_rel) {
                        $entry = $candidate;
                        break;
                    }
                }
                if ($entry === null) {
                    return $plugin_meta;
                }
                $slug = $entry['slug'];
                $cleaned = array();
                $has_our_details = false;
                foreach ($plugin_meta as $link) {
                    if (!is_string($link)) {
                        $cleaned[] = $link;
                        continue;
                    }
                    if (preg_match('/undsgn\.com|support\.undsgn|theme\.uncode|uncode\.net|themeforest\.net\/item\/uncode/i', $link)) {
                        continue;
                    }
                    $is_details = (bool) preg_match('/open-plugin-details-modal|plugin-information|View details|Ver detalhes/i', $link);
                    if ($is_details) {
                        if (stripos($link, 'plugin=' . $slug) !== false) {
                            $has_our_details = true;
                            $cleaned[] = $link;
                        }
                        continue;
                    }
                    $cleaned[] = $link;
                }
                if (!$has_our_details) {
                    $name = !empty($plugin_data['Name']) ? $plugin_data['Name'] : $slug;
                    array_unshift(
                        $cleaned,
                        sprintf(
                            '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
                            esc_url(
                                network_admin_url(
                                    'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode($slug) .
                                    '&TB_iframe=true&width=600&height=550'
                                )
                            ),
                            esc_attr(sprintf(__('More information about %s'), $name)),
                            esc_attr($name),
                            __('View details')
                        )
                    );
                }
                return $cleaned;
            },
            PHP_INT_MAX,
            3
        );
    }
}
