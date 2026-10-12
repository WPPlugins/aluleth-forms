<?php
/**
 * "Import forms" screen: forms found from Contact Form 7, WPForms,
 * Elementor Forms and MetForm, imported with their entries.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

use Aluleth\Core\Admin as Core_Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Import screen.
 */
final class Import_Screen {

	/**
	 * Page slug.
	 */
	const PAGE = 'aluleth-forms-import';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 32 );
		add_action( 'admin_post_aluleth_forms_import', array( __CLASS__, 'run' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
	}

	/**
	 * Hidden page.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( '', __( 'Import forms', 'aluleth-forms' ), __( 'Import forms', 'aluleth-forms' ), Core_Admin::cap( 'forms' ), self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Keeps Aluleth › Forms open in the menu.
	 *
	 * @param string $parent_file Parent menu.
	 * @return string
	 */
	public static function parent_file( $parent_file ) {
		global $plugin_page, $submenu_file, $submenu;
		if ( self::PAGE === $plugin_page ) {
			$submenu_file = 'edit.php?post_type=' . Plugin::POST_TYPE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- highlights the Forms menu item.
			// WordPress would reset the parent of a hidden page to none right after this filter.
			foreach ( isset( $submenu[''] ) ? (array) $submenu[''] : array() as $i => $item ) {
				if ( isset( $item[2] ) && self::PAGE === $item[2] ) {
					unset( $submenu[''][ $i ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- access was already checked.
				}
			}
			return \Aluleth\Core\Admin::MENU;
		}
		return $parent_file;
	}

	/**
	 * Screen URL.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * Styles (shared with the start screen).
	 *
	 * @param string $hook Screen hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'aluleth-forms-start', plugins_url( 'assets/start.css', ALULETH_FORMS_FILE ), array(), ALULETH_FORMS_VERSION );
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( Core_Admin::cap( 'forms' ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import forms.', 'aluleth-forms' ) );
		}
		$results = get_transient( 'aluleth_forms_import_' . get_current_user_id() );
		delete_transient( 'aluleth_forms_import_' . get_current_user_id() );
		$found   = Importer::found();
		$sources = Importer::sources();

		echo '<div class="wrap alf-start alf-import">';
		echo '<p><a href="' . esc_url( Start::url() ) . '">&larr; ' . esc_html__( 'Create a form', 'aluleth-forms' ) . '</a></p>';
		echo '<header class="alf-start-head"><span class="alf-start-kicker">' . esc_html__( 'Import', 'aluleth-forms' ) . '</span><h1>' . esc_html__( 'Bring your forms and entries', 'aluleth-forms' ) . '</h1>';
		echo '<p>' . esc_html__( 'From Contact Form 7 (entries saved by Flamingo), WPForms, Elementor Forms and MetForm, even if the plugin is already deactivated. Your old forms are not changed: replace their shortcode or widget by the Aluleth form when you are ready.', 'aluleth-forms' ) . '</p></header>';

		if ( is_array( $results ) ) {
			echo '<section class="alf-import-results" role="status"><h2>' . esc_html__( 'Import done', 'aluleth-forms' ) . '</h2><ul>';
			foreach ( $results as $result ) {
				if ( ! empty( $result['error'] ) ) {
					echo '<li class="is-error"><strong>' . esc_html( $result['title'] ) . '</strong> — ' . esc_html( $result['error'] ) . '</li>';
					continue;
				}
				echo '<li><strong>' . esc_html( $result['title'] ) . '</strong> — ';
				/* translators: %d: number of fields. */
				echo esc_html( sprintf( _n( '%d field', '%d fields', (int) $result['fields'], 'aluleth-forms' ), (int) $result['fields'] ) . ', ' );
				/* translators: %d: number of entries. */
				echo esc_html( sprintf( _n( '%d entry.', '%d entries.', (int) $result['entries'], 'aluleth-forms' ), (int) $result['entries'] ) );
				echo ' <a href="' . esc_url( Builder::url( (int) $result['form_id'] ) ) . '">' . esc_html__( 'Open the form', 'aluleth-forms' ) . '</a>';
				if ( $result['entries'] ) {
					echo ' · <a href="' . esc_url( admin_url( 'admin.php?page=aluleth-forms-entries&view=board&form=' . (int) $result['form_id'] ) ) . '">' . esc_html__( 'See the entries', 'aluleth-forms' ) . '</a>';
				}
				if ( ! empty( $result['skipped'] ) ) {
					/* translators: %s: field labels. */
					echo '<br><span class="alf-muted">' . esc_html( sprintf( __( 'Not imported (no matching field type yet): %s.', 'aluleth-forms' ), implode( ', ', $result['skipped'] ) ) ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul></section>';
		}

		if ( ! $found ) {
			echo '<div class="alf-start-ai"><p>' . esc_html__( 'No form from Contact Form 7, WPForms, Elementor Forms or MetForm was found on this site.', 'aluleth-forms' ) . '</p></div></div>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="alf-import-form">';
		wp_nonce_field( 'aluleth_forms_import' );
		echo '<input type="hidden" name="action" value="aluleth_forms_import">';
		foreach ( $sources as $source => $name ) {
			$items = array_filter(
				$found,
				static function ( $item ) use ( $source ) {
					return $item['source'] === $source;
				}
			);
			if ( ! $items ) {
				continue;
			}
			echo '<section class="alf-start-ai alf-import-source"><h2>' . esc_html( $name ) . '</h2><ul class="alf-import-list">';
			foreach ( $items as $item ) {
				$id = 'alf-imp-' . sanitize_html_class( str_replace( ':', '-', $item['key'] ) );
				echo '<li>';
				if ( $item['imported'] ) {
					echo '<span class="alf-import-check is-done" aria-hidden="true">✓</span><span class="alf-import-title"><strong>' . esc_html( $item['title'] ) . '</strong>';
				} else {
					echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="forms[]" value="' . esc_attr( $item['key'] ) . '" checked><label class="alf-import-title" for="' . esc_attr( $id ) . '"><strong>' . esc_html( $item['title'] ) . '</strong>';
				}
				$meta = array();
				if ( ! empty( $item['where'] ) ) {
					/* translators: %s: page title. */
					$meta[] = sprintf( __( 'on “%s”', 'aluleth-forms' ), $item['where'] );
				}
				/* translators: %d: number of entries. */
				$meta[] = sprintf( _n( '%d entry', '%d entries', (int) $item['entries'], 'aluleth-forms' ), (int) $item['entries'] );
				echo '<span>' . esc_html( implode( ' · ', $meta ) ) . '</span>' . ( $item['imported'] ? '</span>' : '</label>' );
				if ( $item['imported'] ) {
					echo '<a href="' . esc_url( Builder::url( (int) $item['imported'] ) ) . '">' . esc_html__( 'Already imported: open', 'aluleth-forms' ) . '</a>';
				}
				echo '</li>';
			}
			echo '</ul>';
			if ( 'wpforms' === $source ) {
				echo '<p class="alf-start-note">' . esc_html__( 'WPForms Lite does not keep entries on your site; WPForms Pro entries are imported.', 'aluleth-forms' ) . '</p>';
			}
			echo '</section>';
		}
		echo '<section class="alf-start-ai alf-import-options"><h2>' . esc_html__( 'Options', 'aluleth-forms' ) . '</h2>';
		echo '<label><input type="checkbox" name="entries" value="1" checked> ' . esc_html__( 'Import the entries too', 'aluleth-forms' ) . '</label>';
		echo '<label><input type="checkbox" name="contacts" value="1" checked> ' . esc_html__( 'Link entries to contacts (created as not subscribed: an old consent cannot be proven, so nobody is added to your newsletter)', 'aluleth-forms' ) . '</label>';
		echo '<fieldset><legend>' . esc_html__( 'Put the imported entries in', 'aluleth-forms' ) . '</legend><label><input type="radio" name="status" value="last" checked> ' . esc_html__( 'the last column (handled)', 'aluleth-forms' ) . '</label> <label><input type="radio" name="status" value="first"> ' . esc_html__( 'the first column (to handle)', 'aluleth-forms' ) . '</label></fieldset>';
		echo '<div class="alf-start-row"><span class="alf-start-note">' . esc_html__( 'Imported forms are published so their shortcode works right away; they are not placed on any page.', 'aluleth-forms' ) . '</span><button type="submit" class="alf-start-go"><span>' . esc_html__( 'Import the selected forms', 'aluleth-forms' ) . '</span></button></div></section>';
		echo '</form></div>';
	}

	/**
	 * Runs the import (admin-post).
	 *
	 * @return void
	 */
	public static function run() {
		check_admin_referer( 'aluleth_forms_import' );
		if ( ! current_user_can( Core_Admin::cap( 'forms' ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import forms.', 'aluleth-forms' ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- large imports.
		}
		$keys    = isset( $_POST['forms'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['forms'] ) ) : array();
		$options = array(
			'entries'  => ! empty( $_POST['entries'] ),
			'contacts' => ! empty( $_POST['contacts'] ),
			'status'   => isset( $_POST['status'] ) && 'first' === $_POST['status'] ? 'first' : 'last',
		);
		$titles  = array();
		foreach ( Importer::found() as $item ) {
			$titles[ $item['key'] ] = $item['title'];
		}
		$results = array();
		foreach ( array_slice( $keys, 0, 50 ) as $key ) {
			if ( ! isset( $titles[ $key ] ) ) {
				continue;
			}
			$result = Importer::import( $key, $options );
			if ( is_wp_error( $result ) ) {
				$results[] = array(
					'title' => $titles[ $key ],
					'error' => $result->get_error_message(),
				);
			} else {
				$results[] = $result;
			}
		}
		set_transient( 'aluleth_forms_import_' . get_current_user_id(), $results, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url() );
		exit;
	}
}
