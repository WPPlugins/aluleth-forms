<?php
/**
 * Imports forms and their entries from Contact Form 7 (entries from
 * Flamingo), WPForms, Elementor Forms and MetForm.
 *
 * Everything is read straight from the database, so it also works when the
 * old plugin is already deactivated. The old forms are never changed.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Form importer.
 */
final class Importer {

	/**
	 * Meta key that remembers where an imported form came from.
	 */
	const META = '_aluleth_imported_from';

	/**
	 * Maximum entries imported per form in one go.
	 */
	const MAX_ENTRIES = 5000;

	/**
	 * Fields of the last parsed form that could not be imported (labels).
	 *
	 * @var string[]
	 */
	private static $skipped = array();

	/**
	 * Sources.
	 *
	 * @return array<string, string> Id => name.
	 */
	public static function sources() {
		return array(
			'cf7'       => 'Contact Form 7',
			'wpforms'   => 'WPForms',
			'elementor' => 'Elementor Forms',
			'metform'   => 'MetForm',
		);
	}

	/**
	 * Forms found on the site.
	 *
	 * @return array[] Each: key, source, title, fields, entries, imported (form id or 0).
	 */
	public static function found() {
		global $wpdb;
		$out = array();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- reading other plugins' data, which may be inactive.
		foreach ( $wpdb->get_results( "SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_type = 'wpcf7_contact_form' AND post_status NOT IN ('trash','auto-draft') ORDER BY ID LIMIT 200" ) as $post ) {
			$out[] = array(
				'key'     => 'cf7:' . $post->ID,
				'source'  => 'cf7',
				'title'   => $post->post_title,
				'entries' => self::flamingo_count( $post->post_name ),
			);
		}
		foreach ( $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'wpforms' AND post_status NOT IN ('trash','auto-draft') ORDER BY ID LIMIT 200" ) as $post ) {
			$out[] = array(
				'key'     => 'wpforms:' . $post->ID,
				'source'  => 'wpforms',
				'title'   => $post->post_title,
				'entries' => self::table_exists( 'wpforms_entries' ) ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpforms_entries WHERE form_id = %d", $post->ID ) ) : 0, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}
		foreach ( $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'metform-form' AND post_status NOT IN ('trash','auto-draft') ORDER BY ID LIMIT 200" ) as $post ) {
			$out[] = array(
				'key'     => 'metform:' . $post->ID,
				'source'  => 'metform',
				'title'   => $post->post_title,
				'entries' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = 'metform_entries__form_id' AND m.meta_value = %s AND p.post_type = 'metform-entry' AND p.post_status <> 'trash'", (string) $post->ID ) ),
			);
		}
		$rows = $wpdb->get_results( "SELECT m.post_id, m.meta_value, p.post_title, p.post_type FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' AND m.meta_value LIKE '%\"widgetType\":\"form\"%' AND p.post_status NOT IN ('trash','auto-draft','inherit') AND p.post_type NOT IN ('revision','metform-form') LIMIT 200" );
		// phpcs:enable
		foreach ( $rows as $row ) {
			foreach ( self::elementor_forms( (string) $row->meta_value ) as $widget ) {
				$name  = isset( $widget['settings']['form_name'] ) && '' !== $widget['settings']['form_name'] ? $widget['settings']['form_name'] : $row->post_title;
				$out[] = array(
					'key'     => 'elementor:' . $row->post_id . ':' . $widget['id'],
					'source'  => 'elementor',
					'title'   => $name,
					'where'   => $row->post_title,
					'entries' => self::table_exists( 'e_submissions' ) ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}e_submissions WHERE post_id = %d AND element_id = %s AND type = 'submission' AND status <> 'trash'", $row->post_id, $widget['id'] ) ) : 0, // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				);
			}
		}

		$imported = self::imported_map();
		foreach ( $out as &$item ) {
			$item['imported'] = isset( $imported[ $item['key'] ] ) ? $imported[ $item['key'] ] : 0;
		}
		return $out;
	}

	/**
	 * Forms imported before: source key => Aluleth form id.
	 *
	 * @return array<string, int>
	 */
	private static function imported_map() {
		global $wpdb;
		$map = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_status <> 'trash'", self::META ) ) as $row ) {
			$map[ $row->meta_value ] = (int) $row->post_id;
		}
		return $map;
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $name Table name without prefix.
	 * @return bool
	 */
	private static function table_exists( $name ) {
		global $wpdb;
		$table = $wpdb->prefix . $name;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Imports one form, and its entries when asked.
	 *
	 * @param string $key     Source key, e.g. 'cf7:131'.
	 * @param array  $options 'entries' (bool), 'contacts' (bool), 'status' ('first' or 'last').
	 * @return array|\WP_Error form_id, title, fields, entries, skipped (labels of fields that could not be imported).
	 */
	public static function import( $key, array $options = array() ) {
		$imported = self::imported_map();
		if ( isset( $imported[ $key ] ) ) {
			return new \WP_Error( 'done', __( 'This form was already imported.', 'aluleth-forms' ) );
		}
		self::$skipped = array();
		$parts         = explode( ':', (string) $key );
		switch ( $parts[0] ) {
			case 'cf7':
				$parsed = self::cf7( (int) $parts[1] );
				break;
			case 'wpforms':
				$parsed = self::wpforms( (int) $parts[1] );
				break;
			case 'metform':
				$parsed = self::metform( (int) $parts[1] );
				break;
			case 'elementor':
				$parsed = isset( $parts[2] ) ? self::elementor( (int) $parts[1], sanitize_key( $parts[2] ) ) : null;
				break;
			default:
				$parsed = null;
		}
		if ( ! $parsed || empty( $parsed['fields'] ) ) {
			return new \WP_Error( 'empty', __( 'This form could not be read.', 'aluleth-forms' ) );
		}

		$schema  = Schema::clean(
			array(
				'fields'   => $parsed['fields'],
				'settings' => $parsed['settings'],
			)
		);
		$form_id = Forms::create( $parsed['title'], $schema );
		if ( ! $form_id ) {
			return new \WP_Error( 'create', __( 'The form could not be created.', 'aluleth-forms' ) );
		}
		update_post_meta( $form_id, self::META, $key );

		$count = 0;
		if ( ! empty( $options['entries'] ) && ! empty( $parsed['entries'] ) ) {
			$count = self::save_entries( $form_id, $schema, call_user_func( $parsed['entries'] ), $parsed['map'], $options );
		}
		return array(
			'form_id' => $form_id,
			'title'   => $parsed['title'],
			'fields'  => count( $schema['fields'] ),
			'entries' => $count,
			'skipped' => self::$skipped,
		);
	}

	/**
	 * Saves imported entries.
	 *
	 * @param int     $form_id Aluleth form id.
	 * @param array   $schema  Clean definition.
	 * @param array[] $rows    Each: data (old key => value), date (UTC), url.
	 * @param array   $map     Old key => new field id.
	 * @param array   $options Options.
	 * @return int Entries saved.
	 */
	private static function save_entries( $form_id, array $schema, array $rows, array $map, array $options ) {
		$fields = array();
		foreach ( $schema['fields'] as $field ) {
			$fields[ $field['id'] ] = $field;
		}
		$statuses = array_keys( Entries::statuses( $form_id ) );
		$status   = isset( $options['status'] ) && 'first' === $options['status'] ? $statuses[0] : end( $statuses );
		$count    = 0;
		foreach ( $rows as $row ) {
			$data = array();
			foreach ( (array) $row['data'] as $old => $value ) {
				if ( ! isset( $map[ $old ] ) || ! isset( $fields[ $map[ $old ] ] ) ) {
					continue;
				}
				$field = $fields[ $map[ $old ] ];
				$value = self::value( $value, $field, isset( $row['labels'][ $old ] ) ? $row['labels'][ $old ] : array() );
				if ( null !== $value ) {
					$data[ $field['id'] ] = $value;
				}
			}
			if ( ! $data ) {
				continue;
			}
			$entry_id = Entries::insert(
				array(
					'form_id'    => $form_id,
					'data'       => $data,
					'ip_hash'    => '',
					'user_agent' => '',
					'page_url'   => isset( $row['url'] ) ? substr( esc_url_raw( (string) $row['url'] ), 0, 255 ) : '',
					'created_at' => isset( $row['date'] ) ? (string) $row['date'] : '',
					'status'     => $status,
				)
			);
			if ( ! $entry_id ) {
				continue;
			}
			++$count;
			if ( ! empty( $options['contacts'] ) ) {
				self::contact( $schema, $data, $entry_id );
			}
		}
		return $count;
	}

	/**
	 * Cleans an old value for a field.
	 *
	 * @param mixed $value  Old value.
	 * @param array $field  New field.
	 * @param array $labels Old option value => label.
	 * @return mixed|null Null to drop it.
	 */
	private static function value( $value, array $field, array $labels ) {
		if ( is_string( $value ) && in_array( $field['type'], array( 'checkboxes' ), true ) ) {
			$value = '' === $value ? array() : array_map( 'trim', preg_split( '/\s*(?:\n|,|\|)\s*/', $value ) );
		}
		if ( 'consent' === $field['type'] ) {
			$value = is_array( $value ) ? reset( $value ) : $value;
			return ! empty( $value ) && ! in_array( strtolower( (string) $value ), array( '0', 'no', 'off', 'false', 'non' ), true );
		}
		if ( is_array( $value ) ) {
			$value = array_values(
				array_filter(
					array_map(
						static function ( $item ) use ( $labels ) {
							$item = is_scalar( $item ) ? (string) $item : '';
							return isset( $labels[ $item ] ) ? $labels[ $item ] : sanitize_text_field( $item );
						},
						$value
					),
					'strlen'
				)
			);
			if ( 'checkboxes' !== $field['type'] ) {
				$value = implode( ', ', $value );
			}
			return $value;
		}
		$value = (string) $value;
		if ( isset( $labels[ $value ] ) ) {
			$value = $labels[ $value ];
		}
		return 'textarea' === $field['type'] ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}

	/**
	 * Links an imported entry to a contact (never subscribed: the old consent cannot be proven here).
	 *
	 * @param array $schema   Definition.
	 * @param array $data     Entry data.
	 * @param int   $entry_id Entry id.
	 * @return void
	 */
	private static function contact( array $schema, array $data, $entry_id ) {
		$email   = '';
		$details = array();
		foreach ( $schema['fields'] as $field ) {
			$value = isset( $data[ $field['id'] ] ) && is_string( $data[ $field['id'] ] ) ? $data[ $field['id'] ] : '';
			if ( '' === $value ) {
				continue;
			}
			if ( 'email' === $field['type'] && '' === $email ) {
				$email = $value;
			}
			if ( in_array( $field['map'], array( 'first_name', 'last_name', 'phone' ), true ) ) {
				$details[ $field['map'] ] = $value;
			} elseif ( 'full_name' === $field['map'] ) {
				$split                 = preg_split( '/\s+/', trim( $value ), 2 );
				$details['first_name'] = $split[0];
				if ( isset( $split[1] ) ) {
					$details['last_name'] = $split[1];
				}
			}
		}
		if ( '' === $email || ! function_exists( 'aluleth_core' ) ) {
			return;
		}
		$contact_id = aluleth_core()->contacts()->upsert(
			$email,
			$details,
			array(
				'source'    => 'aluleth-forms-import',
				'status'    => 'transactional',
				'fill_only' => true,
			)
		);
		if ( $contact_id ) {
			Entries::set_contact( $entry_id, $contact_id );
		}
	}

	/**
	 * Field id from a name or label.
	 *
	 * @param string $text Name.
	 * @param array  $used Ids already used (by reference).
	 * @return string
	 */
	private static function id( $text, array &$used ) {
		$id = substr( trim( preg_replace( '/_+/', '_', sanitize_key( str_replace( array( '-', ' ' ), '_', remove_accents( (string) $text ) ) ) ), '_' ), 0, 40 );
		if ( '' === $id ) {
			$id = 'field';
		}
		$base = $id;
		$n    = 2;
		while ( isset( $used[ $id ] ) ) {
			$id = $base . '_' . $n;
			++$n;
		}
		$used[ $id ] = true;
		return $id;
	}

	/**
	 * Readable label from a field name ("your-name" → "Your name").
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function humanize( $name ) {
		$name = trim( preg_replace( '/^(your|mf)[-_]/', '', (string) $name ), '-_ ' );
		return ucfirst( str_replace( array( '-', '_' ), ' ', $name ) );
	}

	/**
	 * Whether a label looks like a name field.
	 *
	 * @param string $text Label or name.
	 * @return bool
	 */
	private static function is_name( $text ) {
		return (bool) preg_match( '/\b(name|nom|nombre|full.?name|your.?name)\b/i', remove_accents( (string) $text ) ) && ! preg_match( '/(company|entreprise|soci[eé]t[eé]|user)/i', (string) $text );
	}

	/**
	 * Whether a consent label is about marketing emails.
	 *
	 * @param string $text Label.
	 * @return bool
	 */
	private static function is_marketing( $text ) {
		return (bool) preg_match( '/(newsletter|news|offer|offre|promo|marketing|actualit|bolet[ií]n|novedades)/i', (string) $text );
	}

	/*
	 * Contact Form 7.
	 */

	/**
	 * Reads a Contact Form 7 form.
	 *
	 * @param int $id Post id.
	 * @return array|null
	 */
	private static function cf7( $id ) {
		$post = get_post( $id );
		if ( ! $post || 'wpcf7_contact_form' !== $post->post_type ) {
			return null;
		}
		$template = (string) get_post_meta( $id, '_form', true );
		$messages = get_post_meta( $id, '_messages', true );
		$mail     = get_post_meta( $id, '_mail', true );
		$fields   = array();
		$map      = array();
		$used     = array();
		$settings = array();

		if ( ! preg_match_all( '/\[(\w+\*?)(?:\s+([^\]]*))?\](?:(.*?)\[\/\1\])?/s', $template, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		$last_end = 0;
		foreach ( $tags as $tag ) {
			$type     = $tag[1][0];
			$required = '*' === substr( $type, -1 );
			$base     = rtrim( $type, '*' );
			$attrs    = isset( $tag[2][0] ) ? (string) $tag[2][0] : '';
			$inner    = isset( $tag[3][0] ) ? trim( wp_strip_all_tags( (string) $tag[3][0] ) ) : '';
			$before   = substr( $template, $last_end, $tag[0][1] - $last_end );
			$last_end = $tag[0][1] + strlen( $tag[0][0] );

			preg_match_all( '/"([^"]*)"|\'([^\']*)\'/', $attrs, $quoted );
			$values = array();
			foreach ( $quoted[0] as $i => $unused ) {
				$values[] = '' !== $quoted[1][ $i ] ? $quoted[1][ $i ] : $quoted[2][ $i ];
			}
			$words   = preg_split( '/\s+/', trim( preg_replace( '/"[^"]*"|\'[^\']*\'/', '', $attrs ) ) );
			$name    = isset( $words[0] ) ? $words[0] : '';
			$options = array_slice( $words, 1 );

			if ( 'submit' === $base ) {
				if ( $values ) {
					$settings['submit_label'] = $values[0];
				}
				continue;
			}
			$label = '';
			$lines = array_filter( array_map( 'trim', explode( "\n", wp_strip_all_tags( $before ) ) ), 'strlen' );
			if ( $lines ) {
				$label = (string) end( $lines );
			}
			if ( 'acceptance' === $base ) {
				$label = '' !== $inner ? $inner : $label;
			}
			if ( '' === $label ) {
				$label = self::humanize( $name );
			}

			$types = array(
				'text'       => 'text',
				'email'      => 'email',
				'tel'        => 'phone',
				'url'        => 'text',
				'number'     => 'number',
				'range'      => 'number',
				'date'       => 'date',
				'textarea'   => 'textarea',
				'select'     => in_array( 'multiple', $options, true ) ? 'checkboxes' : 'select',
				'checkbox'   => in_array( 'exclusive', $options, true ) ? 'radio' : 'checkboxes',
				'radio'      => 'radio',
				'acceptance' => 'consent',
				'hidden'     => 'hidden',
			);
			if ( ! isset( $types[ $base ] ) || '' === $name ) {
				if ( '' !== $name && ! in_array( $base, array( 'quiz', 'captchac', 'captchar', 'recaptcha' ), true ) ) {
					self::$skipped[] = $label;
				}
				continue;
			}
			$field = array(
				'id'       => self::id( $name, $used ),
				'type'     => $types[ $base ],
				'label'    => $label,
				'required' => 'acceptance' === $base ? ! in_array( 'optional', $options, true ) : $required,
			);
			if ( in_array( $field['type'], array( 'select', 'radio', 'checkboxes' ), true ) ) {
				$field['options'] = $values;
			}
			if ( 'number' === $field['type'] ) {
				foreach ( $options as $option ) {
					if ( preg_match( '/^(min|max):(-?[0-9.]+)$/', $option, $m ) ) {
						$field[ $m[1] ] = (float) $m[2];
					}
				}
			}
			if ( 'consent' === $field['type'] ) {
				$field['marketing'] = self::is_marketing( $label );
			}
			if ( 'phone' === $field['type'] ) {
				$field['map'] = 'phone';
			} elseif ( 'text' === $field['type'] && self::is_name( $name . ' ' . $label ) ) {
				$field['map'] = 'full_name';
			}
			if ( 'hidden' === $field['type'] && $values ) {
				$field['default'] = $values[0];
			}
			$fields[]     = $field;
			$map[ $name ] = $field['id'];
		}

		if ( is_array( $messages ) && ! empty( $messages['mail_sent_ok'] ) ) {
			$settings['success_message'] = $messages['mail_sent_ok'];
		}
		if ( is_array( $mail ) && ! empty( $mail['recipient'] ) && false === strpos( $mail['recipient'], '[' ) ) {
			$settings['notify_to'] = $mail['recipient'];
		}
		$slug = $post->post_name;
		return array(
			'title'    => $post->post_title,
			'fields'   => $fields,
			'settings' => $settings,
			'map'      => $map,
			'entries'  => static function () use ( $slug ) {
				return self::flamingo_entries( $slug );
			},
		);
	}

	/**
	 * Ids of the Flamingo messages of a Contact Form 7 form (channel = form slug).
	 *
	 * @param string $slug Form slug.
	 * @param bool   $ids  Return the ids instead of the count.
	 * @return int|int[]
	 */
	private static function flamingo_ids( $slug, $ids = false ) {
		global $wpdb;
		if ( '' === (string) $slug ) {
			return $ids ? array() : 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$found = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->term_relationships} r ON r.object_id = p.ID JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE p.post_type = 'flamingo_inbound' AND p.post_status = 'publish' AND tt.taxonomy = 'flamingo_inbound_channel' AND t.slug = %s ORDER BY p.ID ASC LIMIT %d",
				$slug,
				self::MAX_ENTRIES
			)
		);
		return $ids ? array_map( 'intval', $found ) : count( $found );
	}

	/**
	 * Number of Flamingo messages of a form.
	 *
	 * @param string $slug Form slug.
	 * @return int
	 */
	private static function flamingo_count( $slug ) {
		return (int) self::flamingo_ids( $slug );
	}

	/**
	 * Flamingo messages as rows.
	 *
	 * @param string $slug Form slug.
	 * @return array[]
	 */
	private static function flamingo_entries( $slug ) {
		$rows = array();
		foreach ( self::flamingo_ids( $slug, true ) as $id ) {
			$keys = get_post_meta( $id, '_fields', true );
			$data = array();
			foreach ( is_array( $keys ) ? array_keys( $keys ) : array() as $key ) {
				$data[ $key ] = get_post_meta( $id, '_field_' . $key, true );
			}
			$meta   = get_post_meta( $id, '_meta', true );
			$rows[] = array(
				'data' => $data,
				'date' => get_post_field( 'post_date_gmt', $id ),
				'url'  => is_array( $meta ) && ! empty( $meta['url'] ) ? $meta['url'] : '',
			);
		}
		return $rows;
	}

	/*
	 * WPForms.
	 */

	/**
	 * Reads a WPForms form.
	 *
	 * @param int $id Post id.
	 * @return array|null
	 */
	private static function wpforms( $id ) {
		$post = get_post( $id );
		if ( ! $post || 'wpforms' !== $post->post_type ) {
			return null;
		}
		$json = json_decode( $post->post_content, true );
		if ( ! is_array( $json ) || empty( $json['fields'] ) ) {
			return null;
		}
		$fields   = array();
		$map      = array();
		$labels   = array();
		$used     = array();
		$settings = array();
		$types    = array(
			'text'          => 'text',
			'name'          => 'text',
			'email'         => 'email',
			'phone'         => 'phone',
			'url'           => 'text',
			'textarea'      => 'textarea',
			'select'        => 'select',
			'radio'         => 'radio',
			'checkbox'      => 'checkboxes',
			'number'        => 'number',
			'number-slider' => 'number',
			'date-time'     => 'date',
			'hidden'        => 'hidden',
			'gdpr-checkbox' => 'consent',
			'divider'       => 'heading',
			'html'          => 'paragraph',
			'pagebreak'     => 'step',
			'content'       => 'paragraph',
		);
		$first    = true;
		foreach ( $json['fields'] as $old ) {
			$type  = isset( $old['type'] ) ? $old['type'] : '';
			$label = isset( $old['label'] ) ? wp_strip_all_tags( (string) $old['label'] ) : '';
			if ( 'pagebreak' === $type && isset( $old['position'] ) && 'bottom' === $old['position'] ) {
				continue;
			}
			if ( ! isset( $types[ $type ] ) ) {
				self::$skipped[] = '' !== $label ? $label : $type;
				continue;
			}
			if ( 'pagebreak' === $type && $first ) {
				// WPForms has no break before the first page: add our first step.
				$fields[] = array(
					'id'    => self::id( 'step', $used ),
					'type'  => 'step',
					'label' => isset( $old['title'] ) ? (string) $old['title'] : __( 'Step 1', 'aluleth-forms' ),
				);
			}
			$first = false;
			$field = array(
				'id'       => self::id( '' !== $label ? $label : $type, $used ),
				'type'     => $types[ $type ],
				'label'    => '' !== $label ? $label : self::humanize( $type ),
				'required' => ! empty( $old['required'] ),
			);
			if ( ! empty( $old['description'] ) ) {
				$field['help'] = wp_strip_all_tags( (string) $old['description'] );
			}
			if ( ! empty( $old['placeholder'] ) ) {
				$field['placeholder'] = (string) $old['placeholder'];
			}
			if ( 'name' === $type ) {
				$field['map'] = 'full_name';
			} elseif ( 'phone' === $type ) {
				$field['map'] = 'phone';
			}
			if ( isset( $old['choices'] ) && is_array( $old['choices'] ) ) {
				$field['options'] = array();
				foreach ( $old['choices'] as $choice ) {
					$text = isset( $choice['label'] ) ? (string) $choice['label'] : '';
					if ( '' !== $text ) {
						$field['options'][] = $text;
						if ( isset( $choice['value'] ) && '' !== (string) $choice['value'] ) {
							$labels[ $old['id'] ][ (string) $choice['value'] ] = $text;
						}
					}
				}
				if ( 'gdpr-checkbox' === $type ) {
					$field['label'] = $field['options'] ? $field['options'][0] : $field['label'];
					unset( $field['options'] );
				}
			}
			if ( 'consent' === $field['type'] ) {
				$field['marketing'] = self::is_marketing( $field['label'] );
			}
			if ( 'html' === $type || 'content' === $type ) {
				$field['label'] = '';
				$field['text']  = wp_strip_all_tags( isset( $old['code'] ) ? (string) $old['code'] : ( isset( $old['content'] ) ? (string) $old['content'] : '' ) );
			}
			if ( 'hidden' === $type && isset( $old['default_value'] ) ) {
				$field['default'] = (string) $old['default_value'];
			}
			$fields[]                   = $field;
			$map[ (string) $old['id'] ] = $field['id'];
		}
		$s = isset( $json['settings'] ) ? $json['settings'] : array();
		if ( ! empty( $s['submit_text'] ) ) {
			$settings['submit_label'] = $s['submit_text'];
		}
		if ( ! empty( $s['confirmations'] ) && is_array( $s['confirmations'] ) ) {
			$confirmation = reset( $s['confirmations'] );
			if ( ! empty( $confirmation['message'] ) ) {
				$settings['success_message'] = wp_strip_all_tags( $confirmation['message'] );
			}
			if ( isset( $confirmation['type'] ) && 'redirect' === $confirmation['type'] && ! empty( $confirmation['redirect'] ) ) {
				$settings['redirect'] = $confirmation['redirect'];
			}
		}
		if ( ! empty( $s['notifications'] ) && is_array( $s['notifications'] ) ) {
			$notification = reset( $s['notifications'] );
			if ( ! empty( $notification['email'] ) && false === strpos( $notification['email'], '{' ) ) {
				$settings['notify_to'] = $notification['email'];
			}
		}
		return array(
			'title'    => ! empty( $s['form_title'] ) ? $s['form_title'] : $post->post_title,
			'fields'   => $fields,
			'settings' => $settings,
			'map'      => $map,
			'entries'  => static function () use ( $id, $labels ) {
				return self::wpforms_entries( $id, $labels );
			},
		);
	}

	/**
	 * WPForms entries (WPForms Pro keeps them in wpforms_entries; Lite does not keep entries).
	 *
	 * @param int   $id     Form id.
	 * @param array $labels Field id => value => label.
	 * @return array[]
	 */
	private static function wpforms_entries( $id, array $labels ) {
		global $wpdb;
		if ( ! self::table_exists( 'wpforms_entries' ) ) {
			return array();
		}
		$rows = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT fields, date FROM {$wpdb->prefix}wpforms_entries WHERE form_id = %d ORDER BY entry_id ASC LIMIT %d", $id, self::MAX_ENTRIES ) ) as $entry ) {
			$data = array();
			foreach ( (array) json_decode( (string) $entry->fields, true ) as $field_id => $field ) {
				$data[ (string) $field_id ] = isset( $field['value'] ) ? $field['value'] : '';
				if ( isset( $field['type'] ) && 'checkbox' === $field['type'] && is_string( $data[ (string) $field_id ] ) ) {
					$data[ (string) $field_id ] = array_filter( explode( "\n", $data[ (string) $field_id ] ) );
				}
			}
			$rows[] = array(
				'data'   => $data,
				'date'   => get_gmt_from_date( (string) $entry->date ),
				'url'    => '',
				'labels' => $labels,
			);
		}
		return $rows;
	}

	/*
	 * Elementor Forms.
	 */

	/**
	 * Form widgets in Elementor data.
	 *
	 * @param string $json Elementor data.
	 * @return array[] Widgets.
	 */
	private static function elementor_forms( $json ) {
		$data = json_decode( $json, true );
		$out  = array();
		$walk = static function ( $elements ) use ( &$walk, &$out ) {
			foreach ( (array) $elements as $element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				if ( isset( $element['widgetType'] ) && 'form' === $element['widgetType'] && ! empty( $element['id'] ) ) {
					$out[] = $element;
				}
				if ( ! empty( $element['elements'] ) ) {
					$walk( $element['elements'] );
				}
			}
		};
		$walk( is_array( $data ) ? $data : array() );
		return $out;
	}

	/**
	 * Reads an Elementor form widget.
	 *
	 * @param int    $post_id    Page id.
	 * @param string $element_id Widget id.
	 * @return array|null
	 */
	private static function elementor( $post_id, $element_id ) {
		$widget = null;
		foreach ( self::elementor_forms( (string) get_post_meta( $post_id, '_elementor_data', true ) ) as $candidate ) {
			if ( $candidate['id'] === $element_id ) {
				$widget = $candidate;
			}
		}
		if ( ! $widget ) {
			return null;
		}
		$s        = isset( $widget['settings'] ) ? $widget['settings'] : array();
		$fields   = array();
		$map      = array();
		$labels   = array();
		$used     = array();
		$settings = array();
		$types    = array(
			'text'       => 'text',
			'email'      => 'email',
			'textarea'   => 'textarea',
			'url'        => 'text',
			'tel'        => 'phone',
			'radio'      => 'radio',
			'select'     => 'select',
			'checkbox'   => 'checkboxes',
			'acceptance' => 'consent',
			'number'     => 'number',
			'date'       => 'date',
			'time'       => 'text',
			'hidden'     => 'hidden',
			'html'       => 'paragraph',
			'step'       => 'step',
		);
		foreach ( isset( $s['form_fields'] ) ? (array) $s['form_fields'] : array() as $old ) {
			$type  = isset( $old['field_type'] ) ? $old['field_type'] : 'text';
			$key   = ! empty( $old['custom_id'] ) ? (string) $old['custom_id'] : ( isset( $old['_id'] ) ? (string) $old['_id'] : '' );
			$label = isset( $old['field_label'] ) ? wp_strip_all_tags( (string) $old['field_label'] ) : '';
			if ( 'acceptance' === $type && ! empty( $old['acceptance_text'] ) ) {
				$label = wp_strip_all_tags( (string) $old['acceptance_text'] );
			}
			if ( 'select' === $type && ! empty( $old['allow_multiple'] ) ) {
				$type = 'checkbox';
			}
			if ( ! isset( $types[ $type ] ) ) {
				if ( ! in_array( $type, array( 'recaptcha', 'recaptcha_v3', 'honeypot' ), true ) ) {
					self::$skipped[] = '' !== $label ? $label : $type;
				}
				continue;
			}
			$field = array(
				'id'       => self::id( '' !== $key ? $key : $label, $used ),
				'type'     => $types[ $type ],
				'label'    => '' !== $label ? $label : self::humanize( $key ),
				'required' => ! empty( $old['required'] ) && 'false' !== $old['required'],
			);
			if ( ! empty( $old['placeholder'] ) ) {
				$field['placeholder'] = (string) $old['placeholder'];
			}
			if ( isset( $old['width'] ) ) {
				$field['width'] = in_array( (string) $old['width'], array( '50', '40', '60' ), true ) ? 'half' : ( in_array( (string) $old['width'], array( '33', '25', '20' ), true ) ? 'third' : 'full' );
			}
			if ( ! empty( $old['field_options'] ) ) {
				$field['options'] = array();
				foreach ( preg_split( '/\r\n|\r|\n/', (string) $old['field_options'] ) as $line ) {
					$pair = explode( '|', $line, 2 );
					$text = trim( $pair[0] );
					if ( '' !== $text ) {
						$field['options'][] = $text;
						if ( isset( $pair[1] ) ) {
							$labels[ $key ][ trim( $pair[1] ) ] = $text;
						}
					}
				}
			}
			if ( 'consent' === $field['type'] ) {
				$field['marketing'] = self::is_marketing( $label );
			}
			if ( 'phone' === $field['type'] ) {
				$field['map'] = 'phone';
			} elseif ( 'text' === $field['type'] && self::is_name( $key . ' ' . $label ) ) {
				$field['map'] = 'full_name';
			}
			if ( 'html' === $type ) {
				$field['label'] = '';
				$field['text']  = wp_strip_all_tags( isset( $old['field_html'] ) ? (string) $old['field_html'] : '' );
			}
			if ( 'step' === $type && '' === $label ) {
				$field['label'] = __( 'Step', 'aluleth-forms' );
			}
			$fields[]    = $field;
			$map[ $key ] = $field['id'];
		}
		if ( ! empty( $s['button_text'] ) ) {
			$settings['submit_label'] = $s['button_text'];
		}
		if ( ! empty( $s['success_message'] ) ) {
			$settings['success_message'] = wp_strip_all_tags( $s['success_message'] );
		}
		if ( ! empty( $s['email_to'] ) && is_email( trim( (string) $s['email_to'] ) ) ) {
			$settings['notify_to'] = trim( (string) $s['email_to'] );
		}
		$title = ! empty( $s['form_name'] ) ? (string) $s['form_name'] : get_the_title( $post_id );
		return array(
			'title'    => $title,
			'fields'   => $fields,
			'settings' => $settings,
			'map'      => $map,
			'entries'  => static function () use ( $post_id, $element_id, $labels ) {
				return self::elementor_entries( $post_id, $element_id, $labels );
			},
		);
	}

	/**
	 * Elementor Pro submissions of a form.
	 *
	 * @param int    $post_id    Page id.
	 * @param string $element_id Widget id.
	 * @param array  $labels     Field key => value => label.
	 * @return array[]
	 */
	private static function elementor_entries( $post_id, $element_id, array $labels ) {
		global $wpdb;
		if ( ! self::table_exists( 'e_submissions' ) ) {
			return array();
		}
		$rows = array();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$submissions = $wpdb->get_results( $wpdb->prepare( "SELECT id, referer, created_at_gmt FROM {$wpdb->prefix}e_submissions WHERE post_id = %d AND element_id = %s AND type = 'submission' AND status <> 'trash' ORDER BY id ASC LIMIT %d", $post_id, $element_id, self::MAX_ENTRIES ) );
		foreach ( $submissions as $submission ) {
			$data = array();
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT `key`, `value` FROM {$wpdb->prefix}e_submissions_values WHERE submission_id = %d", $submission->id ) ) as $value ) {
				$data[ (string) $value->key ] = (string) $value->value;
			}
			$rows[] = array(
				'data'   => $data,
				'date'   => (string) $submission->created_at_gmt,
				'url'    => (string) $submission->referer,
				'labels' => $labels,
			);
		}
		// phpcs:enable
		return $rows;
	}

	/*
	 * MetForm.
	 */

	/**
	 * Reads a MetForm form.
	 *
	 * @param int $id Post id.
	 * @return array|null
	 */
	private static function metform( $id ) {
		$post = get_post( $id );
		if ( ! $post || 'metform-form' !== $post->post_type ) {
			return null;
		}
		$data    = json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
		$widgets = array();
		$walk    = static function ( $elements ) use ( &$walk, &$widgets ) {
			foreach ( (array) $elements as $element ) {
				if ( isset( $element['widgetType'] ) && 0 === strpos( (string) $element['widgetType'], 'mf-' ) ) {
					$widgets[] = $element;
				}
				if ( ! empty( $element['elements'] ) ) {
					$walk( $element['elements'] );
				}
			}
		};
		$walk( is_array( $data ) ? $data : array() );

		$types    = array(
			'mf-text'           => 'text',
			'mf-email'          => 'email',
			'mf-telephone'      => 'phone',
			'mf-textarea'       => 'textarea',
			'mf-number'         => 'number',
			'mf-range'          => 'number',
			'mf-rating'         => 'number',
			'mf-date'           => 'date',
			'mf-time'           => 'text',
			'mf-url'            => 'text',
			'mf-select'         => 'select',
			'mf-multi-select'   => 'checkboxes',
			'mf-radio'          => 'radio',
			'mf-checkbox'       => 'checkboxes',
			'mf-switch'         => 'consent',
			'mf-gdpr-consent'   => 'consent',
			'mf-listing-fname'  => 'text',
			'mf-listing-lname'  => 'text',
			'mf-listing-optin'  => 'consent',
			'mf-simple-message' => 'paragraph',
		);
		$fields   = array();
		$map      = array();
		$labels   = array();
		$used     = array();
		$settings = array();
		foreach ( $widgets as $widget ) {
			$type = $widget['widgetType'];
			$s    = isset( $widget['settings'] ) ? $widget['settings'] : array();
			if ( 'mf-button' === $type ) {
				if ( ! empty( $s['mf_btn_text'] ) ) {
					$settings['submit_label'] = $s['mf_btn_text'];
				}
				continue;
			}
			$name  = isset( $s['mf_input_name'] ) ? (string) $s['mf_input_name'] : '';
			$label = isset( $s['mf_input_label'] ) ? wp_strip_all_tags( (string) $s['mf_input_label'] ) : '';
			if ( in_array( $type, array( 'mf-gdpr-consent', 'mf-listing-optin', 'mf-switch' ), true ) ) {
				foreach ( array( 'mf_gdpr_consent_option_text', 'mf_listing_optin_option_text', 'mf_swtich_enable_text', 'mf_input_label' ) as $text_key ) {
					if ( ! empty( $s[ $text_key ] ) ) {
						$label = wp_strip_all_tags( (string) $s[ $text_key ] );
						break;
					}
				}
			}
			if ( ! isset( $types[ $type ] ) ) {
				if ( ! in_array( $type, array( 'mf-recaptcha', 'mf-simple-captcha', 'mf-summary' ), true ) ) {
					self::$skipped[] = '' !== $label ? $label : $type;
				}
				continue;
			}
			$field = array(
				'id'       => self::id( '' !== $name ? $name : $label, $used ),
				'type'     => $types[ $type ],
				'label'    => '' !== $label ? $label : self::humanize( $name ),
				'required' => isset( $s['mf_input_required'] ) && 'yes' === $s['mf_input_required'],
			);
			if ( ! empty( $s['mf_input_placeholder'] ) ) {
				$field['placeholder'] = (string) $s['mf_input_placeholder'];
			}
			if ( ! empty( $s['mf_input_help_text'] ) ) {
				$field['help'] = (string) $s['mf_input_help_text'];
			}
			if ( ! empty( $s['mf_input_list'] ) && is_array( $s['mf_input_list'] ) ) {
				$field['options'] = array();
				foreach ( $s['mf_input_list'] as $option ) {
					$text = isset( $option['mf_input_option_text'] ) ? wp_strip_all_tags( (string) $option['mf_input_option_text'] ) : '';
					if ( '' !== $text ) {
						$field['options'][] = $text;
						if ( isset( $option['mf_input_option_value'] ) ) {
							$labels[ $name ][ (string) $option['mf_input_option_value'] ] = $text;
						}
					}
				}
			}
			if ( 'mf-listing-fname' === $type ) {
				$field['map'] = 'first_name';
			} elseif ( 'mf-listing-lname' === $type ) {
				$field['map'] = 'last_name';
			} elseif ( 'phone' === $field['type'] ) {
				$field['map'] = 'phone';
			} elseif ( 'text' === $field['type'] && self::is_name( $name . ' ' . $label ) ) {
				$field['map'] = 'full_name';
			}
			if ( 'consent' === $field['type'] ) {
				$field['marketing'] = 'mf-listing-optin' === $type || self::is_marketing( $field['label'] );
			}
			if ( 'mf-simple-message' === $type ) {
				$field['label'] = '';
				$field['text']  = wp_strip_all_tags( isset( $s['mf_simple_message_text'] ) ? (string) $s['mf_simple_message_text'] : '' );
			}
			$fields[] = $field;
			if ( '' !== $name ) {
				$map[ $name ] = $field['id'];
			}
		}
		$form_settings = get_post_meta( $id, 'metform_form__form_setting', true );
		if ( is_array( $form_settings ) && ! empty( $form_settings['success_message'] ) ) {
			$settings['success_message'] = wp_strip_all_tags( (string) $form_settings['success_message'] );
		}
		if ( is_array( $form_settings ) && ! empty( $form_settings['admin_email_to'] ) && is_email( trim( (string) $form_settings['admin_email_to'] ) ) ) {
			$settings['notify_to'] = trim( (string) $form_settings['admin_email_to'] );
		}
		return array(
			'title'    => $post->post_title,
			'fields'   => $fields,
			'settings' => $settings,
			'map'      => $map,
			'entries'  => static function () use ( $id, $labels ) {
				return self::metform_entries( $id, $labels );
			},
		);
	}

	/**
	 * MetForm entries.
	 *
	 * @param int   $id     Form id.
	 * @param array $labels Field name => value => label.
	 * @return array[]
	 */
	private static function metform_entries( $id, array $labels ) {
		global $wpdb;
		$rows = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'metform_entries__form_id' WHERE p.post_type = 'metform-entry' AND p.post_status <> 'trash' AND m.meta_value = %s ORDER BY p.ID ASC LIMIT %d", (string) $id, self::MAX_ENTRIES ) );
		foreach ( $ids as $entry_id ) {
			$data   = get_post_meta( (int) $entry_id, 'metform_entries__form_data', true );
			$rows[] = array(
				'data'   => is_array( $data ) ? $data : array(),
				'date'   => get_post_field( 'post_date_gmt', (int) $entry_id ),
				'url'    => '',
				'labels' => $labels,
			);
		}
		return $rows;
	}

	/**
	 * Labels of the fields of the last import that could not be brought over.
	 *
	 * @return string[]
	 */
	public static function skipped() {
		return self::$skipped;
	}
}
