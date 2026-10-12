<?php
/**
 * Form views (one number per form and per day, no cookie, no personal data)
 * and the numbers Aluleth Forms adds to the Aluleth dashboard.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Stats.
 */
final class Stats {

	/**
	 * Hooks the REST route and the dashboard filters.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'aluleth/dashboard/kpis', array( __CLASS__, 'kpis' ), 10, 2 );
		add_filter( 'aluleth/dashboard/series', array( __CLASS__, 'series' ), 10, 2 );
		add_filter( 'aluleth/dashboard/todo', array( __CLASS__, 'todo' ) );
		add_filter( 'aluleth/dashboard/panels', array( __CLASS__, 'panels' ), 10, 2 );
		add_filter( 'aluleth/dashboard/tools', array( __CLASS__, 'tools' ) );
		add_filter( 'aluleth/dashboard/action', array( __CLASS__, 'action' ) );
	}

	/**
	 * Public route that counts a view of the forms shown on a page.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			'aluleth-forms/v1',
			'/view',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_view' ),
				'permission_callback' => '__return_true', // Public counter: no personal data, published forms only, each with its signed form token, at most 30 views an hour from one address.
			)
		);
	}

	/**
	 * Counts one view for each published form listed (at most 5).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function rest_view( $request ) {
		/**
		 * Whether form views are counted.
		 *
		 * @param bool $count True by default.
		 */
		if ( ! apply_filters( 'aluleth_forms/count_views', true ) ) {
			return new \WP_REST_Response( null, 204 );
		}
		// Each form comes with the signed token printed in it ("id:token"), so only forms really shown are counted.
		$pairs = array_slice( array_unique( array_filter( explode( ',', sanitize_text_field( (string) $request->get_param( 'forms' ) ) ) ) ), 0, 5 );
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'aluleth_forms_views_' . substr( hash_hmac( 'sha256', $ip . '|' . gmdate( 'YmdH' ), wp_salt( 'nonce' ) ), 0, 24 );
		$seen  = (int) get_transient( $key );
		foreach ( $pairs as $pair ) {
			$bits = explode( ':', $pair, 2 );
			$id   = absint( $bits[0] );
			if ( $seen >= 30 || 2 !== count( $bits ) || ! $id || ! Submission::valid_token( $id, $bits[1] ) ) {
				continue;
			}
			if ( Plugin::POST_TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
				self::add_view( $id );
				++$seen;
			}
		}
		set_transient( $key, $seen, HOUR_IN_SECONDS );
		return new \WP_REST_Response( null, 204 );
	}

	/**
	 * Adds one view to today's counter of a form.
	 *
	 * @param int $form_id Form id.
	 * @return void
	 */
	public static function add_view( $form_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aluleth_form_stats';
		$day   = wp_date( 'Y-m-d' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$done = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET views = views + 1 WHERE form_id = %d AND day = %s', $table, $form_id, $day ) );
		if ( ! $done ) {
			$wpdb->insert(
				$table,
				array(
					'form_id' => (int) $form_id,
					'day'     => $day,
					'views'   => 1,
				)
			);
		}
		// phpcs:enable
	}

	/**
	 * Views of a form (or of all forms) since a local date.
	 *
	 * @param string $since   Local date Y-m-d.
	 * @param int    $form_id Form id (0 = all).
	 * @param string $until   Local date Y-m-d, excluded (optional).
	 * @return int
	 */
	public static function views( $since, $form_id = 0, $until = '' ) {
		global $wpdb;
		$until = $until ? (string) $until : '9999-12-31';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(views),0) FROM {$wpdb->prefix}aluleth_form_stats WHERE day >= %s AND day < %s AND ( %d = 0 OR form_id = %d )", $since, $until, (int) $form_id, (int) $form_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * First local day of the period.
	 *
	 * @param array $period Period.
	 * @return string Y-m-d.
	 */
	private static function first_day( array $period ) {
		$key   = (string) key( $period['buckets'] );
		$first = 7 === strlen( $key ) ? $key . '-01' : $key;
		$since = (string) get_option( 'aluleth_forms_views_since', '' );
		return $since && $since > $first ? $since : $first;
	}

	/**
	 * Entries received since a UTC datetime (optionally before another one).
	 *
	 * @param string $since   UTC datetime.
	 * @param string $until   UTC datetime (optional).
	 * @param int    $form_id Form id (0 = all).
	 * @return int
	 */
	private static function entries( $since, $until = '', $form_id = 0 ) {
		global $wpdb;
		$until = $until ? (string) $until : '9999-12-31 23:59:59';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}aluleth_form_entries WHERE created_at >= %s AND status <> %s AND created_at < %s AND ( %d = 0 OR form_id = %d )", $since, Entries::SPAM, $until, (int) $form_id, (int) $form_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Key numbers: entries and conversion.
	 *
	 * @param array[] $kpis   Key numbers.
	 * @param array   $period Period.
	 * @return array[]
	 */
	public static function kpis( $kpis, $period ) {
		$now     = self::entries( $period['start'] );
		$before  = self::entries( $period['prev_start'], $period['start'] );
		$views   = self::views( self::first_day( $period ) );
		$counted = self::entries( self::counted_since( $period ) );
		$kpis[]  = array(
			'key'   => 'entries',
			'label' => __( 'Form entries', 'aluleth-forms' ),
			'value' => number_format_i18n( $now ),
			'note'  => Dashboard::delta_text( Dashboard::delta( $now, $before ), $period ),
			'order' => 10,
		);
		$started = get_option( 'aluleth_forms_views_since' );
		if ( $views ) {
			$kpis[] = array(
				'key'   => 'conversion',
				'label' => __( 'Form conversion', 'aluleth-forms' ),
				'value' => number_format_i18n( min( 100, $counted * 100 / $views ), 1 ) . '%',
				/* translators: 1: number of form views, 2: number of entries. */
				'note'  => sprintf( __( '%1$s form views → %2$s entries', 'aluleth-forms' ), number_format_i18n( $views ), number_format_i18n( $counted ) ),
				'order' => 30,
			);
		} else {
			$kpis[] = array(
				'key'   => 'conversion',
				'label' => __( 'Form conversion', 'aluleth-forms' ),
				'value' => '—',
				'note'  => $started ? __( 'No form view yet in this period.', 'aluleth-forms' ) : __( 'Form views are counted from today.', 'aluleth-forms' ),
				'order' => 30,
			);
		}
		return $kpis;
	}

	/**
	 * Start of the conversion figures (UTC): the start of the period, or the
	 * moment the views counter started if it is later.
	 *
	 * @param array $period Period.
	 * @return string UTC datetime.
	 */
	private static function counted_since( array $period ) {
		$started = (string) get_option( 'aluleth_forms_views_started', '' );
		return $started && $started > $period['start'] ? $started : $period['start'];
	}

	/**
	 * Chart line: entries.
	 *
	 * @param array[] $series Series.
	 * @param array   $period Period.
	 * @return array[]
	 */
	public static function series( $series, $period ) {
		global $wpdb;
		$series[] = array(
			'key'   => 'entries',
			'label' => __( 'Entries', 'aluleth-forms' ),
			'data'  => Dashboard::count_by_bucket( $wpdb->prefix . 'aluleth_form_entries', 'created_at', $period, array( 'not' => array( 'status', Entries::SPAM ) ) ),
			'order' => 10,
		);
		return $series;
	}

	/**
	 * Published and draft forms.
	 *
	 * @return \WP_Post[]
	 */
	private static function forms() {
		return get_posts(
			array(
				'post_type'      => Plugin::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Entries waiting in the first column of their form's board.
	 *
	 * @param array $todo List.
	 * @return array
	 */
	public static function todo( $todo ) {
		$items = array();
		$total = 0;
		$first = 0;
		foreach ( self::forms() as $form ) {
			$keys   = array_keys( Entries::statuses( $form->ID ) );
			$column = Entries::column( $form->ID, $keys[0], array_slice( $keys, 1 ), true, '', 5 );
			$total += $column['total'];
			if ( $column['total'] && ! $first ) {
				$first = (int) $form->ID;
			}
			foreach ( $column['items'] as $entry ) {
				$line = '';
				foreach ( $entry->data as $key => $value ) {
					if ( in_array( $key, array( 'name', 'email', 'phone' ), true ) || is_bool( $value ) ) {
						continue;
					}
					$value = Entries::value( $value, '' );
					if ( '' !== $value && Entries::summary( $entry->data ) !== $value ) {
						$line = $value;
						break;
					}
				}
				$items[] = array(
					'id'   => (int) $entry->id,
					'name' => Entries::summary( $entry->data ),
					'text' => get_the_title( $form ) . ( '' !== $line ? ' · ' . wp_html_excerpt( $line, 50, '…' ) : '' ),
					'when' => Board::ago( $entry->created_at ),
					'url'  => add_query_arg(
						array(
							'page'  => 'aluleth-forms-entries',
							'entry' => (int) $entry->id,
						),
						admin_url( 'admin.php' )
					),
				);
			}
		}
		usort(
			$items,
			static function ( $a, $b ) {
				return $b['id'] - $a['id'];
			}
		);
		return array(
			'title'      => __( 'To handle', 'aluleth-forms' ),
			'count'      => $total,
			'link'       => add_query_arg(
				array(
					'page' => 'aluleth-forms-entries',
					'view' => 'board',
					'form' => $first,
				),
				admin_url( 'admin.php' )
			),
			'link_label' => __( 'Open the board', 'aluleth-forms' ),
			'items'      => array_slice( $items, 0, 5 ),
		);
	}

	/**
	 * "Your forms" table.
	 *
	 * @param array[] $panels Panels.
	 * @param array   $period Period.
	 * @return array[]
	 */
	public static function panels( $panels, $period ) {
		global $wpdb;
		$rows  = array();
		$since = self::first_day( $period );
		foreach ( self::forms() as $form ) {
			if ( 'publish' !== $form->post_status ) {
				continue;
			}
			$entries = self::entries( $period['start'], '', $form->ID );
			$views   = self::views( $since, $form->ID );
			$rows[]  = array(
				'form'    => $form,
				'entries' => $entries,
				'counted' => $views ? self::entries( self::counted_since( $period ), '', $form->ID ) : 0,
				'views'   => $views,
				'spark'   => Dashboard::count_by_bucket(
					$wpdb->prefix . 'aluleth_form_entries',
					'created_at',
					$period,
					array(
						'equal' => array( 'form_id', (int) $form->ID ),
						'not'   => array( 'status', Entries::SPAM ),
					)
				),
			);
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['entries'] - $a['entries'];
			}
		);
		$rows = array_slice( $rows, 0, 6 );
		if ( ! $rows ) {
			return $panels;
		}

		$html = '<div class="al-table-wrap"><table class="al-dash-table"><thead><tr><th scope="col">' . esc_html__( 'Form', 'aluleth-forms' ) . '</th><th scope="col">' . esc_html__( 'Views', 'aluleth-forms' ) . '</th><th scope="col">' . esc_html__( 'Entries', 'aluleth-forms' ) . '</th><th scope="col">' . esc_html__( 'Conversion', 'aluleth-forms' ) . '</th><th scope="col" class="al-spark">' . esc_html__( 'Trend', 'aluleth-forms' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$form   = $row['form'];
			$url    = add_query_arg(
				array(
					'page' => 'aluleth-forms-entries',
					'view' => 'board',
					'form' => (int) $form->ID,
				),
				admin_url( 'admin.php' )
			);
			$schema = Forms::get( $form->ID, true );
			$fields = $schema ? $schema['schema']['fields'] : array();
			$steps  = count( wp_list_filter( $fields, array( 'type' => 'step' ) ) );
			if ( $fields && 'step' !== $fields[0]['type'] ) {
				++$steps;
			}
			$steps = max( 1, $steps );
			/* translators: %d: number of steps. */
			$detail = $steps > 1 ? sprintf( __( '%d steps', 'aluleth-forms' ), $steps ) : __( 'One step', 'aluleth-forms' );
			$html  .= '<tr><th scope="row"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $form ) ) . '</a><small>' . esc_html( $detail ) . '</small></th>';
			$html  .= '<td>' . esc_html( $row['views'] ? number_format_i18n( $row['views'] ) : '—' ) . '</td>';
			$html  .= '<td><b>' . esc_html( number_format_i18n( $row['entries'] ) ) . '</b></td>';
			$html  .= '<td>' . esc_html( $row['views'] ? number_format_i18n( min( 100, $row['counted'] * 100 / $row['views'] ), 1 ) . '%' : '—' ) . '</td>';
			$html  .= '<td class="al-spark">' . self::spark( $row['spark'] ) . '</td></tr>';
		}
		$html .= '</tbody></table></div>';

		$panels[] = array(
			'title'      => __( 'Your forms', 'aluleth-forms' ),
			'link'       => admin_url( 'edit.php?post_type=' . Plugin::POST_TYPE ),
			'link_label' => __( 'All forms', 'aluleth-forms' ),
			'html'       => $html,
		);
		return $panels;
	}

	/**
	 * Small trend line (escaped SVG).
	 *
	 * @param int[] $data Values.
	 * @return string
	 */
	private static function spark( array $data ) {
		$values = array_values( $data );
		$n      = count( $values );
		$max    = max( 1, max( $values ? $values : array( 0 ) ) );
		$d      = '';
		foreach ( $values as $i => $v ) {
			$x  = $n > 1 ? 2 + $i * 116 / ( $n - 1 ) : 60;
			$y  = 24 - $v * 20 / $max;
			$d .= ( $i ? ' L' : 'M' ) . round( $x, 1 ) . ' ' . round( $y, 1 );
		}
		return '<svg viewBox="0 0 120 28" width="120" height="28" aria-hidden="true" focusable="false"><path d="' . esc_attr( $d ) . '" fill="none" stroke="#2f5bea" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	/**
	 * The Forms card in "Your Aluleth tools".
	 *
	 * @param array[] $tools Tools.
	 * @return array[]
	 */
	public static function tools( $tools ) {
		$count = wp_count_posts( Plugin::POST_TYPE );
		$all   = Entries::query( array( 'per_page' => 1 ) );
		/* translators: 1: number of forms, 2: number of entries. */
		$text = sprintf( __( '%1$s forms · %2$s entries', 'aluleth-forms' ), number_format_i18n( isset( $count->publish ) ? (int) $count->publish : 0 ), number_format_i18n( $all['total'] ) );
		array_unshift(
			$tools,
			array(
				'name'  => 'Forms',
				'text'  => $text,
				'state' => 'active',
			)
		);
		return $tools;
	}

	/**
	 * Main button of the dashboard.
	 *
	 * @param array|null $action Action.
	 * @return array
	 */
	public static function action( $action ) {
		if ( is_array( $action ) ) {
			return $action;
		}
		return array(
			'label' => __( 'New form', 'aluleth-forms' ),
			'url'   => admin_url( 'post-new.php?post_type=' . Plugin::POST_TYPE ),
		);
	}
}
