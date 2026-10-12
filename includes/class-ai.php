<?php
/**
 * AI agents: abilities of Aluleth Forms for the WordPress Abilities API, so an
 * AI assistant connected to WordPress (for example through the MCP Adapter)
 * can read forms and numbers, create draft forms and, when allowed, work on
 * entries. Everything runs with the rights of the WordPress user the
 * assistant signs in with. Off by default (Aluleth › Settings › Access).
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Admin as Core_Admin;

defined( 'ABSPATH' ) || exit;

/**
 * AI agents.
 */
final class AI {

	/**
	 * Option name.
	 */
	const OPTION = 'aluleth_forms_ai';

	/**
	 * Ability category.
	 */
	const CATEGORY = 'aluleth-forms';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'abilities' ) );
		add_action( 'aluleth/settings/access', array( __CLASS__, 'render' ) );
		add_action( 'aluleth/settings/access_save', array( __CLASS__, 'save' ) );
		add_filter( 'aluleth/settings/access_anchors', array( __CLASS__, 'anchor' ) );
	}

	/**
	 * Choices: the whole feature, then each group of abilities.
	 *
	 * @return array{on: bool, read: bool, create: bool, entries: bool, manage: bool}
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return array_merge(
			array(
				'on'      => false,
				'read'    => true,
				'create'  => true,
				'entries' => false,
				'manage'  => false,
			),
			is_array( $saved ) ? array_map( 'boolval', $saved ) : array()
		);
	}

	/**
	 * Whether a group of abilities is on.
	 *
	 * @param string $group read, create, entries or manage.
	 * @return bool
	 */
	public static function allowed( $group ) {
		$conf = self::get();
		return $conf['on'] && ! empty( $conf[ $group ] );
	}

	/**
	 * Adds the "AI agents" anchor to the Access section.
	 *
	 * @param array $anchors Anchors.
	 * @return array
	 */
	public static function anchor( $anchors ) {
		$anchors['ai'] = __( 'AI agents', 'aluleth-forms' );
		return $anchors;
	}

	/**
	 * Registers the ability category.
	 *
	 * @return void
	 */
	public static function category() {
		if ( ! function_exists( 'wp_register_ability_category' ) || ! self::get()['on'] ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Aluleth Forms', 'aluleth-forms' ),
				'description' => __( 'Forms, their numbers and their entries.', 'aluleth-forms' ),
			)
		);
	}

	/**
	 * Common metadata: shown to the REST API, MCP and AI clients.
	 *
	 * @param bool $read_only   Does not change anything.
	 * @param bool $destructive May change existing data.
	 * @return array
	 */
	private static function meta( $read_only, $destructive = false ) {
		return array(
			'public'       => true,
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => $read_only,
				'destructive' => $destructive,
				'idempotent'  => $read_only,
			),
		);
	}

	/**
	 * Registers the abilities that are switched on.
	 *
	 * @return void
	 */
	public static function abilities() {
		if ( ! function_exists( 'wp_register_ability' ) || ! self::get()['on'] ) {
			return;
		}

		if ( self::allowed( 'read' ) ) {
			wp_register_ability(
				'aluleth-forms/get-forms',
				array(
					'label'               => __( 'Get forms and their numbers', 'aluleth-forms' ),
					'description'         => __( 'Lists the Aluleth forms of the site with, for the chosen period, their entries, views and conversion, plus the entries waiting in the first column of each board. Use it to answer questions such as "how many quote requests this week?". Contains no personal data.', 'aluleth-forms' ),
					'category'            => self::CATEGORY,
					'input_schema'        => array(
						'type'                 => 'object',
						'properties'           => array(
							'days' => array(
								'type'        => 'integer',
								'description' => __( 'Number of days to count, ending today (1 to 365). Default 30.', 'aluleth-forms' ),
								'minimum'     => 1,
								'maximum'     => 365,
								'default'     => 30,
							),
						),
						'additionalProperties' => false,
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'days'    => array( 'type' => 'integer' ),
							'entries' => array( 'type' => 'integer' ),
							'forms'   => array( 'type' => 'array' ),
						),
					),
					'execute_callback'    => array( __CLASS__, 'get_forms' ),
					'permission_callback' => static function () {
						return self::allowed( 'read' ) && ( current_user_can( Core_Admin::cap( 'forms' ) ) || current_user_can( Core_Admin::cap( 'entries' ) ) );
					},
					'meta'                => self::meta( true ),
				)
			);
		}

		if ( self::allowed( 'create' ) ) {
			wp_register_ability(
				'aluleth-forms/create-form',
				array(
					'label'               => __( 'Create a draft form', 'aluleth-forms' ),
					'description'         => __( 'Creates an Aluleth form as a draft, from a title and a list of fields. Nothing is published: the site owner reviews it in the builder (the answer gives its address). Field types: text, email, phone, number, date, textarea, select, radio, checkboxes, consent, heading, paragraph, step.', 'aluleth-forms' ),
					'category'            => self::CATEGORY,
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'title', 'fields' ),
						'properties'           => array(
							'title'           => array(
								'type'      => 'string',
								'minLength' => 1,
								'maxLength' => 120,
							),
							'fields'          => array(
								'type'     => 'array',
								'minItems' => 1,
								'maxItems' => 40,
								'items'    => array(
									'type'       => 'object',
									'required'   => array( 'type', 'label' ),
									'properties' => array(
										'type'     => array(
											'type' => 'string',
											'enum' => array( 'text', 'email', 'phone', 'number', 'date', 'textarea', 'select', 'radio', 'checkboxes', 'consent', 'heading', 'paragraph', 'step' ),
										),
										'label'    => array( 'type' => 'string' ),
										'required' => array( 'type' => 'boolean' ),
										'help'     => array( 'type' => 'string' ),
										'options'  => array(
											'type'  => 'array',
											'items' => array( 'type' => 'string' ),
										),
										'width'    => array(
											'type' => 'string',
											'enum' => array( 'full', 'half', 'third' ),
										),
									),
								),
							),
							'submit_label'    => array( 'type' => 'string' ),
							'success_message' => array( 'type' => 'string' ),
						),
						'additionalProperties' => false,
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'       => array( 'type' => 'integer' ),
							'status'   => array( 'type' => 'string' ),
							'edit_url' => array( 'type' => 'string' ),
						),
					),
					'execute_callback'    => array( __CLASS__, 'create_form' ),
					'permission_callback' => static function () {
						return self::allowed( 'create' ) && current_user_can( Core_Admin::cap( 'forms' ) );
					},
					'meta'                => self::meta( false ),
				)
			);
		}

		if ( self::allowed( 'entries' ) ) {
			wp_register_ability(
				'aluleth-forms/get-entries',
				array(
					'label'               => __( 'Get form entries', 'aluleth-forms' ),
					'description'         => __( 'Returns the latest entries of the forms, with their answers, status and notes. Entries hold personal data: only use them for the site owner\'s request. Spam is left out unless asked for.', 'aluleth-forms' ),
					'category'            => self::CATEGORY,
					'input_schema'        => array(
						'type'                 => 'object',
						'properties'           => array(
							'form_id' => array( 'type' => 'integer' ),
							'status'  => array(
								'type'        => 'string',
								'description' => __( 'A status key of the form (see the statuses in the answer), or "spam".', 'aluleth-forms' ),
							),
							'search'  => array( 'type' => 'string' ),
							'limit'   => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => 50,
								'default' => 20,
							),
						),
						'additionalProperties' => false,
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'total'   => array( 'type' => 'integer' ),
							'entries' => array( 'type' => 'array' ),
						),
					),
					'execute_callback'    => array( __CLASS__, 'get_entries' ),
					'permission_callback' => static function () {
						return self::allowed( 'entries' ) && current_user_can( Core_Admin::cap( 'entries' ) );
					},
					'meta'                => self::meta( true ),
				)
			);
		}

		if ( self::allowed( 'manage' ) ) {
			wp_register_ability(
				'aluleth-forms/update-entry',
				array(
					'label'               => __( 'Change an entry\'s status or add a note', 'aluleth-forms' ),
					'description'         => __( 'Moves an entry to another status (a column of its board) and/or adds a note for the team. Does not delete anything and sends nothing to the visitor.', 'aluleth-forms' ),
					'category'            => self::CATEGORY,
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'entry_id' ),
						'properties'           => array(
							'entry_id' => array( 'type' => 'integer' ),
							'status'   => array( 'type' => 'string' ),
							'note'     => array(
								'type'      => 'string',
								'maxLength' => 2000,
							),
						),
						'additionalProperties' => false,
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'     => array( 'type' => 'integer' ),
							'status' => array( 'type' => 'string' ),
							'notes'  => array( 'type' => 'integer' ),
						),
					),
					'execute_callback'    => array( __CLASS__, 'update_entry' ),
					'permission_callback' => static function () {
						return self::allowed( 'manage' ) && current_user_can( Core_Admin::cap( 'entries_manage' ) );
					},
					'meta'                => self::meta( false ),
				)
			);
		}
	}

	/**
	 * Ability: forms and their numbers.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function get_forms( $input = array() ) {
		global $wpdb;
		$days  = isset( $input['days'] ) ? max( 1, min( 365, (int) $input['days'] ) ) : 30;
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$day   = wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT form_id, COUNT(*) AS n FROM {$wpdb->prefix}aluleth_form_entries WHERE created_at >= %s AND status <> %s GROUP BY form_id", $since, Entries::SPAM ), OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$forms = array();
		$total = 0;
		foreach ( get_posts(
			array(
				'post_type'      => Plugin::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		) as $post ) {
			$entries  = isset( $rows[ $post->ID ] ) ? (int) $rows[ $post->ID ]->n : 0;
			$views    = Stats::views( $day, $post->ID );
			$statuses = Entries::statuses( $post->ID );
			$keys     = array_keys( $statuses );
			$waiting  = Entries::column( $post->ID, $keys[0], array_slice( $keys, 1 ), true, '', 1 );
			$total   += $entries;
			$forms[]  = array(
				'id'         => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'status'     => 'publish' === $post->post_status ? 'published' : 'draft',
				'entries'    => $entries,
				'views'      => $views,
				'conversion' => $views ? round( min( 100, $entries * 100 / $views ), 1 ) : null,
				'waiting'    => (int) $waiting['total'],
				'statuses'   => $statuses,
				'shortcode'  => '[aluleth_form id="' . (int) $post->ID . '"]',
				'edit_url'   => Builder::url( (int) $post->ID ),
			);
		}
		return array(
			'days'    => $days,
			'entries' => $total,
			'forms'   => $forms,
		);
	}

	/**
	 * Ability: create a draft form.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function create_form( $input = array() ) {
		$title  = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
		$fields = array();
		$used   = array();
		foreach ( isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array() as $i => $field ) {
			if ( ! is_array( $field ) || empty( $field['type'] ) ) {
				continue;
			}
			$base = 'email' === $field['type'] && ! isset( $used['email'] ) ? 'email' : substr( str_replace( '-', '_', sanitize_title( isset( $field['label'] ) ? (string) $field['label'] : 'field' ) ), 0, 30 );
			$id   = '' !== $base ? $base : 'field_' . ( $i + 1 );
			$n    = 2;
			while ( isset( $used[ $id ] ) ) {
				$id = $base . '_' . $n;
				++$n;
			}
			$used[ $id ]  = true;
			$field['id']  = $id;
			$field['map'] = '';
			$fields[]     = $field;
		}
		if ( '' === $title || ! $fields ) {
			return new \WP_Error( 'alf_ai_invalid', __( 'A title and at least one field are needed.', 'aluleth-forms' ) );
		}
		$settings = array();
		foreach ( array( 'submit_label', 'success_message' ) as $key ) {
			if ( ! empty( $input[ $key ] ) ) {
				$settings[ $key ] = (string) $input[ $key ];
			}
		}
		$id = wp_insert_post(
			array(
				'post_type'   => Plugin::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		Forms::save_schema(
			(int) $id,
			Forms::with_defaults(
				array(
					'fields'   => $fields,
					'settings' => $settings,
				)
			)
		);
		$form = Forms::get( (int) $id, true );
		return array(
			'id'       => (int) $id,
			'status'   => 'draft',
			'fields'   => $form ? count( $form['schema']['fields'] ) : 0,
			'edit_url' => Builder::url( (int) $id ),
		);
	}

	/**
	 * Ability: latest entries.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function get_entries( $input = array() ) {
		$args   = array(
			'form_id'  => isset( $input['form_id'] ) ? absint( $input['form_id'] ) : 0,
			'status'   => isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : '',
			'search'   => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
			'per_page' => isset( $input['limit'] ) ? max( 1, min( 50, (int) $input['limit'] ) ) : 20,
			'page'     => 1,
		);
		$result = Entries::query( $args );
		$out    = array();
		foreach ( $result['items'] as $entry ) {
			$out[] = array(
				'id'       => (int) $entry->id,
				'form_id'  => (int) $entry->form_id,
				'form'     => get_the_title( (int) $entry->form_id ),
				'status'   => Entries::SPAM === $entry->status ? Entries::SPAM : Entries::column_of( $entry ),
				'label'    => Entries::status_label( $entry ),
				'received' => mysql2date( 'c', $entry->created_at . ' UTC' ),
				'answers'  => $entry->data,
				'payment'  => $entry->payment ? array(
					'status'   => $entry->payment['status'],
					'amount'   => $entry->payment['amount'],
					'currency' => $entry->payment['currency'],
					'test'     => ! empty( $entry->payment['sandbox'] ),
					'method'   => Payments::method_label( $entry->payment ),
					'order'    => isset( $entry->payment['order']['number'] ) ? (string) $entry->payment['order']['number'] : '',
				) : null,
				'notes'    => array_map(
					static function ( $note ) {
						return isset( $note['text'] ) ? (string) $note['text'] : '';
					},
					(array) $entry->notes
				),
			);
		}
		return array(
			'total'   => (int) $result['total'],
			'entries' => $out,
		);
	}

	/**
	 * Ability: change the status of an entry and/or add a note.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_entry( $input = array() ) {
		$entry = Entries::get( isset( $input['entry_id'] ) ? absint( $input['entry_id'] ) : 0 );
		if ( ! $entry ) {
			return new \WP_Error( 'alf_ai_no_entry', __( 'This entry does not exist.', 'aluleth-forms' ) );
		}
		if ( ! empty( $input['status'] ) ) {
			$status = sanitize_key( (string) $input['status'] );
			if ( Entries::SPAM === $status || ! Entries::set_status( (int) $entry->id, $status ) ) {
				return new \WP_Error( 'alf_ai_status', sprintf( /* translators: %s: list of status keys. */ __( 'Unknown status. Use one of: %s.', 'aluleth-forms' ), implode( ', ', array_keys( Entries::statuses( (int) $entry->form_id ) ) ) ) );
			}
		}
		if ( isset( $input['note'] ) && '' !== trim( (string) $input['note'] ) ) {
			Entries::add_note( (int) $entry->id, sanitize_textarea_field( (string) $input['note'] ) );
		}
		$entry = Entries::get( (int) $entry->id );
		return array(
			'id'     => (int) $entry->id,
			'status' => Entries::column_of( $entry ),
			'notes'  => count( (array) $entry->notes ),
		);
	}

	/**
	 * AI agents card, under the roles table.
	 *
	 * @return void
	 */
	public static function render() {
		$conf      = self::get();
		$available = function_exists( 'wp_register_ability' );
		echo '<section class="al-card al-sec" id="ai"><div class="al-sec-head"><div><h2>' . esc_html__( 'AI agents (MCP)', 'aluleth-forms' ) . '</h2><p>' . esc_html__( 'Let an AI assistant you connect to WordPress work with your forms, using the abilities built into WordPress. It acts with the rights of the WordPress user it signs in with, never more.', 'aluleth-forms' ) . '</p></div>';
		echo '<label class="al-switch"><input type="checkbox" name="ai_on" value="1"' . checked( $conf['on'], true, false ) . ( $available ? '' : ' disabled' ) . '> <span>' . esc_html__( 'On', 'aluleth-forms' ) . '</span></label></div>';
		if ( ! $available ) {
			echo '<p class="al-help">' . esc_html__( 'This needs the Abilities API of WordPress 6.9 or later.', 'aluleth-forms' ) . '</p>';
		}
		$groups = array(
			'read'    => array( __( 'Read forms and statistics', 'aluleth-forms' ), __( '"How many quote requests this week?" No personal data.', 'aluleth-forms' ) ),
			'create'  => array( __( 'Create forms as drafts', 'aluleth-forms' ), __( 'Nothing is published without you.', 'aluleth-forms' ) ),
			'entries' => array( __( 'Read entries', 'aluleth-forms' ), __( 'Personal data: off by default. The user also needs "See entries" in the table above.', 'aluleth-forms' ) ),
			'manage'  => array( __( 'Change entry status and add notes', 'aluleth-forms' ), __( 'Never deletes and never writes to visitors.', 'aluleth-forms' ) ),
		);
		echo '<div class="al-grid2">';
		foreach ( $groups as $key => $text ) {
			echo '<label class="al-checkcard"><input type="checkbox" name="ai_' . esc_attr( $key ) . '" value="1"' . checked( $conf[ $key ], true, false ) . '> <span><strong>' . esc_html( $text[0] ) . '</strong><small>' . esc_html( $text[1] ) . '</small></span></label>';
		}
		echo '</div><p class="al-help">' . esc_html__( 'To connect an assistant such as Claude, install the MCP Adapter plugin and sign the assistant in with a WordPress user (an application password works). Abilities: aluleth-forms/get-forms, create-form, get-entries, update-entry.', 'aluleth-forms' ) . '</p></section>';
	}

	/**
	 * Saves the AI agents choices (with the Access section).
	 *
	 * @param array $post Unslashed $_POST.
	 * @return void
	 */
	public static function save( $post ) {
		$post = is_array( $post ) ? $post : array();
		update_option(
			self::OPTION,
			array(
				'on'      => ! empty( $post['ai_on'] ),
				'read'    => ! empty( $post['ai_read'] ),
				'create'  => ! empty( $post['ai_create'] ),
				'entries' => ! empty( $post['ai_entries'] ),
				'manage'  => ! empty( $post['ai_manage'] ),
			),
			true
		);
	}
}
