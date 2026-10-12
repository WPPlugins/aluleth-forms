<?php
/**
 * Payment forms: price fields, total, and the PayPal flow.
 *
 * 1. The visitor clicks a PayPal button: the form is checked like any other
 *    submission, the total is computed on the server from the form's own
 *    prices, and a PayPal order is created. The answers wait in a short-lived
 *    transient (nothing is saved as an entry yet).
 * 2. The visitor approves the payment in PayPal's window: the site captures
 *    the order, checks the amount, then saves the entry as paid and only then
 *    sends the emails and the connections.
 * 3. Safety net: if the visitor closes the page between PayPal and the site,
 *    the daily task asks PayPal about the orders still waiting and finishes
 *    the paid ones.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\PayPal;

defined( 'ABSPATH' ) || exit;

/**
 * Payments.
 */
final class Payments {

	/**
	 * Prefix of the transients holding the answers while PayPal is open.
	 */
	const PENDING = 'aluleth_forms_pay_';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'aluleth/settings/payments', '__return_true' );
		add_action( General::CRON, array( __CLASS__, 'reconcile' ) );
	}

	/**
	 * REST routes (public, like the form submission; protected by the same checks).
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			'aluleth-forms/v1',
			'/pay/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_create' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'aluleth-forms/v1',
			'/pay/capture',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_capture' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Whether a form takes a payment.
	 *
	 * @param array $schema Form schema.
	 * @return bool
	 */
	public static function has_payment( array $schema ) {
		foreach ( $schema['fields'] as $field ) {
			if ( in_array( $field['type'], Schema::payment_types(), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Currency of a payment form: the one of Aluleth › Settings › Payments,
	 * unless an extension prices the form in another one (a store's currency).
	 *
	 * @param array $schema Form schema.
	 * @return string ISO code.
	 */
	public static function currency( array $schema ) {
		/**
		 * Currency of a payment form.
		 *
		 * @param string $currency ISO code.
		 * @param array  $schema   Form schema.
		 */
		$currency = strtoupper( (string) apply_filters( 'aluleth_forms/payment_currency', PayPal::currency(), $schema ) );
		return preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : PayPal::currency();
	}

	/**
	 * How a payment was made, for people ("PayPal", or the store's payment method).
	 *
	 * @param array $payment Payment.
	 * @return string
	 */
	public static function method_label( array $payment ) {
		if ( ! empty( $payment['method'] ) ) {
			return (string) $payment['method'];
		}
		return isset( $payment['gateway'] ) && 'paypal' !== $payment['gateway'] ? (string) $payment['gateway'] : 'PayPal';
	}

	/**
	 * Lines of a payment for people: items (with quantities), discount, tax.
	 *
	 * @param array $payment Payment.
	 * @return array[] Each: label, amount (formatted).
	 */
	public static function lines( array $payment ) {
		$currency = isset( $payment['currency'] ) ? (string) $payment['currency'] : '';
		$out      = array();
		foreach ( isset( $payment['items'] ) ? (array) $payment['items'] : array() as $item ) {
			$qty   = max( 1, isset( $item['quantity'] ) ? (int) $item['quantity'] : 1 );
			$out[] = array(
				/* translators: 1: item name, 2: quantity. */
				'label'  => $qty > 1 ? sprintf( __( '%1$s × %2$d', 'aluleth-forms' ), $item['name'], $qty ) : (string) $item['name'],
				'amount' => PayPal::format( (float) $item['price'] * $qty, $currency ),
			);
		}
		if ( ! empty( $payment['discount'] ) && (float) $payment['discount'] > 0 ) {
			$out[] = array(
				'label'  => __( 'Discount', 'aluleth-forms' ),
				'amount' => '−' . PayPal::format( (float) $payment['discount'], $currency ),
			);
		}
		if ( ! empty( $payment['tax'] ) && (float) $payment['tax'] > 0 ) {
			$out[] = array(
				'label'  => __( 'Tax', 'aluleth-forms' ),
				'amount' => PayPal::format( (float) $payment['tax'], $currency ),
			);
		}
		return $out;
	}

	/**
	 * "Paid with PayPal · transaction …" line.
	 *
	 * @param array $payment Payment.
	 * @return string
	 */
	public static function paid_with( array $payment ) {
		$text = ! empty( $payment['capture_id'] )
			/* translators: 1: payment method (PayPal…), 2: transaction id. */
			? sprintf( __( 'Paid with %1$s · transaction %2$s', 'aluleth-forms' ), self::method_label( $payment ), $payment['capture_id'] )
			/* translators: %s: payment method (PayPal, card…). */
			: sprintf( __( 'Paid with %s', 'aluleth-forms' ), self::method_label( $payment ) );
		if ( ! empty( $payment['order']['number'] ) ) {
			/* translators: %s: order number. */
			$text .= ' · ' . sprintf( __( 'order #%s', 'aluleth-forms' ), $payment['order']['number'] );
		}
		return $text . ( ! empty( $payment['sandbox'] ) ? ' · ' . __( 'test payment', 'aluleth-forms' ) : '' );
	}

	/**
	 * Items, total, tax and discount of a submission, from the form's own prices.
	 *
	 * @param array $schema Form schema.
	 * @param array $values Clean values (hidden fields already left out).
	 * @return array{items: array, total: float, tax: float, discount: float, currency: string, meta: array}
	 */
	public static function compute( array $schema, array $values ) {
		$items = array();
		$total = 0.0;
		foreach ( $schema['fields'] as $field ) {
			if ( ! array_key_exists( $field['id'], $values ) ) {
				continue;
			}
			$value = $values[ $field['id'] ];
			if ( 'product' === $field['type'] ) {
				$chosen = is_array( $value ) ? $value : ( '' !== (string) $value ? array( (string) $value ) : array() );
				foreach ( $field['items'] as $item ) {
					if ( in_array( $item['label'], $chosen, true ) ) {
						$items[] = array(
							'name'     => $item['label'],
							'price'    => (float) $item['price'],
							'quantity' => 1,
						);
						$total  += (float) $item['price'];
					}
				}
			} elseif ( 'amount' === $field['type'] && '' !== (string) $value ) {
				$items[] = array(
					'name'     => wp_strip_all_tags( $field['label'] ),
					'price'    => (float) $value,
					'quantity' => 1,
				);
				$total  += (float) $value;
			}
		}
		$items = array_values(
			array_filter(
				$items,
				static function ( $item ) {
					return $item['price'] > 0;
				}
			)
		);
		$cart  = array(
			'items'    => $items,
			'total'    => round( $total, 2 ),
			'tax'      => 0.0,
			'discount' => 0.0,
			'currency' => self::currency( $schema ),
			'meta'     => array(),
		);
		/**
		 * Lets an extension price its own fields. Items are name, price (per
		 * unit) and quantity; total = items + tax - discount. "meta" is kept with
		 * the pending payment and passed back to aluleth_forms/payment_saved.
		 *
		 * @param array $cart   Items, total, tax, discount, currency, meta.
		 * @param array $schema Form schema.
		 * @param array $values Clean values.
		 */
		$cart             = (array) apply_filters( 'aluleth_forms/payment_cart', $cart, $schema, $values );
		$cart['items']    = isset( $cart['items'] ) ? array_values( (array) $cart['items'] ) : array();
		$cart['tax']      = isset( $cart['tax'] ) ? max( 0, round( (float) $cart['tax'], 2 ) ) : 0.0;
		$cart['discount'] = isset( $cart['discount'] ) ? max( 0, round( (float) $cart['discount'], 2 ) ) : 0.0;
		$cart['meta']     = isset( $cart['meta'] ) ? (array) $cart['meta'] : array();
		$cart['currency'] = isset( $cart['currency'] ) && preg_match( '/^[A-Z]{3}$/', (string) $cart['currency'] ) ? (string) $cart['currency'] : PayPal::currency();
		$sum              = 0.0;
		foreach ( $cart['items'] as $index => $item ) {
			$cart['items'][ $index ]['quantity'] = max( 1, isset( $item['quantity'] ) ? (int) $item['quantity'] : 1 );
			$sum                                += (float) $item['price'] * $cart['items'][ $index ]['quantity'];
		}
		$cart['total'] = round( max( 0, $sum + $cart['tax'] - $cart['discount'] ), 2 );
		return $cart;
	}

	/**
	 * Key that ties a pending payment to the visitor's page.
	 *
	 * @param string $order_id PayPal order id.
	 * @return string
	 */
	private static function key( $order_id ) {
		return substr( hash_hmac( 'sha256', 'alf-pay|' . $order_id, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Step 1: checks the form and creates the PayPal order.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function rest_create( $request ) {
		$params = $request->get_body_params();
		if ( ! $params ) {
			$params = (array) $request->get_json_params();
		}
		$result = self::create( $params );
		return new \WP_REST_Response( $result, $result['success'] ? 200 : 422 );
	}

	/**
	 * Step 1, without the REST layer.
	 *
	 * @param array $params Raw parameters (unslashed).
	 * @return array
	 */
	public static function create( array $params ) {
		if ( ! PayPal::ready() ) {
			return self::fail( __( 'Payments are not available at the moment. Please try again later.', 'aluleth-forms' ) );
		}
		$ctx = Submission::prepare( $params );
		if ( isset( $ctx['result'] ) ) {
			// A robot (honeypot, too fast) gets no order; field errors are shown as usual.
			return ! empty( $ctx['bot'] ) ? self::fail( __( 'Your payment could not be started. Please reload the page and try again.', 'aluleth-forms' ) ) : array_diff_key( $ctx['result'], array( 'values' => 1 ) );
		}
		if ( ! self::has_payment( $ctx['form']['schema'] ) ) {
			return self::fail( __( 'This form takes no payment.', 'aluleth-forms' ) );
		}
		if ( '' !== $ctx['spam'] ) {
			return self::fail( __( 'Your payment could not be started. Please contact us another way.', 'aluleth-forms' ) );
		}
		/**
		 * Whether this form is paid with the PayPal buttons (an extension may send
		 * the visitor to another checkout instead).
		 *
		 * @param bool  $allowed Allowed.
		 * @param array $form    Form.
		 */
		if ( ! apply_filters( 'aluleth_forms/paypal_allowed', true, $ctx['form'] ) ) {
			return self::fail( __( 'This form takes no payment.', 'aluleth-forms' ) );
		}
		$cart = self::compute( $ctx['form']['schema'], $ctx['values'] );
		if ( is_wp_error( isset( $cart['error'] ) ? $cart['error'] : null ) ) {
			return self::fail( $cart['error']->get_error_message() );
		}
		if ( $cart['total'] <= 0 || ! $cart['items'] ) {
			return self::fail( __( 'Please choose what you want to pay for.', 'aluleth-forms' ) );
		}
		if ( ! PayPal::supports( $cart['currency'] ) ) {
			return self::fail( __( 'Payments are not available at the moment. Please try again later.', 'aluleth-forms' ) );
		}

		$reference = 'alf-' . $ctx['form']['id'] . '-' . strtolower( wp_generate_password( 12, false, false ) );
		$order     = PayPal::create_order(
			array(
				'reference'   => $reference,
				'description' => $ctx['form']['title'],
				'items'       => $cart['items'],
				'tax'         => $cart['tax'],
				'discount'    => $cart['discount'],
				'currency'    => $cart['currency'],
				'request'     => $reference,
			)
		);
		if ( is_wp_error( $order ) || empty( $order['id'] ) ) {
			return self::fail( __( 'PayPal could not start the payment. Please try again in a moment.', 'aluleth-forms' ) );
		}

		set_transient(
			self::PENDING . $order['id'],
			array(
				'form_id'    => (int) $ctx['form']['id'],
				'values'     => $ctx['values'],
				'ip_hash'    => $ctx['ip_hash'],
				'user_agent' => $ctx['user_agent'],
				'page_url'   => $ctx['page_url'],
				'items'      => $cart['items'],
				'total'      => $cart['total'],
				'tax'        => $cart['tax'],
				'discount'   => $cart['discount'],
				'meta'       => $cart['meta'],
				'currency'   => $cart['currency'],
				'sandbox'    => PayPal::sandbox(),
				'reference'  => $reference,
				'created'    => time(),
				'entry_id'   => 0,
			),
			2 * DAY_IN_SECONDS
		);

		return array(
			'success' => true,
			'orderID' => (string) $order['id'],
			'key'     => self::key( (string) $order['id'] ),
		);
	}

	/**
	 * Step 2: captures the order and saves the paid entry.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function rest_capture( $request ) {
		$params = $request->get_json_params();
		if ( ! $params ) {
			$params = $request->get_body_params();
		}
		$params = is_array( $params ) ? $params : array();
		$order  = isset( $params['orderID'] ) ? preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $params['orderID'] ) ) : '';
		$key    = isset( $params['key'] ) ? (string) $params['key'] : '';
		if ( '' === $order || ! hash_equals( self::key( $order ), $key ) ) {
			return new \WP_REST_Response( self::fail( __( 'This payment could not be found.', 'aluleth-forms' ) ), 422 );
		}
		$result = self::complete( $order );
		return new \WP_REST_Response( $result, $result['success'] ? 200 : 422 );
	}

	/**
	 * Captures a pending order and saves the paid entry (once).
	 *
	 * @param string $order_id PayPal order id.
	 * @param bool   $capture  Capture it (false: only read it, for the daily check).
	 * @return array
	 */
	public static function complete( $order_id, $capture = true ) {
		$pending = get_transient( self::PENDING . $order_id );
		if ( ! is_array( $pending ) ) {
			return self::fail( __( 'This payment could not be found. If money was taken, please contact us.', 'aluleth-forms' ) );
		}
		$form = Forms::get( (int) $pending['form_id'], true );
		if ( ! $form ) {
			return self::fail( __( 'This form is no longer available.', 'aluleth-forms' ) );
		}
		$success = array(
			'success'  => true,
			'message'  => $form['schema']['settings']['success_message'],
			'redirect' => $form['schema']['settings']['redirect'],
		);
		if ( ! empty( $pending['entry_id'] ) ) {
			return self::success( $success, $form, (int) $pending['entry_id'] ); // Already done (double click, or the daily check got there first).
		}

		// One capture at a time for this order.
		$lock = 'aluleth_forms_paylock_' . md5( $order_id );
		if ( get_transient( $lock ) ) {
			return self::fail( __( 'Your payment is being confirmed. Please wait a moment.', 'aluleth-forms' ) );
		}
		set_transient( $lock, 1, MINUTE_IN_SECONDS );

		$order = $capture ? PayPal::capture( $order_id ) : PayPal::get_order( $order_id );
		if ( is_wp_error( $order ) ) {
			delete_transient( $lock );
			$issue = $order->get_error_message();
			if ( 'INSTRUMENT_DECLINED' === $issue ) {
				return array_merge( self::fail( __( 'The payment was declined. Please choose another way to pay.', 'aluleth-forms' ) ), array( 'retry' => true ) );
			}
			return self::fail( __( 'PayPal could not confirm the payment. You have not been charged; please try again.', 'aluleth-forms' ) );
		}
		$paid = PayPal::summary( $order );
		if ( 'COMPLETED' !== $paid['status'] ) {
			delete_transient( $lock );
			return self::fail( 'PENDING' === $paid['status'] ? __( 'PayPal is still checking this payment. You will get an email once it is confirmed.', 'aluleth-forms' ) : __( 'The payment was not completed.', 'aluleth-forms' ) );
		}

		$expected = (float) PayPal::value( $pending['total'], $pending['currency'] );
		$match    = abs( $paid['amount'] - $expected ) < 0.005 && $paid['currency'] === $pending['currency'] && $paid['reference'] === $pending['reference'];

		$payment = array(
			'status'      => $match ? 'paid' : 'check',
			'gateway'     => 'paypal',
			'amount'      => $paid['amount'],
			'currency'    => $paid['currency'],
			'items'       => $pending['items'],
			'tax'         => isset( $pending['tax'] ) ? (float) $pending['tax'] : 0.0,
			'discount'    => isset( $pending['discount'] ) ? (float) $pending['discount'] : 0.0,
			'order_id'    => $order_id,
			'capture_id'  => $paid['capture_id'],
			'payer_email' => $paid['payer_email'],
			'payer_name'  => $paid['payer_name'],
			'sandbox'     => ! empty( $pending['sandbox'] ),
			'paid_at'     => gmdate( 'Y-m-d H:i:s' ),
		);

		// Marked as done before the entry is saved, so the daily check never saves it twice.
		$pending['entry_id'] = -1;
		set_transient( self::PENDING . $order_id, $pending, DAY_IN_SECONDS );
		$note = $match ? '' : sprintf(
			/* translators: 1: amount paid, 2: amount expected. */
			__( 'Check this payment in PayPal: %1$s received, %2$s expected.', 'aluleth-forms' ),
			PayPal::format( $paid['amount'], $paid['currency'] ),
			PayPal::format( $pending['total'], $pending['currency'] )
		);
		$entry_id            = self::save_paid( $form, $pending, $payment, $note );
		$pending['entry_id'] = $entry_id ? $entry_id : -1;
		set_transient( self::PENDING . $order_id, $pending, DAY_IN_SECONDS );
		delete_transient( $lock );
		return self::success( $success, $form, $entry_id );
	}

	/**
	 * Answer after a payment, with the message of the form.
	 *
	 * @param array $success  Answer.
	 * @param array $form     Form.
	 * @param int   $entry_id Entry id (0 or less: unknown).
	 * @return array
	 */
	private static function success( array $success, array $form, $entry_id ) {
		/**
		 * Message shown after a payment (an extension may add the order number).
		 *
		 * @param string $message  Message.
		 * @param array  $form     Form.
		 * @param int    $entry_id Entry id.
		 */
		$success['message'] = (string) apply_filters( 'aluleth_forms/payment_message', $success['message'], $form, (int) $entry_id );
		return $success;
	}

	/**
	 * Saves a paid entry, then sends the emails and the connections.
	 * Used by the PayPal flow, and by extensions whose checkout happens elsewhere.
	 *
	 * @param array  $form    Form (id, title, schema).
	 * @param array  $pending Answers: values, ip_hash, user_agent, page_url, meta.
	 * @param array  $payment Payment: status, gateway, amount, currency, items.
	 * @param string $note    Note added to the entry (empty: none).
	 * @return int Entry id (0 on failure).
	 */
	public static function save_paid( array $form, array $pending, array $payment, $note = '' ) {
		$entry_id = Entries::insert(
			array(
				'form_id'    => (int) $form['id'],
				'data'       => (array) $pending['values'],
				'ip_hash'    => isset( $pending['ip_hash'] ) ? (string) $pending['ip_hash'] : '',
				'user_agent' => isset( $pending['user_agent'] ) ? (string) $pending['user_agent'] : '',
				'page_url'   => isset( $pending['page_url'] ) ? (string) $pending['page_url'] : '',
				'payment'    => $payment,
			)
		);
		if ( ! $entry_id ) {
			return 0;
		}
		if ( '' !== $note ) {
			Entries::add_note( $entry_id, $note );
		}
		/**
		 * Runs once a paid entry is saved, before the emails and connections, so
		 * an extension can add to the payment (a store order, for example).
		 *
		 * @param array $payment  Payment.
		 * @param int   $entry_id Entry id.
		 * @param array $form     Form.
		 * @param array $pending  Answers and the cart "meta".
		 */
		$updated = (array) apply_filters( 'aluleth_forms/payment_saved', $payment, $entry_id, $form, $pending );
		if ( $updated !== $payment ) {
			$payment = $updated;
			Entries::set_payment( $entry_id, $payment );
		}
		Submission::finish( $form, (array) $pending['values'], $entry_id, isset( $pending['page_url'] ) ? (string) $pending['page_url'] : '', $payment );
		return $entry_id;
	}

	/**
	 * Daily safety net: finishes the orders paid but never confirmed by the
	 * visitor's page, and forgets the ones abandoned for more than a day.
	 *
	 * @return int Number of entries saved.
	 */
	public static function reconcile() {
		global $wpdb;
		if ( ! PayPal::ready() ) {
			return 0;
		}
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 200", $wpdb->esc_like( '_transient_' . self::PENDING ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$done  = 0;
		foreach ( $names as $name ) {
			$order_id = substr( $name, strlen( '_transient_' . self::PENDING ) );
			$pending  = get_transient( self::PENDING . $order_id );
			if ( ! is_array( $pending ) || ! empty( $pending['entry_id'] ) ) {
				continue;
			}
			$order = PayPal::get_order( $order_id );
			if ( is_wp_error( $order ) ) {
				continue;
			}
			$status = isset( $order['status'] ) ? (string) $order['status'] : '';
			if ( 'COMPLETED' === $status ) {
				$result = self::complete( $order_id, false );
				$done  += ! empty( $result['success'] ) ? 1 : 0;
			} elseif ( 'APPROVED' === $status ) {
				$result = self::complete( $order_id, true );
				$done  += ! empty( $result['success'] ) ? 1 : 0;
			} elseif ( time() - (int) $pending['created'] > DAY_IN_SECONDS ) {
				delete_transient( self::PENDING . $order_id );
			}
		}
		return $done;
	}

	/**
	 * Failure answer.
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
	 * Payments received since a UTC date, by currency.
	 *
	 * @param string $since UTC datetime.
	 * @return array<string, float> Currency => amount.
	 */
	public static function received( $since ) {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT payment FROM {$wpdb->prefix}aluleth_form_entries WHERE created_at >= %s AND payment IS NOT NULL AND payment <> ''", $since ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( $rows as $row ) {
			$payment = json_decode( (string) $row, true );
			if ( ! is_array( $payment ) || ! in_array( isset( $payment['status'] ) ? $payment['status'] : '', array( 'paid', 'check' ), true ) || ! empty( $payment['sandbox'] ) ) {
				continue;
			}
			$currency         = (string) $payment['currency'];
			$out[ $currency ] = ( isset( $out[ $currency ] ) ? $out[ $currency ] : 0 ) + (float) $payment['amount'];
		}
		return $out;
	}
}
