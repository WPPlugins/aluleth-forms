<?php
/**
 * Entries storage.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Util;

defined( 'ABSPATH' ) || exit;

/**
 * Entries repository.
 */
final class Entries {

	/**
	 * Statuses already read, by form id.
	 *
	 * @var array<int, array<string, string>>
	 */
	private static $statuses = array();

	/**
	 * Status of entries kept aside as spam (never a board column).
	 */
	const SPAM = 'spam';

	/**
	 * Entry statuses of a form (the columns of its board), or the default ones.
	 *
	 * @param int $form_id Form id (0 = defaults).
	 * @return array<string, string> Key => label, in board order.
	 */
	public static function statuses( $form_id = 0 ) {
		$form_id = (int) $form_id;
		if ( ! isset( self::$statuses[ $form_id ] ) ) {
			$form = $form_id ? Forms::get( $form_id, true ) : null;
			$list = $form ? $form['schema']['settings']['statuses'] : Schema::default_statuses();
			$out  = array();
			foreach ( $list as $status ) {
				$out[ $status['key'] ] = $status['label'];
			}
			self::$statuses[ $form_id ] = $out;
		}
		return self::$statuses[ $form_id ];
	}

	/**
	 * Label of an entry's status (an unknown status shows as the first one).
	 *
	 * @param object $entry Entry.
	 * @return string
	 */
	public static function status_label( $entry ) {
		if ( self::SPAM === $entry->status ) {
			return __( 'Spam', 'aluleth-forms' );
		}
		$statuses = self::statuses( (int) $entry->form_id );
		return isset( $statuses[ $entry->status ] ) ? $statuses[ $entry->status ] : reset( $statuses );
	}

	/**
	 * Board column of an entry (unknown statuses go to the first column).
	 *
	 * @param object $entry Entry.
	 * @return string Status key.
	 */
	public static function column_of( $entry ) {
		$statuses = self::statuses( (int) $entry->form_id );
		return isset( $statuses[ $entry->status ] ) ? $entry->status : (string) key( $statuses );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aluleth_form_entries';
	}

	/**
	 * Saves a new entry.
	 *
	 * @param array $row form_id, data, ip_hash, user_agent, page_url.
	 * @return int Entry id or 0.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$now = Util::now();
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'form_id'    => (int) $row['form_id'],
				'status'     => (string) key( self::statuses( (int) $row['form_id'] ) ),
				'data'       => wp_json_encode( $row['data'] ),
				'ip_hash'    => (string) $row['ip_hash'],
				'user_agent' => (string) $row['user_agent'],
				'page_url'   => (string) $row['page_url'],
				'payment'    => ! empty( $row['payment'] ) && is_array( $row['payment'] ) ? wp_json_encode( $row['payment'] ) : null,
				'extra'      => ! empty( $row['extra'] ) && is_array( $row['extra'] ) ? wp_json_encode( $row['extra'] ) : null,
				'created_at' => isset( $row['created_at'] ) && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $row['created_at'] ) ? $row['created_at'] : $now,
				'updated_at' => $now,
			)
		);
		$id  = $ok ? (int) $wpdb->insert_id : 0;
		if ( $id && ! empty( $row['status'] ) && ( self::SPAM === $row['status'] || isset( self::statuses( (int) $row['form_id'] )[ $row['status'] ] ) ) ) {
			$wpdb->update( self::table(), array( 'status' => (string) $row['status'] ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return $id;
	}

	/**
	 * Links an entry to its contact.
	 *
	 * @param int $entry_id   Entry id.
	 * @param int $contact_id Contact id.
	 * @return void
	 */
	public static function set_contact( $entry_id, $contact_id ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'contact_id' => (int) $contact_id ), array( 'id' => (int) $entry_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Changes the status of an entry.
	 *
	 * @param int    $entry_id Entry id.
	 * @param string $status   Status.
	 * @return bool
	 */
	public static function set_status( $entry_id, $status ) {
		global $wpdb;
		$entry = self::get( $entry_id );
		if ( ! $entry || ( self::SPAM !== $status && ! array_key_exists( $status, self::statuses( (int) $entry->form_id ) ) ) ) {
			return false;
		}
		if ( $entry->status === $status ) {
			return true;
		}
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'status'     => $status,
				'updated_at' => Util::now(),
			),
			array( 'id' => (int) $entry_id )
		);
	}

	/**
	 * Replaces the payment details of an entry (an extension adds its order, or
	 * follows a refund).
	 *
	 * @param int   $entry_id Entry id.
	 * @param array $payment  Payment.
	 * @return bool
	 */
	public static function set_payment( $entry_id, array $payment ) {
		global $wpdb;
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'payment'    => wp_json_encode( $payment ),
				'updated_at' => Util::now(),
			),
			array( 'id' => (int) $entry_id )
		);
	}

	/**
	 * Saves extension data on an entry (a quiz score…), under the extension's key.
	 *
	 * @param int    $entry_id Entry id.
	 * @param string $key      Key.
	 * @param mixed  $value    Value (null removes it).
	 * @return bool
	 */
	public static function set_extra( $entry_id, $key, $value ) {
		global $wpdb;
		$entry = self::get( $entry_id );
		if ( ! $entry ) {
			return false;
		}
		$extra = $entry->extra;
		if ( null === $value ) {
			unset( $extra[ $key ] );
		} else {
			$extra[ $key ] = $value;
		}
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array( 'extra' => $extra ? wp_json_encode( $extra ) : null ),
			array( 'id' => (int) $entry_id )
		);
	}

	/**
	 * Adds an internal note to an entry.
	 *
	 * @param int    $entry_id Entry id.
	 * @param string $text     Note.
	 * @return array[] All notes of the entry, oldest first.
	 */
	public static function add_note( $entry_id, $text ) {
		global $wpdb;
		$entry = self::get( $entry_id );
		$text  = trim( sanitize_textarea_field( (string) $text ) );
		if ( ! $entry ) {
			return array();
		}
		$notes = $entry->notes;
		if ( '' !== $text ) {
			$notes[] = array(
				'user' => get_current_user_id(),
				'text' => substr( $text, 0, 5000 ),
				'at'   => Util::now(),
			);
			$wpdb->update( self::table(), array( 'notes' => wp_json_encode( $notes ) ), array( 'id' => (int) $entry_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return $notes;
	}

	/**
	 * Entries of one board column.
	 *
	 * @param int      $form_id Form id.
	 * @param string   $status  Status key of the column.
	 * @param string[] $others  Keys of the other columns (the first column also gets unknown statuses).
	 * @param bool     $first   Whether it is the first column.
	 * @param string   $search  Search text.
	 * @param int      $limit   Maximum entries.
	 * @return array{items: object[], total: int}
	 */
	public static function column( $form_id, $status, array $others, $first, $search, $limit ) {
		global $wpdb;
		// The first column also gets the entries whose status is none of the other columns.
		$others = array_values( array_filter( array_map( 'sanitize_key', $others ) ) );
		$rest   = $first && $others ? 1 : 0;
		$list   = ',' . implode( ',', $others ) . ',';
		$like   = '' !== $search ? '%' . $wpdb->esc_like( $search ) . '%' : '';
		$table  = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom table.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE form_id = %d AND ( status = %s OR ( %d = 1 AND LOCATE( CONCAT( ',', status, ',' ), %s ) = 0 ) ) AND status <> %s AND ( %s = '' OR data LIKE %s )", $table, (int) $form_id, $status, $rest, $list, self::SPAM, $like, $like ) );
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE form_id = %d AND ( status = %s OR ( %d = 1 AND LOCATE( CONCAT( ',', status, ',' ), %s ) = 0 ) ) AND status <> %s AND ( %s = '' OR data LIKE %s ) ORDER BY id DESC LIMIT %d", $table, (int) $form_id, $status, $rest, $list, self::SPAM, $like, $like, (int) $limit ) );
		// phpcs:enable
		foreach ( (array) $items as $item ) {
			self::decode( $item );
		}
		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Decodes the JSON columns of a row.
	 *
	 * @param object $row Row.
	 * @return object
	 */
	private static function decode( $row ) {
		$row->data    = json_decode( (string) $row->data, true );
		$row->data    = is_array( $row->data ) ? $row->data : array();
		$row->notes   = isset( $row->notes ) ? json_decode( (string) $row->notes, true ) : array();
		$row->notes   = is_array( $row->notes ) ? $row->notes : array();
		$payment      = isset( $row->payment ) && '' !== (string) $row->payment ? json_decode( (string) $row->payment, true ) : null;
		$row->payment = is_array( $payment ) ? $payment : null;
		$extra        = isset( $row->extra ) && '' !== (string) $row->extra ? json_decode( (string) $row->extra, true ) : array();
		$row->extra   = is_array( $extra ) ? $extra : array();
		return $row;
	}

	/**
	 * One entry.
	 *
	 * @param int $entry_id Entry id.
	 * @return object|null
	 */
	public static function get( $entry_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aluleth_form_entries WHERE id = %d", (int) $entry_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Deletes an entry.
	 *
	 * @param int $entry_id Entry id.
	 * @return void
	 */
	public static function delete( $entry_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $entry_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( class_exists( '\\Aluleth\\Core\\Queue' ) ) {
			\Aluleth\Core\Queue::delete_for_object( 'entry', (int) $entry_id );
		}
	}

	/**
	 * Lists entries.
	 *
	 * @param array $args 'form_id', 'status', 'search', 'per_page', 'page'.
	 * @return array{items: object[], total: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 5000, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$form_id  = ! empty( $args['form_id'] ) ? (int) $args['form_id'] : 0;
		// Spam stays aside unless asked for.
		$status = ! empty( $args['status'] ) && preg_match( '/^[a-z0-9_]{1,30}$/', (string) $args['status'] ) ? (string) $args['status'] : '';
		$like   = ! empty( $args['search'] ) ? '%' . $wpdb->esc_like( (string) $args['search'] ) . '%' : '';
		$table  = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom table.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE ( %d = 0 OR form_id = %d ) AND ( ( %s <> '' AND status = %s ) OR ( %s = '' AND status <> %s ) ) AND ( %s = '' OR data LIKE %s )", $table, $form_id, $form_id, $status, $status, $status, self::SPAM, $like, $like ) );
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE ( %d = 0 OR form_id = %d ) AND ( ( %s <> '' AND status = %s ) OR ( %s = '' AND status <> %s ) ) AND ( %s = '' OR data LIKE %s ) ORDER BY id DESC LIMIT %d OFFSET %d", $table, $form_id, $form_id, $status, $status, $status, self::SPAM, $like, $like, $per_page, ( $page - 1 ) * $per_page ) );
		// phpcs:enable
		foreach ( (array) $items as $item ) {
			self::decode( $item );
		}
		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Number of entries since a date (dashboard).
	 *
	 * @param string $since_utc MySQL datetime (UTC).
	 * @return int
	 */
	public static function count_since( $since_utc ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}aluleth_form_entries WHERE created_at >= %s AND status <> %s", $since_utc, self::SPAM ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Number of entries kept aside as spam.
	 *
	 * @param int $form_id Form id (0 = all forms).
	 * @return int
	 */
	public static function count_spam( $form_id = 0 ) {
		global $wpdb;
		if ( $form_id ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}aluleth_form_entries WHERE status = %s AND form_id = %d", self::SPAM, (int) $form_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}aluleth_form_entries WHERE status = %s", self::SPAM ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Removes an entry row only, leaving its sends to outside tools in the
	 * queue ("Do not save entries": the sends still go out).
	 *
	 * @param int $entry_id Entry id.
	 * @return void
	 */
	public static function forget( $entry_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $entry_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Deletes the entries of a contact (WordPress erasure tool).
	 *
	 * @param int $contact_id Contact id.
	 * @return void
	 */
	public static function delete_for_contact( $contact_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'contact_id' => (int) $contact_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Short label of an entry (name or email or first answer).
	 *
	 * @param array $data Entry data.
	 * @return string
	 */
	public static function summary( array $data ) {
		foreach ( array( 'name', 'full_name', 'first_name' ) as $key ) {
			if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				return $data[ $key ];
			}
		}
		// Other field keys that hold a name (your_name, nom, nombre…).
		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) && ! is_email( $value ) && preg_match( '/name|nom|nombre|prenom/i', (string) $key ) ) {
				return $value;
			}
		}
		if ( ! empty( $data['email'] ) && is_string( $data['email'] ) ) {
			return $data['email'];
		}
		foreach ( $data as $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				return wp_html_excerpt( $value, 60, '…' );
			}
		}
		return __( '(empty entry)', 'aluleth-forms' );
	}

	/**
	 * Printable value.
	 *
	 * @param mixed  $value Value.
	 * @param string $type  Field type.
	 * @return string
	 */
	public static function value( $value, $type ) {
		if ( Schema::is_extra( $type ) ) {
			/**
			 * Printable value of a field type added by an extension.
			 *
			 * @param string|null $text  Text, or null to use the default.
			 * @param mixed       $value Saved value.
			 * @param string      $type  Field type.
			 */
			$text = apply_filters( 'aluleth_forms/value_text', null, $value, $type );
			if ( null !== $text ) {
				return (string) $text;
			}
		}
		if ( 'consent' === $type || is_bool( $value ) ) {
			return $value ? __( 'Yes', 'aluleth-forms' ) : __( 'No', 'aluleth-forms' );
		}
		if ( 'date' === $type && is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			$time = strtotime( $value . ' 12:00:00' );
			return $time ? date_i18n( get_option( 'date_format' ), $time ) : $value;
		}
		if ( ! is_array( $value ) ) {
			return (string) $value;
		}
		$parts = array();
		foreach ( $value as $item ) {
			$parts[] = is_scalar( $item ) ? (string) $item : wp_json_encode( $item );
		}
		return implode( ', ', $parts );
	}
}
