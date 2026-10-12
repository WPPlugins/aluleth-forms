<?php
/**
 * Form definition: field types, defaults and cleaning.
 *
 * A form is stored as a JSON document:
 * { "version": 1, "fields": [ {id, type, label, required, …} ], "settings": { … } }
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Schema helpers.
 */
final class Schema {

	/**
	 * Field types that hold a value.
	 */
	const INPUT_TYPES = array( 'text', 'email', 'phone', 'number', 'textarea', 'select', 'radio', 'checkboxes', 'date', 'consent', 'hidden', 'product', 'amount' );

	/**
	 * Field types that only display something.
	 */
	const DISPLAY_TYPES = array( 'heading', 'paragraph', 'step', 'total' );

	/**
	 * Field types that take part in a payment.
	 */
	const PAYMENT_TYPES = array( 'product', 'amount' );

	/**
	 * Field types added by extensions (Aluleth Forms Pro…), cached per request.
	 *
	 * @var array|null
	 */
	private static $extra = null;

	/**
	 * Field types added by extensions.
	 *
	 * @return array{input: string[], display: string[], payment: string[]}
	 */
	private static function extra() {
		if ( null === self::$extra || ! did_action( 'init' ) ) {
			/**
			 * Lets an extension add its own field types. Each list holds type keys
			 * (lower case letters, digits and "_"): "input" types are filled in by
			 * the visitor and saved, "display" types only show something, and
			 * "payment" types (also listed in "input") make the form a payment form.
			 * An extension then cleans, checks and shows its fields with the
			 * aluleth_forms/clean_field, aluleth_forms/validate_field and
			 * aluleth_forms/render_field filters.
			 *
			 * @param array $types Lists "input", "display" and "payment".
			 */
			$raw   = (array) apply_filters(
				'aluleth_forms/field_types',
				array(
					'input'   => array(),
					'display' => array(),
					'payment' => array(),
				)
			);
			$clean = array();
			foreach ( array( 'input', 'display', 'payment' ) as $kind ) {
				$clean[ $kind ] = array_values( array_diff( array_filter( array_map( 'sanitize_key', isset( $raw[ $kind ] ) ? (array) $raw[ $kind ] : array() ) ), self::INPUT_TYPES, self::DISPLAY_TYPES ) );
			}
			$clean['payment'] = array_values( array_intersect( $clean['payment'], $clean['input'] ) );
			if ( ! did_action( 'init' ) ) {
				return $clean;
			}
			self::$extra = $clean;
		}
		return self::$extra;
	}

	/**
	 * Types the visitor fills in (saved with the entry).
	 *
	 * @return string[]
	 */
	public static function input_types() {
		return array_merge( self::INPUT_TYPES, self::extra()['input'] );
	}

	/**
	 * Types that only show something (headings, steps, total…).
	 *
	 * @return string[]
	 */
	public static function display_types() {
		return array_merge( self::DISPLAY_TYPES, self::extra()['display'] );
	}

	/**
	 * Types that make the form a payment form.
	 *
	 * @return string[]
	 */
	public static function payment_types() {
		return array_merge( self::PAYMENT_TYPES, self::extra()['payment'] );
	}

	/**
	 * Whether a type comes from an extension.
	 *
	 * @param string $type Field type.
	 * @return bool
	 */
	public static function is_extra( $type ) {
		return in_array( $type, self::extra()['input'], true ) || in_array( $type, self::extra()['display'], true );
	}

	/**
	 * Contact fields a form field can be saved to.
	 */
	const MAPS = array( '', 'first_name', 'last_name', 'full_name', 'phone' );

	/**
	 * Default form settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'submit_label'    => __( 'Send', 'aluleth-forms' ),
			'success_message' => __( 'Thank you! Your message has been sent.', 'aluleth-forms' ),
			'redirect'        => '',
			'style'           => General::get()['style'],
			'notify'          => true,
			'notify_to'       => '',
			'notify_subject'  => __( 'New entry: {form_title}', 'aluleth-forms' ),
			'confirm'         => false,
			'confirm_subject' => __( 'We received your message', 'aluleth-forms' ),
			'confirm_message' => __( "Hello,\n\nThank you for your message. We will get back to you soon.", 'aluleth-forms' ),
			'tags'            => array(),
			'min_seconds'     => class_exists( '\\Aluleth\\Core\\Security' ) ? (int) \Aluleth\Core\Security::get()['min_seconds'] : 3,
			'captcha'         => 'default',
			'statuses'        => self::default_statuses(),
			'integrations'    => array(),
			'design'          => array(
				'accent' => '',
				'radius' => '',
				'font'   => '',
				'labels' => General::get()['labels'],
				'help'   => General::get()['help'],
				'errors' => General::get()['errors'],
			),
		);
	}

	/**
	 * Default entry statuses (the columns of the entries board).
	 *
	 * @return array[] Each: key, label.
	 */
	public static function default_statuses() {
		return array(
			array(
				'key'   => 'new',
				'label' => __( 'New', 'aluleth-forms' ),
			),
			array(
				'key'   => 'in_progress',
				'label' => __( 'In progress', 'aluleth-forms' ),
			),
			array(
				'key'   => 'done',
				'label' => __( 'Done', 'aluleth-forms' ),
			),
		);
	}

	/**
	 * Cleans the list of entry statuses: 1 to 8 statuses with unique keys.
	 * The first one is where new entries arrive.
	 *
	 * @param mixed $raw Raw list.
	 * @return array[]
	 */
	public static function clean_statuses( $raw ) {
		$out  = array();
		$keys = array();
		foreach ( is_array( $raw ) ? $raw : array() as $status ) {
			if ( ! is_array( $status ) ) {
				continue;
			}
			$label = isset( $status['label'] ) ? trim( sanitize_text_field( (string) $status['label'] ) ) : '';
			if ( '' === $label ) {
				continue;
			}
			$key = isset( $status['key'] ) ? sanitize_key( (string) $status['key'] ) : '';
			if ( '' === $key ) {
				$key = substr( str_replace( '-', '_', sanitize_title( $label ) ), 0, 30 );
			}
			$key  = '' === $key ? 'status' : substr( $key, 0, 30 );
			$base = $key;
			$n    = 2;
			// "spam" is kept for entries set aside by the anti-spam checks.
			while ( isset( $keys[ $key ] ) || Entries::SPAM === $key ) {
				$key = substr( $base, 0, 27 ) . '_' . $n;
				++$n;
			}
			$keys[ $key ] = true;
			$out[]        = array(
				'key'   => $key,
				'label' => substr( $label, 0, 40 ),
			);
			if ( count( $out ) >= 8 ) {
				break;
			}
		}
		return $out ? $out : self::default_statuses();
	}

	/**
	 * The ready-made contact form.
	 *
	 * @return array
	 */
	public static function contact_form() {
		return self::clean(
			array(
				'fields'   => array(
					array(
						'id'           => 'name',
						'type'         => 'text',
						'label'        => __( 'Your name', 'aluleth-forms' ),
						'required'     => true,
						'width'        => 'half',
						'map'          => 'full_name',
						'autocomplete' => 'name',
					),
					array(
						'id'       => 'email',
						'type'     => 'email',
						'label'    => __( 'Email', 'aluleth-forms' ),
						'required' => true,
						'width'    => 'half',
					),
					array(
						'id'       => 'message',
						'type'     => 'textarea',
						'label'    => __( 'Message', 'aluleth-forms' ),
						'required' => true,
					),
					array(
						'id'        => 'newsletter',
						'type'      => 'consent',
						'label'     => __( 'Send me news and offers by email. I can unsubscribe at any time.', 'aluleth-forms' ),
						'marketing' => true,
					),
				),
				'settings' => array(),
			)
		);
	}

	/**
	 * Cleans a form definition coming from the builder or from code.
	 *
	 * @param mixed $schema Raw definition (array or JSON string).
	 * @return array
	 */
	public static function clean( $schema ) {
		if ( is_string( $schema ) ) {
			$schema = json_decode( $schema, true );
		}
		$schema         = is_array( $schema ) ? $schema : array();
		$raw_conditions = array();
		$fields         = array();
		$ids            = array();

		foreach ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ? $schema['fields'] : array() as $field ) {
			if ( ! is_array( $field ) || empty( $field['type'] ) ) {
				continue;
			}
			$type = sanitize_key( $field['type'] );
			if ( ! in_array( $type, array_merge( self::input_types(), self::display_types() ), true ) ) {
				continue;
			}
			$id = isset( $field['id'] ) ? sanitize_key( $field['id'] ) : '';
			if ( '' === $id || isset( $ids[ $id ] ) ) {
				$id = $type . '_' . ( count( $fields ) + 1 );
			}
			$ids[ $id ] = true;

			$clean = array(
				'id'    => $id,
				'type'  => $type,
				'label' => isset( $field['label'] ) ? wp_kses( (string) $field['label'], self::label_html() ) : '',
			);
			if ( in_array( $type, self::input_types(), true ) ) {
				$clean['required']     = ! empty( $field['required'] );
				$clean['placeholder']  = isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '';
				$clean['help']         = isset( $field['help'] ) ? sanitize_text_field( $field['help'] ) : '';
				$clean['width']        = isset( $field['width'] ) && in_array( $field['width'], array( 'half', 'third' ), true ) ? $field['width'] : 'full';
				$clean['map']          = isset( $field['map'] ) && in_array( $field['map'], self::MAPS, true ) ? $field['map'] : '';
				$clean['default']      = isset( $field['default'] ) ? sanitize_text_field( $field['default'] ) : '';
				$clean['autocomplete'] = isset( $field['autocomplete'] ) ? sanitize_text_field( $field['autocomplete'] ) : '';
			}
			if ( in_array( $type, array( 'select', 'radio', 'checkboxes' ), true ) ) {
				$options = array();
				foreach ( isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array() as $option ) {
					$option = sanitize_text_field( is_array( $option ) && isset( $option['label'] ) ? $option['label'] : (string) $option );
					if ( '' !== $option ) {
						$options[] = $option;
					}
				}
				$clean['options'] = array_values( array_unique( $options ) );
			}
			if ( 'number' === $type ) {
				$clean['min'] = isset( $field['min'] ) && '' !== $field['min'] ? (float) $field['min'] : null;
				$clean['max'] = isset( $field['max'] ) && '' !== $field['max'] ? (float) $field['max'] : null;
			}
			if ( in_array( $type, array( 'text', 'textarea' ), true ) ) {
				$clean['maxlength'] = isset( $field['maxlength'] ) ? max( 0, min( 20000, (int) $field['maxlength'] ) ) : 0;
			}
			if ( 'consent' === $type ) {
				$clean['marketing'] = ! empty( $field['marketing'] );
			}
			if ( 'product' === $type ) {
				$clean['mode']  = isset( $field['mode'] ) && in_array( $field['mode'], array( 'one', 'many', 'single' ), true ) ? $field['mode'] : 'one';
				$clean['items'] = array();
				$seen           = array();
				foreach ( isset( $field['items'] ) && is_array( $field['items'] ) ? $field['items'] : array() as $item ) {
					$label = is_array( $item ) && isset( $item['label'] ) ? trim( sanitize_text_field( (string) $item['label'] ) ) : '';
					if ( '' === $label || isset( $seen[ $label ] ) ) {
						continue;
					}
					$seen[ $label ]   = true;
					$price            = is_array( $item ) && isset( $item['price'] ) ? str_replace( ',', '.', (string) $item['price'] ) : '0';
					$clean['items'][] = array(
						'label' => $label,
						'price' => is_numeric( $price ) ? max( 0, min( 1000000, round( (float) $price, 2 ) ) ) : 0,
					);
				}
				if ( 'single' === $clean['mode'] ) {
					$clean['items']    = array_slice( $clean['items'], 0, 1 );
					$clean['required'] = false;
				}
			}
			if ( 'amount' === $type ) {
				$clean['min'] = isset( $field['min'] ) && '' !== $field['min'] && null !== $field['min'] ? max( 0, (float) $field['min'] ) : 1.0;
				$clean['max'] = isset( $field['max'] ) && '' !== $field['max'] && null !== $field['max'] ? max( $clean['min'], (float) $field['max'] ) : null;
			}
			if ( 'paragraph' === $type ) {
				$clean['text'] = isset( $field['text'] ) ? wp_kses( (string) $field['text'], self::label_html() ) : '';
			}
			if ( self::is_extra( $type ) ) {
				/**
				 * Cleans the settings of a field type added by an extension. The
				 * common keys (id, type, label, and for input types required,
				 * placeholder, help, width…) are already clean.
				 *
				 * @param array $clean Clean field.
				 * @param array $field Raw field.
				 */
				$clean         = (array) apply_filters( 'aluleth_forms/clean_field', $clean, $field );
				$clean['id']   = $id;
				$clean['type'] = $type;
			}
			/**
			 * Cleans extension settings kept on any field (the correct answer of a
			 * quiz question…). Extensions must clean what they add.
			 *
			 * @param array $clean Clean field.
			 * @param array $field Raw field.
			 */
			$clean         = (array) apply_filters( 'aluleth_forms/clean_field_all', $clean, $field );
			$clean['id']   = $id;
			$clean['type'] = $type;
			if ( 'step' !== $type && ! empty( $field['conditions'] ) ) {
				$raw_conditions[ count( $fields ) ] = $field['conditions'];
			}
			$fields[] = $clean;
		}

		$input_ids = array();
		/**
		 * Field types added by extensions whose answer can drive conditions
		 * (a single value: a rating, a scale…).
		 *
		 * @param string[] $types Types.
		 */
		$cond_types = (array) apply_filters( 'aluleth_forms/condition_types', array() );
		foreach ( $fields as $field ) {
			if ( in_array( $field['type'], self::input_types(), true ) && 'hidden' !== $field['type'] && ( ! self::is_extra( $field['type'] ) || in_array( $field['type'], $cond_types, true ) ) ) {
				$input_ids[] = $field['id'];
			}
		}
		foreach ( $raw_conditions as $index => $raw ) {
			$conditions = Conditions::clean( $raw, $fields[ $index ]['id'], $input_ids );
			if ( $conditions ) {
				$fields[ $index ]['conditions'] = $conditions;
			}
		}

		$settings       = wp_parse_args( isset( $schema['settings'] ) && is_array( $schema['settings'] ) ? $schema['settings'] : array(), self::default_settings() );
		$clean_settings = array(
			'submit_label'    => sanitize_text_field( $settings['submit_label'] ),
			'success_message' => sanitize_textarea_field( $settings['success_message'] ),
			'redirect'        => esc_url_raw( $settings['redirect'] ),
			'style'           => in_array( $settings['style'], array( 'clean', 'soft', 'dark' ), true ) ? $settings['style'] : 'clean',
			'notify'          => ! empty( $settings['notify'] ),
			'notify_to'       => sanitize_text_field( $settings['notify_to'] ),
			'notify_subject'  => sanitize_text_field( $settings['notify_subject'] ),
			'confirm'         => ! empty( $settings['confirm'] ),
			'confirm_subject' => sanitize_text_field( $settings['confirm_subject'] ),
			'confirm_message' => sanitize_textarea_field( $settings['confirm_message'] ),
			'tags'            => array_values( array_filter( array_map( 'sanitize_text_field', (array) $settings['tags'] ) ) ),
			'min_seconds'     => max( 0, min( 60, (int) $settings['min_seconds'] ) ),
			'captcha'         => in_array( $settings['captcha'], array( 'default', 'none', 'turnstile', 'hcaptcha', 'recaptcha', 'cleantalk' ), true ) ? $settings['captcha'] : 'default',
			'statuses'        => self::clean_statuses( $settings['statuses'] ),
			'integrations'    => self::clean_integrations( $settings['integrations'], $fields ),
			'design'          => self::clean_design( $settings['design'] ),
		);

		/**
		 * Cleans the form settings added by an extension. Keys the extension
		 * does not add are already clean; raw settings hold what the builder sent.
		 *
		 * @param array $clean_settings Clean settings.
		 * @param array $settings       Raw settings (with the defaults).
		 * @param array $fields         Clean fields.
		 */
		$clean_settings = (array) apply_filters( 'aluleth_forms/clean_settings', $clean_settings, $settings, $fields );

		return array(
			'version'  => 1,
			'fields'   => $fields,
			'settings' => $clean_settings,
		);
	}

	/**
	 * Cleans the design settings: accent colour (hex or a site colour token),
	 * corners and font (a site font token). Empty values keep the style's own.
	 *
	 * @param mixed $raw Raw design.
	 * @return array{accent: string, radius: string, font: string, labels: string, help: string, errors: string}
	 */
	public static function clean_design( $raw ) {
		$raw    = is_array( $raw ) ? $raw : array();
		$accent = isset( $raw['accent'] ) ? strtolower( trim( (string) $raw['accent'] ) ) : '';
		$font   = isset( $raw['font'] ) ? strtolower( trim( (string) $raw['font'] ) ) : '';
		if ( preg_match( '/^#[0-9a-f]{3}$/', $accent ) ) {
			$accent = '#' . $accent[1] . $accent[1] . $accent[2] . $accent[2] . $accent[3] . $accent[3];
		}
		return array(
			'accent' => preg_match( '/^(#[0-9a-f]{6}|(wp|el):[a-z0-9_-]{1,60})$/', $accent ) ? $accent : '',
			'radius' => isset( $raw['radius'] ) && in_array( $raw['radius'], array( 'square', 'rounded', 'pill' ), true ) ? $raw['radius'] : '',
			'font'   => preg_match( '/^(wp|el):[a-z0-9_-]{1,60}$/', $font ) ? $font : '',
			'labels' => isset( $raw['labels'] ) && in_array( $raw['labels'], array( 'above', 'beside', 'inside' ), true ) ? $raw['labels'] : 'above',
			'help'   => isset( $raw['help'] ) && 'label' === $raw['help'] ? 'label' : 'below',
			'errors' => isset( $raw['errors'] ) && 'top' === $raw['errors'] ? 'top' : 'field',
		);
	}

	/**
	 * Cleans the integrations (feeds) with the core, which knows the services.
	 *
	 * @param mixed   $raw    Raw feeds.
	 * @param array[] $fields Clean fields (their keys can be mapped).
	 * @return array[]
	 */
	public static function clean_integrations( $raw, array $fields ) {
		if ( ! class_exists( '\\Aluleth\\Core\\Integrations' ) ) {
			return array();
		}
		$keys = array();
		foreach ( $fields as $field ) {
			if ( in_array( $field['type'], self::input_types(), true ) ) {
				$keys[] = $field['id'];
			}
		}
		return \Aluleth\Core\Integrations::clean_feeds( $raw, $keys );
	}

	/**
	 * HTML allowed in labels (consent text with a link to the privacy policy).
	 *
	 * @return array
	 */
	public static function label_html() {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
		);
	}
}
