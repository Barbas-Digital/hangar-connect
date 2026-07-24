<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Migrate legacy folder/slug barbas-connect → hangar-connect after updates.
 *
 * WP keeps the old directory when the zip root slug changes, so the main file
 * path in active_plugins no longer exists ("The plugin does not exist").
 */

/**
 * @return string Absolute path to WP plugins directory (no trailing slash).
 */
function hangar_connect_plugins_dir() {
    return untrailingslashit(WP_PLUGIN_DIR);
}

/**
 * Rewrite active_plugins / recently_activated basenames.
 *
 * @param string $from Old plugin basename (dir/file.php).
 * @param string $to   New plugin basename.
 */
function hangar_connect_rewrite_plugin_basename($from, $to) {
    $from = (string) $from;
    $to = (string) $to;
    if ($from === '' || $to === '' || $from === $to) {
        return;
    }

    $keys = array('active_plugins', 'recently_activated');
    foreach ($keys as $option) {
        $list = get_option($option, array());
        if (!is_array($list) || empty($list)) {
            continue;
        }
        $changed = false;
        // active_plugins is a list of basenames; recently_activated is basename => time.
        if (array_values($list) === $list) {
            foreach ($list as $i => $basename) {
                if ((string) $basename === $from) {
                    $list[ $i ] = $to;
                    $changed = true;
                }
            }
            $list = array_values(array_unique($list));
        } else {
            if (isset($list[ $from ])) {
                $list[ $to ] = $list[ $from ];
                unset($list[ $from ]);
                $changed = true;
            }
        }
        if ($changed) {
            update_option($option, $list);
        }
    }
}

/**
 * Move legacy option name if present.
 */
function hangar_connect_migrate_options() {
    if (false !== get_option('hangar_connect_connections', false)) {
        return;
    }
    $legacy = get_option('barbas_connect_connections', null);
    if ($legacy === null) {
        return;
    }
    add_option('hangar_connect_connections', $legacy, '', false);
    delete_option('barbas_connect_connections');
}

/**
 * If files live under barbas-connect/ but main file is hangar-connect.php,
 * rename the directory and fix active_plugins.
 *
 * @return bool True when a filesystem rename ran.
 */
function hangar_connect_migrate_legacy_directory() {
    $plugins = hangar_connect_plugins_dir();
    $legacy_dir = $plugins . '/barbas-connect';
    $target_dir = $plugins . '/hangar-connect';

    if (!is_dir($legacy_dir)) {
        return false;
    }

    // Already have a proper hangar-connect install — drop leftover legacy folder.
    if (is_dir($target_dir) && is_readable($target_dir . '/hangar-connect.php')) {
        // Do not recursively delete here if both exist and look valid; leave for admin.
        if (is_readable($legacy_dir . '/hangar-connect.php') || is_readable($legacy_dir . '/barbas-connect.php')) {
            hangar_connect_rewrite_plugin_basename(
                'barbas-connect/barbas-connect.php',
                'hangar-connect/hangar-connect.php'
            );
            hangar_connect_rewrite_plugin_basename(
                'barbas-connect/hangar-connect.php',
                'hangar-connect/hangar-connect.php'
            );
        }
        return false;
    }

    $legacy_main_hangar = $legacy_dir . '/hangar-connect.php';
    $legacy_main_old = $legacy_dir . '/barbas-connect.php';

    if (!is_readable($legacy_main_hangar) && !is_readable($legacy_main_old)) {
        return false;
    }

    // Prefer hangar-connect.php inside legacy folder (post-update zip extract case).
    if (!is_readable($legacy_main_hangar) && is_readable($legacy_main_old)) {
        // Still on pre-rebrand files — cannot rename slug until hangar zip is installed.
        return false;
    }

    if (!@rename($legacy_dir, $target_dir)) {
        return false;
    }

    hangar_connect_rewrite_plugin_basename(
        'barbas-connect/barbas-connect.php',
        'hangar-connect/hangar-connect.php'
    );
    hangar_connect_rewrite_plugin_basename(
        'barbas-connect/hangar-connect.php',
        'hangar-connect/hangar-connect.php'
    );

    return true;
}

/**
 * After Plugin Upgrader extracts hangar-connect into barbas-connect destination,
 * normalize active_plugins and attempt directory rename.
 *
 * @param WP_Upgrader $upgrader Upgrader instance.
 * @param array       $options  Result options.
 */
function hangar_connect_upgrader_process_complete($upgrader, $options) {
    unset($upgrader);
    if (!is_array($options) || empty($options['type']) || $options['type'] !== 'plugin') {
        return;
    }

    hangar_connect_migrate_options();
    hangar_connect_migrate_legacy_directory();
}
add_action('upgrader_process_complete', 'hangar_connect_upgrader_process_complete', 20, 2);

/**
 * Ensure extracted zip folder is hangar-connect when updating Connect.
 *
 * @param string      $source        Source directory.
 * @param string      $remote_source Remote source root.
 * @param WP_Upgrader $upgrader      Upgrader.
 * @param array       $hook_extra    Extra data.
 * @return string|WP_Error
 */
function hangar_connect_upgrader_source_selection($source, $remote_source, $upgrader, $hook_extra = array()) {
    unset($upgrader);
    if (!is_string($source) || $source === '' || !is_dir($source)) {
        return $source;
    }

    $plugin = '';
    if (is_array($hook_extra) && !empty($hook_extra['plugin'])) {
        $plugin = (string) $hook_extra['plugin'];
    }
    $is_connect = (
        strpos($plugin, 'barbas-connect/') === 0
        || strpos($plugin, 'hangar-connect/') === 0
        || (is_string($source) && (
            strpos($source, '/barbas-connect') !== false
            || strpos($source, '/hangar-connect') !== false
            || strpos($source, '\\barbas-connect') !== false
            || strpos($source, '\\hangar-connect') !== false
        ))
    );
    if (!$is_connect) {
        return $source;
    }

    $desired = 'hangar-connect';
    $current = basename(untrailingslashit($source));
    if ($current === $desired) {
        return $source;
    }

    if ($current !== 'barbas-connect' && $current !== 'hangar-connect') {
        // Only rewrite known Connect package roots.
        if (!is_readable(trailingslashit($source) . 'hangar-connect.php')
            && !is_readable(trailingslashit($source) . 'barbas-connect.php')
        ) {
            return $source;
        }
    }

    $new_source = trailingslashit($remote_source) . $desired;
    if ($new_source === $source) {
        return $source;
    }
    if (file_exists($new_source)) {
        return $source;
    }
    if (@rename(untrailingslashit($source), $new_source)) {
        return trailingslashit($new_source);
    }

    return $source;
}
add_filter('upgrader_source_selection', 'hangar_connect_upgrader_source_selection', 10, 4);

/**
 * Admin recovery: fix broken basename / leftover folder.
 */
function hangar_connect_admin_migrate_boot() {
    if (!is_admin()) {
        return;
    }
    hangar_connect_migrate_options();
    hangar_connect_migrate_legacy_directory();

    // If active list still points at missing legacy main file, rewrite to hangar path.
    $active = get_option('active_plugins', array());
    if (!is_array($active)) {
        return;
    }
    $legacy = 'barbas-connect/barbas-connect.php';
    $legacy_alt = 'barbas-connect/hangar-connect.php';
    $canonical = 'hangar-connect/hangar-connect.php';
    $plugins = hangar_connect_plugins_dir();
    if (in_array($legacy, $active, true) && !is_readable($plugins . '/' . $legacy)) {
        hangar_connect_rewrite_plugin_basename($legacy, $canonical);
    }
    if (in_array($legacy_alt, $active, true) && is_readable($plugins . '/hangar-connect/hangar-connect.php')) {
        hangar_connect_rewrite_plugin_basename($legacy_alt, $canonical);
    }
}
add_action('admin_init', 'hangar_connect_admin_migrate_boot', 1);
