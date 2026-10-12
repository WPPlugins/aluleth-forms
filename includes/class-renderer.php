<?php
/**
 * Front-end HTML of a form.
 *
 * Lightweight by design: one small stylesheet and one small script, loaded
 * only on pages that show a form, no jQuery. Works without JavaScript too
 * (the form then posts to admin-post.php and comes back to the page).
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\PayPal;

defined( 'ABSPATH' ) || exit;

/**
 * Renderer.
 */
final class Renderer {

	/**
	 * How many times each form was printed on this page (unique ids).
	 *
	 * @var array<int, int>
	 */
	private static $instances = array();

	/**
	 * Whether help texts go under the label of the form being printed.
	 *
	 * @var bool
	 */
	private static $help_top = false;

	/**
	 * Whether the labels of the form being printed sit inside the fields.
	 *
	 * @var bool
	 */
	private static $inside = false;

	/**
	 * Returns the HTML of a form.
	 *
	 * @param int   $form_id Form id.
	 * @param array $args    'preview' (bool) to show drafts to editors,
	 *                       'style' (clean|soft|dark) to override the form's style.
	 * @return string
	 */
	public static function render( $form_id, array $args = array() ) {
		$allow_draft = ! empty( $args['preview'] ) && current_user_can( 'edit_post', (int) $form_id );
		$form        = Forms::get( $form_id, $allow_draft );
		if ( ! $form ) {
			return current_user_can( 'edit_posts' ) ? '<p class="alf-missing">' . esc_html__( 'This form does not exist or is not published.', 'aluleth-forms' ) . '</p>' : '';
		}

		wp_enqueue_style( 'aluleth-forms' );
		wp_enqueue_script( 'aluleth-forms' );
		/**
		 * Fires when a form is shown, so an add-on can load its own scripts.
		 *
		 * @param array $form Form (id, title, schema).
		 */
		do_action( 'aluleth_forms/render_form', $form );

		$id                     = (int) $form['id'];
		self::$instances[ $id ] = isset( self::$instances[ $id ] ) ? self::$instances[ $id ] + 1 : 1;
		$uid                    = 'alf-' . $id . ( self::$instances[ $id ] > 1 ? '-' . self::$instances[ $id ] : '' );
		$schema                 = $form['schema'];
		$settings               = $schema['settings'];
		if ( ! empty( $args['style'] ) && in_array( $args['style'], array( 'clean', 'soft', 'dark' ), true ) ) {
			$settings['style'] = $args['style'];
		}

		// Result of a submission made without JavaScript.
		$notice = self::no_js_result( $id );
		if ( $notice && ! empty( $notice['success'] ) ) {
			return '<div class="alf alf--' . esc_attr( $settings['style'] ) . '"><div class="alf-done" role="status">' . esc_html( $notice['message'] ) . '</div></div>';
		}
		$old    = $notice && ! empty( $notice['values'] ) ? (array) $notice['values'] : array();
		$errors = $notice && ! empty( $notice['errors'] ) ? (array) $notice['errors'] : array();

		$initial = array();
		foreach ( $schema['fields'] as $field ) {
			$initial[ $field['id'] ] = isset( $old[ $field['id'] ] ) ? $old[ $field['id'] ] : ( isset( $field['default'] ) ? $field['default'] : '' );
		}
		$visible = Conditions::visibility( $schema, $initial );

		$steps = array( array() );
		$names = array();
		foreach ( $schema['fields'] as $field ) {
			if ( 'step' === $field['type'] ) {
				if ( $steps[ count( $steps ) - 1 ] ) {
					$steps[] = array();
				}
				$names[ count( $steps ) - 1 ] = $field['label'];
				continue;
			}
			$steps[ count( $steps ) - 1 ][] = $field;
		}
		$multi = count( $steps ) > 1;

		$design         = self::design_css( $id, isset( $settings['design'] ) ? (array) $settings['design'] : array() );
		$html           = '';
		$layout         = Schema::clean_design( isset( $settings['design'] ) ? $settings['design'] : array() );
		self::$help_top = 'label' === $layout['help'] && 'inside' !== $layout['labels'];
		self::$inside   = 'inside' === $layout['labels'];
		$html          .= '<div class="alf alf--' . esc_attr( $settings['style'] ) . ( $design ? ' alf-d-' . esc_attr( $id ) : '' ) . ( 'pill' === $layout['radius'] ? ' alf-pill' : '' ) . ' alf-labels-' . esc_attr( $layout['labels'] ) . ' alf-help-' . esc_attr( $layout['help'] ) . '"' . ( $design ? ' style="' . esc_attr( $design ) . '"' : '' ) . '>';
		$html          .= '<form id="' . esc_attr( $uid ) . '" class="alf-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate data-alf="' . esc_attr( $id ) . '"' . ( current_user_can( 'edit_posts' ) || ! empty( $args['preview'] ) ? ' data-alf-count="0"' : '' ) . ( $settings['redirect'] ? ' data-alf-redirect="' . esc_url( $settings['redirect'] ) . '"' : '' ) . '>';

		// Error summary at the top (Design › Error messages: "listed at the top"), filled by forms.js or below without JavaScript.
		if ( 'top' === $layout['errors'] ) {
			$html .= '<div class="alf-summary" tabindex="-1"' . ( $errors ? '' : ' hidden' ) . '><p class="alf-summary-title">' . esc_html__( 'Please check these fields:', 'aluleth-forms' ) . '</p><ul>';
			foreach ( $errors as $field_id => $message ) {
				foreach ( $schema['fields'] as $field ) {
					if ( $field['id'] === $field_id ) {
						$html .= '<li><a href="#' . esc_attr( $uid . '-' . $field_id ) . '">' . esc_html( wp_strip_all_tags( $field['label'] ) ) . '</a>: ' . esc_html( $message ) . '</li>';
					}
				}
			}
			$html .= '</ul></div>';
		}

		if ( $multi ) {
			$html .= '<ol class="alf-progress" aria-label="' . esc_attr__( 'Form steps', 'aluleth-forms' ) . '">';
			foreach ( $steps as $index => $unused ) {
				$label = isset( $names[ $index ] ) && '' !== $names[ $index ] ? $names[ $index ] : sprintf( /* translators: %d: step number. */ __( 'Step %d', 'aluleth-forms' ), $index + 1 );
				$html .= '<li class="alf-progress-item' . ( 0 === $index ? ' is-current' : '' ) . '"' . ( 0 === $index ? ' aria-current="step"' : '' ) . '><span class="alf-progress-bar"></span><span class="alf-progress-label">' . esc_html( ( $index + 1 ) . ' · ' . wp_strip_all_tags( $label ) ) . '</span></li>';
			}
			$html .= '</ol>';
		}

		foreach ( $steps as $index => $fields ) {
			$html .= '<div class="alf-step"' . ( $multi ? ' data-step="' . esc_attr( $index ) . '"' . ( 0 === $index ? '' : ' hidden' ) : '' ) . '>';
			foreach ( $fields as $field ) {
				$html .= self::wrap_condition( $field, ! empty( $visible[ $field['id'] ] ), self::field( $field, $uid, isset( $old[ $field['id'] ] ) ? $old[ $field['id'] ] : null, isset( $errors[ $field['id'] ] ) ? $errors[ $field['id'] ] : '' ) );
			}
			$html .= '</div>';
		}

		$html .= '<div class="alf-hp" aria-hidden="true"><label>' . esc_html__( 'Leave this field empty', 'aluleth-forms' ) . '<input type="text" name="alf_hp" value="" tabindex="-1" autocomplete="off"></label></div>';
		$html .= '<input type="hidden" name="action" value="aluleth_forms_submit">';
		$html .= '<input type="hidden" name="alf_form" value="' . esc_attr( $id ) . '">';
		$html .= '<input type="hidden" name="alf_token" value="' . esc_attr( Submission::token( $id ) ) . '">';
		$html .= '<input type="hidden" name="alf_page" value="' . esc_url( self::current_url() ) . '">';

		$html .= self::captcha( $settings, ! empty( $args['preview'] ) );

		$html .= '<div class="alf-actions">';
		if ( $multi ) {
			$html .= '<button type="button" class="alf-button alf-button--ghost alf-back" hidden>' . esc_html__( 'Back', 'aluleth-forms' ) . '</button>';
			$html .= '<button type="button" class="alf-button alf-next">' . esc_html__( 'Next step', 'aluleth-forms' ) . '</button>';
		}
		if ( Payments::has_payment( $schema ) ) {
			$html .= self::pay_area( $form, ! empty( $args['preview'] ), $multi );
		} else {
			$html .= '<button type="submit" class="alf-button alf-submit"' . ( $multi ? ' hidden' : '' ) . '>' . esc_html( $settings['submit_label'] ) . '</button>';
		}
		$html .= '</div>';

		$status = $notice && ! empty( $notice['message'] ) ? $notice['message'] : '';
		$html  .= '<div class="alf-status' . ( $status ? ' is-error' : '' ) . '" role="status" aria-live="polite">' . esc_html( $status ) . '</div>';
		$html  .= '</form></div>';
		return $html;
	}

	/**
	 * Adds the conditional-logic attributes to a field's HTML.
	 *
	 * @param array  $field   Field.
	 * @param bool   $visible Visible for the current answers.
	 * @param string $html    Field HTML.
	 * @return string
	 */
	private static function wrap_condition( array $field, $visible, $html ) {
		if ( empty( $field['conditions'] ) || '' === $html ) {
			return $html;
		}
		$attr = ' data-alf-cond="' . esc_attr( wp_json_encode( $field['conditions'] ) ) . '"' . ( $visible ? '' : ' hidden' );
		if ( 'hidden' === $field['type'] ) {
			return '<div class="alf-cond-box" data-field="' . esc_attr( $field['id'] ) . '"' . $attr . '>' . $html . '</div>';
		}
		if ( in_array( $field['type'], array( 'heading', 'paragraph' ), true ) ) {
			return '<div class="alf-cond-box alf-field" data-field="' . esc_attr( $field['id'] ) . '"' . $attr . '>' . $html . '</div>';
		}
		return (string) preg_replace( '/^<div class="([^"]*)" data-field="([^"]*)"/', '<div class="$1" data-field="$2"' . $attr, $html, 1 );
	}

	/**
	 * HTML of one field.
	 *
	 * @param array  $field Field.
	 * @param string $uid   Form element id.
	 * @param mixed  $value Previous value (after a failed submission without JS).
	 * @param string $error Error message for this field.
	 * @return string
	 */
	private static function field( array $field, $uid, $value, $error ) {
		$type = $field['type'];
		if ( ! in_array( $type, array_merge( Schema::input_types(), Schema::display_types() ), true ) ) {
			return ''; // A field of an extension that is no longer active.
		}

		if ( 'heading' === $type ) {
			return '<h3 class="alf-heading">' . wp_kses( $field['label'], Schema::label_html() ) . '</h3>';
		}
		if ( 'paragraph' === $type ) {
			return '<p class="alf-paragraph">' . wp_kses( '' !== $field['text'] ? $field['text'] : $field['label'], Schema::label_html() ) . '</p>';
		}
		if ( Schema::is_extra( $type ) && in_array( $type, Schema::display_types(), true ) ) {
			/** This filter is documented below. */
			return (string) apply_filters(
				'aluleth_forms/render_field',
				'',
				$field,
				array(
					'id'    => $uid . '-' . $field['id'],
					'name'  => '',
					'value' => null,
					'error' => '',
				)
			);
		}
		if ( 'total' === $type ) {
			return '<div class="alf-field alf-field--total"><div class="alf-total" aria-live="polite"><ul class="alf-total-lines"></ul><p class="alf-total-row"><span>' . esc_html( wp_strip_all_tags( $field['label'] ) ) . '</span><strong class="alf-total-value">' . esc_html( PayPal::format( 0 ) ) . '</strong></p></div></div>';
		}

		$fid   = $uid . '-' . $field['id'];
		$name  = 'alf[' . $field['id'] . ']';
		$value = null === $value ? $field['default'] : $value;
		$req   = $field['required'] ? ' required' : '';
		$descr = array();
		if ( '' !== $field['help'] ) {
			$descr[] = $fid . '-help';
		}
		$descr[]  = $fid . '-error';
		$describe = ' aria-describedby="' . esc_attr( implode( ' ', $descr ) ) . '"';
		$invalid  = $error ? ' aria-invalid="true"' : '';
		$mark     = $field['required'] ? '<span class="alf-required" aria-hidden="true">*</span>' : '';

		if ( 'hidden' === $type ) {
			return '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}

		$classes = 'alf-field alf-field--' . $type . ( 'half' === $field['width'] ? ' alf-half' : '' ) . ( 'third' === $field['width'] ? ' alf-third' : '' ) . ( $error ? ' has-error' : '' );
		$out     = '<div class="' . esc_attr( $classes ) . '" data-field="' . esc_attr( $field['id'] ) . '">';

		$label = wp_kses( $field['label'], Schema::label_html() );
		if ( Schema::is_extra( $type ) ) {
			/**
			 * HTML of a field type added by an extension: its label and controls.
			 * The field box, help text and error message are added around it.
			 * Inputs are named "alf[field id]" (ctx name) so they are sent with the form.
			 *
			 * @param string $html  HTML.
			 * @param array  $field Field.
			 * @param array  $ctx   id (element id), name, value (after a failed
			 *                      submission without JavaScript), error, describe
			 *                      (aria-describedby attribute), mark (required star).
			 */
			$out .= (string) apply_filters(
				'aluleth_forms/render_field',
				'',
				$field,
				array(
					'id'       => $fid,
					'name'     => $name,
					'value'    => $value,
					'error'    => $error,
					'describe' => $describe,
					'mark'     => $mark,
					'label'    => $label,
				)
			);
		}
		switch ( Schema::is_extra( $type ) ? '' : $type ) {
			case '':
				break;

			case 'textarea':
				$out .= '<label class="alf-label" for="' . esc_attr( $fid ) . '">' . $label . $mark . '</label>';
				$out .= '<textarea class="alf-input" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" rows="5"' . $req . $describe . $invalid . ( $field['maxlength'] ? ' maxlength="' . esc_attr( $field['maxlength'] ) . '"' : '' ) . self::placeholder( $field ) . '>' . esc_textarea( (string) $value ) . '</textarea>';
				break;

			case 'select':
				$out .= '<label class="alf-label" for="' . esc_attr( $fid ) . '">' . $label . $mark . '</label>';
				$out .= '<select class="alf-input" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '"' . $req . $describe . $invalid . '>';
				$out .= '<option value="">' . esc_html( '' !== $field['placeholder'] ? $field['placeholder'] : __( 'Choose…', 'aluleth-forms' ) ) . '</option>';
				foreach ( $field['options'] as $option ) {
					$out .= '<option value="' . esc_attr( $option ) . '"' . selected( (string) $value, $option, false ) . '>' . esc_html( $option ) . '</option>';
				}
				$out .= '</select>';
				break;

			case 'radio':
			case 'checkboxes':
				$is_radio = 'radio' === $type;
				$values   = is_array( $value ) ? $value : ( '' !== (string) $value ? array( (string) $value ) : array() );
				$out     .= '<fieldset class="alf-choices"' . $describe . ( $field['required'] ? ' data-required="1"' : '' ) . ( $field['required'] && $is_radio ? ' aria-required="true"' : '' ) . '><legend class="alf-label">' . $label . $mark . '</legend>';
				foreach ( $field['options'] as $i => $option ) {
					$out .= '<label class="alf-choice"><input type="' . ( $is_radio ? 'radio' : 'checkbox' ) . '" name="' . esc_attr( $name . ( $is_radio ? '' : '[]' ) ) . '" value="' . esc_attr( $option ) . '"' . ( in_array( $option, $values, true ) ? ' checked' : '' ) . ( $is_radio && $field['required'] && 0 === $i ? ' required' : '' ) . '><span>' . esc_html( $option ) . '</span></label>';
				}
				$out .= '</fieldset>';
				break;

			case 'product':
				if ( 'single' === $field['mode'] ) {
					$item = isset( $field['items'][0] ) ? $field['items'][0] : array(
						'label' => '',
						'price' => 0,
					);
					$out .= '<span class="alf-label">' . $label . '</span><p class="alf-line"><span>' . esc_html( $item['label'] ) . '</span><strong>' . esc_html( PayPal::format( $item['price'] ) ) . '</strong></p>';
					$out .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $item['label'] ) . '" data-price="' . esc_attr( (string) $item['price'] ) . '" data-item="' . esc_attr( $item['label'] ) . '">';
					break;
				}
				$is_one = 'one' === $field['mode'];
				$values = is_array( $value ) ? $value : ( '' !== (string) $value ? array( (string) $value ) : array() );
				$out   .= '<fieldset class="alf-choices alf-prices"' . $describe . ( $field['required'] ? ' data-required="1"' : '' ) . ( $field['required'] && $is_one ? ' aria-required="true"' : '' ) . '><legend class="alf-label">' . $label . $mark . '</legend>';
				foreach ( $field['items'] as $i => $item ) {
					$out .= '<label class="alf-choice alf-price-choice"><input type="' . ( $is_one ? 'radio' : 'checkbox' ) . '" name="' . esc_attr( $name . ( $is_one ? '' : '[]' ) ) . '" value="' . esc_attr( $item['label'] ) . '" data-price="' . esc_attr( (string) $item['price'] ) . '" data-item="' . esc_attr( $item['label'] ) . '"' . ( in_array( $item['label'], $values, true ) ? ' checked' : '' ) . ( $is_one && $field['required'] && 0 === $i ? ' required' : '' ) . '><span class="alf-price-name">' . esc_html( $item['label'] ) . '</span><strong class="alf-price">' . esc_html( ( $is_one ? '' : '+ ' ) . PayPal::format( $item['price'] ) ) . '</strong></label>';
				}
				$out .= '</fieldset>';
				break;

			case 'amount':
				$out .= '<label class="alf-label" for="' . esc_attr( $fid ) . '">' . $label . $mark . '</label>';
				$out .= '<span class="alf-amount"><span class="alf-currency" aria-hidden="true">' . esc_html( PayPal::currency() ) . '</span><input class="alf-input" type="text" inputmode="decimal" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" data-amount="1" data-item="' . esc_attr( wp_strip_all_tags( $field['label'] ) ) . '"' . $req . $describe . $invalid . self::placeholder( $field ) . ' autocomplete="off"></span>';
				break;

			case 'consent':
				$out .= '<label class="alf-consent"><input type="checkbox" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( $value ? ' checked' : '' ) . $req . $describe . $invalid . '><span>' . $label . $mark . '</span></label>';
				break;

			default:
				$types        = array(
					'text'   => 'text',
					'email'  => 'email',
					'phone'  => 'tel',
					'number' => 'number',
					'date'   => 'date',
				);
				$auto         = array(
					'email' => 'email',
					'phone' => 'tel',
				);
				$autocomplete = '' !== $field['autocomplete'] ? $field['autocomplete'] : ( isset( $auto[ $type ] ) ? $auto[ $type ] : '' );
				$extra        = '';
				if ( 'number' === $type ) {
					$extra .= null !== $field['min'] ? ' min="' . esc_attr( $field['min'] ) . '"' : '';
					$extra .= null !== $field['max'] ? ' max="' . esc_attr( $field['max'] ) . '"' : '';
					$extra .= ' step="any" inputmode="decimal"';
				}
				if ( 'text' === $type && $field['maxlength'] ) {
					$extra .= ' maxlength="' . esc_attr( $field['maxlength'] ) . '"';
				}
				$out .= '<label class="alf-label" for="' . esc_attr( $fid ) . '">' . $label . $mark . '</label>';
				$out .= '<input class="alf-input" type="' . esc_attr( $types[ $type ] ) . '" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . $req . $describe . $invalid . $extra . ( '' !== $autocomplete ? ' autocomplete="' . esc_attr( $autocomplete ) . '"' : '' ) . self::placeholder( $field ) . '>';
		}

		if ( '' !== $field['help'] ) {
			$help = '<p class="alf-help" id="' . esc_attr( $fid . '-help' ) . '">' . esc_html( $field['help'] ) . '</p>';
			$end  = in_array( $type, array( 'radio', 'checkboxes' ), true ) ? '</legend>' : '</label>';
			$at   = 'consent' !== $type && self::$help_top ? strpos( $out, $end ) : false;
			// Help text under the label (Design › Help text), else under the field.
			$out = false !== $at ? substr_replace( $out, $end . $help, $at, strlen( $end ) ) : $out . $help;
		}
		$out .= '<p class="alf-error" id="' . esc_attr( $fid . '-error' ) . '"' . ( $error ? '' : ' hidden' ) . '>' . esc_html( $error ) . '</p>';
		$out .= '</div>';
		return $out;
	}

	/**
	 * Payment buttons area (replaces the send button of a payment form).
	 *
	 * @param array $form    Form.
	 * @param bool  $preview Builder or editor preview.
	 * @param bool  $multi   Form in several steps (hidden until the last one).
	 * @return string
	 */
	private static function pay_area( array $form, $preview, $multi ) {
		$hidden = $multi ? ' hidden' : '';
		/**
		 * Replaces the PayPal buttons of a payment form (an extension shows a
		 * "Continue to checkout" button instead, for example). Return null to keep them.
		 * Keep the classes "alf-submit" (and "alf-pay" for the PayPal flow).
		 *
		 * @param string|null $html    HTML, or null.
		 * @param array       $form    Form.
		 * @param bool        $preview Builder or editor preview.
		 * @param bool        $multi   Form in several steps.
		 */
		$custom = apply_filters( 'aluleth_forms/pay_area', null, $form, $preview, $multi );
		if ( is_string( $custom ) ) {
			return $custom;
		}
		$currency = Payments::currency( $form['schema'] );
		/**
		 * More ways to pay shown under the PayPal buttons (an add-on adds a
		 * "Pay with crypto" button, for example). Buttons must be type="button".
		 *
		 * @param string $html    HTML.
		 * @param array  $form    Form.
		 * @param bool   $preview Builder or editor preview.
		 */
		$more = (string) apply_filters( 'aluleth_forms/pay_methods', '', $form, $preview );
		if ( $preview ) {
			return '<div class="alf-pay alf-pay--preview alf-submit"' . $hidden . '><span>' . esc_html__( 'PayPal buttons: PayPal, Pay Later, card', 'aluleth-forms' ) . '</span></div>';
		}
		if ( ! PayPal::ready() && '' !== $more && ! $preview ) {
			return '<div class="alf-pay alf-submit"' . $hidden . ' data-currency="' . esc_attr( $currency ) . '" data-locale="' . esc_attr( str_replace( '_', '-', get_locale() ) ) . '">' . $more . '</div>';
		}
		if ( ! PayPal::ready() ) {
			$out = '<div class="alf-pay alf-pay--off alf-submit"' . $hidden . '><p>' . esc_html__( 'Payments are not available at the moment.', 'aluleth-forms' ) . '</p>';
			if ( current_user_can( \Aluleth\Core\Admin::cap( 'settings' ) ) ) {
				$out .= '<p><a href="' . esc_url( \Aluleth\Core\Settings::url( 'payments' ) ) . '">' . esc_html__( 'Set up PayPal (only you see this link)', 'aluleth-forms' ) . '</a></p>';
			}
			return $out . '</div>';
		}
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- PayPal's own script, served and versioned by PayPal.
		wp_enqueue_script( 'aluleth-paypal', PayPal::script_url( $currency ), array(), null, true );
		$style = PayPal::button_style();
		return '<div class="alf-pay alf-submit"' . $hidden . ' data-currency="' . esc_attr( $currency ) . '" data-locale="' . esc_attr( str_replace( '_', '-', get_locale() ) ) . '" data-color="' . esc_attr( $style['color'] ) . '" data-shape="' . esc_attr( $style['shape'] ) . '"><div class="alf-paypal"></div><p class="alf-pay-note">' . esc_html( PayPal::sandbox() ? __( 'Test mode: no real money is taken.', 'aluleth-forms' ) : __( 'Secure payment with PayPal. Your card details never reach this site.', 'aluleth-forms' ) ) . '</p>' . $more . '<noscript><p>' . esc_html__( 'Payments need JavaScript. Please turn it on.', 'aluleth-forms' ) . '</p></noscript></div>';
	}

	/**
	 * Placeholder attribute. With labels inside the fields, an empty field gets
	 * a single space so the label can sit in it until the visitor types.
	 *
	 * @param array $field Field.
	 * @return string
	 */
	private static function placeholder( array $field ) {
		if ( '' !== $field['placeholder'] ) {
			return ' placeholder="' . esc_attr( $field['placeholder'] ) . '"';
		}
		return self::$inside ? ' placeholder=" "' : '';
	}

	/**
	 * Captcha widget of the form's anti-spam service (Aluleth › Settings › Security).
	 * The service's script is loaded only on pages that show such a form.
	 *
	 * @param array $settings Form settings.
	 * @param bool  $preview  Builder or editor preview: a placeholder instead of the widget.
	 * @return string
	 */
	private static function captcha( array $settings, $preview ) {
		if ( ! class_exists( '\\Aluleth\\Core\\Security' ) ) {
			return '';
		}
		$service = \Aluleth\Core\Security::resolve( isset( $settings['captcha'] ) ? $settings['captcha'] : 'default' );
		$widget  = '' !== $service ? \Aluleth\Core\Security::widget( $service ) : null;
		if ( ! $widget ) {
			return '';
		}
		if ( $preview ) {
			$names = \Aluleth\Core\Security::services();
			/* translators: %s: anti-spam service name. */
			return '<div class="alf-captcha alf-captcha--preview">' . esc_html( sprintf( __( 'Anti-spam check (%s)', 'aluleth-forms' ), $names[ $service ]['label'] ) ) . '</div>';
		}
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the service's own script, served and versioned by the service.
		wp_enqueue_script( 'aluleth-captcha-' . $service, $widget['script'], array(), null, true );
		$attrs = ' data-provider="' . esc_attr( $service ) . '" data-sitekey="' . esc_attr( $widget['site'] ) . '"';
		if ( isset( $widget['appearance'] ) ) {
			$attrs .= ' data-appearance="' . esc_attr( $widget['appearance'] ) . '"';
		}
		if ( isset( $widget['version'] ) ) {
			$attrs .= ' data-version="' . esc_attr( $widget['version'] ) . '"';
		}
		return '<div class="alf-captcha"' . $attrs . '></div><input type="hidden" name="alf_captcha" value=""><noscript><p class="alf-help">' . esc_html__( 'This form needs JavaScript for its anti-spam check.', 'aluleth-forms' ) . '</p></noscript>';
	}

	/**
	 * Result of a submission made without JavaScript, if any.
	 *
	 * @param int $form_id Form id.
	 * @return array|null
	 */
	private static function no_js_result( $form_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a random key only reads a short-lived result.
		if ( empty( $_GET['alf_result'] ) || empty( $_GET['alf_form'] ) || (int) $_GET['alf_form'] !== (int) $form_id ) {
			return null;
		}
		$key = sanitize_key( wp_unslash( $_GET['alf_result'] ) );
		// phpcs:enable
		if ( 'x' === $key ) {
			return array(
				'success' => false,
				'message' => __( 'Your message could not be sent: the page may be too old, or too many messages were sent. Please reload the page and try again in a few minutes.', 'aluleth-forms' ),
			);
		}
		$result = get_transient( 'aluleth_forms_result_' . $key );
		return is_array( $result ) ? $result : null;
	}

	/**
	 * URL of the page being shown.
	 *
	 * @return string
	 */
	private static function current_url() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$home = wp_parse_url( home_url() );
		$base = ( isset( $home['scheme'] ) ? $home['scheme'] : 'https' ) . '://' . ( isset( $home['host'] ) ? $home['host'] : '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		return $base . remove_query_arg( array( 'alf_result', 'alf_form' ), $uri );
	}

	/**
	 * CSS declarations of a form's design settings (accent colour, corners, font), for the form's style attribute.
	 *
	 * Uses the site's CSS variables when a site colour or font was chosen, so the
	 * form follows the site when its colours change.
	 *
	 * @param int   $id     Form id.
	 * @param array $design Clean design settings.
	 * @return string Declarations, empty when nothing is set.
	 */
	public static function design_css( $id, array $design ) {
		if ( ! class_exists( '\\Aluleth\\Core\\Site_Style' ) ) {
			return '';
		}
		$vars = array();
		if ( ! empty( $design['accent'] ) ) {
			$color = \Aluleth\Core\Site_Style::color_css( $design['accent'] );
			if ( $color ) {
				$vars['--alf-accent']    = $color['css'];
				$vars['--alf-focus']     = $color['css'];
				$vars['--alf-on-accent'] = \Aluleth\Core\Site_Style::on( $color['hex'] );
			}
		}
		$radius = array(
			'square'  => '4px',
			'rounded' => '10px',
			'pill'    => '24px',
		);
		if ( ! empty( $design['radius'] ) && isset( $radius[ $design['radius'] ] ) ) {
			$vars['--alf-radius'] = $radius[ $design['radius'] ];
		}
		$css = '';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}
		if ( ! empty( $design['font'] ) ) {
			$font = \Aluleth\Core\Site_Style::font_css( $design['font'] );
			if ( '' !== $font ) {
				$css .= 'font-family:' . $font . ';';
			}
		}
		if ( '' === $css ) {
			return '';
		}
		// Values come from the clean settings and the site's sanitised colours and fonts.
		return str_replace( array( '<', '>', '{', '}' ), '', $css );
	}
}
