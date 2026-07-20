<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Leitura/remoção de licenças no wp-config.php (ferramenta apply/remove interna).
 */

if (!function_exists('barbas_update_wpconfig_block_begin')) {
    function barbas_update_wpconfig_block_begin() {
        return '// BEGIN Barbas Update licenses (barbas-wp-config-tools)';
    }
}

if (!function_exists('barbas_update_wpconfig_block_end')) {
    function barbas_update_wpconfig_block_end() {
        return '// END Barbas Update licenses (barbas-wp-config-tools)';
    }
}

if (!function_exists('barbas_update_wpconfig_locate_path')) {
    /** @return string|false */
    function barbas_update_wpconfig_locate_path() {
        $candidates = array(ABSPATH . 'wp-config.php');

        if (@is_dir(ABSPATH . 'wp-content') && @is_file(dirname(ABSPATH) . '/wp-config.php')) {
            $candidates[] = dirname(ABSPATH) . '/wp-config.php';
        }

        foreach ($candidates as $path) {
            if (@is_readable($path) && @is_file($path)) {
                return $path;
            }
        }

        return false;
    }
}

if (!function_exists('barbas_update_suite_constant_names')) {
    /** @return string[] */
    function barbas_update_suite_constant_names() {
        return array(
            'BARBAS_UPDATE_TOKEN_SUITE',
            'BARBAS_UPDATE_TOKEN_GLOBAL', // alias
        );
    }
}

if (!function_exists('barbas_update_constant_name_for_tab')) {
    function barbas_update_constant_name_for_tab($tab_id) {
        $tab_id = sanitize_key($tab_id);
        if ($tab_id === 'suite') {
            return 'BARBAS_UPDATE_TOKEN_SUITE';
        }
        return 'BARBAS_UPDATE_TOKEN_' . strtoupper(str_replace('-', '_', $tab_id));
    }
}

if (!function_exists('barbas_update_wpconfig_constant_names_for_tab')) {
    /** @return string[] */
    function barbas_update_wpconfig_constant_names_for_tab($tab_id) {
        $tab_id = sanitize_key($tab_id);
        if ($tab_id === 'suite') {
            return barbas_update_suite_constant_names();
        }

        $names = array(barbas_update_constant_name_for_tab($tab_id));
        if ($tab_id === 'uncode-core-fix') {
            $names[] = 'BARBAS_UNCODE_CORE_FIX_GITHUB_TOKEN';
        }
        return $names;
    }
}

if (!function_exists('barbas_update_get_suite_constant_token')) {
    function barbas_update_get_suite_constant_token() {
        foreach (barbas_update_suite_constant_names() as $constant) {
            if (defined($constant) && constant($constant)) {
                return (string) constant($constant);
            }
        }
        return '';
    }
}

if (!function_exists('barbas_update_get_suite_token')) {
    /**
     * Suite license: dashboard override for tab `suite`, else SUITE/GLOBAL constants.
     */
    function barbas_update_get_suite_token() {
        if (function_exists('barbas_update_get_db_token_for_plugin')) {
            $db = barbas_update_get_db_token_for_plugin('suite');
            if ($db !== '') {
                return $db;
            }
        }
        return barbas_update_get_suite_constant_token();
    }
}

if (!function_exists('barbas_update_suite_is_active')) {
    function barbas_update_suite_is_active() {
        return barbas_update_get_suite_token() !== '';
    }
}

if (!function_exists('barbas_update_get_db_token_for_plugin')) {
    function barbas_update_get_db_token_for_plugin($tab_id) {
        if (function_exists('barbas_update_maybe_migrate_legacy_tokens')) {
            barbas_update_maybe_migrate_legacy_tokens($tab_id);
        }

        $option_key = barbas_update_token_option_key($tab_id);
        if (function_exists('barbas_update_read_token_option')) {
            return barbas_update_read_token_option($option_key);
        }

        $token = get_option($option_key, '');
        return is_string($token) ? trim($token) : '';
    }
}

if (!function_exists('barbas_update_get_wpconfig_token_for_plugin')) {
    function barbas_update_get_wpconfig_token_for_plugin($tab_id) {
        $tab_id = sanitize_key($tab_id);

        foreach (barbas_update_wpconfig_constant_names_for_tab($tab_id) as $constant) {
            if (defined($constant) && constant($constant)) {
                return (string) constant($constant);
            }
        }

        // Individual plugin falls back to suite/global constant.
        if ($tab_id !== 'suite') {
            $suite = barbas_update_get_suite_constant_token();
            if ($suite !== '') {
                return $suite;
            }
        }

        return '';
    }
}

if (!function_exists('barbas_update_has_db_token_override')) {
    function barbas_update_has_db_token_override($tab_id) {
        return barbas_update_get_db_token_for_plugin($tab_id) !== '';
    }
}

if (!function_exists('barbas_update_wpconfig_constant_in_file')) {
    function barbas_update_wpconfig_constant_in_file($content, $constant) {
        $pattern = '/define\s*\(\s*[\'"]' . preg_quote($constant, '/') . '[\'"]\s*,/i';
        return (bool) preg_match($pattern, $content);
    }
}

if (!function_exists('barbas_update_wpconfig_remove_define_line')) {
    function barbas_update_wpconfig_remove_define_line($content, $constant) {
        $pattern = '/\r?\n?define\s*\(\s*[\'"]' . preg_quote($constant, '/') . '[\'"]\s*,[^;]*;\s*/i';
        return preg_replace($pattern, "\n", $content);
    }
}

if (!function_exists('barbas_update_wpconfig_remove_empty_block')) {
    /**
     * Remove marcadores BEGIN/END quando não resta nenhuma define no bloco.
     */
    function barbas_update_wpconfig_remove_empty_block($content) {
        $begin = preg_quote(barbas_update_wpconfig_block_begin(), '/');
        $end   = preg_quote(barbas_update_wpconfig_block_end(), '/');
        $pattern = '/\r?\n?' . $begin . '\s*' . $end . '\r?\n?/';
        $updated = preg_replace($pattern, "\n", $content);
        return is_string($updated) ? $updated : $content;
    }
}

if (!function_exists('barbas_update_wpconfig_remove_for_tab')) {
    /**
     * Remove constantes deste plugin do wp-config.php.
     *
     * @return bool True se o arquivo foi alterado.
     */
    function barbas_update_wpconfig_remove_for_tab($tab_id) {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $path = barbas_update_wpconfig_locate_path();
        if ($path === false || !@is_writable($path)) {
            return false;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return false;
        }

        $original = $content;
        foreach (barbas_update_wpconfig_constant_names_for_tab($tab_id) as $constant) {
            $content = barbas_update_wpconfig_remove_define_line($content, $constant);
        }

        $content = barbas_update_wpconfig_remove_empty_block($content);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);
        if (!is_string($content) || $content === $original) {
            return false;
        }

        return @file_put_contents($path, $content) !== false;
    }
}
