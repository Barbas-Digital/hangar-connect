<?php
/**
 * Hangar Connect — native WSAL reader (ported from Activity Reports engine).
 * Read-only against wsal_occurrences / wsal_metadata. No AR plugin dependency.
 */

/**
 * WP Activity Log event dictionary (English source) and productivity categories.
 *
 * Source: https://melapress.com/support/kb/wp-activity-log-list-event-ids/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map alert_id => English label (gettext).
 */
function hangar_wsal_event_labels() {
	return apply_filters(
		'hangar_wsal_event_labels',
		array(
			// --- Session / logged-in user ---
			1000 => __( 'User logged in', 'hangar-connect' ),
			1001 => __( 'User logged out', 'hangar-connect' ),
			1002 => __( 'Failed login attempt', 'hangar-connect' ),
			1003 => __( 'Failed login (unknown user)', 'hangar-connect' ),
			1004 => __( 'Login blocked', 'hangar-connect' ),
			1005 => __( 'Logged in with other sessions open', 'hangar-connect' ),
			1006 => __( 'Terminated other sessions for the same user', 'hangar-connect' ),
			1007 => __( 'Terminated a user session', 'hangar-connect' ),
			1008 => __( 'Switched to another user', 'hangar-connect' ),
			1009 => __( 'Idle session terminated', 'hangar-connect' ),
			1010 => __( 'Requested a password reset', 'hangar-connect' ),
			1011 => __( 'Denied access to a page', 'hangar-connect' ),
			// --- Content (posts/pages) ---
			2000 => __( 'Created a post/draft', 'hangar-connect' ),
			2001 => __( 'Published a post', 'hangar-connect' ),
			2002 => __( 'Modified a post', 'hangar-connect' ),
			2008 => __( 'Permanently deleted a post', 'hangar-connect' ),
			2010 => __( 'Uploaded a media file', 'hangar-connect' ),
			2011 => __( 'Deleted a file', 'hangar-connect' ),
			2012 => __( 'Moved a post to trash', 'hangar-connect' ),
			2014 => __( 'Restored a post from trash', 'hangar-connect' ),
			2016 => __( 'Changed a post category', 'hangar-connect' ),
			2017 => __( 'Changed a post URL', 'hangar-connect' ),
			2019 => __( 'Changed a post author', 'hangar-connect' ),
			2021 => __( 'Changed a post status', 'hangar-connect' ),
			2023 => __( 'Created a category', 'hangar-connect' ),
			2024 => __( 'Deleted a category', 'hangar-connect' ),
			2025 => __( 'Changed post visibility', 'hangar-connect' ),
			2027 => __( 'Changed a post date', 'hangar-connect' ),
			2042 => __( 'Added a widget', 'hangar-connect' ),
			2043 => __( 'Modified a widget', 'hangar-connect' ),
			2044 => __( 'Deleted a widget', 'hangar-connect' ),
			2045 => __( 'Moved a widget between sections', 'hangar-connect' ),
			2046 => __( 'Edited a theme file (theme editor)', 'hangar-connect' ),
			2047 => __( 'Changed the parent post', 'hangar-connect' ),
			2048 => __( 'Changed a post template', 'hangar-connect' ),
			2049 => __( 'Stuck a post', 'hangar-connect' ),
			2050 => __( 'Unstuck a post', 'hangar-connect' ),
			2051 => __( 'Edited a plugin file (plugin editor)', 'hangar-connect' ),
			2052 => __( 'Changed the parent category', 'hangar-connect' ),
			2053 => __( 'Created a custom field on a post', 'hangar-connect' ),
			2054 => __( 'Changed a custom field value', 'hangar-connect' ),
			2055 => __( 'Deleted a custom field', 'hangar-connect' ),
			2062 => __( 'Renamed a custom field', 'hangar-connect' ),
			2065 => __( 'Changed post content', 'hangar-connect' ),
			2071 => __( 'Changed a widget position', 'hangar-connect' ),
			2073 => __( 'Submitted a post for review', 'hangar-connect' ),
			2074 => __( 'Scheduled a post for publishing', 'hangar-connect' ),
			2078 => __( 'Created a menu', 'hangar-connect' ),
			2079 => __( 'Added item(s) to a menu', 'hangar-connect' ),
			2080 => __( 'Removed item(s) from a menu', 'hangar-connect' ),
			2081 => __( 'Deleted a menu', 'hangar-connect' ),
			2082 => __( 'Changed menu settings', 'hangar-connect' ),
			2083 => __( 'Modified menu item(s)', 'hangar-connect' ),
			2084 => __( 'Renamed a menu', 'hangar-connect' ),
			2085 => __( 'Reordered menu items', 'hangar-connect' ),
			2086 => __( 'Changed a post title', 'hangar-connect' ),
			2089 => __( 'Moved an item as a menu sub-item', 'hangar-connect' ),
			2090 => __( 'Approved a comment', 'hangar-connect' ),
			2091 => __( 'Unapproved a comment', 'hangar-connect' ),
			2092 => __( 'Replied to a comment', 'hangar-connect' ),
			2093 => __( 'Edited a comment', 'hangar-connect' ),
			2094 => __( 'Marked a comment as spam', 'hangar-connect' ),
			2095 => __( 'Marked a comment as not spam', 'hangar-connect' ),
			2096 => __( 'Moved a comment to trash', 'hangar-connect' ),
			2097 => __( 'Restored a comment', 'hangar-connect' ),
			2098 => __( 'Permanently deleted a comment', 'hangar-connect' ),
			2099 => __( 'Published a comment', 'hangar-connect' ),
			2100 => __( 'Opened a post in the editor', 'hangar-connect' ),
			2101 => __( 'Viewed a post', 'hangar-connect' ),
			2111 => __( 'Enabled/disabled comments on a post', 'hangar-connect' ),
			2112 => __( 'Enabled/disabled trackbacks on a post', 'hangar-connect' ),
			2119 => __( 'Added tag(s) to a post', 'hangar-connect' ),
			2120 => __( 'Removed tag(s) from a post', 'hangar-connect' ),
			2121 => __( 'Created a tag', 'hangar-connect' ),
			2122 => __( 'Deleted a tag', 'hangar-connect' ),
			2123 => __( 'Renamed a tag', 'hangar-connect' ),
			2124 => __( 'Changed a tag slug', 'hangar-connect' ),
			2125 => __( 'Changed a tag description', 'hangar-connect' ),
			2127 => __( 'Renamed a category', 'hangar-connect' ),
			2128 => __( 'Changed a category slug', 'hangar-connect' ),
			2129 => __( 'Changed a post excerpt', 'hangar-connect' ),
			2130 => __( 'Changed a post featured image', 'hangar-connect' ),
			2131 => __( 'Added a relationship in an ACF field', 'hangar-connect' ),
			2132 => __( 'Removed a relationship in an ACF field', 'hangar-connect' ),
			2133 => __( 'Took over a post from another user', 'hangar-connect' ),
			2134 => __( 'Accessed a password-protected post', 'hangar-connect' ),
			2135 => __( 'Wrong password on a protected post', 'hangar-connect' ),
			// --- Users / profile ---
			4000 => __( 'A new user was created', 'hangar-connect' ),
			4001 => __( 'Created a new user', 'hangar-connect' ),
			4002 => __( 'Changed a user role', 'hangar-connect' ),
			4003 => __( 'Changed their own password', 'hangar-connect' ),
			4004 => __( 'Changed a user password', 'hangar-connect' ),
			4005 => __( 'Changed their own email', 'hangar-connect' ),
			4006 => __( 'Changed a user email', 'hangar-connect' ),
			4007 => __( 'Deleted a user', 'hangar-connect' ),
			4008 => __( 'Granted super admin privileges', 'hangar-connect' ),
			4009 => __( 'Revoked super admin privileges', 'hangar-connect' ),
			4014 => __( 'Opened a user profile', 'hangar-connect' ),
			4015 => __( 'Changed a user profile field', 'hangar-connect' ),
			4016 => __( 'Created a user profile field', 'hangar-connect' ),
			4017 => __( 'Changed a user first name', 'hangar-connect' ),
			4018 => __( 'Changed a user last name', 'hangar-connect' ),
			4019 => __( 'Changed a user nickname', 'hangar-connect' ),
			4020 => __( 'Changed a user display name', 'hangar-connect' ),
			4021 => __( 'Changed a user website (URL)', 'hangar-connect' ),
			4025 => __( 'Changed an application password (own profile)', 'hangar-connect' ),
			4026 => __( 'Changed an application password (another user)', 'hangar-connect' ),
			4029 => __( 'Sent a password reset request', 'hangar-connect' ),
			// --- Plugins / themes ---
			5000 => __( 'Installed a plugin', 'hangar-connect' ),
			5001 => __( 'Activated a plugin', 'hangar-connect' ),
			5002 => __( 'Deactivated a plugin', 'hangar-connect' ),
			5003 => __( 'Uninstalled a plugin', 'hangar-connect' ),
			5004 => __( 'Updated a plugin', 'hangar-connect' ),
			5005 => __( 'Installed a theme', 'hangar-connect' ),
			5006 => __( 'Activated a theme', 'hangar-connect' ),
			5007 => __( 'Deleted a theme', 'hangar-connect' ),
			5010 => __( 'Plugin created database table(s)', 'hangar-connect' ),
			5011 => __( 'Plugin changed table structure', 'hangar-connect' ),
			5012 => __( 'Plugin deleted database table(s)', 'hangar-connect' ),
			5019 => __( 'Plugin created post(s)', 'hangar-connect' ),
			5025 => __( 'Plugin deleted post(s)', 'hangar-connect' ),
			5028 => __( 'Changed automatic updates for a plugin', 'hangar-connect' ),
			5029 => __( 'Changed automatic updates for a theme', 'hangar-connect' ),
			5030 => __( 'Failed to install/update a plugin', 'hangar-connect' ),
			5031 => __( 'Updated a theme', 'hangar-connect' ),
			5032 => __( 'Plugin update available', 'hangar-connect' ),
			5033 => __( 'Theme update available', 'hangar-connect' ),
			5034 => __( 'Plugin translation files updated', 'hangar-connect' ),
			5035 => __( 'Theme translation files updated', 'hangar-connect' ),
			// --- System / settings / WSAL ---
			6000 => __( 'Events pruned automatically', 'hangar-connect' ),
			6001 => __( 'Changed "anyone can register"', 'hangar-connect' ),
			6002 => __( 'Changed the default role for new users', 'hangar-connect' ),
			6003 => __( 'Changed the admin notification email', 'hangar-connect' ),
			6004 => __( 'Updated WordPress', 'hangar-connect' ),
			6005 => __( 'Changed permalinks', 'hangar-connect' ),
			6008 => __( 'Changed "discourage search engines"', 'hangar-connect' ),
			6024 => __( 'Changed the WordPress address (URL)', 'hangar-connect' ),
			6025 => __( 'Changed the site address (URL)', 'hangar-connect' ),
			6030 => __( 'A file was deleted from the site', 'hangar-connect' ),
			6034 => __( 'Cleared the activity log', 'hangar-connect' ),
			6035 => __( 'Changed "your homepage displays"', 'hangar-connect' ),
			6036 => __( 'Changed the homepage (WP setting)', 'hangar-connect' ),
			6037 => __( 'Changed the posts page (WP setting)', 'hangar-connect' ),
			6040 => __( 'Changed the timezone', 'hangar-connect' ),
			6041 => __( 'Changed the date format', 'hangar-connect' ),
			6042 => __( 'Changed the time format', 'hangar-connect' ),
			6044 => __( 'Changed WordPress automatic updates', 'hangar-connect' ),
			6045 => __( 'Changed the site language', 'hangar-connect' ),
			6049 => __( 'Changed plugin access restriction', 'hangar-connect' ),
			6051 => __( 'Changed "hide plugin on the plugins page"', 'hangar-connect' ),
			6052 => __( 'Changed log retention', 'hangar-connect' ),
			6059 => __( 'Changed the site title', 'hangar-connect' ),
			6061 => __( 'The site sent an email', 'hangar-connect' ),
			6063 => __( 'Added a site icon', 'hangar-connect' ),
			6064 => __( 'Removed the site icon', 'hangar-connect' ),
			6065 => __( 'Changed the site icon', 'hangar-connect' ),
			6079 => __( 'WordPress core update available', 'hangar-connect' ),
			6080 => __( 'WordPress core translations updated', 'hangar-connect' ),
		)
	);
}

/**
 * Human-readable label for an event.
 */
function hangar_wsal_event_label( $alert_id, $event_type = '', $object = '' ) {
	$labels = hangar_wsal_event_labels();
	$id     = (int) $alert_id;
	if ( isset( $labels[ $id ] ) ) {
		return $labels[ $id ];
	}
	$et  = trim( (string) $event_type );
	$obj = trim( (string) $object );
	$parts = array_filter( array( ucfirst( $et ), $obj ) );
	$fallback = trim( implode( ' · ', $parts ) );
	return '' !== $fallback ? $fallback : sprintf( __( 'Event %d', 'hangar-connect' ), $id );
}
/**
 * Classify an event into a productivity category:
 * view | login | content | media | menu | user | plugin | theme | setting | system | other
 */
function hangar_wsal_event_kind( $alert_id, $event_type = '', $object = '' ) {
	$id  = (int) $alert_id;
	$obj = (string) $object;

	// Visualizações / navegação passiva.
	if ( in_array( $id, array( 2100, 2101, 4014, 9072, 9073, 11020, 11021, 11215, 11216 ), true ) ) {
		return 'view';
	}
	if ( 'viewed' === $event_type || 'opened' === $event_type ) {
		return 'view';
	}

	// Sessão.
	if ( $id >= 1000 && $id <= 1011 ) {
		return 'login';
	}

	// Eventos automáticos do sistema (não são trabalho humano).
	if ( in_array( $id, array( 5032, 5033, 5034, 5035, 6000, 6061, 6079, 6080 ), true ) ) {
		return 'system';
	}
	if ( 'system' === $obj && 'available' === $event_type ) {
		return 'system';
	}
	if ( $id >= 6066 && $id <= 6072 ) { // cron jobs
		return 'system';
	}

	// Mídia.
	if ( in_array( $id, array( 2010, 2011, 6030 ), true ) ) {
		return 'media';
	}
	// Menus.
	if ( $id >= 2078 && $id <= 2089 ) {
		return 'menu';
	}
	// Conteúdo.
	if ( ( $id >= 2000 && $id <= 2135 ) || in_array( $id, array( 5019, 5025 ), true ) ) {
		return 'content';
	}
	// Usuários.
	if ( $id >= 4000 && $id <= 4029 ) {
		return 'user';
	}
	// Plugins.
	if ( in_array( $id, array( 5000, 5001, 5002, 5003, 5004, 5010, 5011, 5012, 5028, 5030 ), true ) ) {
		return 'plugin';
	}
	// Temas.
	if ( in_array( $id, array( 5005, 5006, 5007, 5008, 5009, 5013, 5014, 5015, 5029, 5031 ), true ) ) {
		return 'theme';
	}
	// Configurações / sistema.
	if ( $id >= 6000 && $id <= 6099 ) {
		return 'setting';
	}

	return 'other';
}

/**
 * Successful login alerts (timeline dots / session badges).
 * 1000 = User logged in; 1005 = Logged in with other sessions open.
 * Failures (1002–1004), logout (1001), and idle termination (1009) are excluded.
 *
 * @param int $alert_id WSAL alert ID.
 * @return bool
 */
function hangar_wsal_is_successful_login_alert( $alert_id ) {
	return in_array( (int) $alert_id, array( 1000, 1005 ), true );
}

/** Is this a productive work action? */
function hangar_wsal_is_work_kind( $kind ) {
	return in_array( $kind, array( 'content', 'media', 'menu', 'user', 'plugin', 'theme', 'setting' ), true );
}

/**
 * Severity metadata aligned with WP Activity Log codes.
 *
 * @param mixed $sev Severity code or label.
 * @return array{code:int,css:string,label:string}
 */
function hangar_wsal_severity_meta( $sev ) {
	$n = (int) $sev;
	$map = array(
		500 => array( 'css' => 'critical', 'label' => __( 'Critical', 'hangar-connect' ) ),
		400 => array( 'css' => 'high', 'label' => __( 'High', 'hangar-connect' ) ),
		300 => array( 'css' => 'medium', 'label' => __( 'Medium', 'hangar-connect' ) ),
		250 => array( 'css' => 'low', 'label' => __( 'Low', 'hangar-connect' ) ),
		200 => array( 'css' => 'info', 'label' => __( 'Informational', 'hangar-connect' ) ),
		100 => array( 'css' => 'info', 'label' => __( 'Debug', 'hangar-connect' ) ),
		0   => array( 'css' => 'unknown', 'label' => __( 'Unknown', 'hangar-connect' ) ),
	);
	if ( isset( $map[ $n ] ) ) {
		return array(
			'code'  => $n,
			'css'   => $map[ $n ]['css'],
			'label' => $map[ $n ]['label'],
		);
	}
	$raw = trim( (string) $sev );
	return array(
		'code'  => $n,
		'css'   => 'unknown',
		'label' => '' !== $raw ? $raw : __( 'Unknown', 'hangar-connect' ),
	);
}

/** Label for CSV / exports. */
function hangar_wsal_severity_label( $sev ) {
	return hangar_wsal_severity_meta( $sev )['label'];
}

/**
 * Human-readable role list (locale-aware when possible).
 *
 * @param string $roles_raw Comma-separated roles from WSAL.
 * @return string
 */
function hangar_wsal_format_roles( $roles_raw ) {
	$parts = array_filter( array_map( 'trim', explode( ',', (string) $roles_raw ) ) );
	if ( empty( $parts ) ) {
		return '';
	}
	$out = array();
	foreach ( $parts as $role ) {
		$key = sanitize_key( $role );
		if ( function_exists( 'translate_user_role' ) && '' !== $key ) {
			$out[] = translate_user_role( ucfirst( str_replace( array( '-', '_' ), ' ', $role ) ) );
		} else {
			$out[] = $role;
		}
	}
	return implode( ', ', $out );
}
