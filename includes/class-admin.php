<?php
/**
 * Admin screens: entries, entry, CSV export, form settings.
 *
 * The visual builder replaces the form edit screen in a later version; for
 * now the edit screen offers the settings and the field list as JSON.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Admin as Core_Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
final class Admin {

	/**
	 * Hooks the admin.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'add_meta_boxes_' . Plugin::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . Plugin::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_post_aluleth_forms_entry', array( __CLASS__, 'entry_action' ) );
		add_action( 'admin_post_aluleth_forms_export', array( __CLASS__, 'export' ) );
		add_filter( 'manage_' . Plugin::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Plugin::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Adds the Entries page.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( Core_Admin::MENU, __( 'Entries', 'aluleth-forms' ), __( 'Entries', 'aluleth-forms' ), Core_Admin::cap( 'entries' ), 'aluleth-forms-entries', array( __CLASS__, 'entries' ) );
	}

	/**
	 * Entries screen: board or list, or one entry.
	 *
	 * @return void
	 */
	public static function entries() {
		if ( ! current_user_can( Core_Admin::cap( 'entries' ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to see entries.', 'aluleth-forms' ) );
		}
		self::entries_assets();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen.
		$entry_id = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;
		if ( $entry_id ) {
			self::entry( $entry_id );
			return;
		}
		$form_id = isset( $_GET['form'] ) ? absint( $_GET['form'] ) : 0;
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$notice  = isset( $_GET['alf_notice'] ) ? sanitize_key( wp_unslash( $_GET['alf_notice'] ) ) : '';
		$view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		// phpcs:enable

		/**
		 * Views of the entries screen. An add-on can add one (key => label) and
		 * print it on the "aluleth_forms/entries_view" action.
		 *
		 * @param array<string, string> $views Views.
		 */
		$views = (array) apply_filters(
			'aluleth_forms/entries_views',
			array(
				'board' => __( 'Board', 'aluleth-forms' ),
				'list'  => __( 'List', 'aluleth-forms' ),
			)
		);
		// Remember the last view per user (only when chosen with the view switcher, which carries a nonce).
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the nonce, checked just below.
		if ( isset( $views[ $view ] ) ) {
			if ( wp_verify_nonce( $nonce, 'aluleth_forms_entries_view' ) ) {
				update_user_meta( get_current_user_id(), 'aluleth_forms_entries_view', $view );
			}
		} else {
			$stored = (string) get_user_meta( get_current_user_id(), 'aluleth_forms_entries_view', true );
			$view   = isset( $views[ $stored ] ) ? $stored : 'board';
		}

		$forms = get_posts(
			array(
				'post_type'      => Plugin::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		// The board shows one form: the chosen one, or the one with the latest entry.
		if ( 'list' !== $view && ! $form_id ) {
			$latest  = Entries::query( array( 'per_page' => 1 ) );
			$form_id = $latest['items'] ? (int) $latest['items'][0]->form_id : ( $forms ? (int) $forms[0]->ID : 0 );
		}

		echo '<div class="wrap al-wrap alf-entries' . ( 'board' === $view ? ' is-board' : '' ) . '">';
		echo '<div class="alf-entries-head"><h1 class="al-title">' . esc_html__( 'Entries', 'aluleth-forms' ) . '</h1>';
		$base = array(
			'page' => 'aluleth-forms-entries',
			'form' => $form_id,
		);
		echo '<div class="alf-seg" role="group" aria-label="' . esc_attr__( 'View', 'aluleth-forms' ) . '">';
		foreach ( $views as $key => $label ) {
			echo '<a href="' . esc_url( wp_nonce_url( add_query_arg( array_merge( $base, array( 'view' => $key ) ), admin_url( 'admin.php' ) ), 'aluleth_forms_entries_view' ) ) . '"' . ( $view === $key ? ' class="is-on" aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</div></div>';

		if ( 'deleted' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry deleted.', 'aluleth-forms' ) . '</p></div>';
		}
		if ( 'spammed' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry moved to Spam.', 'aluleth-forms' ) . '</p></div>';
		}
		self::retried_notice();

		if ( ! in_array( $view, array( 'board', 'list' ), true ) ) {
			echo '<form method="get" class="al-search alf-filters"><input type="hidden" name="page" value="aluleth-forms-entries"><input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
			echo '<label class="screen-reader-text" for="alf-form-filter">' . esc_html__( 'Form', 'aluleth-forms' ) . '</label><select id="alf-form-filter" name="form" class="alf-autosubmit">';
			foreach ( $forms as $form ) {
				echo '<option value="' . esc_attr( $form->ID ) . '"' . selected( $form_id, $form->ID, false ) . '>' . esc_html( get_the_title( $form ) ) . '</option>';
			}
			echo '</select> <button class="button">' . esc_html__( 'Show', 'aluleth-forms' ) . '</button></form>';
			/**
			 * Prints a view added with the "aluleth_forms/entries_views" filter.
			 *
			 * @param string $view    View key.
			 * @param int    $form_id Chosen form.
			 */
			do_action( 'aluleth_forms/entries_view', $view, $form_id );
			echo '</div>';
			return;
		}

		$statuses = Entries::statuses( $form_id );
		echo '<form method="get" class="al-search alf-filters"><input type="hidden" name="page" value="aluleth-forms-entries"><input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
		echo '<label class="screen-reader-text" for="alf-form-filter">' . esc_html__( 'Form', 'aluleth-forms' ) . '</label><select id="alf-form-filter" name="form" class="alf-autosubmit">';
		if ( 'list' === $view ) {
			echo '<option value="">' . esc_html__( 'All forms', 'aluleth-forms' ) . '</option>';
		}
		foreach ( $forms as $form ) {
			echo '<option value="' . esc_attr( $form->ID ) . '"' . selected( $form_id, $form->ID, false ) . '>' . esc_html( get_the_title( $form ) ) . '</option>';
		}
		echo '</select>';
		if ( 'list' === $view ) {
			echo ' <label class="screen-reader-text" for="alf-status-filter">' . esc_html__( 'Status', 'aluleth-forms' ) . '</label><select id="alf-status-filter" name="status"><option value="">' . esc_html__( 'All statuses', 'aluleth-forms' ) . '</option>';
			foreach ( $statuses as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '<option value="' . esc_attr( Entries::SPAM ) . '"' . selected( $status, Entries::SPAM, false ) . '>' . esc_html__( 'Spam', 'aluleth-forms' ) . '</option>';
			echo '</select>';
		}
		echo ' <label class="screen-reader-text" for="alf-s">' . esc_html__( 'Search entries', 'aluleth-forms' ) . '</label><input id="alf-s" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search entries', 'aluleth-forms' ) . '"> <button class="button">' . esc_html__( 'Filter', 'aluleth-forms' ) . '</button>';
		$export = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'aluleth_forms_export',
					'form'   => $form_id,
					'status' => 'list' === $view ? $status : '',
				),
				admin_url( 'admin-post.php' )
			),
			'aluleth_forms_export'
		);
		if ( current_user_can( Core_Admin::cap( 'entries_manage' ) ) ) {
			echo ' <a class="button" href="' . esc_url( $export ) . '">' . esc_html__( 'Export CSV', 'aluleth-forms' ) . '</a>';
		}
		if ( $form_id && current_user_can( 'edit_post', $form_id ) ) {
			$settings = add_query_arg(
				array(
					'page'    => Builder::PAGE,
					'form'    => $form_id,
					'tab'     => 'settings',
					'section' => 'statuses',
				),
				admin_url( 'admin.php' )
			);
			echo ' <a class="alf-edit-statuses" href="' . esc_url( $settings ) . '">' . esc_html__( 'Edit the columns', 'aluleth-forms' ) . '</a>';
		}
		$spam = Entries::count_spam( 'board' === $view ? $form_id : 0 );
		if ( $spam && Entries::SPAM !== $status ) {
			$spam_url = add_query_arg(
				array(
					'page'   => 'aluleth-forms-entries',
					'view'   => 'list',
					'form'   => 'board' === $view ? $form_id : '',
					'status' => Entries::SPAM,
				),
				admin_url( 'admin.php' )
			);
			/* translators: %s: number of entries kept aside as spam. */
			echo ' <a class="alf-spam-link" href="' . esc_url( $spam_url ) . '">' . esc_html( sprintf( __( 'Spam (%s)', 'aluleth-forms' ), number_format_i18n( $spam ) ) ) . '</a>';
		}
		echo '</form>';

		if ( ! $forms ) {
			echo '<div class="al-empty"><p>' . esc_html__( 'Create a form first: its entries will appear here.', 'aluleth-forms' ) . '</p></div></div>';
			return;
		}

		if ( 'board' === $view ) {
			Board::render( $form_id, $search );
			echo '</div>';
			return;
		}

		$result = Entries::query(
			array(
				'form_id'  => $form_id,
				'status'   => $status,
				'search'   => $search,
				'page'     => $paged,
				'per_page' => 25,
			)
		);
		if ( ! $result['items'] ) {
			echo '<div class="al-empty"><p>' . esc_html( '' !== $search || $status ? __( 'No entry matches these filters.', 'aluleth-forms' ) : __( 'No entries yet. They appear here as soon as someone sends one of your forms.', 'aluleth-forms' ) ) . '</p></div></div>';
			return;
		}

		$titles = array();
		foreach ( $forms as $form ) {
			$titles[ $form->ID ] = get_the_title( $form );
		}

		echo '<table class="widefat striped al-table"><thead><tr><th>' . esc_html__( 'Entry', 'aluleth-forms' ) . '</th><th>' . esc_html__( 'Form', 'aluleth-forms' ) . '</th><th>' . esc_html__( 'Status', 'aluleth-forms' ) . '</th><th>' . esc_html__( 'Received', 'aluleth-forms' ) . '</th></tr></thead><tbody>';
		foreach ( $result['items'] as $item ) {
			$url   = add_query_arg(
				array(
					'page'  => 'aluleth-forms-entries',
					'entry' => (int) $item->id,
				),
				admin_url( 'admin.php' )
			);
			$keys  = array_keys( Entries::statuses( (int) $item->form_id ) );
			$index = array_search( Entries::column_of( $item ), $keys, true );
			$dot   = Entries::SPAM === $item->status ? '#9a3412' : Board::color( (int) $index, count( $keys ) );
			echo '<tr><td><a href="' . esc_url( $url ) . '"><strong>' . esc_html( self::summary( $item->data ) ) . '</strong></a></td><td>' . esc_html( isset( $titles[ $item->form_id ] ) ? $titles[ $item->form_id ] : '#' . $item->form_id ) . '</td><td><span class="alf-status-pill"><span class="alf-dot" style="background:' . esc_attr( $dot ) . '"></span>' . esc_html( Entries::status_label( $item ) ) . '</span></td><td>' . esc_html( Core_Admin::when( $item->created_at ) ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$pages = (int) ceil( $result['total'] / 25 );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			) . '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * Styles and script of the entries screens.
	 *
	 * @return void
	 */
	private static function entries_assets() {
		$url = plugins_url( 'assets/', ALULETH_FORMS_FILE );
		wp_enqueue_style( 'aluleth-forms-entries', $url . 'entries.css', array(), ALULETH_FORMS_VERSION );
		wp_enqueue_script( 'aluleth-forms-entries', $url . 'entries.js', array(), ALULETH_FORMS_VERSION, true );
		wp_localize_script(
			'aluleth-forms-entries',
			'alulethFormsEntries',
			array(
				'ajax' => admin_url( 'admin-ajax.php' ),
				'i18n' => array(
					'error'   => __( 'This change could not be saved. Please try again.', 'aluleth-forms' ),
					'loading' => __( 'Loading…', 'aluleth-forms' ),
					/* translators: %d: number of notes. */
					'notes'   => _n_noop( '%d note', '%d notes', 'aluleth-forms' ),
					/* translators: %s: status name. */
					'moved'   => __( 'Moved to %s', 'aluleth-forms' ),
				),
			)
		);
	}

	/**
	 * Result of a "Send again" click.
	 *
	 * @return void
	 */
	private static function retried_notice() {
		$retried = isset( $_GET['al_retried'] ) ? sanitize_key( wp_unslash( $_GET['al_retried'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only shows a message.
		if ( 'ok' === $retried ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sent again successfully.', 'aluleth-forms' ) . '</p></div>';
		} elseif ( 'error' === $retried ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'It failed again. Open the entry to see why.', 'aluleth-forms' ) . '</p></div>';
		}
	}

	/**
	 * One entry on its own page.
	 *
	 * @param int $entry_id Entry id.
	 * @return void
	 */
	private static function entry( $entry_id ) {
		$entry = Entries::get( $entry_id );
		echo '<div class="wrap al-wrap alf-entries"><p><a href="' . esc_url( admin_url( 'admin.php?page=aluleth-forms-entries' . ( $entry ? '&form=' . (int) $entry->form_id : '' ) ) ) . '">&larr; ' . esc_html__( 'All entries', 'aluleth-forms' ) . '</a></p>';
		if ( ! $entry ) {
			echo '<p>' . esc_html__( 'This entry does not exist.', 'aluleth-forms' ) . '</p></div>';
			return;
		}
		self::retried_notice();
		echo '<div class="alf-page al-card">' . Board::panel( $entry, false ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Board::panel().
	}

	/**
	 * Saves an entry status or a note, or deletes the entry.
	 *
	 * @return void
	 */
	public static function entry_action() {
		$entry_id = isset( $_POST['entry'] ) ? absint( $_POST['entry'] ) : 0;
		check_admin_referer( 'aluleth_forms_entry_' . $entry_id );
		if ( ! current_user_can( Core_Admin::cap( 'entries_manage' ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'aluleth-forms' ) );
		}
		$do    = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$entry = Entries::get( $entry_id );
		if ( 'delete' === $do ) {
			Entries::delete( $entry_id );
			wp_safe_redirect( admin_url( 'admin.php?page=aluleth-forms-entries&alf_notice=deleted' . ( $entry ? '&form=' . (int) $entry->form_id : '' ) ) );
			exit;
		}
		if ( 'note' === $do ) {
			Entries::add_note( $entry_id, isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '' );
		} elseif ( 'spam' === $do && $entry ) {
			Entries::set_status( $entry_id, Entries::SPAM );
			Entries::add_note( $entry_id, __( 'Marked as spam.', 'aluleth-forms' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=aluleth-forms-entries&alf_notice=spammed&form=' . (int) $entry->form_id ) );
			exit;
		} elseif ( 'notspam' === $do && $entry ) {
			// Back to the first column. Notifications and connections are not sent again.
			Entries::set_status( $entry_id, (string) key( Entries::statuses( (int) $entry->form_id ) ) );
			Entries::add_note( $entry_id, __( 'Marked as not spam. Emails and connections were not sent for this entry.', 'aluleth-forms' ) );
		} else {
			$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
			Entries::set_status( $entry_id, $status );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=aluleth-forms-entries&entry=' . $entry_id ) );
		exit;
	}

	/**
	 * CSV export of the filtered entries.
	 *
	 * @return void
	 */
	public static function export() {
		check_admin_referer( 'aluleth_forms_export' );
		if ( ! current_user_can( Core_Admin::cap( 'entries_manage' ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'aluleth-forms' ) );
		}
		$form_id = isset( $_GET['form'] ) ? absint( $_GET['form'] ) : 0;
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$result  = Entries::query(
			array(
				'form_id'  => $form_id,
				'status'   => $status,
				'per_page' => 5000,
			)
		);

		$columns = array();
		$labels  = array();
		$types   = array();
		$form    = $form_id ? Forms::get( $form_id, true ) : null;
		if ( $form ) {
			foreach ( $form['schema']['fields'] as $field ) {
				if ( in_array( $field['type'], Schema::input_types(), true ) ) {
					$columns[]              = $field['id'];
					$labels[ $field['id'] ] = wp_strip_all_tags( $field['label'] );
					$types[ $field['id'] ]  = $field['type'];
				}
			}
		}
		foreach ( $result['items'] as $item ) {
			foreach ( array_keys( $item->data ) as $key ) {
				if ( ! in_array( $key, $columns, true ) ) {
					$columns[]      = $key;
					$labels[ $key ] = $key;
				}
			}
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=entries-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$head = array( 'ID', __( 'Status', 'aluleth-forms' ), __( 'Received (UTC)', 'aluleth-forms' ) );
		foreach ( $columns as $key ) {
			$head[] = $labels[ $key ];
		}
		$paid = (bool) array_filter( wp_list_pluck( $result['items'], 'payment' ) );
		if ( $paid ) {
			array_push( $head, __( 'Amount paid', 'aluleth-forms' ), __( 'Currency', 'aluleth-forms' ), __( 'PayPal transaction', 'aluleth-forms' ), __( 'Test payment', 'aluleth-forms' ), __( 'Order', 'aluleth-forms' ), __( 'Paid with', 'aluleth-forms' ) );
		}
		// Extra columns from add-ons, as key => heading.
		$extra_cols = (array) apply_filters( 'aluleth_forms/csv_columns', array(), $result['items'] );
		foreach ( $extra_cols as $heading ) {
			$head[] = (string) $heading;
		}
		fputcsv( $out, $head );
		foreach ( $result['items'] as $item ) {
			$row = array( $item->id, $item->status, $item->created_at );
			foreach ( $columns as $key ) {
				$value = isset( $item->data[ $key ] ) ? self::value( $item->data[ $key ], isset( $types[ $key ] ) ? $types[ $key ] : '' ) : '';
				// Avoid formulas being run when the file is opened in a spreadsheet.
				$row[] = preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
			}
			if ( $paid ) {
				$p = is_array( $item->payment ) ? $item->payment : array();
				array_push( $row, isset( $p['amount'] ) ? number_format( (float) $p['amount'], 2, '.', '' ) : '', isset( $p['currency'] ) ? $p['currency'] : '', isset( $p['capture_id'] ) ? $p['capture_id'] : '', ! empty( $p['sandbox'] ) ? 'yes' : '', isset( $p['order']['number'] ) ? (string) $p['order']['number'] : '', Payments::method_label( $p ) );
			}
			if ( $extra_cols ) {
				$values = (array) apply_filters( 'aluleth_forms/csv_row', array(), $item, array_keys( $extra_cols ) );
				foreach ( array_keys( $extra_cols ) as $key ) {
					$value = isset( $values[ $key ] ) ? (string) $values[ $key ] : '';
					$row[] = preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
				}
			}
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Meta boxes of the form edit screen.
	 *
	 * @return void
	 */
	public static function meta_boxes() {
		add_meta_box( 'alf-embed', __( 'Show this form', 'aluleth-forms' ), array( __CLASS__, 'box_embed' ), Plugin::POST_TYPE, 'side', 'high' );
		add_meta_box( 'alf-settings', __( 'Settings', 'aluleth-forms' ), array( __CLASS__, 'box_settings' ), Plugin::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'alf-fields', __( 'Fields', 'aluleth-forms' ), array( __CLASS__, 'box_fields' ), Plugin::POST_TYPE, 'normal', 'default' );
	}

	/**
	 * "Show this form" box.
	 *
	 * @param \WP_Post $post Form.
	 * @return void
	 */
	public static function box_embed( $post ) {
		echo '<p>' . esc_html__( 'Add the "Aluleth Form" block to any page, or paste this shortcode:', 'aluleth-forms' ) . '</p>';
		echo '<p><input type="text" readonly class="widefat code" onfocus="this.select()" value="' . esc_attr( '[aluleth_form id="' . $post->ID . '"]' ) . '" aria-label="' . esc_attr__( 'Shortcode', 'aluleth-forms' ) . '"></p>';
	}

	/**
	 * Settings box.
	 *
	 * @param \WP_Post $post Form.
	 * @return void
	 */
	public static function box_settings( $post ) {
		$form = Forms::get( $post->ID, true );
		$s    = $form ? $form['schema']['settings'] : Schema::default_settings();
		wp_nonce_field( 'aluleth_forms_save', 'alf_nonce' );
		$styles = array(
			'clean' => __( 'Clean', 'aluleth-forms' ),
			'soft'  => __( 'Soft', 'aluleth-forms' ),
			'dark'  => __( 'Dark', 'aluleth-forms' ),
		);
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="alf-style">' . esc_html__( 'Style', 'aluleth-forms' ) . '</label></th><td><select id="alf-style" name="alf_settings[style]">';
		foreach ( $styles as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $s['style'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="alf-submit">' . esc_html__( 'Button text', 'aluleth-forms' ) . '</label></th><td><input id="alf-submit" class="regular-text" name="alf_settings[submit_label]" value="' . esc_attr( $s['submit_label'] ) . '"></td></tr>';
		echo '<tr><th><label for="alf-success">' . esc_html__( 'Message after sending', 'aluleth-forms' ) . '</label></th><td><textarea id="alf-success" class="large-text" rows="2" name="alf_settings[success_message]">' . esc_textarea( $s['success_message'] ) . '</textarea></td></tr>';
		echo '<tr><th><label for="alf-redirect">' . esc_html__( 'Or go to this page', 'aluleth-forms' ) . '</label></th><td><input id="alf-redirect" type="url" class="regular-text" name="alf_settings[redirect]" value="' . esc_attr( $s['redirect'] ) . '" placeholder="https://"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Email to your team', 'aluleth-forms' ) . '</th><td><label><input type="checkbox" name="alf_settings[notify]" value="1"' . checked( $s['notify'], true, false ) . '> ' . esc_html__( 'Send each new entry by email', 'aluleth-forms' ) . '</label><br><label for="alf-notify-to" class="screen-reader-text">' . esc_html__( 'Send to', 'aluleth-forms' ) . '</label><input id="alf-notify-to" class="regular-text" name="alf_settings[notify_to]" value="' . esc_attr( $s['notify_to'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '"><p class="description">' . esc_html__( 'Several addresses: separate them with commas. Empty: the site admin email.', 'aluleth-forms' ) . '</p></td></tr>';
		echo '<tr><th>' . esc_html__( 'Email to the visitor', 'aluleth-forms' ) . '</th><td><label><input type="checkbox" name="alf_settings[confirm]" value="1"' . checked( $s['confirm'], true, false ) . '> ' . esc_html__( 'Send a confirmation with a copy of their answers', 'aluleth-forms' ) . '</label><br><label for="alf-confirm-subject" class="screen-reader-text">' . esc_html__( 'Subject', 'aluleth-forms' ) . '</label><input id="alf-confirm-subject" class="regular-text" name="alf_settings[confirm_subject]" value="' . esc_attr( $s['confirm_subject'] ) . '"><br><label for="alf-confirm-message" class="screen-reader-text">' . esc_html__( 'Message', 'aluleth-forms' ) . '</label><textarea id="alf-confirm-message" class="large-text" rows="4" name="alf_settings[confirm_message]">' . esc_textarea( $s['confirm_message'] ) . '</textarea></td></tr>';
		echo '<tr><th><label for="alf-tags">' . esc_html__( 'Tags for the contact', 'aluleth-forms' ) . '</label></th><td><input id="alf-tags" class="regular-text" name="alf_settings[tags]" value="' . esc_attr( implode( ', ', $s['tags'] ) ) . '"><p class="description">' . esc_html__( 'Separated by commas. Shared with every Aluleth plugin.', 'aluleth-forms' ) . '</p></td></tr>';
		echo '</tbody></table>';
	}

	/**
	 * Fields box (JSON until the visual builder ships).
	 *
	 * @param \WP_Post $post Form.
	 * @return void
	 */
	public static function box_fields( $post ) {
		$form   = Forms::get( $post->ID, true );
		$fields = $form ? $form['schema']['fields'] : Schema::contact_form()['fields'];
		echo '<p class="description">' . esc_html__( 'The visual builder is coming. Until then, the fields are edited here as JSON.', 'aluleth-forms' ) . '</p>';
		echo '<label for="alf-fields-json" class="screen-reader-text">' . esc_html__( 'Fields', 'aluleth-forms' ) . '</label><textarea id="alf-fields-json" class="large-text code" rows="18" name="alf_fields">' . esc_textarea( wp_json_encode( $fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</textarea>';
	}

	/**
	 * Saves the edit screen.
	 *
	 * @param int      $post_id Form id.
	 * @param \WP_Post $post    Form.
	 * @return void
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['alf_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['alf_nonce'] ) ), 'aluleth_forms_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		$current = Forms::get( $post_id, true );
		$schema  = $current ? $current['schema'] : Schema::contact_form();
		// Settings are plain text; Schema::clean() then checks each one again.
		$settings = isset( $_POST['alf_settings'] ) && is_array( $_POST['alf_settings'] ) ? map_deep( wp_unslash( $_POST['alf_settings'] ), 'sanitize_textarea_field' ) : array();
		foreach ( array( 'notify', 'confirm' ) as $flag ) {
			$settings[ $flag ] = ! empty( $settings[ $flag ] );
		}
		if ( isset( $settings['tags'] ) && ! is_array( $settings['tags'] ) ) {
			$settings['tags'] = array_map( 'trim', explode( ',', (string) $settings['tags'] ) );
		}
		$schema['settings'] = array_merge( $schema['settings'], $settings );
		if ( isset( $_POST['alf_fields'] ) ) {
			// Each field is cleaned by Schema::clean() as soon as it is read (type, id, labels with wp_kses, options...).
			$posted = Schema::clean( array( 'fields' => json_decode( wp_unslash( $_POST['alf_fields'] ), true ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by Schema::clean() in this statement.
			if ( ! empty( $posted['fields'] ) ) {
				$schema['fields'] = $posted['fields'];
			}
		}
		Forms::save_schema( $post_id, Builder::protect( $schema, $post_id ) );
	}

	/**
	 * Forms list columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['date'] );
		$columns['alf_shortcode']        = __( 'Shortcode', 'aluleth-forms' );
		$columns['aluleth_form_entries'] = __( 'Entries', 'aluleth-forms' );
		if ( $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	/**
	 * Forms list column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Form id.
	 * @return void
	 */
	public static function column( $column, $post_id ) {
		if ( 'alf_shortcode' === $column ) {
			echo '<code>' . esc_html( '[aluleth_form id="' . $post_id . '"]' ) . '</code>';
		} elseif ( 'aluleth_form_entries' === $column ) {
			$count = Entries::query(
				array(
					'form_id'  => $post_id,
					'per_page' => 1,
				)
			);
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=aluleth-forms-entries&form=' . $post_id ) ) . '">' . esc_html( number_format_i18n( $count['total'] ) ) . '</a>';
		}
	}

	/**
	 * Short label of an entry (name or email or first answer).
	 *
	 * @param array $data Entry data.
	 * @return string
	 */
	private static function summary( array $data ) {
		return Entries::summary( $data );
	}

	/**
	 * Printable value.
	 *
	 * @param mixed  $value Value.
	 * @param string $type  Field type.
	 * @return string
	 */
	private static function value( $value, $type ) {
		return Entries::value( $value, $type );
	}
}
