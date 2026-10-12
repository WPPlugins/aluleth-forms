<?php
/**
 * Entries board: one column per status, cards you drag, a side panel with
 * the answers, the contact's history and internal notes.
 *
 * Works without JavaScript too: cards are links to the entry page, and the
 * panel's forms post to admin-post.php.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Admin as Core_Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Board view.
 */
final class Board {

	/**
	 * Cards shown per column (the rest is in the list view).
	 */
	const PER_COLUMN = 30;

	/**
	 * Hooks the AJAX handlers.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_ajax_aluleth_forms_board_move', array( __CLASS__, 'ajax_move' ) );
		add_action( 'wp_ajax_aluleth_forms_board_panel', array( __CLASS__, 'ajax_panel' ) );
		add_action( 'wp_ajax_aluleth_forms_board_note', array( __CLASS__, 'ajax_note' ) );
	}

	/**
	 * Colour of a column dot, by position (the last column is grey).
	 *
	 * @param int $index Position.
	 * @param int $count Number of columns.
	 * @return string
	 */
	public static function color( $index, $count ) {
		if ( $index === $count - 1 && $count > 1 ) {
			return '#5b6370';
		}
		$colors = array( '#e8590c', '#2f5bea', '#7c3aed', '#0e7490', '#b45309', '#be185d', '#1e3a8a' );
		return $colors[ $index % count( $colors ) ];
	}

	/**
	 * Prints the board of a form.
	 *
	 * @param int    $form_id Form id.
	 * @param string $search  Search text.
	 * @return void
	 */
	public static function render( $form_id, $search ) {
		$statuses = Entries::statuses( $form_id );
		$keys     = array_keys( $statuses );
		$form     = Forms::get( $form_id, true );
		$fields   = $form ? $form['schema']['fields'] : array();

		echo '<div class="alf-board" data-form="' . esc_attr( (string) $form_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'aluleth_forms_board' ) ) . '">';
		foreach ( $keys as $index => $key ) {
			$others = array_values( array_diff( $keys, array( $key ) ) );
			$column = Entries::column( $form_id, $key, $others, 0 === $index, $search, self::PER_COLUMN );
			$color  = self::color( $index, count( $keys ) );
			echo '<section class="alf-col" data-status="' . esc_attr( $key ) . '" aria-labelledby="alf-col-' . esc_attr( $key ) . '">';
			echo '<header class="alf-col-head"><h2 id="alf-col-' . esc_attr( $key ) . '"><span class="alf-dot" style="background:' . esc_attr( $color ) . '"></span>' . esc_html( $statuses[ $key ] ) . '</h2><span class="alf-count">' . esc_html( number_format_i18n( $column['total'] ) ) . '</span></header>';
			echo '<ol class="alf-cards">';
			foreach ( $column['items'] as $entry ) {
				self::card( $entry, $fields );
			}
			echo '</ol>';
			if ( ! $column['items'] ) {
				echo '<p class="alf-col-empty">' . esc_html__( 'Drop an entry here', 'aluleth-forms' ) . '</p>';
			}
			if ( $column['total'] > count( $column['items'] ) ) {
				$more = add_query_arg(
					array(
						'page'   => 'aluleth-forms-entries',
						'view'   => 'list',
						'form'   => $form_id,
						'status' => $key,
					),
					admin_url( 'admin.php' )
				);
				/* translators: %s: number of entries. */
				echo '<a class="alf-col-more" href="' . esc_url( $more ) . '">' . esc_html( sprintf( __( 'See all %s', 'aluleth-forms' ), number_format_i18n( $column['total'] ) ) ) . '</a>';
			}
			echo '</section>';
		}
		echo '</div>';
		echo '<aside class="alf-panel" id="alf-panel" hidden aria-label="' . esc_attr__( 'Entry details', 'aluleth-forms' ) . '"></aside>';
	}

	/**
	 * Prints one card.
	 *
	 * @param object $entry  Entry.
	 * @param array  $fields Form fields.
	 * @return void
	 */
	private static function card( $entry, array $fields ) {
		$url   = add_query_arg(
			array(
				'page'  => 'aluleth-forms-entries',
				'entry' => (int) $entry->id,
			),
			admin_url( 'admin.php' )
		);
		$name  = Entries::summary( $entry->data );
		$line  = '';
		$chips = array();
		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field['id'], $entry->data ) || in_array( $field['id'], array( 'name', 'email' ), true ) ) {
				continue;
			}
			$value = Entries::value( $entry->data[ $field['id'] ], $field['type'] );
			if ( '' === $value || $value === $name ) {
				continue;
			}
			if ( in_array( $field['type'], array( 'select', 'radio', 'date', 'number' ), true ) && count( $chips ) < 2 && strlen( $value ) <= 24 ) {
				$chips[] = $value;
			} elseif ( '' === $line && in_array( $field['type'], array( 'text', 'textarea', 'checkboxes' ), true ) ) {
				$line = wp_html_excerpt( $value, 70, '…' );
			}
		}
		/**
		 * Short labels shown on the board card, after the field values.
		 *
		 * @param string[] $chips Labels.
		 * @param object   $entry Entry.
		 */
		$chips = array_slice( array_filter( array_map( 'strval', (array) apply_filters( 'aluleth_forms/card_chips', $chips, $entry ) ) ), 0, 4 );
		echo '<li class="alf-card" draggable="' . ( current_user_can( Core_Admin::cap( 'entries_manage' ) ) ? 'true' : 'false' ) . '" data-entry="' . esc_attr( (string) $entry->id ) . '">';
		echo '<a class="alf-card-link" href="' . esc_url( $url ) . '"><span class="alf-card-top"><strong>' . esc_html( $name ) . '</strong><time datetime="' . esc_attr( mysql2date( 'c', $entry->created_at . ' UTC' ) ) . '">' . esc_html( self::ago( $entry->created_at ) ) . '</time></span>';
		if ( '' !== $line ) {
			echo '<span class="alf-card-line">' . esc_html( $line ) . '</span>';
		}
		if ( $chips || $entry->notes || $entry->payment ) {
			echo '<span class="alf-chips">';
			if ( $entry->payment ) {
				echo '<span class="alf-chip alf-chip--paid">' . esc_html( self::payment_label( $entry->payment ) ) . '</span>';
				if ( ! empty( $entry->payment['order']['number'] ) ) {
					/* translators: %s: order number. */
					echo '<span class="alf-chip">' . esc_html( sprintf( __( 'Order #%s', 'aluleth-forms' ), $entry->payment['order']['number'] ) ) . '</span>';
				}
			}
			foreach ( $chips as $chip ) {
				echo '<span class="alf-chip">' . esc_html( $chip ) . '</span>';
			}
			if ( $entry->notes ) {
				/* translators: %d: number of notes. */
				echo '<span class="alf-chip alf-chip--note">' . esc_html( sprintf( _n( '%d note', '%d notes', count( $entry->notes ), 'aluleth-forms' ), count( $entry->notes ) ) ) . '</span>';
			}
			echo '</span>';
		}
		echo '</a></li>';
	}

	/**
	 * "Paid · 114,00 €" label of a payment.
	 *
	 * @param array $payment Payment.
	 * @return string
	 */
	public static function payment_label( array $payment ) {
		$amount = \Aluleth\Core\PayPal::format( (float) $payment['amount'], (string) $payment['currency'] );
		if ( 'refunded' === $payment['status'] ) {
			/* translators: %s: amount. */
			return sprintf( __( 'Refunded · %s', 'aluleth-forms' ), $amount );
		}
		if ( 'check' === $payment['status'] ) {
			/* translators: %s: amount. */
			return sprintf( __( 'Paid, to check · %s', 'aluleth-forms' ), $amount );
		}
		/* translators: %s: amount. */
		return sprintf( __( 'Paid · %s', 'aluleth-forms' ), $amount );
	}

	/**
	 * Short "how long ago" text.
	 *
	 * @param string $utc MySQL datetime in UTC.
	 * @return string
	 */
	public static function ago( $utc ) {
		$time = strtotime( $utc . ' UTC' );
		if ( ! $time ) {
			return '';
		}
		$diff = time() - $time;
		if ( $diff < DAY_IN_SECONDS && wp_date( 'Ymd', $time ) === wp_date( 'Ymd' ) ) {
			return wp_date( get_option( 'time_format' ), $time );
		}
		if ( $diff < 2 * DAY_IN_SECONDS && wp_date( 'Ymd', $time ) === wp_date( 'Ymd', time() - DAY_IN_SECONDS ) ) {
			return __( 'Yesterday', 'aluleth-forms' );
		}
		/* translators: %s: time span, e.g. 3 days. */
		return sprintf( __( '%s ago', 'aluleth-forms' ), human_time_diff( $time ) );
	}

	/**
	 * HTML of the details panel (also the body of the entry page).
	 *
	 * @param object $entry Entry.
	 * @param bool   $in_panel Whether it is shown in the side panel.
	 * @return string
	 */
	public static function panel( $entry, $in_panel = true ) {
		$form     = Forms::get( (int) $entry->form_id, true );
		$fields   = $form ? $form['schema']['fields'] : array();
		$statuses = Entries::statuses( (int) $entry->form_id );
		$current  = Entries::column_of( $entry );
		$manage   = current_user_can( Core_Admin::cap( 'entries_manage' ) );
		$contact  = $entry->contact_id ? aluleth_core()->contacts()->get( (int) $entry->contact_id ) : null;
		$page_url = add_query_arg(
			array(
				'page'  => 'aluleth-forms-entries',
				'entry' => (int) $entry->id,
			),
			admin_url( 'admin.php' )
		);

		ob_start();
		echo '<div class="alf-p" data-entry="' . esc_attr( (string) $entry->id ) . '">';
		echo '<header class="alf-p-head"><div><h2 class="alf-p-title">' . esc_html( Entries::summary( $entry->data ) ) . '</h2>';
		$sub = array();
		if ( $contact ) {
			$sub[] = $contact->email;
			if ( $contact->phone ) {
				$sub[] = $contact->phone;
			}
		}
		$sub[] = ( $form ? $form['title'] : __( 'Deleted form', 'aluleth-forms' ) ) . ' · ' . Core_Admin::when( $entry->created_at );
		echo '<p class="alf-p-sub">' . esc_html( implode( ' · ', $sub ) ) . '</p></div>';
		if ( $in_panel ) {
			echo '<button type="button" class="alf-p-close" aria-label="' . esc_attr__( 'Close', 'aluleth-forms' ) . '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>';
		}
		echo '</header>';

		// Spam.
		if ( $manage && Entries::SPAM === $entry->status ) {
			echo '<form class="alf-p-spam" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'aluleth_forms_entry_' . $entry->id );
			echo '<input type="hidden" name="action" value="aluleth_forms_entry"><input type="hidden" name="entry" value="' . esc_attr( (string) $entry->id ) . '"><input type="hidden" name="do" value="notspam">';
			echo '<p>' . esc_html__( 'This entry was kept aside as spam: no email was sent and it was not passed to your connections.', 'aluleth-forms' ) . '</p><button type="submit" class="button">' . esc_html__( 'Not spam', 'aluleth-forms' ) . '</button></form>';
		}

		// Status.
		if ( ! $manage ) {
			echo '<p class="alf-p-status"><strong>' . esc_html__( 'Status', 'aluleth-forms' ) . '</strong> ' . esc_html( Entries::status_label( $entry ) ) . '</p>';
		}
		if ( $manage ) {
			echo '<form class="alf-p-status" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'aluleth_forms_entry_' . $entry->id );
			echo '<input type="hidden" name="action" value="aluleth_forms_entry"><input type="hidden" name="entry" value="' . esc_attr( (string) $entry->id ) . '"><input type="hidden" name="do" value="status">';
			echo '<label for="alf-p-status-' . esc_attr( (string) $entry->id ) . '">' . esc_html__( 'Status', 'aluleth-forms' ) . '</label><span class="alf-p-row"><select id="alf-p-status-' . esc_attr( (string) $entry->id ) . '" name="status">';
			foreach ( $statuses as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select><button type="submit" class="button alf-p-save">' . esc_html__( 'Save', 'aluleth-forms' ) . '</button></span></form>';
		}

		// Answers.
		echo '<dl class="alf-p-answers">';
		$shown = array();
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field['id'], $entry->data ) ) {
				$shown[ $field['id'] ] = true;
				$value                 = Entries::value( $entry->data[ $field['id'] ], $field['type'] );
				echo '<dt>' . esc_html( wp_strip_all_tags( $field['label'] ) ) . '</dt><dd>' . ( '' === $value ? '<span class="alf-muted">—</span>' : nl2br( esc_html( $value ) ) ) . '</dd>';
			}
		}
		foreach ( $entry->data as $key => $value ) {
			if ( ! isset( $shown[ $key ] ) ) {
				echo '<dt>' . esc_html( $key ) . '</dt><dd>' . nl2br( esc_html( Entries::value( $value, '' ) ) ) . '</dd>';
			}
		}
		if ( $entry->page_url ) {
			echo '<dt>' . esc_html__( 'Sent from', 'aluleth-forms' ) . '</dt><dd><a href="' . esc_url( $entry->page_url ) . '">' . esc_html( wp_parse_url( $entry->page_url, PHP_URL_PATH ) ? wp_parse_url( $entry->page_url, PHP_URL_PATH ) : $entry->page_url ) . '</a></dd>';
		}
		echo '</dl>';

		// Contact.
		if ( $contact ) {
			$contact_url = add_query_arg(
				array(
					'page'    => 'aluleth-contacts',
					'contact' => (int) $contact->id,
				),
				admin_url( 'admin.php' )
			);
			echo '<section class="alf-p-block"><div class="alf-p-block-head"><h3>' . esc_html__( 'Contact history', 'aluleth-forms' ) . '</h3><a href="' . esc_url( $contact_url ) . '">' . esc_html__( 'Open contact', 'aluleth-forms' ) . '</a></div>';
			echo '<p class="alf-chips"><span class="alf-chip alf-chip--status">' . esc_html( Core_Admin::status_label( $contact->status ) ) . '</span>';
			foreach ( aluleth_core()->tags()->of( (int) $contact->id ) as $tag ) {
				echo '<span class="alf-chip">' . esc_html( $tag ) . '</span>';
			}
			echo '</p><ol class="alf-p-timeline">';
			foreach ( aluleth_core()->events()->for_contact( (int) $contact->id, 6 ) as $event ) {
				echo '<li><strong>' . esc_html( aluleth_core()->events()->label( $event ) ) . '</strong><span>' . esc_html( Core_Admin::when( $event->created_at ) ) . '</span></li>';
			}
			echo '</ol></section>';
		}

		// Sends to outside tools.
		if ( class_exists( '\\Aluleth\\Core\\Queue' ) ) {
			$sends = \Aluleth\Core\Queue::for_object( 'entry', (int) $entry->id );
			$back  = $in_panel ? add_query_arg(
				array(
					'page' => 'aluleth-forms-entries',
					'view' => 'board',
					'form' => (int) $entry->form_id,
				),
				admin_url( 'admin.php' )
			) : $page_url;
			if ( $sends ) {
				echo '<section class="alf-p-block alf-p-sends"><h3>' . esc_html__( 'Sent to your tools', 'aluleth-forms' ) . '</h3><ul class="alf-p-sendlist">';
				foreach ( $sends as $send ) {
					echo '<li><span class="alf-p-send-top"><strong>' . esc_html( $send->label ) . '</strong>' . \Aluleth\Core\Connections::badge( $send ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in badge().
					$detail = \Aluleth\Core\Queue::detail( $send );
					if ( '' !== $detail ) {
						echo '<span class="alf-p-send-detail">' . esc_html( $detail ) . '</span>';
					}
					if ( 'failed' === $send->status ) {
						echo \Aluleth\Core\Connections::retry_form( $send, $back ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in retry_form().
					}
					echo '</li>';
				}
				echo '</ul></section>';
			}
		}

		// Payment.
		if ( $entry->payment ) {
			/**
			 * Payment details shown in the entry panel. An extension can add a
			 * fresh "order" (number, url, status, store) and a "note".
			 *
			 * @param array  $payment Payment.
			 * @param object $entry   Entry.
			 */
			$p = (array) apply_filters( 'aluleth_forms/payment_view', $entry->payment, $entry );
			echo '<section class="alf-p-block alf-p-payment"><h3>' . esc_html__( 'Payment', 'aluleth-forms' ) . '</h3><p class="alf-p-paid"><strong>' . esc_html( self::payment_label( $p ) ) . '</strong>';
			if ( ! empty( $p['sandbox'] ) ) {
				echo ' <span class="alf-chip">' . esc_html__( 'Test payment', 'aluleth-forms' ) . '</span>';
			}
			echo '</p><ul class="alf-p-items">';
			foreach ( Payments::lines( $p ) as $line ) {
				echo '<li><span>' . esc_html( $line['label'] ) . '</span><span>' . esc_html( $line['amount'] ) . '</span></li>';
			}
			echo '</ul><dl class="alf-p-meta">';
			if ( ! empty( $p['order']['number'] ) ) {
				/* translators: 1: order number, 2: store name (WooCommerce…). */
				$order_text = sprintf( __( '#%1$s in %2$s', 'aluleth-forms' ), $p['order']['number'], isset( $p['order']['store'] ) ? $p['order']['store'] : '' );
				echo '<dt>' . esc_html__( 'Order', 'aluleth-forms' ) . '</dt><dd>' . ( ! empty( $p['order']['url'] ) ? '<a href="' . esc_url( $p['order']['url'] ) . '">' . esc_html( $order_text ) . '</a>' : esc_html( $order_text ) ) . '</dd>';
				if ( ! empty( $p['order']['status'] ) ) {
					echo '<dt>' . esc_html__( 'Order status', 'aluleth-forms' ) . '</dt><dd>' . esc_html( $p['order']['status'] ) . '</dd>';
				}
			}
			if ( ! empty( $p['capture_id'] ) && 'paypal' === ( isset( $p['gateway'] ) ? $p['gateway'] : 'paypal' ) ) {
				echo '<dt>' . esc_html__( 'PayPal transaction', 'aluleth-forms' ) . '</dt><dd><a href="' . esc_url( \Aluleth\Core\PayPal::activity_url( $p['capture_id'], ! empty( $p['sandbox'] ) ) ) . '" target="_blank" rel="noopener">' . esc_html( $p['capture_id'] ) . '</a></dd>';
			} else {
				echo '<dt>' . esc_html__( 'Paid with', 'aluleth-forms' ) . '</dt><dd>' . esc_html( Payments::method_label( $p ) . ( ! empty( $p['capture_id'] ) ? ' · ' . $p['capture_id'] : '' ) ) . '</dd>';
			}
			if ( ! empty( $p['payer_email'] ) ) {
				echo '<dt>' . esc_html__( 'Paid by', 'aluleth-forms' ) . '</dt><dd>' . esc_html( trim( ( isset( $p['payer_name'] ) ? $p['payer_name'] : '' ) . ' · ' . $p['payer_email'], ' ·' ) ) . '</dd>';
			}
			echo '<dt>' . esc_html__( 'Paid on', 'aluleth-forms' ) . '</dt><dd>' . esc_html( Core_Admin::when( $p['paid_at'] ) ) . '</dd></dl>';
			echo '<p class="alf-muted">' . esc_html( ! empty( $p['note'] ) ? (string) $p['note'] : __( 'Refunds are made in your PayPal account.', 'aluleth-forms' ) ) . '</p></section>';
		}

		/**
		 * Lets an add-on print its own block in the entry panel, before the notes.
		 *
		 * @param object $entry Entry.
		 */
		do_action( 'aluleth_forms/entry_panel', $entry );

		// Notes.
		echo '<section class="alf-p-block alf-p-notes"><h3>' . esc_html__( 'Notes', 'aluleth-forms' ) . '</h3>' . self::notes_html( $entry->notes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in notes_html().
		if ( ! $manage ) {
			echo '</section>';
		}
		if ( $manage ) {
			echo '<form class="alf-p-note" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'aluleth_forms_entry_' . $entry->id );
			echo '<input type="hidden" name="action" value="aluleth_forms_entry"><input type="hidden" name="entry" value="' . esc_attr( (string) $entry->id ) . '"><input type="hidden" name="do" value="note">';
			echo '<label class="screen-reader-text" for="alf-p-note-' . esc_attr( (string) $entry->id ) . '">' . esc_html__( 'Add a note', 'aluleth-forms' ) . '</label><textarea id="alf-p-note-' . esc_attr( (string) $entry->id ) . '" name="note" rows="2" placeholder="' . esc_attr__( 'Add a note for your team…', 'aluleth-forms' ) . '"></textarea><button type="submit" class="button">' . esc_html__( 'Add note', 'aluleth-forms' ) . '</button></form></section>';
		}

		// Footer.
		echo '<footer class="alf-p-foot">';
		if ( $in_panel ) {
			echo '<a href="' . esc_url( $page_url ) . '">' . esc_html__( 'Open on its own page', 'aluleth-forms' ) . '</a>';
		}
		if ( $manage && Entries::SPAM !== $entry->status ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="alf-p-markspam">';
			wp_nonce_field( 'aluleth_forms_entry_' . $entry->id );
			echo '<input type="hidden" name="action" value="aluleth_forms_entry"><input type="hidden" name="entry" value="' . esc_attr( (string) $entry->id ) . '"><input type="hidden" name="do" value="spam"><button type="submit" class="button-link">' . esc_html__( 'Mark as spam', 'aluleth-forms' ) . '</button></form>';
		}
		if ( $manage ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="alf-p-delete">';
			wp_nonce_field( 'aluleth_forms_entry_' . $entry->id );
			echo '<input type="hidden" name="action" value="aluleth_forms_entry"><input type="hidden" name="entry" value="' . esc_attr( (string) $entry->id ) . '"><input type="hidden" name="do" value="delete"><button type="submit" class="button-link button-link-delete" data-confirm="' . esc_attr__( 'Click again to delete', 'aluleth-forms' ) . '">' . esc_html__( 'Delete this entry', 'aluleth-forms' ) . '</button></form>';
		}
		echo '</footer></div>';
		return (string) ob_get_clean();
	}

	/**
	 * HTML of a notes list.
	 *
	 * @param array[] $notes Notes.
	 * @return string
	 */
	public static function notes_html( array $notes ) {
		if ( ! $notes ) {
			return '<ol class="alf-p-notelist"><li class="alf-muted">' . esc_html__( 'No note yet. Notes are only visible to your team.', 'aluleth-forms' ) . '</li></ol>';
		}
		$out = '<ol class="alf-p-notelist">';
		foreach ( array_reverse( $notes ) as $note ) {
			$user = ! empty( $note['user'] ) ? get_userdata( (int) $note['user'] ) : null;
			$out .= '<li><p>' . nl2br( esc_html( $note['text'] ) ) . '</p><span>' . esc_html( ( $user ? $user->display_name . ' · ' : '' ) . Core_Admin::when( $note['at'] ) ) . '</span></li>';
		}
		return $out . '</ol>';
	}

	/**
	 * Checks the AJAX request and returns the entry.
	 *
	 * @param string $area Access area needed (see Aluleth › Settings › Access).
	 * @return object
	 */
	private static function ajax_entry( $area = 'entries_manage' ) {
		check_ajax_referer( 'aluleth_forms_board' );
		if ( ! current_user_can( Core_Admin::cap( $area ) ) ) {
			wp_send_json_error( null, 403 );
		}
		$entry = Entries::get( isset( $_POST['entry'] ) ? absint( $_POST['entry'] ) : 0 );
		if ( ! $entry ) {
			wp_send_json_error( array( 'message' => __( 'This entry no longer exists.', 'aluleth-forms' ) ), 404 );
		}
		return $entry;
	}

	/**
	 * AJAX: moves an entry to another status.
	 *
	 * @return void
	 */
	public static function ajax_move() {
		$entry  = self::ajax_entry();
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in ajax_entry().
		if ( ! Entries::set_status( (int) $entry->id, $status ) ) {
			wp_send_json_error( array( 'message' => __( 'The status could not be changed.', 'aluleth-forms' ) ), 400 );
		}
		wp_send_json_success( array( 'status' => $status ) );
	}

	/**
	 * AJAX: the details panel of an entry.
	 *
	 * @return void
	 */
	public static function ajax_panel() {
		$entry = self::ajax_entry( 'entries' );
		wp_send_json_success( array( 'html' => self::panel( $entry ) ) );
	}

	/**
	 * AJAX: adds a note.
	 *
	 * @return void
	 */
	public static function ajax_note() {
		$entry = self::ajax_entry();
		$text  = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in ajax_entry().
		$notes = Entries::add_note( (int) $entry->id, $text );
		wp_send_json_success(
			array(
				'html'  => self::notes_html( $notes ),
				'count' => count( $notes ),
			)
		);
	}
}
