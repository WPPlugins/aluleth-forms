<?php
/**
 * Reading and saving form definitions.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Forms repository (custom post type + JSON definition in post meta).
 */
final class Forms {

	/**
	 * Meta key of the JSON definition.
	 */
	const META = '_aluleth_form_schema';

	/**
	 * Creates a form.
	 *
	 * @param string $title  Title.
	 * @param array  $schema Definition.
	 * @return int Form id or 0.
	 */
	public static function create( $title, array $schema ) {
		$schema = self::with_defaults( $schema );

		$id = wp_insert_post(
			array(
				'post_type'   => Plugin::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $title ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		self::save_schema( (int) $id, $schema );
		return (int) $id;
	}

	/**
	 * Adds the defaults of Aluleth › Settings › General to the definition of a
	 * new form: the style when it has none, and the layout (labels, help text,
	 * error messages).
	 *
	 * @param array $schema   Definition.
	 * @param bool  $override Replace the layout even when the definition has one
	 *                        (templates and AI drafts, whose definition is already cleaned).
	 * @return array
	 */
	public static function with_defaults( array $schema, $override = false ) {
		$defaults = General::form_defaults();
		if ( ! isset( $schema['settings'] ) || ! is_array( $schema['settings'] ) ) {
			$schema['settings'] = array();
		}
		if ( empty( $schema['settings']['style'] ) ) {
			$schema['settings']['style'] = $defaults['style'];
		}
		$design = isset( $schema['settings']['design'] ) && is_array( $schema['settings']['design'] ) ? $schema['settings']['design'] : array();
		foreach ( array( 'labels', 'help', 'errors' ) as $key ) {
			if ( $override || empty( $design[ $key ] ) ) {
				$design[ $key ] = $defaults[ $key ];
			}
		}
		$schema['settings']['design'] = $design;
		return $schema;
	}

	/**
	 * Saves a definition.
	 *
	 * @param int   $form_id Form id.
	 * @param mixed $schema  Definition.
	 * @return void
	 */
	public static function save_schema( $form_id, $schema ) {
		update_post_meta( $form_id, self::META, wp_slash( wp_json_encode( Schema::clean( $schema ) ) ) );
	}

	/**
	 * Who gets the email of each new entry: the addresses of "Send to", or the site admin email when it is
	 * empty. None when the email is off.
	 *
	 * @param array $settings Form settings.
	 * @return string[]
	 */
	public static function recipients( array $settings ) {
		if ( empty( $settings['notify'] ) ) {
			return array();
		}
		$admin = (string) get_option( 'admin_email' );
		$to    = isset( $settings['notify_to'] ) ? trim( (string) $settings['notify_to'] ) : '';
		$list  = '' !== $to ? array_map( 'trim', explode( ',', $to ) ) : array( $admin );
		$list  = str_replace( '{admin_email}', $admin, $list );
		return array_values( array_filter( $list, 'strlen' ) );
	}

	/**
	 * Returns a published form, or null.
	 *
	 * @param int  $form_id      Form id.
	 * @param bool $allow_draft  Also return drafts (admin preview).
	 * @return array|null { id, title, schema }
	 */
	public static function get( $form_id, $allow_draft = false ) {
		$post = get_post( (int) $form_id );
		if ( ! $post || Plugin::POST_TYPE !== $post->post_type ) {
			return null;
		}
		if ( 'publish' !== $post->post_status && ! $allow_draft ) {
			return null;
		}
		$raw = get_post_meta( $post->ID, self::META, true );
		return array(
			'id'     => (int) $post->ID,
			'title'  => get_the_title( $post ),
			'schema' => Schema::clean( $raw ? $raw : array() ),
		);
	}

	/**
	 * Finds a field definition by id.
	 *
	 * @param array  $schema   Definition.
	 * @param string $field_id Field id.
	 * @return array|null
	 */
	public static function field( array $schema, $field_id ) {
		foreach ( $schema['fields'] as $field ) {
			if ( $field['id'] === $field_id ) {
				return $field;
			}
		}
		return null;
	}
}
