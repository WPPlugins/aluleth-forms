<?php
/**
 * Server-side validation and cleaning of field values.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Validator.
 */
final class Validator {

	/**
	 * Whether the domain of an email address is made of letters, digits and
	 * hyphens only, in any alphabet. A symbol pasted by mistake ("◘alulethcorp.com",
	 * which the browser sends as "xn--alulethcorp-…") is refused, so the visitor
	 * fixes the address instead of never getting the email.
	 *
	 * @param string $email Email address as sent.
	 * @return bool
	 */
	public static function clean_domain( $email ) {
		$at = strrpos( (string) $email, '@' );
		if ( false === $at ) {
			return true;
		}
		foreach ( explode( '.', strtolower( substr( (string) $email, $at + 1 ) ) ) as $label ) {
			if ( 0 === strpos( $label, 'xn--' ) ) {
				if ( ! function_exists( 'idn_to_utf8' ) ) {
					continue;
				}
				$label = idn_to_utf8( $label, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
				if ( false === $label ) {
					return false;
				}
			}
			if ( '' === $label || ! preg_match( '/^[\p{L}\p{M}\p{N}\-]+$/u', $label ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Writes an accented domain the way mail servers read it
	 * ("ana@münchen.de" → "ana@xn--mnchen-3ya.de"), so it is not stripped.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function ascii_domain( $email ) {
		$at = strrpos( (string) $email, '@' );
		if ( false === $at || ! function_exists( 'idn_to_ascii' ) || ! preg_match( '/[^\x00-\x7F]/', substr( $email, $at + 1 ) ) ) {
			return (string) $email;
		}
		$domain = idn_to_ascii( substr( $email, $at + 1 ), IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
		return false === $domain ? (string) $email : substr( $email, 0, $at + 1 ) . $domain;
	}

	/**
	 * Validates and cleans one value.
	 *
	 * @param array $field Field definition.
	 * @param mixed $raw   Submitted value.
	 * @return mixed|\WP_Error Clean value, or an error with a message for the visitor.
	 */
	public static function field( array $field, $raw ) {
		$type     = $field['type'];
		$required = ! empty( $field['required'] );

		if ( Schema::is_extra( $type ) ) {
			/**
			 * Checks and cleans the value of a field type added by an extension.
			 *
			 * @param mixed|\WP_Error|null $check Clean value or error; null when nobody checked it.
			 * @param array                $field Field.
			 * @param mixed                $raw   Submitted value.
			 */
			$check = apply_filters( 'aluleth_forms/validate_field', null, $field, $raw );
			return null === $check ? new \WP_Error( 'unknown', __( 'This field is not available.', 'aluleth-forms' ) ) : $check;
		}

		if ( 'checkboxes' === $type ) {
			$list  = is_array( $raw ) ? $raw : array();
			$clean = array();
			foreach ( $list as $item ) {
				if ( ! is_scalar( $item ) ) {
					continue;
				}
				$item = sanitize_text_field( (string) $item );
				if ( in_array( $item, $field['options'], true ) ) {
					$clean[] = $item;
				}
			}
			if ( $required && ! $clean ) {
				return new \WP_Error( 'required', General::msg( 'choose', __( 'Please choose at least one option.', 'aluleth-forms' ) ) );
			}
			return array_values( array_unique( $clean ) );
		}

		if ( 'product' === $type ) {
			$labels = wp_list_pluck( $field['items'], 'label' );
			if ( 'single' === $field['mode'] ) {
				return $labels ? $labels[0] : '';
			}
			$list  = is_array( $raw ) ? $raw : ( is_scalar( $raw ) && '' !== (string) $raw ? array( (string) $raw ) : array() );
			$clean = array();
			foreach ( $list as $item ) {
				$item = is_scalar( $item ) ? sanitize_text_field( (string) $item ) : '';
				if ( in_array( $item, $labels, true ) ) {
					$clean[] = $item;
				}
			}
			$clean = array_values( array_unique( $clean ) );
			if ( 'one' === $field['mode'] ) {
				$clean = array_slice( $clean, 0, 1 );
			}
			if ( $required && ! $clean ) {
				return new \WP_Error( 'required', General::msg( 'choose', __( 'Please choose an option.', 'aluleth-forms' ) ) );
			}
			return 'one' === $field['mode'] ? ( $clean ? $clean[0] : '' ) : $clean;
		}

		if ( 'amount' === $type ) {
			$value = is_scalar( $raw ) ? trim( str_replace( array( ' ', "\u{00a0}" ), '', (string) $raw ) ) : '';
			$value = str_replace( ',', '.', $value );
			if ( '' === $value ) {
				return $required ? new \WP_Error( 'required', General::msg( 'required', __( 'This field is required.', 'aluleth-forms' ) ) ) : '';
			}
			if ( ! is_numeric( $value ) ) {
				return new \WP_Error( 'number', General::msg( 'number', __( 'Please enter a number.', 'aluleth-forms' ) ) );
			}
			$amount = round( (float) $value, 2 );
			if ( $amount < (float) $field['min'] ) {
				/* translators: %s: minimum value. */
				return new \WP_Error( 'min', General::msg( 'min', sprintf( __( 'Please enter %s or more.', 'aluleth-forms' ), number_format_i18n( $field['min'], 2 ) ), array( '{value}' => number_format_i18n( $field['min'], 2 ) ) ) );
			}
			if ( null !== $field['max'] && $amount > (float) $field['max'] ) {
				/* translators: %s: maximum value. */
				return new \WP_Error( 'max', General::msg( 'max', sprintf( __( 'Please enter %s or less.', 'aluleth-forms' ), number_format_i18n( $field['max'], 2 ) ), array( '{value}' => number_format_i18n( $field['max'], 2 ) ) ) );
			}
			return number_format( $amount, 2, '.', '' );
		}

		if ( 'consent' === $type ) {
			$given = is_scalar( $raw ) && ! empty( $raw ) && '0' !== (string) $raw;
			if ( $required && ! $given ) {
				return new \WP_Error( 'required', General::msg( 'consent', __( 'Please tick this box to continue.', 'aluleth-forms' ) ) );
			}
			return $given;
		}

		if ( is_array( $raw ) ) {
			$raw = '';
		}
		$value = 'textarea' === $type ? sanitize_textarea_field( (string) $raw ) : sanitize_text_field( (string) $raw );
		$value = trim( $value );

		if ( '' === $value ) {
			if ( $required ) {
				return new \WP_Error( 'required', General::msg( 'required', __( 'This field is required.', 'aluleth-forms' ) ) );
			}
			return '';
		}

		switch ( $type ) {
			case 'email':
				if ( ! self::clean_domain( $value ) ) {
					return new \WP_Error( 'email', General::msg( 'email', __( 'Please enter a valid email address.', 'aluleth-forms' ) ) );
				}
				$email = sanitize_email( self::ascii_domain( $value ) );
				if ( ! is_email( $email ) ) {
					return new \WP_Error( 'email', General::msg( 'email', __( 'Please enter a valid email address.', 'aluleth-forms' ) ) );
				}
				return strtolower( $email );

			case 'phone':
				if ( ! preg_match( '/^[0-9+().\s\-]{5,30}$/', $value ) ) {
					return new \WP_Error( 'phone', General::msg( 'phone', __( 'Please enter a valid phone number.', 'aluleth-forms' ) ) );
				}
				return $value;

			case 'number':
				$number = str_replace( ',', '.', $value );
				if ( ! is_numeric( $number ) ) {
					return new \WP_Error( 'number', General::msg( 'number', __( 'Please enter a number.', 'aluleth-forms' ) ) );
				}
				$number = (float) $number;
				if ( null !== $field['min'] && $number < $field['min'] ) {
					/* translators: %s: minimum value. */
					return new \WP_Error( 'min', General::msg( 'min', sprintf( __( 'Please enter %s or more.', 'aluleth-forms' ), number_format_i18n( $field['min'] ) ), array( '{value}' => number_format_i18n( $field['min'] ) ) ) );
				}
				if ( null !== $field['max'] && $number > $field['max'] ) {
					/* translators: %s: maximum value. */
					return new \WP_Error( 'max', General::msg( 'max', sprintf( __( 'Please enter %s or less.', 'aluleth-forms' ), number_format_i18n( $field['max'] ) ), array( '{value}' => number_format_i18n( $field['max'] ) ) ) );
				}
				return $value;

			case 'date':
				$date = \DateTime::createFromFormat( 'Y-m-d', $value );
				if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
					return new \WP_Error( 'date', General::msg( 'date', __( 'Please enter a valid date.', 'aluleth-forms' ) ) );
				}
				return $value;

			case 'select':
			case 'radio':
				if ( ! in_array( $value, $field['options'], true ) ) {
					return new \WP_Error( 'option', General::msg( 'choose', __( 'Please choose one of the options.', 'aluleth-forms' ) ) );
				}
				return $value;

			case 'text':
			case 'textarea':
				$max = ! empty( $field['maxlength'] ) ? (int) $field['maxlength'] : ( 'text' === $type ? 500 : 20000 );
				if ( function_exists( 'mb_strlen' ) ? mb_strlen( $value ) > $max : strlen( $value ) > $max ) {
					/* translators: %d: maximum number of characters. */
					return new \WP_Error( 'length', General::msg( 'length', sprintf( __( 'Please use %d characters or fewer.', 'aluleth-forms' ), $max ), array( '{max}' => number_format_i18n( $max ) ) ) );
				}
				return $value;
		}

		return substr( $value, 0, 500 );
	}
}
