<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('hangar_connect_connections');
delete_option('barbas_update_token_connect');

// Clear one-time reveal / nonce transients is best-effort (keyed); skip scan.
