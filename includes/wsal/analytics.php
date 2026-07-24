<?php
/**
 * Hangar Connect — native WSAL reader (ported from Activity Reports engine).
 * Read-only against wsal_occurrences / wsal_metadata. No AR plugin dependency.
 */

/**
 * Motor de análise de produtividade.
 * Recebe eventos já filtrados (ordenados por created_on ASC) + metadados e
 * calcula sessões, tempo ativo estimado, resumo por dia e quebras.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'HANGAR_WSAL_SESSION_GAP' ) ) {
	define( 'HANGAR_WSAL_SESSION_GAP', 1800 ); // 30 min separam sessões.
}
if ( ! defined( 'HANGAR_WSAL_ACTIVE_CAP' ) ) {
	define( 'HANGAR_WSAL_ACTIVE_CAP', 300 );   // teto de 5 min por intervalo ao estimar tempo ativo.
}

/** Formata segundos como "0min", "18min", "1h43". */
function hangar_wsal_fmt_dur( $seconds ) {
	$seconds = (int) round( $seconds );
	if ( $seconds < 60 ) {
		return '0min';
	}
	$m = intdiv( $seconds, 60 );
	if ( $m < 60 ) {
		return $m . 'min';
	}
	$h = intdiv( $m, 60 );
	$rem = $m % 60;
	return $h . 'h' . str_pad( (string) $rem, 2, '0', STR_PAD_LEFT );
}

/** Data local (Y-m-d) a partir de um timestamp UNIX, respeitando o fuso do site. */
function hangar_wsal_local_date( $ts ) {
	return wp_date( 'Y-m-d', (int) $ts );
}
function hangar_wsal_local_time( $ts ) {
	return wp_date( 'H:i', (int) $ts );
}

/**
 * Analisa um conjunto de eventos.
 *
 * @param array $events  linhas de wsal_occurrences (stdClass), ASC por created_on.
 * @param array $details occurrence_id => array(name=>value)
 * @return array estrutura completa do relatório.
 */
function hangar_wsal_analyze( $events, $details ) {
	$out = array(
		'totals'   => array(
			'events' => 0, 'work' => 0, 'views' => 0, 'logins' => 0, 'system' => 0,
			'sessions' => 0, 'active_s' => 0, 'session_s' => 0, 'days' => 0,
			'first_ts' => null, 'last_ts' => null,
			'peak_hour' => null, 'avg_work_per_session' => 0, 'work_ratio' => 0,
		),
		'user'     => array( 'username' => '', 'roles' => '', 'user_id' => 0, 'user_agent' => '' ),
		'sessions' => array(),
		'per_day'  => array(),
		'kinds'    => array(),
		'categories' => array(),
		'hourly'   => array(),
		'heatmap'  => array(), // [0-6][0-23] work counts (0=Sunday)
		'posts'    => array(),
		'ips'      => array(),
		'uploads'  => array(),
	);

	if ( empty( $events ) ) {
		return $out;
	}

	// Garantia de ordenação.
	usort(
		$events,
		function ( $a, $b ) {
			return ( (float) $a->created_on <=> (float) $b->created_on );
		}
	);

	$kind_counts = array(); // label => count
	$cat_counts  = array(); // category => count
	$hour_counts = array(); // 0-23 => work actions
	$heatmap     = array(); // dow => hour => work count
	$post_counts = array(); // post_id => array(title,count)
	$ip_counts   = array();
	$uploads     = array();

	for ( $d = 0; $d <= 6; $d++ ) {
		$heatmap[ $d ] = array_fill( 0, 24, 0 );
	}

	$cur   = null; // sessão corrente
	$prev_ts = null;

	$flush_session = function () use ( &$cur, &$out ) {
		if ( null === $cur ) {
			return;
		}
		$cur['duration_s'] = max( 0, $cur['end'] - $cur['start'] );
		$out['sessions'][] = $cur;
		$out['totals']['active_s']  += $cur['active_s'];
		$out['totals']['session_s'] += $cur['duration_s'];
		$cur = null;
	};

	foreach ( $events as $e ) {
		$ts    = (float) $e->created_on;
		$alert = (int) $e->alert_id;
		$et    = isset( $e->event_type ) ? (string) $e->event_type : '';
		$obj   = isset( $e->object ) ? (string) $e->object : '';
		$oid   = isset( $e->id ) ? (int) $e->id : 0;
		$kind  = hangar_wsal_event_kind( $alert, $et, $obj );
		$label = hangar_wsal_event_label( $alert, $et, $obj );

		// Totais gerais.
		$out['totals']['events']++;
		if ( null === $out['totals']['first_ts'] ) {
			$out['totals']['first_ts'] = $ts;
		}
		$out['totals']['last_ts'] = $ts;

		if ( 'view' === $kind ) {
			$out['totals']['views']++;
		} elseif ( 'login' === $kind ) {
			$out['totals']['logins']++;
		} elseif ( 'system' === $kind ) {
			$out['totals']['system']++;
		}
		if ( hangar_wsal_is_work_kind( $kind ) ) {
			$out['totals']['work']++;
			$hour = (int) wp_date( 'G', (int) $ts );
			$dow  = (int) wp_date( 'w', (int) $ts );
			$hour_counts[ $hour ] = isset( $hour_counts[ $hour ] ) ? $hour_counts[ $hour ] + 1 : 1;
			if ( ! isset( $heatmap[ $dow ] ) ) {
				$heatmap[ $dow ] = array_fill( 0, 24, 0 );
			}
			$heatmap[ $dow ][ $hour ]++;
		}

		if ( 'system' !== $kind && 'view' !== $kind ) {
			$cat_counts[ $kind ] = isset( $cat_counts[ $kind ] ) ? $cat_counts[ $kind ] + 1 : 1;
		}

		// Info do usuário (primeiro evento com dados).
		if ( '' === $out['user']['username'] && ! empty( $e->username ) ) {
			$out['user']['username']   = (string) $e->username;
			$out['user']['roles']      = isset( $e->user_roles ) ? (string) $e->user_roles : '';
			$out['user']['user_id']    = isset( $e->user_id ) ? (int) $e->user_id : 0;
			$out['user']['user_agent'] = isset( $e->user_agent ) ? (string) $e->user_agent : '';
		}

		// Quebra por tipo de ação (exclui ruído de sistema).
		if ( 'system' !== $kind ) {
			if ( ! isset( $kind_counts[ $label ] ) ) {
				$kind_counts[ $label ] = 0;
			}
			$kind_counts[ $label ]++;
		}

		// Posts mais trabalhados (ações de conteúdo).
		if ( 'content' === $kind && ! empty( $e->post_id ) ) {
			$pid   = (int) $e->post_id;
			$title = isset( $details[ $oid ]['PostTitle'] ) ? $details[ $oid ]['PostTitle'] : '';
			if ( ! isset( $post_counts[ $pid ] ) ) {
				$post_counts[ $pid ] = array( 'title' => $title, 'count' => 0, 'post_id' => $pid );
			}
			if ( '' !== $title ) {
				$post_counts[ $pid ]['title'] = $title;
			}
			$post_counts[ $pid ]['count']++;
		}

		// IPs.
		if ( ! empty( $e->client_ip ) ) {
			$ip = (string) $e->client_ip;
			$ip_counts[ $ip ] = isset( $ip_counts[ $ip ] ) ? $ip_counts[ $ip ] + 1 : 1;
		}

		// Uploads.
		if ( 2010 === $alert ) {
			$fname = '';
			foreach ( array( 'FileName', 'AttachmentName', 'FilePath' ) as $k ) {
				if ( ! empty( $details[ $oid ][ $k ] ) ) {
					$fname = $details[ $oid ][ $k ];
					break;
				}
			}
			$uploads[] = array( 'ts' => $ts, 'file' => $fname );
		}

		// Sessões: nova sessão se gap > limite.
		if ( null === $cur || ( $prev_ts !== null && ( $ts - $prev_ts ) > HANGAR_WSAL_SESSION_GAP ) ) {
			$flush_session();
			$cur = array(
				'start' => $ts, 'end' => $ts, 'active_s' => 0,
				'events' => 0, 'work' => 0, 'views' => 0,
				'ip' => isset( $e->client_ip ) ? (string) $e->client_ip : '',
				'has_login' => false, 'login_ts' => array(),
			);
		} else {
			// Mesmo bloco: soma tempo ativo (com teto por intervalo).
			$gap = $ts - $prev_ts;
			$cur['active_s'] += min( $gap, HANGAR_WSAL_ACTIVE_CAP );
			$cur['end'] = $ts;
		}

		$cur['events']++;
		if ( hangar_wsal_is_work_kind( $kind ) ) {
			$cur['work']++;
		}
		if ( 'view' === $kind ) {
			$cur['views']++;
		}
		if ( 'login' === $kind && hangar_wsal_is_successful_login_alert( $alert ) ) {
			$cur['has_login'] = true;
			$cur['login_ts'][] = $ts;
		}
		if ( '' === $cur['ip'] && ! empty( $e->client_ip ) ) {
			$cur['ip'] = (string) $e->client_ip;
		}

		$prev_ts = $ts;
	}
	$flush_session();

	// Marcas das sessões (intenso / ocioso / login).
	foreach ( $out['sessions'] as &$s ) {
		$s['marks'] = array();
		if ( $s['has_login'] ) {
			$s['marks'][] = 'login';
		}
		if ( $s['work'] >= 15 ) {
			$s['marks'][] = 'intense';
		} elseif ( $s['duration_s'] >= 1800 && $s['active_s'] < ( $s['duration_s'] * 0.40 ) ) {
			$s['marks'][] = 'idle';
		}
	}
	unset( $s );

	$out['totals']['sessions'] = count( $out['sessions'] );
	$work_sessions = 0;
	$work_in_sessions = 0;
	foreach ( $out['sessions'] as $s ) {
		if ( $s['work'] > 0 ) {
			$work_sessions++;
			$work_in_sessions += $s['work'];
		}
	}
	$out['totals']['avg_work_per_session'] = $work_sessions > 0
		? round( $work_in_sessions / $work_sessions, 1 )
		: 0;
	$out['totals']['work_ratio'] = $out['totals']['events'] > 0
		? (int) round( $out['totals']['work'] / $out['totals']['events'] * 100 )
		: 0;
	if ( ! empty( $hour_counts ) ) {
		arsort( $hour_counts );
		$peak = (int) array_key_first( $hour_counts );
		$out['totals']['peak_hour'] = $peak;
		$out['hourly'] = $hour_counts;
	}

	$out['heatmap'] = $heatmap;

	arsort( $cat_counts );
	$out['categories'] = $cat_counts;

	// Resumo por dia.
	$per_day = array();
	foreach ( $out['sessions'] as $s ) {
		$day = hangar_wsal_local_date( $s['start'] );
		if ( ! isset( $per_day[ $day ] ) ) {
			$per_day[ $day ] = array(
				'date' => $day, 'first' => $s['start'], 'last' => $s['end'],
				'sessions' => 0, 'events' => 0, 'work' => 0, 'views' => 0, 'active_s' => 0,
			);
		}
		$per_day[ $day ]['sessions']++;
		$per_day[ $day ]['events']  += $s['events'];
		$per_day[ $day ]['work']    += $s['work'];
		$per_day[ $day ]['views']   += $s['views'];
		$per_day[ $day ]['active_s'] += $s['active_s'];
		$per_day[ $day ]['first']   = min( $per_day[ $day ]['first'], $s['start'] );
		$per_day[ $day ]['last']    = max( $per_day[ $day ]['last'], $s['end'] );
	}
	ksort( $per_day );
	$out['per_day']       = $per_day;
	$out['totals']['days'] = count( $per_day );

	// Ordenações finais.
	arsort( $kind_counts );
	$out['kinds'] = $kind_counts;

	uasort(
		$post_counts,
		function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		}
	);
	$out['posts'] = array_values( $post_counts );

	arsort( $ip_counts );
	$out['ips'] = $ip_counts;

	$out['uploads'] = $uploads;

	return $out;
}
