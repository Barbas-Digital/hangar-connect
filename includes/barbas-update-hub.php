<?php
defined('ABSPATH') or die('No script kiddies please!');

if (!defined('BARBAS_UPDATE_MENU_SLUG')) {
    define('BARBAS_UPDATE_MENU_SLUG', 'barbas-update');
}

/**
 * Hub Barbas Update — carregado pelo bootstrap com a cópia de maior HUB_VERSION
 * entre os plugins Barbas ativos (não o primeiro a carregar no PHP).
 * Novos plugins: copie hub/bootstrap/assets/PUC, registre aba em `barbas_update_tabs`.
 * Sem dependência entre plugins; comunicação só via hooks e opções WP.
 *
 * Escala 1–100+: abas A–Z; 2–10 = tabs horizontais; 11+ = busca + lista;
 * painéis carregados sob demanda (AJAX). Stack canônico: sync-hub-stack.ps1.
 */

if (defined('BARBAS_UPDATE_HUB_LOADED')) {
    return;
}

if (!defined('BARBAS_UPDATE_PLUGIN_FILE')) {
    return;
}

define('BARBAS_UPDATE_HUB_LOADED', true);

if (!defined('BARBAS_UPDATE_HUB_VERSION')) {
    define('BARBAS_UPDATE_HUB_VERSION', '2.2.25');
}

if (!defined('BARBAS_UPDATE_INLINE_TABS_MAX')) {
    define('BARBAS_UPDATE_INLINE_TABS_MAX', 10);
}

$crypto_file = plugin_dir_path(BARBAS_UPDATE_PLUGIN_FILE) . 'includes/barbas-update-crypto.php';
if (is_readable($crypto_file)) {
    require_once $crypto_file;
}

$wpconfig_file = plugin_dir_path(BARBAS_UPDATE_PLUGIN_FILE) . 'includes/barbas-update-wp-config.php';
if (is_readable($wpconfig_file)) {
    require_once $wpconfig_file;
}

/**
 * License constant name for a tab.
 * Safe without barbas-update-wp-config.php (partial restore / hub mismatch).
 *
 * @param string $tab_id Tab id (e.g. activity-reports).
 * @return string
 */
function barbas_update_resolve_constant_name_for_tab($tab_id) {
    if (function_exists('barbas_update_constant_name_for_tab')) {
        return barbas_update_constant_name_for_tab($tab_id);
    }

    $tab_id = sanitize_key($tab_id);
    if ($tab_id === 'suite') {
        return 'BARBAS_UPDATE_TOKEN_SUITE';
    }

    return 'BARBAS_UPDATE_TOKEN_' . strtoupper(str_replace('-', '_', $tab_id));
}

function barbas_update_load_puc_library($plugin_file = '') {
    if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory', false)) {
        return true;
    }

    $host_file = defined('BARBAS_UPDATE_PLUGIN_FILE') ? BARBAS_UPDATE_PLUGIN_FILE : $plugin_file;
    if (!is_string($host_file) || $host_file === '') {
        return false;
    }

    $library = plugin_dir_path($host_file) . 'lib/plugin-update-checker/plugin-update-checker.php';
    if (!is_readable($library)) {
        return false;
    }

    require_once $library;
    return class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory', false);
}

function barbas_update_token_option_key($tab_id) {
    return 'barbas_update_token_' . str_replace('-', '_', sanitize_key($tab_id));
}

function barbas_update_get_token_for_plugin($tab_id) {
    $tab_id = sanitize_key($tab_id);

    if ($tab_id === 'suite' && function_exists('barbas_update_get_suite_token')) {
        return barbas_update_normalize_github_token(barbas_update_get_suite_token());
    }

    $token = '';

    if (function_exists('barbas_update_get_db_token_for_plugin')) {
        $token = barbas_update_get_db_token_for_plugin($tab_id);
        if ($token === '' && function_exists('barbas_update_get_wpconfig_token_for_plugin')) {
            $token = barbas_update_get_wpconfig_token_for_plugin($tab_id);
        }
    } else {
        barbas_update_maybe_migrate_legacy_tokens($tab_id);

        if (function_exists('barbas_update_read_token_option')) {
            $token = barbas_update_read_token_option(barbas_update_token_option_key($tab_id));
        } else {
            $raw = get_option(barbas_update_token_option_key($tab_id), '');
            $token = is_string($raw) ? trim($raw) : '';
        }

        if ($token === '') {
            $constant = barbas_update_resolve_constant_name_for_tab($tab_id);
            if (defined($constant) && constant($constant)) {
                $token = (string) constant($constant);
            } elseif ($tab_id === 'uncode-core-fix' && defined('BARBAS_UNCODE_CORE_FIX_GITHUB_TOKEN') && BARBAS_UNCODE_CORE_FIX_GITHUB_TOKEN) {
                $token = (string) BARBAS_UNCODE_CORE_FIX_GITHUB_TOKEN;
            }
        }
    }

    // Suite license (dashboard or constant) covers every active plugin tab.
    if ($token === '' && $tab_id !== 'suite' && function_exists('barbas_update_get_suite_token')) {
        $token = barbas_update_get_suite_token();
    }

    return barbas_update_normalize_github_token($token);
}

function barbas_update_has_token_for_plugin($tab_id) {
    return barbas_update_get_token_for_plugin($tab_id) !== '';
}

function barbas_update_token_from_constant($tab_id) {
    if (function_exists('barbas_update_get_wpconfig_token_for_plugin')) {
        return barbas_update_get_wpconfig_token_for_plugin($tab_id) !== '';
    }

    $tab_id = sanitize_key($tab_id);
    $constant = barbas_update_resolve_constant_name_for_tab($tab_id);
    if (defined($constant) && constant($constant)) {
        return true;
    }
    return $tab_id === 'uncode-core-fix' && defined('BARBAS_UNCODE_CORE_FIX_GITHUB_TOKEN') && BARBAS_UNCODE_CORE_FIX_GITHUB_TOKEN;
}

function barbas_update_maybe_migrate_legacy_tokens($tab_id) {
    $key = barbas_update_token_option_key($tab_id);
    if (get_option($key, '') !== '') {
        return;
    }

    if ($tab_id === 'uncode-core-fix') {
        foreach (array('barbas_uncode_core_fix_github_token', 'barbas_update_github_token') as $legacy) {
            $legacy_val = get_option($legacy, '');
            if (is_string($legacy_val) && trim($legacy_val) !== '') {
                if (function_exists('barbas_update_store_token_option')) {
                    barbas_update_store_token_option($key, trim($legacy_val));
                } else {
                    update_option($key, trim($legacy_val), false);
                }
                return;
            }
        }
    }
}

function barbas_update_mask_token($token) {
    $length = strlen($token);
    if ($length <= 12) {
        return str_repeat('•', $length);
    }
    return substr($token, 0, 7) . ' … ' . substr($token, -4);
}

/** @deprecated */
function barbas_uncode_core_fix_get_github_token() {
    return barbas_update_get_token_for_plugin('uncode-core-fix');
}

/** @deprecated */
function barbas_uncode_core_fix_has_github_token() {
    return barbas_update_has_token_for_plugin('uncode-core-fix');
}

/**
 * Registra aba no hub (cada plugin Barbas chama no filtro barbas_update_tabs).
 *
 * @param array<int, array<string, mixed>> $tabs   Abas existentes.
 * @param array<string, mixed>             $config id, label, plugin, github_repo?, render?.
 * @return array<int, array<string, mixed>>
 */
function barbas_update_register_tab($tabs, $config) {
    if (empty($config['id']) || empty($config['label']) || empty($config['plugin'])) {
        return $tabs;
    }

    $tabs[] = $config;
    return $tabs;
}

function barbas_update_get_logo_url() {
    if (!defined('BARBAS_UPDATE_PLUGIN_FILE')) {
        return '';
    }

    $path = plugin_dir_path(BARBAS_UPDATE_PLUGIN_FILE) . 'assets/img/logo-black.svg';
    if (!is_readable($path)) {
        return '';
    }

    return plugins_url('assets/img/logo-black.svg', BARBAS_UPDATE_PLUGIN_FILE);
}

function barbas_update_render_brand_footer() {
    $logo_url = barbas_update_get_logo_url();
    $site_url = 'https://www.barbas.digital';
    ?>
    <footer class="barbas-update-footer">
        <?php if ($logo_url !== '') : ?>
            <a
                href="<?php echo esc_url($site_url); ?>"
                class="barbas-update-footer__logo-link"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="<?php echo esc_attr__('Barbas Digital', 'barbas-update'); ?>"
            >
                <img
                    src="<?php echo esc_url($logo_url); ?>"
                    alt=""
                    class="barbas-update-footer__logo"
                    width="36"
                    height="63"
                    decoding="async"
                />
            </a>
        <?php endif; ?>
        <div class="barbas-update-footer__copy">
            <p class="barbas-update-footer__name">
                <a href="<?php echo esc_url($site_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html__('Barbas Digital', 'barbas-update'); ?>
                </a>
            </p>
            <p class="barbas-update-footer__tagline">
                <?php echo esc_html__('The brand behind the most impactful websites on the Internet.', 'barbas-update'); ?>
            </p>
        </div>
    </footer>
    <?php
}

/**
 * Active Barbas plugin license tabs only (excludes suite).
 *
 * @return array<string, array<string, mixed>>
 */
function barbas_update_get_plugin_tabs() {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $tabs = apply_filters('barbas_update_tabs', array());
    $visible = array();

    foreach ($tabs as $tab) {
        if (empty($tab['id']) || empty($tab['label']) || empty($tab['plugin'])) {
            continue;
        }
        if (sanitize_key($tab['id']) === 'suite') {
            continue;
        }
        if (!is_plugin_active($tab['plugin'])) {
            continue;
        }
        if (!isset($tab['render']) || !is_callable($tab['render'])) {
            $tab['render'] = '__return_null';
        }
        $visible[sanitize_key($tab['id'])] = $tab;
    }

    uasort(
        $visible,
        static function ($a, $b) {
            return strcasecmp((string) $a['label'], (string) $b['label']);
        }
    );

    return $visible;
}

/**
 * Suite tab covering active plugin tabs on this site (not the full catalog).
 *
 * @param array<string, array<string, mixed>> $plugin_tabs
 * @return array<string, mixed>
 */
function barbas_update_build_suite_tab(array $plugin_tabs) {
    $repos = array();
    foreach ($plugin_tabs as $tab) {
        if (!empty($tab['github_repo']) && is_string($tab['github_repo'])) {
            $repos[] = $tab['github_repo'];
        }
    }

    return array(
        'id' => 'suite',
        'label' => __('Suite license', 'barbas-update'),
        'plugin' => '',
        'github_repos' => array_values(array_unique($repos)),
        'covered_plugins' => $plugin_tabs,
        'render' => 'barbas_update_render_suite_extra',
    );
}

/**
 * Visible hub tabs: always includes suite; when suite license is active, only suite.
 *
 * @return array<string, array<string, mixed>>
 */
function barbas_update_get_tabs() {
    $plugin_tabs = barbas_update_get_plugin_tabs();
    $suite = barbas_update_build_suite_tab($plugin_tabs);

    if (function_exists('barbas_update_suite_is_active') && barbas_update_suite_is_active()) {
        return array('suite' => $suite);
    }

    return array_merge(array('suite' => $suite), $plugin_tabs);
}

/**
 * Suite tab extra content (coverage list lives in the top status card).
 *
 * @param string               $tab_id
 * @param array<string, mixed> $tab
 */
function barbas_update_render_suite_extra($tab_id, $tab) {
    unset($tab_id, $tab);
}

/**
 * Remember a live Plugin Update Checker instance for force-check / status.
 *
 * @param string $plugin_file Absolute path or plugin_basename.
 * @param object $checker     UpdateChecker instance.
 */
function barbas_update_remember_puc($plugin_file, $checker) {
    if (!is_object($checker) || !method_exists($checker, 'checkForUpdates')) {
        return;
    }

    $basename = '';
    if (isset($checker->pluginFile) && is_string($checker->pluginFile) && $checker->pluginFile !== '') {
        $basename = $checker->pluginFile;
    } elseif (is_string($plugin_file) && $plugin_file !== '' && function_exists('plugin_basename')) {
        $basename = plugin_basename($plugin_file);
    }
    if ($basename === '') {
        return;
    }

    if (!isset($GLOBALS['barbas_update_puc_instances']) || !is_array($GLOBALS['barbas_update_puc_instances'])) {
        $GLOBALS['barbas_update_puc_instances'] = array();
    }
    $GLOBALS['barbas_update_puc_instances'][$basename] = $checker;
}

/**
 * @return array<string, object>
 */
function barbas_update_get_registered_puc_instances() {
    if (empty($GLOBALS['barbas_update_puc_instances']) || !is_array($GLOBALS['barbas_update_puc_instances'])) {
        return array();
    }
    return $GLOBALS['barbas_update_puc_instances'];
}

/**
 * Installed version for a plugin basename (folder/file.php).
 *
 * @param string $plugin_basename
 * @return string
 */
function barbas_update_get_plugin_installed_version($plugin_basename) {
    $plugin_basename = is_string($plugin_basename) ? $plugin_basename : '';
    if ($plugin_basename === '') {
        return '';
    }

    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $absolute = WP_PLUGIN_DIR . '/' . $plugin_basename;
    if (!is_readable($absolute)) {
        return '';
    }

    $data = get_plugin_data($absolute, false, false);
    return !empty($data['Version']) ? (string) $data['Version'] : '';
}

/**
 * New version from update_plugins transient, if any.
 *
 * @param string $plugin_basename
 * @return string
 */
function barbas_update_get_available_update_version($plugin_basename) {
    $plugin_basename = is_string($plugin_basename) ? $plugin_basename : '';
    if ($plugin_basename === '') {
        return '';
    }

    $updates = get_site_transient('update_plugins');
    if (!is_object($updates) || empty($updates->response) || !is_array($updates->response)) {
        return '';
    }
    if (empty($updates->response[$plugin_basename]) || !is_object($updates->response[$plugin_basename])) {
        return '';
    }
    $new = isset($updates->response[$plugin_basename]->new_version)
        ? (string) $updates->response[$plugin_basename]->new_version
        : '';
    return $new;
}

/**
 * PUC / GitHub slug for a plugin tab.
 *
 * @param array<string, mixed> $tab
 * @return string
 */
function barbas_update_get_tab_plugin_slug(array $tab) {
    if (!empty($tab['github_repo']) && is_string($tab['github_repo'])) {
        $parts = explode('/', $tab['github_repo']);
        return sanitize_key(end($parts));
    }
    if (!empty($tab['plugin']) && is_string($tab['plugin'])) {
        $slug = sanitize_key(dirname($tab['plugin']));
        if ($slug === '.' || $slug === '') {
            $slug = sanitize_key(basename($tab['plugin'], '.php'));
        }
        return $slug;
    }
    return '';
}

/**
 * Whether the plugin has its own license (not only suite fallback).
 *
 * @param string $tab_id
 * @return bool
 */
function barbas_update_has_own_token_for_plugin($tab_id) {
    $tab_id = sanitize_key($tab_id);
    if ($tab_id === '' || $tab_id === 'suite') {
        return false;
    }

    if (function_exists('barbas_update_get_db_token_for_plugin')
        && barbas_update_get_db_token_for_plugin($tab_id) !== ''
    ) {
        return true;
    }
    if (function_exists('barbas_update_get_wpconfig_token_for_plugin')
        && barbas_update_get_wpconfig_token_for_plugin($tab_id) !== ''
    ) {
        return true;
    }

    return false;
}

/**
 * Status rows for active Barbas plugins (versions, license, updates).
 *
 * @return array<int, array<string, mixed>>
 */
function barbas_update_collect_plugins_status() {
    $plugin_tabs = barbas_update_get_plugin_tabs();
    $suite_active = function_exists('barbas_update_suite_is_active') && barbas_update_suite_is_active();
    $can_update = current_user_can('update_plugins');
    $rows = array();

    foreach ($plugin_tabs as $tab_id => $tab) {
        $plugin_file = isset($tab['plugin']) ? (string) $tab['plugin'] : '';
        $has_token = barbas_update_has_token_for_plugin($tab_id);
        $installed = $plugin_file !== '' ? barbas_update_get_plugin_installed_version($plugin_file) : '';
        $available = $plugin_file !== '' ? barbas_update_get_available_update_version($plugin_file) : '';

        if ($available === '' && $plugin_file !== '') {
            $checkers = barbas_update_find_puc_instances(array($plugin_file));
            if (isset($checkers[$plugin_file]) && method_exists($checkers[$plugin_file], 'getUpdate')) {
                $update = $checkers[$plugin_file]->getUpdate();
                if (is_object($update) && !empty($update->version)) {
                    $available = (string) $update->version;
                }
            }
        }

        $update_available = $available !== '' && $installed !== ''
            && version_compare($available, $installed, '>');
        if ($available !== '' && $installed === '') {
            $update_available = true;
        }

        if (!$has_token) {
            $status_key = 'no_license';
            $status_label = __('No license', 'barbas-update');
        } elseif ($update_available) {
            $status_key = 'update';
            $status_label = sprintf(
                /* translators: %s: new version */
                __('Update available (%s)', 'barbas-update'),
                $available
            );
        } else {
            $status_key = 'current';
            $status_label = __('Up to date', 'barbas-update');
        }

        $license_note = '';
        if ($has_token && $suite_active && !barbas_update_has_own_token_for_plugin($tab_id)) {
            $license_note = __('Licensed via suite', 'barbas-update');
        }

        $rows[] = array(
            'tab_id' => (string) $tab_id,
            'label' => isset($tab['label']) ? (string) $tab['label'] : (string) $tab_id,
            'plugin' => $plugin_file,
            'slug' => barbas_update_get_tab_plugin_slug($tab),
            'installed_version' => $installed,
            'new_version' => $update_available ? $available : '',
            'has_token' => $has_token,
            'status_key' => $status_key,
            'status_label' => $status_label,
            'license_note' => $license_note,
            'can_update' => $can_update && $has_token && $update_available && $plugin_file !== '',
        );
    }

    return $rows;
}

/**
 * Render the shared “plugins on this site” status list (inner markup).
 *
 * @param array<int, array<string, mixed>>|null $rows
 */
function barbas_update_render_plugins_status_list($rows = null) {
    if ($rows === null) {
        $rows = barbas_update_collect_plugins_status();
    }
    ?>
    <ul class="barbas-update-suite-list barbas-update-plugins-status-list">
        <?php if (empty($rows)) : ?>
            <li class="barbas-update-suite-list__item">
                <span class="barbas-update-suite-list__label">
                    <?php echo esc_html__('No Barbas plugin with a license panel is active.', 'barbas-update'); ?>
                </span>
            </li>
        <?php else : ?>
            <?php foreach ($rows as $row) : ?>
                <?php
                $status_class = 'is-muted';
                if ($row['status_key'] === 'current') {
                    $status_class = 'is-ok';
                } elseif ($row['status_key'] === 'update') {
                    $status_class = 'is-update';
                } elseif ($row['status_key'] === 'no_license') {
                    $status_class = 'is-warn';
                }
                ?>
                <li
                    class="barbas-update-suite-list__item"
                    data-tab-id="<?php echo esc_attr($row['tab_id']); ?>"
                    data-plugin="<?php echo esc_attr($row['plugin']); ?>"
                    data-slug="<?php echo esc_attr($row['slug']); ?>"
                >
                    <div class="barbas-update-plugins-status__main">
                        <span class="barbas-update-suite-list__label"><?php echo esc_html($row['label']); ?></span>
                        <?php if ($row['installed_version'] !== '') : ?>
                            <span class="barbas-update-plugins-status__version">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: installed version */
                                        __('v%s', 'barbas-update'),
                                        $row['installed_version']
                                    )
                                );
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="barbas-update-plugins-status__meta">
                        <span class="barbas-update-suite-list__status <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html($row['status_label']); ?>
                        </span>
                        <?php if ($row['license_note'] !== '') : ?>
                            <span class="barbas-update-plugins-status__license">
                                <?php echo esc_html($row['license_note']); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($row['can_update'])) : ?>
                            <button
                                type="button"
                                class="button button-small barbas-update-plugin-update"
                                data-plugin="<?php echo esc_attr($row['plugin']); ?>"
                                data-slug="<?php echo esc_attr($row['slug']); ?>"
                            >
                                <?php echo esc_html__('Update', 'barbas-update'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
    <?php
}

/**
 * Top-of-page card: active Barbas plugins with versions and update actions.
 */
function barbas_update_render_plugins_status_card() {
    $rows = barbas_update_collect_plugins_status();
    $suite_active = function_exists('barbas_update_suite_is_active') && barbas_update_suite_is_active();
    ?>
    <div class="barbas-update-card barbas-update-plugins-status" data-role="plugins-status">
        <h2 class="barbas-update-suite-coverage__title">
            <?php echo esc_html__('Barbas plugins on this site', 'barbas-update'); ?>
        </h2>
        <p class="barbas-update-suite-coverage__intro">
            <?php
            if ($suite_active) {
                echo esc_html__('Active Barbas plugins covered by the suite license. Versions and updates appear below.', 'barbas-update');
            } else {
                echo esc_html__('Active Barbas plugins on this site. Configure a license per plugin (or suite) to check and install updates.', 'barbas-update');
            }
            ?>
        </p>
        <div data-role="plugins-status-list">
            <?php barbas_update_render_plugins_status_list($rows); ?>
        </div>
    </div>
    <?php
}

/**
 * @return string HTML for the plugins status list.
 */
function barbas_update_get_plugins_status_html() {
    ob_start();
    barbas_update_render_plugins_status_list();
    return (string) ob_get_clean();
}

/**
 * Navigation UI: none (1 plugin), tabs (2–10), picker with search (11+).
 *
 * @param int $tab_count Active Barbas plugins with a license panel.
 * @return string 'none'|'tabs'|'picker'
 */
function barbas_update_get_navigation_mode($tab_count) {
    $tab_count = (int) $tab_count;
    if ($tab_count <= 1) {
        return 'none';
    }
    if ($tab_count <= (int) BARBAS_UPDATE_INLINE_TABS_MAX) {
        return 'tabs';
    }
    return 'picker';
}

function barbas_update_get_tab_by_id($tab_id) {
    $tabs = barbas_update_get_tabs();
    return isset($tabs[$tab_id]) ? $tabs[$tab_id] : null;
}

function barbas_update_repo_line_ok($line) {
    return substr($line, -4) === ': OK';
}

/**
 * Corrige colagem com prefixo duplo (ex.: github_pat_ghp_… → ghp_…).
 *
 * @param string $token Raw token.
 * @return string
 */
function barbas_update_normalize_github_token($token) {
    $token = is_string($token) ? trim($token) : '';
    if ($token === '') {
        return '';
    }

    if (preg_match('/^github_pat_(ghp_[A-Za-z0-9_]+)$/', $token, $matches)) {
        return $matches[1];
    }

    return $token;
}

/**
 * @param string $token Raw token.
 * @return array{ok:bool,message:string}|null Null if format looks acceptable.
 */
function barbas_update_github_token_format_error($token) {
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    if (preg_match('/^github_pat_github_pat_/i', $token)) {
        return array(
            'ok' => false,
            'message' => __('Token format looks invalid. Paste either a fine-grained token (github_pat_…) or a classic token (ghp_…), not both prefixes.', 'barbas-update'),
        );
    }

    if (preg_match('/^ghp_github_pat_/i', $token)) {
        return array(
            'ok' => false,
            'message' => __('Token format looks invalid. Remove the extra prefix and keep only one GitHub token.', 'barbas-update'),
        );
    }

    return null;
}

/**
 * Public GitHub status page (outages / degraded API).
 *
 * @return string
 */
function barbas_update_github_status_url() {
    return apply_filters('barbas_update_github_status_url', 'https://www.githubstatus.com/');
}

/**
 * HTTP codes that usually mean GitHub is overloaded or down (not a bad license).
 *
 * @param int $code HTTP status.
 * @return bool
 */
function barbas_update_is_github_availability_http_code($code) {
    return in_array((int) $code, array(429, 500, 502, 503, 504), true);
}

/**
 * Structured failure for GitHub API / transport errors (hub AJAX).
 *
 * @param int|\WP_Error $code_or_error HTTP status or transport WP_Error.
 * @param string        $context       user|repo|generic|update.
 * @return array{ok:false,message:string,error_type:string,http_status?:int,help_url?:string,help_label?:string}
 */
function barbas_update_github_api_failure($code_or_error, $context = 'generic') {
    $status_url = barbas_update_github_status_url();
    $help_label = __('GitHub Status', 'barbas-update');

    if (is_wp_error($code_or_error)) {
        $transport = $code_or_error->get_error_message();
        return array(
            'ok'         => false,
            'message'    => sprintf(
                /* translators: %s: transport error from WordPress HTTP API */
                __('Could not reach GitHub (%s). If GitHub is down, check the status page and try again later.', 'barbas-update'),
                $transport
            ),
            'error_type' => 'github_unreachable',
            'help_url'   => $status_url,
            'help_label' => $help_label,
        );
    }

    $code = (int) $code_or_error;

    if (401 === $code) {
        return array(
            'ok'          => false,
            'message'     => __('Invalid or expired license key.', 'barbas-update'),
            'error_type'  => 'auth',
            'http_status' => $code,
        );
    }

    if (403 === $code) {
        return array(
            'ok'          => false,
            'message'     => __('GitHub denied access (HTTP 403). The token may lack permission, or the API rate limit was exceeded. Try again later.', 'barbas-update'),
            'error_type'  => 'forbidden',
            'http_status' => $code,
            'help_url'    => $status_url,
            'help_label'  => $help_label,
        );
    }

    if (429 === $code) {
        return array(
            'ok'          => false,
            'message'     => __('GitHub rate limit reached (HTTP 429). Wait a few minutes and try again.', 'barbas-update'),
            'error_type'  => 'rate_limit',
            'http_status' => $code,
            'help_url'    => $status_url,
            'help_label'  => $help_label,
        );
    }

    if (barbas_update_is_github_availability_http_code($code)) {
        return array(
            'ok'          => false,
            'message'     => sprintf(
                /* translators: %d: HTTP status code */
                __('GitHub is temporarily unavailable (HTTP %d). This is usually a GitHub outage, not a problem with your license.', 'barbas-update'),
                $code
            ),
            'error_type'  => 'github_unavailable',
            'http_status' => $code,
            'help_url'    => $status_url,
            'help_label'  => $help_label,
        );
    }

    if ('user' === $context) {
        $message = sprintf(
            /* translators: %d: HTTP status code */
            __('GitHub returned status %d while validating the user.', 'barbas-update'),
            $code
        );
    } else {
        $message = sprintf(
            /* translators: %d: HTTP status code */
            __('GitHub returned HTTP %d.', 'barbas-update'),
            $code
        );
    }

    return array(
        'ok'          => false,
        'message'     => $message,
        'error_type'  => 'github_http',
        'http_status' => $code,
        'help_url'    => $status_url,
        'help_label'  => $help_label,
    );
}

/**
 * Best-effort summary from Plugin Update Checker API error list.
 *
 * @param array $api_errors From getLastRequestApiErrors().
 * @return array|null Failure payload or null.
 */
function barbas_update_failure_from_puc_api_errors($api_errors) {
    if (empty($api_errors) || !is_array($api_errors)) {
        return null;
    }

    foreach ($api_errors as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!empty($item['httpResponse'])) {
            $code = (int) wp_remote_retrieve_response_code($item['httpResponse']);
            if ($code > 0) {
                return barbas_update_github_api_failure($code, 'update');
            }
        }
        if (!empty($item['error']) && is_wp_error($item['error'])) {
            $err = $item['error'];
            $data = $err->get_error_data();
            if (is_array($data) && isset($data['status'])) {
                return barbas_update_github_api_failure((int) $data['status'], 'update');
            }
            $msg = $err->get_error_message();
            if (preg_match('/\b(429|500|502|503|504)\b/', $msg, $m)) {
                return barbas_update_github_api_failure((int) $m[1], 'update');
            }
            return barbas_update_github_api_failure($err, 'update');
        }
    }

    return null;
}

function barbas_update_validate_github_token($token, $github_repos = array()) {
    $token = barbas_update_normalize_github_token($token);
    if ($token === '') {
        return array('ok' => false, 'message' => __('Please enter a license key.', 'barbas-update'));
    }

    $format_error = barbas_update_github_token_format_error($token);
    if ($format_error !== null) {
        return $format_error;
    }

    $headers = array(
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/vnd.github+json',
        'User-Agent' => 'Barbas-Update-Hub/2.1',
    );

    $user_response = wp_remote_get('https://api.github.com/user', array(
        'headers' => $headers,
        'timeout' => 15,
    ));

    if (is_wp_error($user_response)) {
        return barbas_update_github_api_failure($user_response, 'user');
    }

    $user_code = (int) wp_remote_retrieve_response_code($user_response);
    if ($user_code !== 200) {
        return barbas_update_github_api_failure($user_code, 'user');
    }

    $user_body = json_decode(wp_remote_retrieve_body($user_response), true);
    $login = is_array($user_body) && !empty($user_body['login']) ? $user_body['login'] : '';

    $repo_results = array();
    $repo_help    = null;
    foreach (array_filter($github_repos) as $repo) {
        $repo_response = wp_remote_get('https://api.github.com/repos/' . $repo, array(
            'headers' => $headers,
            'timeout' => 15,
        ));

        if (is_wp_error($repo_response)) {
            $fail = barbas_update_github_api_failure($repo_response, 'repo');
            $repo_results[] = $repo . ': ' . $fail['message'];
            if (empty($repo_help) && !empty($fail['help_url'])) {
                $repo_help = $fail;
            }
            continue;
        }

        $repo_code = (int) wp_remote_retrieve_response_code($repo_response);
        if ($repo_code === 200) {
            $repo_results[] = $repo . ': OK';
        } elseif ($repo_code === 404) {
            $repo_results[] = $repo . ': ' . __('no access or repository not found', 'barbas-update');
        } else {
            $fail = barbas_update_github_api_failure($repo_code, 'repo');
            $repo_results[] = $repo . ': ' . $fail['message'];
            if (empty($repo_help) && !empty($fail['help_url'])) {
                $repo_help = $fail;
            }
        }
    }

    if (!empty($github_repos)) {
        $all_ok = !empty($repo_results);
        foreach ($repo_results as $line) {
            if (!barbas_update_repo_line_ok($line)) {
                $all_ok = false;
                break;
            }
        }
        if (!$all_ok) {
            $out = array(
                'ok' => false,
                'message' => count($github_repos) > 1
                    ? __('License authenticated but one or more plugin repositories are not accessible.', 'barbas-update')
                    : __('License authenticated but this plugin repository is not accessible.', 'barbas-update'),
                'login' => $login,
                'repos' => $repo_results,
            );
            if (is_array($repo_help)) {
                if (!empty($repo_help['help_url'])) {
                    $out['help_url'] = $repo_help['help_url'];
                    $out['help_label'] = isset($repo_help['help_label']) ? $repo_help['help_label'] : __('GitHub Status', 'barbas-update');
                }
                if (!empty($repo_help['error_type'])) {
                    $out['error_type'] = $repo_help['error_type'];
                }
                if (!empty($repo_help['http_status'])) {
                    $out['http_status'] = $repo_help['http_status'];
                }
                if (!empty($repo_help['message']) && barbas_update_is_github_availability_http_code(isset($repo_help['http_status']) ? $repo_help['http_status'] : 0)) {
                    $out['message'] = $repo_help['message'];
                }
            }
            return $out;
        }
    }

    return array(
        'ok' => true,
        'message' => $login
            ? sprintf(__('Valid license (account: %s).', 'barbas-update'), $login)
            : __('Valid license.', 'barbas-update'),
        'login' => $login,
        'repos' => $repo_results,
    );
}

/**
 * Attach GitHub Status help when an upgrader/API message looks like an outage.
 *
 * @param array $payload JSON error payload.
 * @return array
 */
function barbas_update_enrich_github_error_payload($payload) {
    if (!is_array($payload) || !empty($payload['help_url'])) {
        return $payload;
    }

    $msg = isset($payload['message']) ? (string) $payload['message'] : '';
    if ($msg === '') {
        return $payload;
    }

    if (preg_match('/\b(429|500|502|503|504)\b/', $msg, $m)) {
        $fail = barbas_update_github_api_failure((int) $m[1], 'update');
        foreach (array('message', 'error_type', 'http_status', 'help_url', 'help_label') as $k) {
            if (!empty($fail[ $k ])) {
                $payload[ $k ] = $fail[ $k ];
            }
        }
        return $payload;
    }

    if (
        false !== stripos($msg, 'github')
        && (false !== stripos($msg, 'timed out') || false !== stripos($msg, 'could not') || false !== stripos($msg, 'failed'))
    ) {
        $payload['help_url'] = barbas_update_github_status_url();
        $payload['help_label'] = __('GitHub Status', 'barbas-update');
        $payload['error_type'] = 'github_unreachable';
    }

    return $payload;
}

function barbas_update_settings_url($tab_id = '') {
    $slug = defined('BARBAS_UPDATE_MENU_SLUG') ? BARBAS_UPDATE_MENU_SLUG : 'barbas-update';
    $url = admin_url('options-general.php?page=' . $slug);
    if ($tab_id !== '') {
        $url = add_query_arg('tab', sanitize_key($tab_id), $url);
    }
    return $url;
}

function barbas_update_register_admin_menu() {
    if (defined('BARBAS_UPDATE_MENU_REGISTERED')) {
        return;
    }

    define('BARBAS_UPDATE_MENU_REGISTERED', true);

    add_options_page(
        __('Barbas Update', 'barbas-update'),
        __('Barbas Update', 'barbas-update'),
        'manage_options',
        BARBAS_UPDATE_MENU_SLUG,
        'barbas_update_render_admin_page'
    );
}

/* Hooks registrados em barbas-update-bootstrap.php → barbas_update_register_hooks() */

/**
 * True quando a tela atual é o painel Barbas Update.
 */
function barbas_update_is_hub_admin_page() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === BARBAS_UPDATE_MENU_SLUG) {
        return true;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    return $screen && isset($screen->id) && $screen->id === 'settings_page_barbas-update';
}

/**
 * Remove notices/banners de terceiros na página Barbas Update (padrão plugins premium).
 */
function barbas_update_suppress_third_party_notices() {
    if (!barbas_update_is_hub_admin_page()) {
        return;
    }

    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');
    remove_all_actions('network_admin_notices');
    remove_all_actions('user_admin_notices');
}

function barbas_update_admin_notices() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Já estamos no hub — não poluir a própria tela.
    if (barbas_update_is_hub_admin_page()) {
        return;
    }

    try {
        $tabs = barbas_update_get_tabs();
        $missing = array();
        foreach ($tabs as $id => $tab) {
            // Suite tab is optional when individual plugin tabs are also listed.
            if ($id === 'suite' && count($tabs) > 1) {
                continue;
            }
            if (!barbas_update_has_token_for_plugin($id) && !barbas_update_token_from_constant($id)) {
                $missing[] = $tab['label'];
            }
        }

        if (empty($missing)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && in_array($screen->id, array('plugins', 'plugins-network', 'update-core'), true)) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Barbas Update: configure the update license for each installed plugin.', 'barbas-update');
            echo ' <a href="' . esc_url(barbas_update_settings_url()) . '">' . esc_html__('Configure', 'barbas-update') . '</a>';
            echo '</p></div>';
        }
    } catch (Throwable $e) {
        // Never take down wp-admin if hub helpers are partially missing after restore.
        return;
    }
}

function barbas_update_handle_settings_post($tab_id) {
    if (!current_user_can('manage_options')) {
        return null;
    }

    $tab = barbas_update_get_tab_by_id($tab_id);
    if (!$tab) {
        return null;
    }

    if (empty($_POST['barbas_update_settings_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['barbas_update_settings_nonce'])), 'barbas_update_save_' . $tab_id)) {
        return null;
    }

    $option_key = barbas_update_token_option_key($tab_id);

    if (!empty($_POST['barbas_update_clear_token'])) {
        delete_option($option_key);
        if ($tab_id === 'uncode-core-fix') {
            delete_option('barbas_uncode_core_fix_github_token');
            delete_option('barbas_update_github_token');
        }
        return 'cleared';
    }

    $raw = isset($_POST['barbas_update_github_token'])
        ? sanitize_text_field(wp_unslash($_POST['barbas_update_github_token']))
        : '';

    if ($raw !== '') {
        if (function_exists('barbas_update_store_token_option')) {
            if (!barbas_update_store_token_option($option_key, $raw)) {
                return 'encrypt_failed';
            }
        } else {
            update_option($option_key, $raw, false);
        }
        return 'saved';
    }

    return null;
}

function barbas_update_ajax_validate_token() {
    check_ajax_referer('barbas_update_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to do this.', 'barbas-update')), 403);
    }

    $tab_id = isset($_POST['tab_id']) ? sanitize_key(wp_unslash($_POST['tab_id'])) : '';
    $tab = barbas_update_get_tab_by_id($tab_id);
    if (!$tab) {
        wp_send_json_error(array('message' => __('Invalid tab.', 'barbas-update')), 400);
    }

    $token = barbas_update_get_token_for_plugin($tab_id);
    if (!empty($_POST['token'])) {
        $token = sanitize_text_field(wp_unslash($_POST['token']));
    }

    $repos = array();
    if ($tab_id === 'suite' && !empty($tab['github_repos']) && is_array($tab['github_repos'])) {
        $repos = array_values(array_filter($tab['github_repos']));
    } elseif (!empty($tab['github_repo'])) {
        $repos = array($tab['github_repo']);
    }
    $result = barbas_update_validate_github_token($token, $repos);

    if ($result['ok']) {
        wp_send_json_success($result);
    }
    wp_send_json_error($result);
}

function barbas_update_ajax_save_token() {
    check_ajax_referer('barbas_update_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to do this.', 'barbas-update')), 403);
    }

    $tab_id = isset($_POST['tab_id']) ? sanitize_key(wp_unslash($_POST['tab_id'])) : '';
    $tab = barbas_update_get_tab_by_id($tab_id);
    if (!$tab) {
        wp_send_json_error(array('message' => __('Invalid tab.', 'barbas-update')), 400);
    }

    $token_action = isset($_POST['token_action']) ? sanitize_key(wp_unslash($_POST['token_action'])) : 'save';
    $option_key = barbas_update_token_option_key($tab_id);

    if ($token_action === 'clear') {
        delete_option($option_key);
        if ($tab_id === 'uncode-core-fix') {
            delete_option('barbas_uncode_core_fix_github_token');
            delete_option('barbas_update_github_token');
        }

        $wpconfig_removed = function_exists('barbas_update_wpconfig_remove_for_tab')
            ? barbas_update_wpconfig_remove_for_tab($tab_id)
            : false;

        $message = $wpconfig_removed
            ? __('License removed from the dashboard and wp-config.php.', 'barbas-update')
            : __('License removed.', 'barbas-update');

        wp_send_json_success(array(
            'message' => $message,
            'has_token' => false,
            'masked_token' => '',
            'wpconfig_removed' => $wpconfig_removed,
            'reload' => ($tab_id === 'suite'),
        ));
    }

    $raw = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';

    if ($raw === '') {
        $existing = barbas_update_get_token_for_plugin($tab_id);
        if ($existing !== '') {
            wp_send_json_success(array(
                'message' => __('License kept.', 'barbas-update'),
                'has_token' => true,
                'masked_token' => barbas_update_mask_token($existing),
                'reload' => false,
            ));
        }
        wp_send_json_error(array('message' => __('Please enter a license key.', 'barbas-update')), 400);
    }

    $stored = false;
    if (function_exists('barbas_update_store_token_option')) {
        $stored = barbas_update_store_token_option($option_key, $raw);
    } else {
        $stored = (bool) update_option($option_key, $raw, false);
    }

    if (!$stored) {
        wp_send_json_error(
            array(
                'message' => __(
                    'Could not save the license securely. OpenSSL (AES-256) is required on this server.',
                    'barbas-update'
                ),
            ),
            500
        );
    }

    wp_send_json_success(array(
        'message' => __('License saved.', 'barbas-update'),
        'has_token' => true,
        'masked_token' => barbas_update_mask_token($raw),
        'reload' => ($tab_id === 'suite'),
    ));
}

function barbas_update_get_asset_version() {
    if (defined('BARBAS_UPDATE_HUB_VERSION')) {
        return BARBAS_UPDATE_HUB_VERSION;
    }
    if (defined('BARBAS_UNCODE_CORE_FIX_VERSION')) {
        return BARBAS_UNCODE_CORE_FIX_VERSION;
    }
    if (defined('BARBAS_IMAGE_UPLOAD_VERSION')) {
        return BARBAS_IMAGE_UPLOAD_VERSION;
    }
    return '1.0.0';
}

function barbas_update_enqueue_admin_assets($hook) {
    if ($hook !== 'settings_page_barbas-update') {
        return;
    }

    $plugin_version = barbas_update_get_asset_version();

    wp_enqueue_style(
        'barbas-update-admin',
        plugins_url('assets/css/barbas-update-admin.css', BARBAS_UPDATE_PLUGIN_FILE),
        array(),
        $plugin_version
    );

    wp_enqueue_script(
        'barbas-update-admin',
        plugins_url('assets/js/barbas-update-admin.js', BARBAS_UPDATE_PLUGIN_FILE),
        array('jquery'),
        $plugin_version,
        true
    );

    $tabs = barbas_update_get_tabs();
    $tab_count = count($tabs);

    wp_localize_script('barbas-update-admin', 'barbasUpdateAdmin', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('barbas_update_admin'),
        'settingsBase' => admin_url('options-general.php?page=' . BARBAS_UPDATE_MENU_SLUG),
        'navMode' => barbas_update_get_navigation_mode($tab_count),
        'tabCount' => $tab_count,
        'inlineTabsMax' => (int) BARBAS_UPDATE_INLINE_TABS_MAX,
        'githubStatusUrl' => barbas_update_github_status_url(),
        'i18n' => array(
            'validating' => __('Validating license…', 'barbas-update'),
            'saving' => __('Saving…', 'barbas-update'),
            'removing' => __('Removing…', 'barbas-update'),
            'confirmRemove' => __('Remove the license for this plugin?', 'barbas-update'),
            'requestFailed' => __('Request failed. Please try again.', 'barbas-update'),
            'githubStatus' => __('GitHub Status', 'barbas-update'),
            'githubUnavailableHint' => __('GitHub may be experiencing an outage. Check the status page and try again later.', 'barbas-update'),
            'licenseActive' => __('License active', 'barbas-update'),
            'licenseMissing' => __('No license', 'barbas-update'),
            'licenseHint' => __('Leave blank and save to keep the current license.', 'barbas-update'),
            'licenseCurrent' => __('Current license:', 'barbas-update'),
            'wpConfig' => __('License is defined in wp-config.php. The field below is ignored.', 'barbas-update'),
            'wpConfigActive' => __('License is active from wp-config.php. Enter a key below to override it for this site.', 'barbas-update'),
            'wpConfigOverride' => __('Dashboard license overrides wp-config.php for this plugin.', 'barbas-update'),
            'confirmRemoveWpConfig' => __('Remove the license for this plugin? This will also delete it from wp-config.php if present.', 'barbas-update'),
            'loadingPanel' => __('Loading plugin panel…', 'barbas-update'),
            'searchPlugins' => __('Search plugins…', 'barbas-update'),
            'pluginsCount' => __('Showing %1$d of %2$d plugins', 'barbas-update'),
            'noPluginsMatch' => __('No plugins match your search.', 'barbas-update'),
            'checkingUpdates' => __('Checking for updates…', 'barbas-update'),
            'checkUpdates' => __('Check for updates', 'barbas-update'),
            'updatesAvailableLead' => __('Update check complete. Updates available:', 'barbas-update'),
            'updatingPlugin' => __('Updating…', 'barbas-update'),
            'updatePlugin' => __('Update', 'barbas-update'),
            'updateSuccess' => __('Plugin updated.', 'barbas-update'),
            'updateFailed' => __('Could not update the plugin.', 'barbas-update'),
        ),
    ));
}

function barbas_update_ajax_load_panel() {
    check_ajax_referer('barbas_update_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to do this.', 'barbas-update')), 403);
    }

    $tab_id = isset($_POST['tab_id']) ? sanitize_key(wp_unslash($_POST['tab_id'])) : '';
    $tab = barbas_update_get_tab_by_id($tab_id);
    if (!$tab) {
        wp_send_json_error(array('message' => __('Invalid tab.', 'barbas-update')), 400);
    }

    ob_start();
    barbas_update_render_tab_panel($tab_id, $tab, true);
    $html = ob_get_clean();

    wp_send_json_success(
        array(
            'html' => $html,
            'tab_id' => $tab_id,
        )
    );
}

/**
 * Locate live Plugin Update Checker instances bound to Barbas plugin files.
 *
 * @param string[] $plugin_basenames Relative plugin paths (plugin_basename).
 * @return array<string, object> Map of plugin basename => UpdateChecker instance.
 */
function barbas_update_find_puc_instances(array $plugin_basenames) {
    $found = array();
    $wanted = array_fill_keys($plugin_basenames, true);

    foreach (barbas_update_get_registered_puc_instances() as $basename => $checker) {
        if (isset($wanted[$basename]) && is_object($checker) && method_exists($checker, 'checkForUpdates')) {
            $found[$basename] = $checker;
        }
    }

    if (empty($wanted) || empty($GLOBALS['wp_filter']['site_transient_update_plugins'])) {
        return $found;
    }

    $hook = $GLOBALS['wp_filter']['site_transient_update_plugins'];
    $callbacks = is_object($hook) && isset($hook->callbacks) && is_array($hook->callbacks)
        ? $hook->callbacks
        : array();

    foreach ($callbacks as $priority_group) {
        if (!is_array($priority_group)) {
            continue;
        }
        foreach ($priority_group as $entry) {
            if (empty($entry['function']) || !is_array($entry['function'])) {
                continue;
            }
            if (($entry['function'][1] ?? '') !== 'injectUpdate' || !is_object($entry['function'][0])) {
                continue;
            }
            $checker = $entry['function'][0];
            if (!method_exists($checker, 'checkForUpdates')) {
                continue;
            }
            $plugin_file = '';
            if (isset($checker->pluginFile) && is_string($checker->pluginFile)) {
                $plugin_file = $checker->pluginFile;
            } elseif (method_exists($checker, 'getPluginFile')) {
                $plugin_file = (string) $checker->getPluginFile();
            }
            if ($plugin_file === '' || !isset($wanted[$plugin_file])) {
                continue;
            }
            $found[$plugin_file] = $checker;
        }
    }

    return $found;
}

/**
 * Clear shared WordPress / PUC update caches for Barbas plugins about to be checked.
 *
 * @param array<string, array<string, mixed>> $plugin_tabs
 */
function barbas_update_clear_update_caches(array $plugin_tabs) {
    delete_site_transient('update_plugins');

    foreach ($plugin_tabs as $tab) {
        $slug = '';
        if (!empty($tab['github_repo']) && is_string($tab['github_repo'])) {
            $parts = explode('/', $tab['github_repo']);
            $slug = sanitize_key(end($parts));
        } elseif (!empty($tab['plugin']) && is_string($tab['plugin'])) {
            $slug = sanitize_key(dirname($tab['plugin']));
            if ($slug === '.' || $slug === '') {
                $slug = sanitize_key(basename($tab['plugin'], '.php'));
            }
        }
        if ($slug === '') {
            continue;
        }
        delete_site_option('external_updates-' . $slug);
        delete_option('external_updates-' . $slug);
    }
}

/**
 * AJAX: force GitHub update checks for all active Barbas plugins registered in the hub.
 */
function barbas_update_ajax_force_check() {
    check_ajax_referer('barbas_update_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to do this.', 'barbas-update')), 403);
    }

    $plugin_tabs = barbas_update_get_plugin_tabs();
    if (empty($plugin_tabs)) {
        wp_send_json_error(
            array('message' => __('No Barbas plugin with a license panel is active.', 'barbas-update')),
            400
        );
    }

    $basenames = array();
    foreach ($plugin_tabs as $tab) {
        if (!empty($tab['plugin']) && is_string($tab['plugin'])) {
            $basenames[] = $tab['plugin'];
        }
    }

    barbas_update_clear_update_caches($plugin_tabs);

    $checkers = barbas_update_find_puc_instances($basenames);
    $checked = 0;
    $updates = array();
    $errors = array();
    $skipped = array();
    $github_fail = null;

    foreach ($plugin_tabs as $tab_id => $tab) {
        $label = isset($tab['label']) ? (string) $tab['label'] : (string) $tab_id;
        $plugin_file = isset($tab['plugin']) ? (string) $tab['plugin'] : '';

        if ($plugin_file === '' || !isset($checkers[$plugin_file])) {
            if (!barbas_update_has_token_for_plugin($tab_id)) {
                $skipped[] = $label;
                continue;
            }
            $errors[] = $label;
            continue;
        }

        $checker = $checkers[$plugin_file];

        try {
            if (method_exists($checker, 'resetUpdateState')) {
                $checker->resetUpdateState();
            }
            $update = $checker->checkForUpdates();
            $api_errors = method_exists($checker, 'getLastRequestApiErrors')
                ? $checker->getLastRequestApiErrors()
                : array();

            if (!empty($api_errors)) {
                $fail = barbas_update_failure_from_puc_api_errors($api_errors);
                if (is_array($fail) && empty($github_fail) && !empty($fail['help_url'])) {
                    $github_fail = $fail;
                }
                $detail = (is_array($fail) && !empty($fail['http_status']))
                    ? ('HTTP ' . (int) $fail['http_status'])
                    : '';
                $errors[] = $detail !== '' ? ($label . ' (' . $detail . ')') : $label;
                continue;
            }

            $checked++;

            if ($update !== null && is_object($update) && !empty($update->version)) {
                $updates[] = sprintf('%s (%s)', $label, $update->version);
            }
        } catch (Exception $e) {
            $errors[] = $label;
        }
    }

    // Rebuild WP update_plugins so plugins.php and the status list stay in sync with PUC.
    delete_site_transient('update_plugins');
    if (!function_exists('wp_update_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/update.php';
    }
    if (function_exists('wp_update_plugins')) {
        wp_update_plugins();
    }

    // Prefer versions from the rebuilt transient (covers injectUpdate edge cases).
    if (empty($updates)) {
        foreach ($plugin_tabs as $tab_id => $tab) {
            $label = isset($tab['label']) ? (string) $tab['label'] : (string) $tab_id;
            $plugin_file = isset($tab['plugin']) ? (string) $tab['plugin'] : '';
            if ($plugin_file === '') {
                continue;
            }
            $available = barbas_update_get_available_update_version($plugin_file);
            $installed = barbas_update_get_plugin_installed_version($plugin_file);
            if ($available !== '' && ($installed === '' || version_compare($available, $installed, '>'))) {
                $updates[] = sprintf('%s (%s)', $label, $available);
            }
        }
        $updates = array_values(array_unique($updates));
    }

    if ($checked === 0 && !empty($errors) && empty($skipped)) {
        $payload = array(
            'message' => is_array($github_fail) && !empty($github_fail['message'])
                ? $github_fail['message']
                : __('Could not check for updates. Configure a license first.', 'barbas-update'),
            'checked' => 0,
            'updates' => array(),
            'errors' => $errors,
            'skipped' => $skipped,
            'html' => barbas_update_get_plugins_status_html(),
        );
        if (is_array($github_fail)) {
            foreach (array('help_url', 'help_label', 'error_type', 'http_status') as $k) {
                if (!empty($github_fail[ $k ])) {
                    $payload[ $k ] = $github_fail[ $k ];
                }
            }
        }
        wp_send_json_error($payload, 500);
    }

    if ($checked === 0 && !empty($skipped) && empty($errors)) {
        wp_send_json_error(
            array(
                'message' => __('Could not check for updates. Configure a license first.', 'barbas-update'),
                'checked' => 0,
                'updates' => array(),
                'errors' => $errors,
                'skipped' => $skipped,
                'html' => barbas_update_get_plugins_status_html(),
            ),
            400
        );
    }

    if (!empty($updates)) {
        $message = sprintf(
            /* translators: %s: comma-separated plugin names with versions */
            __('Update check complete. Updates available: %s.', 'barbas-update'),
            implode(', ', $updates)
        );
    } elseif ($checked === 0 && !empty($errors)) {
        $message = is_array($github_fail) && !empty($github_fail['message'])
            ? $github_fail['message']
            : __('Could not check for updates for one or more plugins.', 'barbas-update');
    } else {
        $message = __('Update check complete. All checked plugins are up to date.', 'barbas-update');
    }

    if (!empty($errors) && !(is_array($github_fail) && !empty($github_fail['message']) && $checked === 0)) {
        $message .= ' ' . sprintf(
            /* translators: %s: comma-separated plugin names */
            __('Could not check: %s.', 'barbas-update'),
            implode(', ', $errors)
        );
    }

    $success = array(
        'message' => $message,
        'checked' => $checked,
        'updates' => $updates,
        'errors' => $errors,
        'skipped' => $skipped,
        'html' => barbas_update_get_plugins_status_html(),
    );
    if (is_array($github_fail)) {
        foreach (array('help_url', 'help_label', 'error_type', 'http_status') as $k) {
            if (!empty($github_fail[ $k ])) {
                $success[ $k ] = $github_fail[ $k ];
            }
        }
    }

    wp_send_json_success($success);
}

/**
 * Whether a plugin basename is an active Barbas hub plugin with a license.
 *
 * @param string $plugin_basename
 * @return bool
 */
function barbas_update_can_ajax_upgrade_plugin($plugin_basename) {
    $plugin_basename = is_string($plugin_basename) ? $plugin_basename : '';
    if ($plugin_basename === '' || !current_user_can('update_plugins')) {
        return false;
    }

    foreach (barbas_update_get_plugin_tabs() as $tab_id => $tab) {
        if (empty($tab['plugin']) || (string) $tab['plugin'] !== $plugin_basename) {
            continue;
        }
        return barbas_update_has_token_for_plugin($tab_id);
    }

    return false;
}

/**
 * AJAX: install a pending Barbas plugin update from the hub (no plugins.php DOM).
 */
function barbas_update_ajax_install_plugin() {
    check_ajax_referer('barbas_update_admin', 'nonce');

    if (!current_user_can('manage_options') || !current_user_can('update_plugins')) {
        wp_send_json_error(array('message' => __('You do not have permission to do this.', 'barbas-update')), 403);
    }

    $plugin = isset($_POST['plugin']) ? plugin_basename(wp_unslash((string) $_POST['plugin'])) : '';
    if ($plugin === '' || !barbas_update_can_ajax_upgrade_plugin($plugin)) {
        wp_send_json_error(array('message' => __('Invalid plugin.', 'barbas-update')), 400);
    }

    $available = barbas_update_get_available_update_version($plugin);
    if ($available === '') {
        $checkers = barbas_update_find_puc_instances(array($plugin));
        if (isset($checkers[$plugin]) && method_exists($checkers[$plugin], 'checkForUpdates')) {
            $checkers[$plugin]->checkForUpdates();
        }
        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . 'wp-includes/update.php';
        }
        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }
        $available = barbas_update_get_available_update_version($plugin);
    }

    if ($available === '') {
        wp_send_json_error(
            array(
                'message' => __('No update is available for this plugin.', 'barbas-update'),
                'html' => barbas_update_get_plugins_status_html(),
            ),
            400
        );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    // Quiet skin — hub refreshes its own status list; do not depend on plugins.php rows.
    if (!class_exists('WP_Ajax_Upgrader_Skin', false)) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
    }

    // Match wp_ajax_update_plugin: bulk_upgrade() keeps the plugin active.
    // Plugin_Upgrader::upgrade() calls deactivate_plugin_before_upgrade() and never
    // reactivates, so the hub status list (is_plugin_active + barbas_update_tabs)
    // would drop the plugin from the HTML returned after a successful update.
    $was_active = is_plugin_active($plugin);

    $skin = new WP_Ajax_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader($skin);
    $result = $upgrader->bulk_upgrade(array($plugin));

    if (is_wp_error($skin->result)) {
        wp_send_json_error(
            barbas_update_enrich_github_error_payload(
                array(
                    'message' => $skin->result->get_error_message(),
                    'html' => barbas_update_get_plugins_status_html(),
                )
            ),
            500
        );
    }

    if ($skin->get_errors()->has_errors()) {
        wp_send_json_error(
            barbas_update_enrich_github_error_payload(
                array(
                    'message' => $skin->get_errors()->get_error_message(),
                    'html' => barbas_update_get_plugins_status_html(),
                )
            ),
            500
        );
    }

    if (is_wp_error($result)) {
        wp_send_json_error(
            barbas_update_enrich_github_error_payload(
                array(
                    'message' => $result->get_error_message(),
                    'html' => barbas_update_get_plugins_status_html(),
                )
            ),
            500
        );
    }

    if ($result === false) {
        wp_send_json_error(
            array(
                'message' => __('Could not update the plugin.', 'barbas-update'),
                'html' => barbas_update_get_plugins_status_html(),
            ),
            500
        );
    }

    $plugin_result = (is_array($result) && array_key_exists($plugin, $result))
        ? $result[$plugin]
        : null;

    if (is_wp_error($plugin_result)) {
        wp_send_json_error(
            array(
                'message' => $plugin_result->get_error_message(),
                'html' => barbas_update_get_plugins_status_html(),
            ),
            500
        );
    }

    if ($plugin_result === false || $plugin_result === null) {
        wp_send_json_error(
            array(
                'message' => __('Could not update the plugin.', 'barbas-update'),
                'html' => barbas_update_get_plugins_status_html(),
            ),
            500
        );
    }

    // bulk_upgrade() returns true when the plugin is already at the latest version.
    if ($plugin_result === true) {
        wp_send_json_error(
            array(
                'message' => __('No update is available for this plugin.', 'barbas-update'),
                'html' => barbas_update_get_plugins_status_html(),
            ),
            400
        );
    }

    // Re-activate like WP admin updates: hub list/tabs only include active plugins.
    // (upgrade() deactivates without reactivate; bulk_upgrade usually keeps active — still ensure.)
    if ($was_active) {
        $activated = activate_plugin($plugin, '', false, false);
        if (is_wp_error($activated)) {
            wp_send_json_error(
                array(
                    'message' => sprintf(
                        /* translators: %s: activation error */
                        __('Plugin files updated, but reactivation failed: %s', 'barbas-update'),
                        $activated->get_error_message()
                    ),
                    'html' => barbas_update_get_plugins_status_html(),
                    'plugin' => $plugin,
                    'version' => barbas_update_get_plugin_installed_version($plugin),
                ),
                500
            );
        }
    }

    // Refresh WP update cache so the status card reflects the new version.
    delete_site_transient('update_plugins');
    if (function_exists('wp_clean_plugins_cache')) {
        wp_clean_plugins_cache(true);
    }
    if (function_exists('wp_update_plugins')) {
        wp_update_plugins();
    }

    wp_send_json_success(
        array(
            'message' => __('Plugin updated.', 'barbas-update'),
            'html' => barbas_update_get_plugins_status_html(),
            'plugin' => $plugin,
            'version' => barbas_update_get_plugin_installed_version($plugin),
        )
    );
}

/**
 * AJAX: refresh the plugins status list markup.
 */
function barbas_update_ajax_plugins_status() {
    check_ajax_referer('barbas_update_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to do this.', 'barbas-update')), 403);
    }

    wp_send_json_success(
        array(
            'html' => barbas_update_get_plugins_status_html(),
        )
    );
}

function barbas_update_render_tab_panel($tab_id, $tab, $is_active = false) {
    $wpconfig_token = function_exists('barbas_update_get_wpconfig_token_for_plugin')
        ? barbas_update_get_wpconfig_token_for_plugin($tab_id)
        : '';
    $db_token = function_exists('barbas_update_get_db_token_for_plugin')
        ? barbas_update_get_db_token_for_plugin($tab_id)
        : '';
    $stored_token = barbas_update_get_token_for_plugin($tab_id);
    $has_wpconfig = $wpconfig_token !== '';
    $has_db_override = $db_token !== '';
    $has_token = $stored_token !== '';
    $hint_token = $has_db_override ? $db_token : ($has_wpconfig ? $wpconfig_token : '');
    $input_id = 'barbas_update_token_' . $tab_id;
    ?>
    <section
        class="barbas-update-panel<?php echo $is_active ? ' is-active' : ''; ?>"
        id="barbas-update-panel-<?php echo esc_attr($tab_id); ?>"
        data-tab-id="<?php echo esc_attr($tab_id); ?>"
        role="tabpanel"
        aria-labelledby="barbas-update-tab-<?php echo esc_attr($tab_id); ?>"
        <?php echo $is_active ? '' : 'hidden'; ?>
    >
        <div class="barbas-update-card">
            <div class="barbas-update-card__head">
                <div>
                    <h2 class="barbas-update-card__title"><?php echo esc_html($tab['label']); ?></h2>
                    <p class="barbas-update-card__subtitle">
                        <?php
                        echo esc_html(
                            $tab_id === 'suite'
                                ? __('One license key for all active Barbas plugins on this site.', 'barbas-update')
                                : __('License key to receive updates for this plugin.', 'barbas-update')
                        );
                        ?>
                    </p>
                </div>
                <span class="barbas-update-badge<?php echo $has_token ? ' is-active' : ''; ?>" data-role="badge">
                    <?php echo esc_html($has_token ? __('License active', 'barbas-update') : __('No license', 'barbas-update')); ?>
                </span>
            </div>

            <?php if ($has_db_override && $has_wpconfig) : ?>
                <div class="barbas-update-alert barbas-update-alert--info" data-role="wpconfig-notice">
                    <?php echo esc_html__('Dashboard license overrides wp-config.php for this plugin.', 'barbas-update'); ?>
                </div>
            <?php elseif ($has_wpconfig) : ?>
                <div class="barbas-update-alert barbas-update-alert--info" data-role="wpconfig-notice">
                    <?php echo esc_html__('License is active from wp-config.php. Enter a key below to override it for this site.', 'barbas-update'); ?>
                </div>
            <?php else : ?>
                <div class="barbas-update-alert barbas-update-alert--info" data-role="wpconfig-notice" hidden></div>
            <?php endif; ?>

            <div class="barbas-update-field">
                <label class="barbas-update-label" for="<?php echo esc_attr($input_id); ?>">
                    <?php echo esc_html__('License key', 'barbas-update'); ?>
                </label>
                <input
                    type="password"
                    id="<?php echo esc_attr($input_id); ?>"
                    class="barbas-update-input barbas-update-token-input"
                    autocomplete="off"
                    placeholder="<?php echo esc_attr__('Paste your license key', 'barbas-update'); ?>"
                />
                <p class="barbas-update-hint" data-role="hint"<?php echo $hint_token !== '' ? '' : ' hidden'; ?>>
                    <span><?php echo esc_html__('Current license:', 'barbas-update'); ?></span>
                    <code data-role="masked"><?php echo $hint_token !== '' ? esc_html(barbas_update_mask_token($hint_token)) : ''; ?></code>
                    <span><?php echo esc_html__('Leave blank and save to keep.', 'barbas-update'); ?></span>
                </p>
            </div>

            <div class="barbas-update-feedback" data-role="feedback" aria-live="polite"></div>

            <div class="barbas-update-actions">
                <button type="button" class="button button-primary barbas-update-save" data-tab-id="<?php echo esc_attr($tab_id); ?>">
                    <?php echo esc_html__('Save', 'barbas-update'); ?>
                </button>
                <button
                    type="button"
                    class="button barbas-update-clear"
                    data-tab-id="<?php echo esc_attr($tab_id); ?>"
                    data-has-wpconfig="<?php echo $has_wpconfig ? '1' : '0'; ?>"
                    <?php echo ($has_token || $has_wpconfig) ? '' : 'hidden'; ?>
                >
                    <?php echo esc_html__('Remove', 'barbas-update'); ?>
                </button>
                <button type="button" class="button barbas-update-validate-token" data-tab-id="<?php echo esc_attr($tab_id); ?>">
                    <?php echo esc_html__('Validate license', 'barbas-update'); ?>
                </button>
                <span class="spinner barbas-update-spinner" aria-hidden="true"></span>
            </div>
        </div>
        <?php
        ob_start();
        call_user_func($tab['render'], $tab_id, $tab);
        $extra = trim(ob_get_clean());
        if ($extra !== '') {
            echo '<div class="barbas-update-extra">' . $extra . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    </section>
    <?php
}

/** @deprecated Use barbas_update_render_tab_panel() */
function barbas_update_render_tab_token_form($tab_id, $tab, $post_status) {
    unset($post_status);
    barbas_update_render_tab_panel($tab_id, $tab, true);
}

function barbas_update_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $tabs = barbas_update_get_tabs();
    $tab_count = count($tabs);
    $nav_mode = barbas_update_get_navigation_mode($tab_count);
    $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    if ($active_tab === '' || !isset($tabs[$active_tab])) {
        $active_tab = $tabs ? (string) array_key_first($tabs) : '';
    }

    ?>
    <div class="wrap barbas-update-wrap">
        <div class="barbas-update-app" data-nav-mode="<?php echo esc_attr($nav_mode); ?>">
            <header class="barbas-update-header">
                <div class="barbas-update-header__top">
                    <div class="barbas-update-header__copy">
                        <h1><?php echo esc_html__('Barbas Update', 'barbas-update'); ?></h1>
                        <p class="barbas-update-lead">
                            <?php
                            if (isset($tabs['suite']) && count($tabs) === 1) {
                                echo esc_html__('A suite license covers all active Barbas plugins on this site.', 'barbas-update');
                            } elseif ($nav_mode === 'picker') {
                                echo esc_html__('Manage update licenses for your Barbas plugins. Search and select a plugin below.', 'barbas-update');
                            } else {
                                echo esc_html__('Manage update licenses for your Barbas plugins. Use the suite tab for one key, or configure each plugin.', 'barbas-update');
                            }
                            ?>
                        </p>
                    </div>
                    <?php if (!empty($tabs)) : ?>
                        <div class="barbas-update-header__actions">
                            <button type="button" class="button barbas-update-force-check">
                                <?php echo esc_html__('Check for updates', 'barbas-update'); ?>
                            </button>
                            <span class="spinner barbas-update-force-check-spinner" aria-hidden="true"></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="barbas-update-force-check-feedback" data-role="force-check-feedback" aria-live="polite"></div>
            </header>

            <?php if (empty($tabs)) : ?>
                <div class="barbas-update-alert barbas-update-alert--info">
                    <?php echo esc_html__('No Barbas plugin with a license panel is active.', 'barbas-update'); ?>
                </div>
            <?php else : ?>
                <?php if ($nav_mode === 'tabs') : ?>
                    <nav class="barbas-update-tabs" role="tablist" aria-label="<?php echo esc_attr__('Barbas plugins', 'barbas-update'); ?>">
                        <?php foreach ($tabs as $id => $tab) : ?>
                            <button
                                type="button"
                                class="barbas-update-tab<?php echo $id === $active_tab ? ' is-active' : ''; ?>"
                                id="barbas-update-tab-<?php echo esc_attr($id); ?>"
                                role="tab"
                                aria-selected="<?php echo $id === $active_tab ? 'true' : 'false'; ?>"
                                aria-controls="barbas-update-panel-<?php echo esc_attr($id); ?>"
                                data-tab-id="<?php echo esc_attr($id); ?>"
                            >
                                <?php echo esc_html($tab['label']); ?>
                            </button>
                        <?php endforeach; ?>
                    </nav>
                <?php elseif ($nav_mode === 'picker') : ?>
                    <div class="barbas-update-nav barbas-update-nav--picker">
                        <label class="barbas-update-label barbas-update-picker-label" for="barbas-update-picker-search">
                            <?php echo esc_html__('Plugins', 'barbas-update'); ?>
                        </label>
                        <input
                            type="search"
                            id="barbas-update-picker-search"
                            class="barbas-update-input barbas-update-picker-search"
                            placeholder="<?php echo esc_attr__('Search plugins…', 'barbas-update'); ?>"
                            autocomplete="off"
                        />
                        <p class="barbas-update-picker-meta" data-role="picker-meta">
                            <?php
                            printf(
                                /* translators: 1: visible count, 2: total count */
                                esc_html__('Showing %1$d of %2$d plugins', 'barbas-update'),
                                $tab_count,
                                $tab_count
                            );
                            ?>
                        </p>
                        <ul class="barbas-update-picker-list" role="listbox" aria-label="<?php echo esc_attr__('Barbas plugins', 'barbas-update'); ?>">
                            <?php foreach ($tabs as $id => $tab) : ?>
                                <li class="barbas-update-picker-list__item">
                                    <button
                                        type="button"
                                        class="barbas-update-picker-item<?php echo $id === $active_tab ? ' is-active' : ''; ?>"
                                        id="barbas-update-tab-<?php echo esc_attr($id); ?>"
                                        role="option"
                                        aria-selected="<?php echo $id === $active_tab ? 'true' : 'false'; ?>"
                                        aria-controls="barbas-update-panel-<?php echo esc_attr($id); ?>"
                                        data-tab-id="<?php echo esc_attr($id); ?>"
                                    >
                                        <?php echo esc_html($tab['label']); ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="barbas-update-picker-empty" data-role="picker-empty" hidden>
                            <?php echo esc_html__('No plugins match your search.', 'barbas-update'); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="barbas-update-panels<?php
                    if ($nav_mode === 'tabs' && $active_tab !== '' && !empty($tabs)) {
                        $tab_ids = array_keys($tabs);
                        $first_id = (string) $tab_ids[0];
                        $last_id = (string) $tab_ids[ count($tab_ids) - 1 ];
                        echo $active_tab === $first_id ? ' is-edge-first' : '';
                        echo $active_tab === $last_id ? ' is-edge-last' : '';
                    }
                ?>" data-active-tab="<?php echo esc_attr($active_tab); ?>">
                    <?php
                    if ($active_tab !== '' && isset($tabs[ $active_tab ])) {
                        barbas_update_render_tab_panel($active_tab, $tabs[ $active_tab ], true);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (!empty(barbas_update_get_plugin_tabs())) : ?>
                <?php barbas_update_render_plugins_status_card(); ?>
            <?php endif; ?>

            <?php barbas_update_render_brand_footer(); ?>

            <div class="barbas-update-toast-stack" data-role="toast-stack" aria-live="polite"></div>
        </div>
    </div>
    <?php
}

function barbas_update_redirect_legacy_urls() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    global $pagenow;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ($pagenow === 'options-general.php' && isset($_GET['page'])) {
        $page = sanitize_key(wp_unslash($_GET['page']));
        $legacy_pages = array(
            'barbas-uncode-core-fix' => 'uncode-core-fix',
            'barbas-image-upload-size-limit' => 'image-upload-size-limit',
        );
        if (isset($legacy_pages[$page])) {
            wp_safe_redirect(barbas_update_settings_url($legacy_pages[$page]));
            exit;
        }
    }

    if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === BARBAS_UPDATE_MENU_SLUG) {
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        wp_safe_redirect(barbas_update_settings_url($tab));
        exit;
    }
}

/**
 * Registry of Barbas plugins that need a correct "View details" modal.
 *
 * @return array<string, array{file:string,slug:string,homepage:string}>
 */
function barbas_update_plugin_details_registry() {
    if (!isset($GLOBALS['barbas_update_plugin_details']) || !is_array($GLOBALS['barbas_update_plugin_details'])) {
        $GLOBALS['barbas_update_plugin_details'] = array();
    }
    return $GLOBALS['barbas_update_plugin_details'];
}

/**
 * Register one plugin for details-link + plugins_api local fallback.
 *
 * @param string $plugin_file Absolute main plugin file.
 * @param string $slug        PUC / modal slug (e.g. barbas-image-upload-size-limit).
 * @param string $homepage    Prefer GitHub repo URL.
 */
function barbas_update_register_plugin_details_guard($plugin_file, $slug, $homepage = '') {
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
    if (!isset($GLOBALS['barbas_update_plugin_details']) || !is_array($GLOBALS['barbas_update_plugin_details'])) {
        $GLOBALS['barbas_update_plugin_details'] = array();
    }
    $GLOBALS['barbas_update_plugin_details'][ $slug ] = array(
        'file'     => $plugin_file,
        'slug'     => $slug,
        'homepage' => $homepage,
    );
}

/**
 * Pull every hub tab into the details registry (covers plugins whose bootstrap is older).
 */
function barbas_update_sync_plugin_details_from_tabs() {
    if (!function_exists('barbas_update_get_tabs')) {
        $tabs = apply_filters('barbas_update_tabs', array());
    } else {
        $tabs = barbas_update_get_tabs();
    }
    if (!is_array($tabs)) {
        return;
    }
    foreach ($tabs as $tab) {
        if (!is_array($tab) || empty($tab['plugin']) || !is_string($tab['plugin'])) {
            continue;
        }
        $basename = $tab['plugin'];
        $abs = trailingslashit(WP_PLUGIN_DIR) . $basename;
        if (!is_readable($abs)) {
            continue;
        }
        $slug = '';
        $homepage = '';
        if (!empty($tab['github_repo']) && is_string($tab['github_repo'])) {
            $parts = explode('/', $tab['github_repo']);
            $slug = sanitize_key(end($parts));
            $homepage = 'https://github.com/' . $tab['github_repo'];
        }
        if ($slug === '') {
            $slug = function_exists('barbas_update_get_tab_plugin_slug')
                ? barbas_update_get_tab_plugin_slug($tab)
                : sanitize_key(dirname($basename));
        }
        if ($slug === '' || $slug === '.') {
            $slug = sanitize_key(basename($basename, '.php'));
        }
        if ($slug === '') {
            continue;
        }
        barbas_update_register_plugin_details_guard($abs, $slug, $homepage);
    }
}

/**
 * Build a plugins_api plugin_information object from local headers/readme.
 *
 * @param array{file:string,slug:string,homepage:string} $entry Registry entry.
 * @return object|null
 */
function barbas_update_build_local_plugin_information(array $entry) {
    $file = isset($entry['file']) ? (string) $entry['file'] : '';
    $slug = isset($entry['slug']) ? (string) $entry['slug'] : '';
    $homepage = isset($entry['homepage']) ? (string) $entry['homepage'] : '';
    if ($file === '' || $slug === '' || !is_readable($file)) {
        return null;
    }
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $data = get_plugin_data($file, false, false);
    if (!is_array($data) || empty($data['Name'])) {
        return null;
    }

    $description = isset($data['Description']) ? (string) $data['Description'] : '';
    $sections = array(
        'description' => $description !== '' ? wpautop($description) : '<p></p>',
    );

    $readme = plugin_dir_path($file) . 'readme.txt';
    if (is_readable($readme)) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $raw = file_get_contents($readme);
        if (is_string($raw) && $raw !== '') {
            if (preg_match('/^==\s*Changelog\s*==\s*(.+?)(?=^==\s|\z)/ms', $raw, $m)) {
                $sections['changelog'] = barbas_update_readme_section_to_html($m[1]);
            }
            if (preg_match('/^==\s*Description\s*==\s*(.+?)(?=^==\s|\z)/ms', $raw, $m)) {
                $sections['description'] = barbas_update_readme_section_to_html($m[1]);
            }
            if (preg_match('/^==\s*Installation\s*==\s*(.+?)(?=^==\s|\z)/ms', $raw, $m)) {
                $sections['installation'] = barbas_update_readme_section_to_html($m[1]);
            }
            if (preg_match('/^==\s*Frequently Asked Questions\s*==\s*(.+?)(?=^==\s|\z)/ms', $raw, $m)) {
                $sections['faq'] = barbas_update_readme_section_to_html($m[1]);
            }
        }
    }

    $info = (object) array(
        'name'          => $data['Name'],
        'slug'          => $slug,
        'version'       => isset($data['Version']) ? (string) $data['Version'] : '',
        'author'        => isset($data['Author']) ? (string) $data['Author'] : '',
        'author_profile'=> isset($data['AuthorURI']) ? (string) $data['AuthorURI'] : '',
        'homepage'      => $homepage !== '' ? $homepage : (isset($data['PluginURI']) ? (string) $data['PluginURI'] : ''),
        'requires'      => '',
        'tested'        => '',
        'requires_php'  => '',
        'sections'      => $sections,
        'download_link' => '',
        'banners'       => array(),
        'icons'         => array(),
        'external'      => true,
    );

    return $info;
}

/**
 * Minimal readme.txt section → HTML (headings + paragraphs + lists).
 *
 * @param string $text Raw section body.
 * @return string
 */
function barbas_update_readme_section_to_html($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return '<p></p>';
    }
    $text = preg_replace('/^=\s*(.+?)\s*=\s*$/m', '<h4>$1</h4>', $text);
    $text = preg_replace('/^\*\s+(.+)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/(?:<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $text);
    $parts = preg_split('/\n{2,}/', $text);
    $html = '';
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^<(h4|ul|li)\b/i', $part)) {
            $html .= $part;
            continue;
        }
        $html .= '<p>' . nl2br(esc_html($part)) . '</p>';
    }
    return $html !== '' ? $html : '<p></p>';
}

/**
 * plugins_api: after PUC (20), serve local info so private repos never hit "Plugin not found".
 *
 * @param false|object|WP_Error $result Result so far.
 * @param string                $action Action.
 * @param object                $args   Args.
 * @return false|object|WP_Error
 */
function barbas_update_filter_plugins_api_local_details($result, $action, $args) {
    if ($action !== 'plugin_information' || !is_object($args) || empty($args->slug)) {
        return $result;
    }
    if ($result !== false && !is_wp_error($result) && is_object($result)) {
        return $result;
    }
    $slug = sanitize_key((string) $args->slug);
    $registry = barbas_update_plugin_details_registry();
    if ($slug === '' || !isset($registry[ $slug ])) {
        return $result;
    }
    $local = barbas_update_build_local_plugin_information($registry[ $slug ]);
    return $local ? $local : $result;
}

/**
 * plugins_api_result: replace WP.org "Plugin not found" for our slugs.
 *
 * @param object|WP_Error $result Result.
 * @param string          $action Action.
 * @param object          $args   Args.
 * @return object|WP_Error
 */
function barbas_update_filter_plugins_api_result_local_details($result, $action, $args) {
    if ($action !== 'plugin_information' || !is_object($args) || empty($args->slug)) {
        return $result;
    }
    $slug = sanitize_key((string) $args->slug);
    $registry = barbas_update_plugin_details_registry();
    if ($slug === '' || !isset($registry[ $slug ])) {
        return $result;
    }
    $needs_local = is_wp_error($result)
        || !is_object($result)
        || empty($result->sections);
    if (!$needs_local) {
        return $result;
    }
    $local = barbas_update_build_local_plugin_information($registry[ $slug ]);
    return $local ? $local : $result;
}

/**
 * Force correct slug + PluginURI (Uncode/Undsgn often hijack these).
 *
 * @param array<string, array<string, mixed>> $plugins Plugins list.
 * @return array<string, array<string, mixed>>
 */
function barbas_update_filter_all_plugins_details_guard($plugins) {
    if (!is_array($plugins)) {
        return $plugins;
    }
    foreach (barbas_update_plugin_details_registry() as $entry) {
        $key = plugin_basename($entry['file']);
        if (!isset($plugins[ $key ]) || !is_array($plugins[ $key ])) {
            continue;
        }
        $plugins[ $key ]['slug'] = $entry['slug'];
        $plugins[ $key ]['PluginURI'] = $entry['homepage'];
    }
    return $plugins;
}

/**
 * Keep update transient slugs aligned with our GitHub repos.
 *
 * @param object|mixed $transient Transient.
 * @return object|mixed
 */
function barbas_update_filter_update_plugins_details_guard($transient) {
    if (!is_object($transient) || empty($transient->response) || !is_array($transient->response)) {
        return $transient;
    }
    foreach (barbas_update_plugin_details_registry() as $entry) {
        $key = plugin_basename($entry['file']);
        if (isset($transient->response[ $key ]) && is_object($transient->response[ $key ])) {
            $transient->response[ $key ]->slug = $entry['slug'];
            $transient->response[ $key ]->url  = $entry['homepage'];
        }
    }
    return $transient;
}

/**
 * Rebuild "View details" meta links; strip Undsgn/Uncode hijacks.
 *
 * @param string[]             $plugin_meta Meta links.
 * @param string               $plugin_file Plugin basename.
 * @param array<string, mixed> $plugin_data Header data.
 * @return string[]
 */
function barbas_update_filter_plugin_row_meta_details_guard($plugin_meta, $plugin_file, $plugin_data = array()) {
    if (!is_array($plugin_meta)) {
        return $plugin_meta;
    }
    $entry = null;
    foreach (barbas_update_plugin_details_registry() as $candidate) {
        if (plugin_basename($candidate['file']) === $plugin_file) {
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
            if (stripos($link, 'plugin=' . $slug) !== false || stripos($link, 'plugin=' . rawurlencode($slug)) !== false) {
                $has_our_details = true;
                $cleaned[] = $link;
            }
            continue;
        }
        $cleaned[] = $link;
    }

    if (!$has_our_details) {
        $name = !empty($plugin_data['Name']) ? (string) $plugin_data['Name'] : $slug;
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
                /* translators: %s: Plugin name. */
                esc_attr(sprintf(__('More information about %s'), $name)),
                esc_attr($name),
                __('View details')
            )
        );
    }

    return $cleaned;
}

/**
 * Install details guards once the newest hub is loaded.
 */
function barbas_update_boot_plugin_details_guards() {
    static $booted = false;
    if ($booted) {
        return;
    }
    $booted = true;

    barbas_update_sync_plugin_details_from_tabs();

    add_filter('plugins_api', 'barbas_update_filter_plugins_api_local_details', 25, 3);
    add_filter('plugins_api_result', 'barbas_update_filter_plugins_api_result_local_details', 5, 3);
    add_filter('all_plugins', 'barbas_update_filter_all_plugins_details_guard', PHP_INT_MAX);
    add_filter('site_transient_update_plugins', 'barbas_update_filter_update_plugins_details_guard', PHP_INT_MAX);
    add_filter('plugin_row_meta', 'barbas_update_filter_plugin_row_meta_details_guard', PHP_INT_MAX, 3);

    add_action('load-plugins.php', 'barbas_update_reassert_plugin_details_php_guards', 99999);
    add_action('admin_head-plugins.php', 'barbas_update_reassert_plugin_details_php_guards', 99999);
    add_action('load-plugins.php', 'barbas_update_start_plugins_page_output_guard', 0);
    // Print as early as possible: Uncode/Undsgn rewrites "View details" for any row whose name contains "Uncode".
    add_action('admin_head-plugins.php', 'barbas_update_print_plugin_details_guard_js', 1);
    add_action('admin_print_scripts-plugins.php', 'barbas_update_print_plugin_details_guard_js', 1);
    add_action('admin_print_footer_scripts-plugins.php', 'barbas_update_print_plugin_details_guard_js', 1);
    add_action('admin_footer-plugins.php', 'barbas_update_print_plugin_details_guard_js', 1);
    add_action('admin_footer-plugins.php', 'barbas_update_print_plugin_details_guard_js', 99999);
}

/**
 * Re-attach PHP meta/all_plugins guards after late Uncode hooks register.
 */
function barbas_update_reassert_plugin_details_php_guards() {
    barbas_update_sync_plugin_details_from_tabs();
    remove_filter('all_plugins', 'barbas_update_filter_all_plugins_details_guard', PHP_INT_MAX);
    remove_filter('plugin_row_meta', 'barbas_update_filter_plugin_row_meta_details_guard', PHP_INT_MAX);
    add_filter('all_plugins', 'barbas_update_filter_all_plugins_details_guard', PHP_INT_MAX);
    add_filter('plugin_row_meta', 'barbas_update_filter_plugin_row_meta_details_guard', PHP_INT_MAX, 3);
    barbas_update_remove_uncode_plugin_row_meta_hijacks();
}

/**
 * Remove known Undsgn/Uncode plugin_row_meta hijacks (name/file match).
 */
function barbas_update_remove_uncode_plugin_row_meta_hijacks() {
    global $wp_filter;
    if (!isset($wp_filter['plugin_row_meta']) || !is_object($wp_filter['plugin_row_meta'])) {
        return;
    }
    $hook = $wp_filter['plugin_row_meta'];
    if (empty($hook->callbacks) || !is_array($hook->callbacks)) {
        return;
    }
    foreach ($hook->callbacks as $priority => $callbacks) {
        if (!is_array($callbacks)) {
            continue;
        }
        foreach ($callbacks as $id => $cb) {
            if (!is_array($cb) || !isset($cb['function'])) {
                continue;
            }
            $label = barbas_update_callable_debug_label($cb['function']);
            if ($label === '') {
                continue;
            }
            if (preg_match('/uncode|undsgn/i', $label) && !preg_match('/barbas_update_/i', $label)) {
                remove_filter('plugin_row_meta', $cb['function'], (int) $priority);
            }
        }
    }
}

/**
 * @param mixed $fn Callable.
 * @return string
 */
function barbas_update_callable_debug_label($fn) {
    if (is_string($fn)) {
        return $fn;
    }
    if (is_array($fn) && isset($fn[0], $fn[1])) {
        $class = is_object($fn[0]) ? get_class($fn[0]) : (string) $fn[0];
        return $class . '::' . (string) $fn[1];
    }
    if ($fn instanceof Closure) {
        try {
            $ref = new ReflectionFunction($fn);
            $file = (string) $ref->getFileName();
            return $file !== '' ? $file : 'Closure';
        } catch (Exception $e) {
            return 'Closure';
        }
    }
    return '';
}

/**
 * Buffer plugins.php HTML and rewrite Undsgn "View details" on Barbas rows.
 */
function barbas_update_start_plugins_page_output_guard() {
    static $started = false;
    if ($started) {
        return;
    }
    $started = true;
    ob_start('barbas_update_rewrite_plugins_page_details_html');
}

/**
 * @param string $html Plugins page HTML.
 * @return string
 */
function barbas_update_rewrite_plugins_page_details_html($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }
    barbas_update_sync_plugin_details_from_tabs();
    $registry = barbas_update_plugin_details_registry();
    if (empty($registry)) {
        return $html;
    }

    foreach ($registry as $entry) {
        if (empty($entry['file']) || empty($entry['slug'])) {
            continue;
        }
        $basename = plugin_basename($entry['file']);
        $slug     = (string) $entry['slug'];
        $details  = admin_url(
            'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode($slug) .
            '&TB_iframe=true&width=600&height=550'
        );
        $details_attr = esc_url($details);

        $pattern = '/(<tr\b[^>]*\bdata-plugin="' . preg_quote($basename, '/') . '"[^>]*>)(.*?)(<\/tr>)/is';
        $html    = preg_replace_callback(
            $pattern,
            static function ($m) use ($details_attr) {
                $row = $m[2];
                $row = preg_replace(
                    '/href=(["\'])https?:\/\/(?:support\.)?undsgn\.com[^"\']*\1/i',
                    'href="' . $details_attr . '"',
                    $row
                );
                $row = preg_replace(
                    '/href=(["\'])https?:\/\/(?:www\.)?(?:theme\.)?uncode\.net[^"\']*\1/i',
                    'href="' . $details_attr . '"',
                    $row
                );
                $row = preg_replace(
                    '/href=(["\'])[^"\']*213454129[^"\']*\1/i',
                    'href="' . $details_attr . '"',
                    $row
                );
                $row = preg_replace(
                    '/<a\b[^>]*>\s*(?:View details|Ver detalhes)\s*<\/a>/iu',
                    '<a href="' . $details_attr . '" class="thickbox open-plugin-details-modal" data-barbas-details="1">' .
                    esc_html__('View details') . '</a>',
                    $row
                );
                return $m[1] . $row . $m[3];
            },
            $html,
            1
        );
    }

    return $html;
}

/**
 * Client-side guard: Uncode rewrites "Ver detalhes" to support.undsgn.com after load.
 */
function barbas_update_print_plugin_details_guard_js() {
    if (!is_admin() || !current_user_can('activate_plugins')) {
        return;
    }
    static $printed = false;
    if ($printed) {
        return;
    }

    barbas_update_sync_plugin_details_from_tabs();
    $registry = barbas_update_plugin_details_registry();
    if (empty($registry)) {
        return;
    }
    $printed = true;

    $by_plugin = array();
    $by_slug   = array();
    foreach ($registry as $entry) {
        if (empty($entry['file']) || empty($entry['slug'])) {
            continue;
        }
        $basename = plugin_basename($entry['file']);
        $slug     = (string) $entry['slug'];
        $title    = $slug;
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (is_readable($entry['file'])) {
            $pdata = get_plugin_data($entry['file'], false, false);
            if (is_array($pdata) && !empty($pdata['Name'])) {
                $title = (string) $pdata['Name'];
            }
        }
        $payload  = array(
            'slug'       => $slug,
            'plugin'     => $basename,
            'title'      => $title,
            'homepage'   => isset($entry['homepage']) ? (string) $entry['homepage'] : '',
            'detailsUrl' => admin_url(
                'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode($slug) .
                '&TB_iframe=true&width=600&height=550'
            ),
            'label'      => __('View details'),
        );
        $by_plugin[ $basename ] = $payload;
        $by_slug[ $slug ]       = $payload;
    }
    if (empty($by_plugin)) {
        return;
    }

    $json_plugin = wp_json_encode($by_plugin);
    $json_slug   = wp_json_encode($by_slug);
    if (!is_string($json_plugin) || $json_plugin === '' || !is_string($json_slug) || $json_slug === '') {
        return;
    }
    ?>
<script id="barbas-update-plugin-details-guard">
(function () {
  if (window.__barbasDetailsGuardBooted) return;
  window.__barbasDetailsGuardBooted = true;

  var byPlugin = <?php echo $json_plugin; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
  var bySlug = <?php echo $json_slug; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
  var hijack = /undsgn\.com|support\.undsgn|theme\.uncode|uncode\.net|themeforest\.net\/item\/uncode|213454129/i;
  var detailsText = /view details|ver detalhes|voir les d[eé]tails|detalles|visualizza dettagli|details anzeigen/i;

  function confForRow(tr) {
    if (!tr) return null;
    var plugin = tr.getAttribute('data-plugin') || '';
    if (plugin && byPlugin[plugin]) return byPlugin[plugin];
    var slug = tr.getAttribute('data-slug') || '';
    if (slug && bySlug[slug]) return bySlug[slug];
    // Undsgn sometimes rewrites data-slug; match Barbas folder names.
    if (plugin) {
      for (var k in byPlugin) {
        if (!Object.prototype.hasOwnProperty.call(byPlugin, k)) continue;
        if (plugin === k || plugin.indexOf(byPlugin[k].slug + '/') === 0) return byPlugin[k];
      }
    }
    return null;
  }

  function confFor(el) {
    var tr = el && el.closest ? el.closest('tr[data-plugin]') : null;
    return confForRow(tr);
  }

  function isDetailsAnchor(a, conf) {
    if (!a || !conf) return false;
    var href = a.getAttribute('href') || '';
    var text = (a.textContent || '').trim();
    return a.classList.contains('open-plugin-details-modal')
      || detailsText.test(text)
      || /plugin-information/.test(href)
      || hijack.test(href)
      || a.getAttribute('data-barbas-details') === '1';
  }

  function needsFix(a, conf) {
    if (!a || !conf) return false;
    var href = a.getAttribute('href') || '';
    if (hijack.test(href)) return true;
    if (!isDetailsAnchor(a, conf)) return false;
    return href.indexOf('plugin=' + conf.slug) === -1;
  }

  function freshAnchor(conf) {
    var a = document.createElement('a');
    a.href = conf.detailsUrl;
    a.setAttribute('href', conf.detailsUrl);
    a.className = 'thickbox open-plugin-details-modal';
    a.setAttribute('data-barbas-details', '1');
    if (conf.title) a.setAttribute('data-title', conf.title);
    a.setAttribute('aria-label', conf.label || 'View details');
    a.textContent = conf.label || 'View details';
    return a;
  }

  function applyOrReplace(a, conf) {
    if (!a || !conf) return a;
    var href = a.getAttribute('href') || '';
    var alreadyGood = a.getAttribute('data-barbas-details') === '1'
      && href.indexOf('plugin=' + conf.slug) !== -1
      && !hijack.test(href);
    if (alreadyGood) {
      if (conf.title) a.setAttribute('data-title', conf.title);
      return a;
    }

    // Replace node to drop Undsgn jQuery/native listeners bound to the old <a>.
    if (needsFix(a, conf) || hijack.test(href)) {
      var neu = freshAnchor(conf);
      if (a.parentNode) {
        a.parentNode.replaceChild(neu, a);
        return neu;
      }
    }
    a.setAttribute('href', conf.detailsUrl);
    try { a.href = conf.detailsUrl; } catch (e) {}
    a.classList.add('thickbox', 'open-plugin-details-modal');
    a.setAttribute('data-barbas-details', '1');
    if (conf.title) a.setAttribute('data-title', conf.title);
    a.removeAttribute('target');
    a.removeAttribute('rel');
    if (detailsText.test((a.textContent || '').trim())) {
      a.textContent = conf.label || 'View details';
    }
    return a;
  }

  function fixRow(tr, conf) {
    if (!tr || !conf || !conf.detailsUrl) return;
    var links = tr.querySelectorAll('a');
    var hasGood = false;
    for (var i = 0; i < links.length; i++) {
      var a = links[i];
      if (!isDetailsAnchor(a, conf) && !hijack.test(a.getAttribute('href') || '')) continue;
      var fixed = applyOrReplace(a, conf);
      if (fixed && (fixed.getAttribute('href') || '').indexOf('plugin=' + conf.slug) !== -1) hasGood = true;
    }
    if (!hasGood) {
      var cell = tr.querySelector('.plugin-version-author-uri');
      if (!cell) return;
      cell.appendChild(document.createTextNode(' | '));
      cell.appendChild(freshAnchor(conf));
    }
  }

  function fixAll() {
    var rows = document.querySelectorAll('tr[data-plugin]');
    for (var i = 0; i < rows.length; i++) {
      var conf = confForRow(rows[i]);
      if (conf) fixRow(rows[i], conf);
    }
  }

  function openDetails(conf) {
    if (!conf || !conf.detailsUrl) return;
    if (typeof window.tb_show === 'function') {
      // Caption must be the plugin name — using "View details"/"Ver detalhes" as caption
      // puts that string in the thickbox title bar.
      window.tb_show(conf.title || conf.slug, conf.detailsUrl, false);
      return;
    }
    window.location.href = conf.detailsUrl;
  }

  function onActivate(e) {
    var a = e.target && e.target.closest ? e.target.closest('a') : null;
    if (!a) return;
    var conf = confFor(a);
    if (!conf || !isDetailsAnchor(a, conf)) return;
    // Always win over Undsgn capture/bubble handlers.
    e.preventDefault();
    e.stopPropagation();
    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    applyOrReplace(a, conf);
    openDetails(conf);
  }

  document.addEventListener('click', onActivate, true);
  document.addEventListener('mousedown', onActivate, true);
  document.addEventListener('pointerdown', onActivate, true);
  document.addEventListener('auxclick', onActivate, true);

  function boot() {
    fixAll();
    var list = document.getElementById('the-list') || document.body;
    if (list && typeof MutationObserver !== 'undefined') {
      var scheduled = false;
      var obs = new MutationObserver(function () {
        if (scheduled) return;
        scheduled = true;
        setTimeout(function () { scheduled = false; fixAll(); }, 16);
      });
      obs.observe(list, { subtree: true, childList: true, attributes: true, attributeFilter: ['href', 'class', 'data-slug', 'data-plugin'] });
    }
    // Keep fighting Undsgn for the whole plugins.php lifetime (name match "Uncode").
    setInterval(fixAll, 400);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
</script>
    <?php
}


if (did_action('plugins_loaded')) {
    barbas_update_boot_plugin_details_guards();
} else {
    add_action('plugins_loaded', 'barbas_update_boot_plugin_details_guards', 20);
}
add_action('admin_init', 'barbas_update_sync_plugin_details_from_tabs', 1);
