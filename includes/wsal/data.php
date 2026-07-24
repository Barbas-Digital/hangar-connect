<?php
/**
 * Hangar Connect — native WSAL reader (ported from Activity Reports engine).
 * Read-only against wsal_occurrences / wsal_metadata. No AR plugin dependency.
 */

/**
 * Camada de acesso aos dados do WP Activity Log.
 * Somente leitura. Nunca grava nas tabelas do WSAL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolve os nomes das tabelas do WSAL respeitando o prefixo do banco. */
function hangar_wsal_tables() {
	global $wpdb;
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$prefixes = array_unique( array( $wpdb->prefix, $wpdb->base_prefix ) );
	foreach ( $prefixes as $p ) {
		$occ   = $p . 'wsal_occurrences';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $occ ) );
		if ( $found ) {
			$cache = array(
				'occ'  => $occ,
				'meta' => $p . 'wsal_metadata',
			);
			return $cache;
		}
	}
	$cache = false;
	return false;
}

/** Colunas existentes na tabela de ocorrências. */
function hangar_wsal_occ_columns() {
	global $wpdb;
	static $cols = null;
	if ( null !== $cols ) {
		return $cols;
	}
	$t = hangar_wsal_tables();
	if ( ! $t ) {
		$cols = array();
		return $cols;
	}
	$result = $wpdb->get_col( "SHOW COLUMNS FROM `{$t['occ']}`" ); // phpcs:ignore WordPress.DB
	$cols   = $result ? $result : array();
	return $cols;
}

function hangar_wsal_has_col( $col ) {
	return in_array( $col, hangar_wsal_occ_columns(), true );
}

/** Valores distintos de uma coluna (para dropdowns de filtro). */
function hangar_wsal_distinct( $col ) {
	global $wpdb;
	if ( ! hangar_wsal_has_col( $col ) ) {
		return array();
	}
	$t   = hangar_wsal_tables();
	$out = $wpdb->get_col( "SELECT DISTINCT `$col` FROM `{$t['occ']}` WHERE `$col` <> '' ORDER BY `$col` ASC LIMIT 200" ); // phpcs:ignore WordPress.DB
	return $out ? $out : array();
}

/** Lista de usuários (username + user_id) presentes no log, para o seletor do relatório. */
function hangar_wsal_known_users() {
	global $wpdb;
	$t = hangar_wsal_tables();
	if ( ! $t || ! hangar_wsal_has_col( 'username' ) ) {
		return array();
	}
	$rows = $wpdb->get_results( "SELECT username, MAX(user_id) AS user_id, COUNT(*) AS n FROM `{$t['occ']}` WHERE username <> '' AND username IS NOT NULL GROUP BY username ORDER BY n DESC LIMIT 200" ); // phpcs:ignore WordPress.DB
	return $rows ? $rows : array();
}

/**
 * Busca eventos de acordo com um conjunto de condições preparadas.
 *
 * @param array $args {
 *   from_ts   float  timestamp UNIX inicial (inclusive) ou null
 *   to_ts     float  timestamp UNIX final (inclusive) ou null
 *   user      string username (LIKE) ou user_id (se numérico) ou ''
 *   severity  string
 *   event_type string
 *   object    string
 *   alert_id  int
 *   s         string busca livre
 *   limit     int
 *   offset    int
 * }
 * @param int $total (saída) total sem limite.
 */
function hangar_wsal_fetch_events( $args, &$total = null ) {
	global $wpdb;
	$t = hangar_wsal_tables();
	if ( ! $t ) {
		$total = 0;
		return array();
	}

	$where  = array();
	$params = array();

	if ( isset( $args['from_ts'] ) && null !== $args['from_ts'] ) {
		$where[]  = 'created_on >= %f';
		$params[] = (float) $args['from_ts'];
	}
	if ( isset( $args['to_ts'] ) && null !== $args['to_ts'] ) {
		$where[]  = 'created_on <= %f';
		$params[] = (float) $args['to_ts'];
	}

	if ( ! empty( $args['user'] ) ) {
		$u = (string) $args['user'];
		$exact = ! empty( $args['user_exact'] );
		if ( ctype_digit( $u ) && hangar_wsal_has_col( 'user_id' ) ) {
			$clause = 'user_id = %d';
			$params[] = (int) $u;
			if ( hangar_wsal_has_col( 'username' ) ) {
				$clause  .= ' OR username = %s';
				$params[] = $u;
			}
			$where[] = '( ' . $clause . ' )';
		} elseif ( hangar_wsal_has_col( 'username' ) ) {
			if ( $exact ) {
				$where[]  = 'username = %s';
				$params[] = $u;
			} else {
				$where[]  = 'username LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $u ) . '%';
			}
		}
	}

	foreach ( array( 'severity', 'event_type', 'object' ) as $col ) {
		if ( ! empty( $args[ $col ] ) && hangar_wsal_has_col( $col ) ) {
			$where[]  = "$col = %s";
			$params[] = $args[ $col ];
		}
	}
	if ( ! empty( $args['alert_id'] ) ) {
		$where[]  = 'alert_id = %d';
		$params[] = (int) $args['alert_id'];
	}
	if ( ! empty( $args['s'] ) ) {
		$like = '%' . $wpdb->esc_like( (string) $args['s'] ) . '%';
		$sub  = array();
		foreach ( array( 'username', 'client_ip', 'object', 'event_type', 'user_roles' ) as $c ) {
			if ( hangar_wsal_has_col( $c ) ) {
				$sub[]    = "$c LIKE %s";
				$params[] = $like;
			}
		}
		if ( $sub ) {
			$where[] = '( ' . implode( ' OR ', $sub ) . ' )';
		}
	}

	$where_sql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

	// Total.
	$count_sql = "SELECT COUNT(*) FROM `{$t['occ']}` $where_sql"; // phpcs:ignore WordPress.DB
	$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB

	$limit  = isset( $args['limit'] ) ? (int) $args['limit'] : 100;
	$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

	$allowed_orderby = array( 'created_on', 'alert_id', 'severity', 'user_id', 'client_ip', 'object', 'event_type', 'username' );
	$orderby         = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'created_on';
	if ( ! in_array( $orderby, $allowed_orderby, true ) || ! hangar_wsal_has_col( $orderby ) ) {
		$orderby = 'created_on';
	}
	$order = ( isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ) ? 'ASC' : 'DESC';

	$sql     = "SELECT * FROM `{$t['occ']}` $where_sql ORDER BY `$orderby` $order LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB
	$qparams = array_merge( $params, array( $limit, $offset ) );
	$rows    = $wpdb->get_results( $wpdb->prepare( $sql, $qparams ) ); // phpcs:ignore WordPress.DB
	return $rows ? $rows : array();
}

/**
 * Busca em lote metadados de um conjunto de occurrence_ids.
 * Retorna: array( occurrence_id => array( name => value ) )
 */
function hangar_wsal_fetch_metadata( $occurrence_ids, $names = array() ) {
	global $wpdb;
	$out = array();
	$ids = array_values( array_unique( array_map( 'intval', (array) $occurrence_ids ) ) );
	if ( empty( $ids ) ) {
		return $out;
	}
	$t = hangar_wsal_tables();
	if ( ! $t ) {
		return $out;
	}

	$id_ph  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$params = $ids;
	$name_sql = '';
	if ( ! empty( $names ) ) {
		$name_ph  = implode( ',', array_fill( 0, count( $names ), '%s' ) );
		$name_sql = " AND name IN ($name_ph)";
		$params   = array_merge( $params, array_values( $names ) );
	}

	$sql  = "SELECT occurrence_id, name, value FROM `{$t['meta']}` WHERE occurrence_id IN ($id_ph)$name_sql"; // phpcs:ignore WordPress.DB
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB
	foreach ( (array) $rows as $r ) {
		$oid = (int) $r->occurrence_id;
		if ( ! isset( $out[ $oid ] ) ) {
			$out[ $oid ] = array();
		}
		$out[ $oid ][ $r->name ] = (string) $r->value;
	}
	return $out;
}
