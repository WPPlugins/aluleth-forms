<?php
/**
 * Receiving a form: spam checks, validation, entry, contact, emails.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Security;
use Aluleth\Core\Util;

defined( 'ABSPATH' ) || exit;

/**
 * Submission handling, for the REST route (JavaScript) and admin-post (no JavaScript).
 */
final class Submission {

	/**
	 * Maximum submissions per visitor in 10 minutes.
	 */
	const RATE_LIMIT = 10;

	/**
	 * Hooks the endpoints.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_post_aluleth_forms_submit', array( __CLASS__, 'no_js' ) );
		add_action( 'admin_post_nopriv_aluleth_forms_submit', array( __CLASS__, 'no_js' ) );
	}

	/**
	 * REST route used by the front-end script.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			'aluleth-forms/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => '__return_true', // Public forms: protected by the time token, honeypot and rate limit.
			)
		);
	}

	/**
	 * Signed time token printed in the form (the time trap against bots).
	 *
	 * @param int $form_id Form id.
	 * @return string
	 */
	public static function token( $form_id ) {
		$time = time();
		return $time . '.' . substr( hash_hmac( 'sha256', $form_id . '|' . $time, wp_salt( 'nonce' ) ), 0, 20 );
	}

	/**
	 * REST callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function rest( $request ) {
		$params = $request->get_body_params();
		if ( ! $params ) {
			$params = (array) $request->get_json_params();
		}
		$result = self::process( $params );
		unset( $result['values'] ); // Only needed to refill the form without JavaScript.
		return new \WP_REST_Response( $result, $result['success'] ? 200 : 422 );
	}

	/**
	 * Handles the admin-post request: processes, then goes back to the page.
	 *
	 * @return void
	 */
	public static function no_js() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public form; protected by the time token, honeypot and rate limit (a nonce would break on cached pages).
		$params             = wp_unslash( $_POST );
		$params             = is_array( $params ) ? $params : array();
		$params['alf_nojs'] = 1;
		$result             = self::process( $params );

		$page = isset( $params['alf_page'] ) ? esc_url_raw( (string) $params['alf_page'] ) : home_url( '/' );
		if ( $result['success'] && ! empty( $result['redirect'] ) ) {
			wp_safe_redirect( $result['redirect'] );
			exit;
		}
		// Only results a visitor needs to see again are kept (success or field errors): requests
		// without a valid form, token or rate are answered without writing anything.
		$key = empty( $result['success'] ) && empty( $result['errors'] ) ? 'x' : strtolower( wp_generate_password( 20, false, false ) );
		if ( 'x' !== $key ) {
			set_transient( 'aluleth_forms_result_' . $key, $result, 10 * MINUTE_IN_SECONDS );
		}
		$url = add_query_arg(
			array(
				'alf_result' => $key,
				'alf_form'   => isset( $params['alf_form'] ) ? absint( $params['alf_form'] ) : 0,
			),
			$page
		);
		wp_safe_redirect( $url . '#alf-' . ( isset( $params['alf_form'] ) ? absint( $params['alf_form'] ) : 0 ) );
		exit;
	}

	/**
	 * Processes a submission.
	 *
	 * @param array $params Raw parameters (unslashed).
	 * @return array{success: bool, message: string, errors?: array, redirect?: string, values?: array}
	 */
	public static function process( array $params ) {
		$ctx = self::prepare( $params );
		if ( isset( $ctx['result'] ) ) {
			return $ctx['result'];
		}
		if ( Payments::has_payment( $ctx['form']['schema'] ) ) {
			/**
			 * Handles the sending of a payment form whose checkout is not the PayPal
			 * buttons (an extension sends the visitor to a store checkout, for example).
			 * Return the answer for the visitor (success, message, redirect), or null.
			 *
			 * @param array|null $result Answer, or null.
			 * @param array      $ctx    Checked submission (form, values, spam, ip_hash, page_url…).
			 */
			$result = apply_filters( 'aluleth_forms/submit_payment_form', null, $ctx );
			if ( is_array( $result ) ) {
				return $result;
			}
			return self::fail( __( 'This form takes a payment: please pay with the buttons at the bottom of the form. They need JavaScript.', 'aluleth-forms' ) );
		}

		// 4b. Blocklist, Akismet, CleanTalk: likely spam is kept aside (status "spam"),
		// with no email, no contact and nothing sent to outside tools. The visitor
		// sees the usual message, so robots learn nothing.
		if ( '' !== $ctx['spam'] ) {
			if ( General::get()['no_store'] ) {
				return $ctx['success']; // Entries are not stored at all: spam is simply dropped.
			}
			$spam_id = Entries::insert( self::row( $ctx, array( 'status' => Entries::SPAM ) ) );
			if ( $spam_id ) {
				/* translators: %s: why the entry was kept aside (blocked word, Akismet…). */
				Entries::add_note( $spam_id, sprintf( __( 'Kept aside as spam: %s.', 'aluleth-forms' ), $ctx['spam'] ) );
			}
			return $ctx['success'];
		}

		// 5. Entry.
		/**
		 * Extension data saved with the entry (a quiz score…), by extension key.
		 *
		 * @param array $extra  Data.
		 * @param array $form   Form (id, title, schema).
		 * @param array $values Clean values.
		 */
		$extra    = (array) apply_filters( 'aluleth_forms/entry_extra', array(), $ctx['form'], $ctx['values'] );
		$entry_id = Entries::insert( self::row( $ctx, $extra ? array( 'extra' => $extra ) : array() ) );
		if ( ! $entry_id ) {
			return self::fail( __( 'Your message could not be saved. Please try again.', 'aluleth-forms' ) );
		}
		self::finish( $ctx['form'], $ctx['values'], $entry_id, $ctx['page_url'] );
		/**
		 * Answer shown to the visitor once the entry is saved (message, redirect,
		 * and any data an extension adds for its own display, like a quiz result).
		 *
		 * @param array $success  success, message, redirect.
		 * @param array $form     Form.
		 * @param array $values   Clean values.
		 * @param int   $entry_id Entry id.
		 * @param array $extra    Extension data saved with the entry.
		 */
		return (array) apply_filters( 'aluleth_forms/success', $ctx['success'], $ctx['form'], $ctx['values'], $entry_id, $extra );
	}

	/**
	 * Files attached to an email of the form (a PDF of the entry…).
	 *
	 * @param string $kind     'notify' (team) or 'confirm' (visitor).
	 * @param array  $form     Form.
	 * @param array  $values   Clean values.
	 * @param int    $entry_id Entry id.
	 * @return string[] File paths.
	 */
	private static function attachments( $kind, array $form, array $values, $entry_id ) {
		/**
		 * Files attached to the email to the team ('notify') or to the visitor ('confirm').
		 *
		 * @param string[] $files    File paths.
		 * @param string   $kind     'notify' or 'confirm'.
		 * @param array    $form     Form.
		 * @param array    $values   Clean values.
		 * @param int      $entry_id Entry id.
		 */
		return array_values( array_filter( (array) apply_filters( 'aluleth_forms/mail_attachments', array(), $kind, $form, $values, (int) $entry_id ), 'is_string' ) );
	}

	/**
	 * Checks a submission (form, honeypot, time, rate, captcha, fields, spam rules).
	 *
	 * @param array $params Raw parameters (unslashed).
	 * @return array Either 'result' (the answer to give right away), or the
	 *               context: form, values, ip_hash, page_url, user_agent, spam (reason or ''), success.
	 */
	public static function prepare( array $params ) {
		$form_id = isset( $params['alf_form'] ) ? absint( $params['alf_form'] ) : 0;
		$form    = Forms::get( $form_id );
		if ( ! $form ) {
			return array( 'result' => self::fail( __( 'This form is no longer available.', 'aluleth-forms' ) ) );
		}
		$schema   = $form['schema'];
		$settings = $schema['settings'];
		$success  = array(
			'success'  => true,
			'message'  => $settings['success_message'],
			'redirect' => $settings['redirect'],
		);

		// 1. Spam: honeypot. Bots get a success message, nothing is saved.
		if ( ! empty( $params['alf_hp'] ) ) {
			return array(
				'result' => $success,
				'bot'    => true,
			);
		}

		// 2. Time trap.
		$token = isset( $params['alf_token'] ) ? (string) $params['alf_token'] : '';
		$check = self::check_token( $form_id, $token, (int) $settings['min_seconds'] );
		if ( 'fast' === $check ) {
			return array(
				'result' => $success,
				'bot'    => true,
			);
		}
		if ( true !== $check ) {
			return array( 'result' => self::fail( __( 'This page is too old. Please reload it and send the form again.', 'aluleth-forms' ) ) );
		}

		// 3. Rate limit per visitor and per form (Aluleth › Settings › Security).
		$ip_hash = Util::ip_hash();
		$limit   = class_exists( '\\Aluleth\\Core\\Security' ) ? (int) Security::get()['per_hour'] : self::RATE_LIMIT;
		$rl_key  = 'aluleth_forms_rl_' . substr( md5( $ip_hash . '|' . $form_id ), 0, 32 );
		$count   = (int) get_transient( $rl_key );
		if ( $count >= max( 1, $limit ) ) {
			return array( 'result' => self::fail( __( 'Too many messages in a short time. Please try again later.', 'aluleth-forms' ) ) );
		}
		set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

		// 3b. Captcha chosen for this form (or the default one).
		$service = class_exists( '\\Aluleth\\Core\\Security' ) ? Security::resolve( isset( $settings['captcha'] ) ? $settings['captcha'] : 'default' ) : '';
		if ( '' !== $service && Security::widget( $service ) ) {
			if ( ! empty( $params['alf_nojs'] ) ) {
				return array( 'result' => self::fail( __( 'This form uses an anti-spam check that needs JavaScript. Please turn JavaScript on and send it again.', 'aluleth-forms' ) ) );
			}
			$verified = Security::verify( $service, isset( $params['alf_captcha'] ) ? (string) $params['alf_captcha'] : '' );
			if ( is_wp_error( $verified ) ) {
				return array( 'result' => self::fail( $verified->get_error_message() ) );
			}
		}

		// 4. Validation.
		$input   = isset( $params['alf'] ) && is_array( $params['alf'] ) ? $params['alf'] : array();
		$visible = Conditions::visibility( $schema, $input );
		$values  = array();
		$errors  = array();
		foreach ( $schema['fields'] as $field ) {
			// Fields hidden by conditional logic are neither required nor saved.
			if ( ! in_array( $field['type'], Schema::input_types(), true ) || empty( $visible[ $field['id'] ] ) ) {
				continue;
			}
			$raw   = isset( $input[ $field['id'] ] ) ? $input[ $field['id'] ] : null;
			$check = Validator::field( $field, $raw );
			if ( is_wp_error( $check ) ) {
				$errors[ $field['id'] ] = $check->get_error_message();
				continue;
			}
			$values[ $field['id'] ] = $check;
		}

		/**
		 * Filters validation errors, so extensions can add their own checks.
		 *
		 * @param array $errors Field id => message.
		 * @param array $values Clean values.
		 * @param array $form   Form (id, title, schema).
		 */
		$errors = (array) apply_filters( 'aluleth_forms/validate', $errors, $values, $form );

		if ( $errors ) {
			$result           = self::fail( __( 'Please check the highlighted fields.', 'aluleth-forms' ) );
			$result['errors'] = $errors;
			$result['values'] = $values;
			return array( 'result' => $result );
		}

		return array(
			'form'       => $form,
			'values'     => $values,
			'ip_hash'    => $ip_hash,
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
			'page_url'   => self::own_url( isset( $params['alf_page'] ) ? (string) $params['alf_page'] : '' ),
			'spam'       => class_exists( '\\Aluleth\\Core\\Security' ) ? Security::spam_reason( self::spam_args( $schema, $values, $service, $token ) ) : '',
			'success'    => $success,
		);
	}

	/**
	 * Entry row from a checked submission.
	 *
	 * @param array $ctx   Context from prepare().
	 * @param array $extra Extra columns (status, payment…).
	 * @return array
	 */
	public static function row( array $ctx, array $extra = array() ) {
		return array_merge(
			array(
				'form_id'    => (int) $ctx['form']['id'],
				'data'       => $ctx['values'],
				'ip_hash'    => $ctx['ip_hash'],
				'user_agent' => $ctx['user_agent'],
				'page_url'   => $ctx['page_url'],
			),
			$extra
		);
	}

	/**
	 * What happens once an entry is saved (and paid, for a payment form):
	 * contact, emails, outside tools, the "entry created" action.
	 *
	 * With $steps (an entry saved in steps by its own code: an order, a crypto
	 * payment), each step is recorded once it is done and skipped when it was done
	 * before, so a finish that was interrupted goes on where it stopped; an email
	 * the mail server refused then throws (the step runs again on the next try).
	 *
	 * @param array             $form     Form.
	 * @param array             $values   Clean values.
	 * @param int               $entry_id Entry id.
	 * @param string            $page_url Page the form was sent from.
	 * @param array             $payment  Payment details (Payments::record()), or empty.
	 * @param Finish_Steps|null $steps    Steps already done (null: everything runs now).
	 * @throws \RuntimeException With $steps, when an email could not be sent.
	 * @return int Contact id.
	 */
	public static function finish( array $form, array $values, $entry_id, $page_url, array $payment = array(), $steps = null ) {
		$settings = $form['schema']['settings'];
		$steps    = $steps instanceof Finish_Steps ? $steps : null;

		// 6. Contact, consent, tags, event.
		$contact_id = Finish_Steps::run(
			$steps,
			'contact',
			static function () use ( $form, $values, $entry_id ) {
				return self::contact( $form, $values, $entry_id );
			}
		);

		// 7. Emails (team, confirmation, receipt: one step each).
		self::notify( $form, $values, $entry_id, $contact_id, $payment, $steps );

		// 8. Outside tools (MailPoet, FluentCRM, webhooks…), queued and sent in the background.
		if ( ! empty( $settings['integrations'] ) && class_exists( '\\Aluleth\\Core\\Integrations' ) ) {
			$payload = self::payload( $form, $values, $entry_id, $page_url, $contact_id );
			if ( $payment ) {
				$payload['payment'] = array(
					'amount'      => $payment['amount'],
					'currency'    => $payment['currency'],
					'status'      => $payment['status'],
					'gateway'     => isset( $payment['gateway'] ) ? (string) $payment['gateway'] : 'paypal',
					'transaction' => isset( $payment['capture_id'] ) ? (string) $payment['capture_id'] : '',
				);
				if ( ! empty( $payment['order']['number'] ) ) {
					$payload['payment']['order'] = (string) $payment['order']['number'];
				}
			}
			Finish_Steps::run(
				$steps,
				'integrations',
				static function () use ( $settings, $payload, $entry_id, $contact_id ) {
					\Aluleth\Core\Integrations::dispatch(
						$settings['integrations'],
						$payload,
						array(
							'object'  => array( 'entry', $entry_id ),
							'contact' => $contact_id,
						)
					);
				}
			);
		}

		Finish_Steps::run(
			$steps,
			'created',
			static function () use ( $entry_id, $form, $values, $contact_id ) {
				/**
				 * Fires after an entry was saved (after the payment, for a payment form).
				 *
				 * @param int   $entry_id   Entry id.
				 * @param array $form       Form (id, title, schema).
				 * @param array $values     Clean values.
				 * @param int   $contact_id Contact id (0 without an email field).
				 */
				do_action( 'aluleth_forms/entry_created', $entry_id, $form, $values, $contact_id );
			}
		);

		// "Do not save entries" (Aluleth › Settings › General): the entry only lived for the emails and
		// sends above. Paid entries are always kept, as the record of the payment.
		if ( General::get()['no_store'] && ! $payment ) {
			Entries::forget( $entry_id );
		}
		return $contact_id;
	}

	/**
	 * Data sent to outside tools (see Aluleth\Core\Integrations).
	 *
	 * @param array  $form     Form.
	 * @param array  $values   Clean values.
	 * @param int    $entry_id Entry id.
	 * @param string $page_url   Page the form was sent from.
	 * @param int    $contact_id Contact id: a contact who unsubscribed is sent without consent.
	 * @return array
	 */
	public static function payload( array $form, array $values, $entry_id, $page_url, $contact_id = 0 ) {
		$payload = array(
			'event'      => 'form.submitted',
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'phone'      => '',
			'consent'    => false,
			'fields'     => array(),
			'source'     => array(
				'type'  => 'form',
				'id'    => (int) $form['id'],
				'title' => (string) $form['title'],
				'entry' => (int) $entry_id,
				'url'   => (string) $page_url,
			),
			'time'       => gmdate( 'c' ),
		);
		foreach ( $form['schema']['fields'] as $field ) {
			if ( ! array_key_exists( $field['id'], $values ) ) {
				continue;
			}
			$value = $values[ $field['id'] ];
			if ( 'email' === $field['type'] && '' === $payload['email'] && '' !== $value ) {
				$payload['email'] = (string) $value;
			}
			if ( 'consent' === $field['type'] ) {
				if ( $field['marketing'] && $value ) {
					$payload['consent'] = true;
				}
				$value = $value ? __( 'Yes', 'aluleth-forms' ) : __( 'No', 'aluleth-forms' );
			}
			$text = Entries::value( $value, $field['type'] );
			if ( '' !== $text ) {
				switch ( $field['map'] ) {
					case 'first_name':
					case 'last_name':
					case 'phone':
						$payload[ $field['map'] ] = $text;
						break;
					case 'full_name':
						$parts                 = preg_split( '/\s+/', trim( $text ), 2 );
						$payload['first_name'] = $parts[0];
						if ( isset( $parts[1] ) ) {
							$payload['last_name'] = $parts[1];
						}
						break;
				}
				if ( 'phone' === $field['type'] && '' === $payload['phone'] ) {
					$payload['phone'] = $text;
				}
			}
			$payload['fields'][ $field['id'] ] = array(
				'label' => wp_strip_all_tags( $field['label'] ),
				'value' => $text,
			);
		}

		if ( $payload['consent'] && $contact_id ) {
			$contact = aluleth_core()->contacts()->get( (int) $contact_id );
			if ( $contact && 'subscribed' !== $contact->status ) {
				$payload['consent'] = false;
			}
		}

		/**
		 * Filters the data sent to outside tools for an entry.
		 *
		 * @param array $payload Payload.
		 * @param array $form    Form.
		 * @param array $values  Clean values.
		 */
		return (array) apply_filters( 'aluleth_forms/integration_payload', $payload, $form, $values );
	}

	/**
	 * Keeps a page address only when it belongs to this site.
	 *
	 * @param string $url Address sent by the browser.
	 * @return string
	 */
	private static function own_url( $url ) {
		$url  = esc_url_raw( $url, array( 'http', 'https' ) );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return $host && strtolower( $host ) === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ? substr( $url, 0, 255 ) : '';
	}

	/**
	 * Whether a form token is genuine and not too old (no minimum time: used to count views).
	 *
	 * @param int    $form_id Form id.
	 * @param string $token   Token.
	 * @return bool
	 */
	public static function valid_token( $form_id, $token ) {
		return true === self::check_token( (int) $form_id, (string) $token, 0 );
	}

	/**
	 * Checks the time token.
	 *
	 * @param int    $form_id     Form id.
	 * @param string $token       Token from the form.
	 * @param int    $min_seconds Minimum seconds a human needs.
	 * @return true|string True when fine, 'fast' when sent too fast, 'invalid' otherwise.
	 */
	private static function check_token( $form_id, $token, $min_seconds ) {
		$parts = explode( '.', $token );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return 'invalid';
		}
		$time     = (int) $parts[0];
		$expected = substr( hash_hmac( 'sha256', $form_id . '|' . $time, wp_salt( 'nonce' ) ), 0, 20 );
		if ( ! hash_equals( $expected, $parts[1] ) ) {
			return 'invalid';
		}
		$age = time() - $time;
		if ( $age < $min_seconds ) {
			return 'fast';
		}
		// Pages can stay in a cache for a long time; 30 days is generous.
		return $age > 30 * DAY_IN_SECONDS ? 'invalid' : true;
	}

	/**
	 * Creates or updates the contact behind the entry.
	 *
	 * @param array $form     Form.
	 * @param array $values   Clean values.
	 * @param int   $entry_id Entry id.
	 * @return int Contact id or 0.
	 */
	private static function contact( array $form, array $values, $entry_id ) {
		$email        = '';
		$data         = array( 'meta' => array() );
		$marketing    = false;
		$consent_text = '';
		foreach ( $form['schema']['fields'] as $field ) {
			$value = isset( $values[ $field['id'] ] ) ? $values[ $field['id'] ] : '';
			if ( 'email' === $field['type'] && '' === $email && '' !== $value ) {
				$email = $value;
			}
			if ( 'consent' === $field['type'] && $field['marketing'] && $value ) {
				$marketing    = true;
				$consent_text = wp_strip_all_tags( $field['label'] );
			}
			if ( '' === $value || array() === $value ) {
				continue;
			}
			switch ( $field['map'] ) {
				case 'first_name':
				case 'last_name':
				case 'phone':
					$data[ $field['map'] ] = (string) $value;
					break;
				case 'full_name':
					$parts              = preg_split( '/\s+/', trim( (string) $value ), 2 );
					$data['first_name'] = $parts[0];
					if ( isset( $parts[1] ) ) {
						$data['last_name'] = $parts[1];
					}
					break;
			}
			if ( 'phone' === $field['type'] && empty( $data['phone'] ) ) {
				$data['phone'] = (string) $value;
			}
		}
		if ( '' === $email ) {
			return 0;
		}

		$core       = aluleth_core();
		$contact_id = $core->contacts()->upsert(
			$email,
			$data,
			array(
				'source'    => 'aluleth-forms',
				'status'    => $marketing ? 'subscribed' : 'transactional',
				'consent'   => $marketing,
				// Anyone can send a public form: never overwrite what we know about an existing contact,
				// and link a WordPress account only when it is the visitor's own.
				'fill_only' => true,
				'link_user' => is_user_logged_in() && Util::email( wp_get_current_user()->user_email ) === Util::email( $email ),
			)
		);
		if ( ! $contact_id ) {
			return 0;
		}
		Entries::set_contact( $entry_id, $contact_id );
		$contact = $core->contacts()->get( $contact_id );
		if ( $marketing && $contact && 'subscribed' === $contact->status ) {
			// Consent is only recorded when it took effect (not for someone who unsubscribed before).
			$core->consents()->record( $contact_id, 'email_marketing', true, 'form:' . $form['id'], $consent_text );
		}
		if ( $form['schema']['settings']['tags'] ) {
			$core->tags()->add( $contact_id, $form['schema']['settings']['tags'] );
		}
		$core->events()->record(
			'form.submitted',
			array(
				'contact' => $contact_id,
				'object'  => array( 'form', $form['id'] ),
				'source'  => 'aluleth-forms',
				'data'    => array(
					'title' => $form['title'],
					'entry' => $entry_id,
				),
			)
		);
		return $contact_id;
	}

	/**
	 * Sends the team notification, the optional confirmation and, for a
	 * payment, the receipt.
	 *
	 * @param array             $form       Form.
	 * @param array             $values     Clean values.
	 * @param int               $entry_id   Entry id.
	 * @param int               $contact_id Contact id.
	 * @param array             $payment    Payment details, or empty.
	 * @param Finish_Steps|null $steps Steps (see finish()).
	 * @return void
	 */
	private static function notify( array $form, array $values, $entry_id, $contact_id, array $payment = array(), $steps = null ) {
		if ( ! $steps instanceof Finish_Steps ) {
			self::notify_steps( $form, $values, $entry_id, $contact_id, $payment, null );
			return;
		}
		add_action( 'wp_mail_failed', array( __CLASS__, 'mail_failed' ) );
		try {
			self::notify_steps( $form, $values, $entry_id, $contact_id, $payment, $steps );
		} finally {
			remove_action( 'wp_mail_failed', array( __CLASS__, 'mail_failed' ) );
		}
	}

	/**
	 * Work of notify().
	 *
	 * @param array             $form       Form.
	 * @param array             $values     Clean values.
	 * @param int               $entry_id   Entry id.
	 * @param int               $contact_id Contact id.
	 * @param array             $payment    Payment, or empty.
	 * @param Finish_Steps|null $steps      Steps.
	 * @return void
	 */
	private static function notify_steps( array $form, array $values, $entry_id, $contact_id, array $payment, $steps ) {
		$settings = $form['schema']['settings'];
		$mail     = aluleth_core()->mail();
		$email    = '';
		$rows     = '';
		foreach ( $form['schema']['fields'] as $field ) {
			if ( ! array_key_exists( $field['id'], $values ) ) {
				continue;
			}
			$value = $values[ $field['id'] ];
			if ( 'email' === $field['type'] && '' === $email ) {
				$email = (string) $value;
			}
			if ( 'consent' === $field['type'] ) {
				$value = $value ? __( 'Yes', 'aluleth-forms' ) : __( 'No', 'aluleth-forms' );
			}
			$value = Entries::value( $value, $field['type'] );
			$rows .= '<tr><td style="padding:8px 12px 8px 0;color:#5b6370;vertical-align:top">' . esc_html( wp_strip_all_tags( $field['label'] ) ) . '</td><td style="padding:8px 0;font-weight:600">' . nl2br( esc_html( '' !== $value ? $value : '—' ) ) . '</td></tr>';
		}
		$pay_rows = '';
		if ( $payment ) {
			foreach ( Payments::lines( $payment ) as $line ) {
				$pay_rows .= '<tr><td style="padding:6px 12px 6px 0;color:#5b6370">' . esc_html( $line['label'] ) . '</td><td style="padding:6px 0;text-align:right">' . esc_html( $line['amount'] ) . '</td></tr>';
			}
			$pay_rows .= '<tr><td style="padding:10px 12px 6px 0;border-top:1px solid #e3e6eb;font-weight:700">' . esc_html__( 'Total paid', 'aluleth-forms' ) . '</td><td style="padding:10px 0 6px;border-top:1px solid #e3e6eb;text-align:right;font-weight:700">' . esc_html( \Aluleth\Core\PayPal::format( $payment['amount'], $payment['currency'] ) ) . '</td></tr>';
			$pay_rows .= '<tr><td colspan="2" style="padding:6px 0;color:#5b6370;font-size:13px">' . esc_html( Payments::paid_with( $payment ) ) . '</td></tr>';
			$pay_rows  = '<table style="border-collapse:collapse;width:100%;margin-top:16px">' . $pay_rows . '</table>';
		}
		$replace = array(
			'{form_title}' => $form['title'],
			'{site_name}'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		/**
		 * Words replaced in the email subjects and in the confirmation message.
		 *
		 * @param array<string, string> $replace  Token => text.
		 * @param array                 $form     Form.
		 * @param array                 $values   Clean values.
		 * @param int                   $entry_id Entry id.
		 */
		$replace = (array) apply_filters( 'aluleth_forms/mail_tokens', $replace, $form, $values, $entry_id );
		/**
		 * Answer rows of the emails (HTML table rows): an extension can add its own.
		 *
		 * @param string $rows     Rows.
		 * @param array  $form     Form.
		 * @param array  $values   Clean values.
		 * @param int    $entry_id Entry id.
		 */
		$rows = (string) apply_filters( 'aluleth_forms/mail_rows', $rows, $form, $values, $entry_id );

		if ( $settings['notify'] ) {
			$to   = Forms::recipients( $settings );
			$link = admin_url( 'admin.php?page=aluleth-forms-entries&entry=' . (int) $entry_id );
			$html = '<h2 style="margin:0 0 16px;font-size:18px">' . esc_html( $form['title'] ) . '</h2><table style="border-collapse:collapse;width:100%">' . $rows . '</table>' . $pay_rows . '<p style="margin:20px 0 0"><a href="' . esc_url( $link ) . '" style="display:inline-block;background:#0e1116;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600">' . esc_html__( 'Open the entry', 'aluleth-forms' ) . '</a></p>';
			Finish_Steps::run(
				$steps,
				'team',
				static function () use ( $mail, $to, $settings, $replace, $html, $email, $form, $values, $entry_id, $steps ) {
					self::$mail_failed = false;
					$mail->send(
						$to,
						strtr( $settings['notify_subject'], $replace ),
						$html,
						array_merge(
							General::sender(),
							array(
								'reply_to'    => $email,
								'attachments' => self::attachments( 'notify', $form, $values, $entry_id ),
							)
						)
					);
					self::sent_or_throw( $steps, __( 'The email to the team could not be sent.', 'aluleth-forms' ) );
				}
			);
		}

		// At most 3 confirmations per address and per hour, so the form cannot be used to send mail to strangers.
		$cap_key = 'aluleth_forms_cf_' . substr( md5( strtolower( $email ) ), 0, 32 );
		if ( $settings['confirm'] && '' !== $email && ! ( $steps && $steps->has( 'confirm' ) ) ) {
			Finish_Steps::run(
				$steps,
				'confirm',
				static function () use ( $mail, $email, $settings, $replace, $rows, $pay_rows, $contact_id, $form, $values, $entry_id, $cap_key, $steps ) {
					$count = (int) get_transient( $cap_key );
					if ( $count >= 3 ) {
						return; // The cap is reached: this step is done without an email.
					}
					$html              = wpautop( esc_html( strtr( $settings['confirm_message'], $replace ) ) ) . '<table style="border-collapse:collapse;width:100%;margin-top:16px">' . $rows . '</table>' . $pay_rows;
					self::$mail_failed = false;
					$mail->send(
						$email,
						strtr( $settings['confirm_subject'], $replace ),
						$html,
						array_merge(
							General::sender(),
							array(
								'contact'     => $contact_id,
								'kind'        => 'transactional',
								'attachments' => self::attachments( 'confirm', $form, $values, $entry_id ),
							)
						)
					);
					set_transient( $cap_key, $count + 1, HOUR_IN_SECONDS );
					self::sent_or_throw( $steps, __( 'The confirmation email could not be sent.', 'aluleth-forms' ) );
				}
			);
		}
		// Receipt to the payer when the form sends no confirmation of its own (Aluleth › Settings › Payments).
		/**
		 * Whether the payer gets the payment receipt (when the form sends no
		 * confirmation of its own). An extension whose store emails its own order
		 * confirmation can turn it off.
		 *
		 * @param bool  $send    Send it (Aluleth › Settings › Payments).
		 * @param array $form    Form.
		 * @param array $payment Payment.
		 */
		$receipt = $payment && (bool) apply_filters( 'aluleth_forms/payment_receipt', ! empty( \Aluleth\Core\PayPal::get()['receipt'] ), $form, $payment );
		if ( $payment && ! $settings['confirm'] && $receipt ) {
			$to = '' !== $email ? $email : ( isset( $payment['payer_email'] ) ? (string) $payment['payer_email'] : '' );
			if ( '' !== $to ) {
				/* translators: %s: site name. */
				$subject = sprintf( __( 'Your payment to %s', 'aluleth-forms' ), $replace['{site_name}'] );
				$html    = '<h2 style="margin:0 0 8px;font-size:18px">' . esc_html__( 'Thank you, your payment was received.', 'aluleth-forms' ) . '</h2><p style="margin:0;color:#5b6370">' . esc_html( $form['title'] . ' · ' . wp_date( get_option( 'date_format' ) ) ) . '</p>' . $pay_rows;
				Finish_Steps::run(
					$steps,
					'receipt',
					static function () use ( $mail, $to, $subject, $html, $contact_id, $steps ) {
						self::$mail_failed = false;
						$mail->send(
							$to,
							$subject,
							$html,
							array_merge(
								General::sender(),
								array(
									'contact' => $contact_id,
									'kind'    => 'transactional',
								)
							)
						);
						self::sent_or_throw( $steps, __( 'The payment receipt could not be sent.', 'aluleth-forms' ) );
					}
				);
			}
		}
	}

	/**
	 * Whether the mail server refused the last email (WordPress "wp_mail_failed").
	 *
	 * @var bool
	 */
	private static $mail_failed = false;

	/**
	 * Notes that the mail server refused an email.
	 *
	 * @return void
	 */
	public static function mail_failed() {
		self::$mail_failed = true;
	}

	/**
	 * For an entry finished in steps, an email the mail server refused stops the
	 * finish here (the step runs again on the next try). An address that cannot get
	 * mail (unsubscribed, not valid) is not a failure. Without steps nothing
	 * happens: a form sent by a visitor never fails because of the mail server.
	 *
	 * @param Finish_Steps|null $steps   Steps.
	 * @param string            $message Message.
	 * @throws \RuntimeException When the email was refused and the finish runs in steps.
	 * @return void
	 */
	private static function sent_or_throw( $steps, $message ) {
		if ( $steps instanceof Finish_Steps && self::$mail_failed ) {
			throw new \RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- an internal message, saved as an entry note.
		}
	}

	/**
	 * Failure result.
	 *
	 * @param string $message Message.
	 * @return array
	 */
	private static function fail( $message ) {
		return array(
			'success' => false,
			'message' => $message,
		);
	}

	/**
	 * What the background spam checks look at: the email, the name and every text answer.
	 *
	 * @param array  $schema  Form schema.
	 * @param array  $values  Clean values.
	 * @param string $service Anti-spam service of the form ('' for none).
	 * @param string $token   Time token (gives the time taken to fill the form).
	 * @return array
	 */
	private static function spam_args( array $schema, array $values, $service, $token ) {
		$email = '';
		$names = array();
		$texts = array();
		foreach ( $schema['fields'] as $field ) {
			if ( ! isset( $values[ $field['id'] ] ) || ! is_scalar( $values[ $field['id'] ] ) ) {
				continue;
			}
			$value = trim( (string) $values[ $field['id'] ] );
			if ( '' === $value ) {
				continue;
			}
			$map = isset( $field['map'] ) ? $field['map'] : '';
			if ( 'email' === $field['type'] ) {
				if ( '' === $email ) {
					$email = $value;
				}
			} elseif ( in_array( $map, array( 'full_name', 'first_name', 'last_name' ), true ) || ( '' === $map && 'name' === $field['id'] ) ) {
				$names[] = $value;
			} elseif ( in_array( $field['type'], array( 'text', 'textarea' ), true ) ) {
				$texts[] = $value;
			}
		}
		$parts = explode( '.', (string) $token );
		return array(
			'email'   => $email,
			'name'    => implode( ' ', $names ),
			'message' => implode( "\n", $texts ),
			'service' => (string) $service,
			'seconds' => ctype_digit( $parts[0] ) ? max( 0, time() - (int) $parts[0] ) : 0,
		);
	}
}
