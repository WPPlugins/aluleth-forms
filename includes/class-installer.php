<?php
/**
 * Aluleth Forms tables and first form.
 *
 * @package Aluleth\Forms
 */

namespace Aluleth\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
final class Installer {

	/**
	 * Database version of the forms tables.
	 */
	const DB_VERSION = '0.6.0';

	/**
	 * Creates or updates the entries table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::migrate_names();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}aluleth_form_entries (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  form_id bigint(20) unsigned NOT NULL,
  contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(30) NOT NULL DEFAULT 'new',
  data longtext,
  notes longtext,
  payment longtext,
  extra longtext,
  ip_hash char(64) NOT NULL DEFAULT '',
  user_agent varchar(255) NOT NULL DEFAULT '',
  page_url varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY form_status (form_id,status),
  KEY contact_id (contact_id),
  KEY created_at (created_at)
) $charset;"
		);
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}aluleth_form_stats (
  form_id bigint(20) unsigned NOT NULL,
  day date NOT NULL,
  views int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (form_id,day),
  KEY day (day)
) $charset;"
		);
		if ( ! get_option( 'aluleth_forms_views_since' ) ) {
			update_option( 'aluleth_forms_views_since', wp_date( 'Y-m-d' ), false );
		}
		if ( ! get_option( 'aluleth_forms_views_started' ) ) {
			// Exact start of the views counter (UTC): conversion only compares entries received since then.
			update_option( 'aluleth_forms_views_started', gmdate( 'Y-m-d H:i:s' ), false );
		}
		\Aluleth\Core\Installer::finish_renames( self::renamed_tables() );
		update_option( 'aluleth_forms_db_version', self::DB_VERSION, false );
	}

	/**
	 * Tables renamed in 0.19.2 (old full name => new full name).
	 *
	 * @return array
	 */
	private static function renamed_tables() {
		global $wpdb;
		return array(
			$wpdb->prefix . 'alf_entries' => $wpdb->prefix . 'aluleth_form_entries',
			$wpdb->prefix . 'alf_stats'   => $wpdb->prefix . 'aluleth_form_stats',
		);
	}

	/**
	 * Names used before 0.19.2 ("alf_…"), renamed with the plugin's full prefix.
	 *
	 * @return void
	 */
	private static function migrate_names() {
		global $wpdb;
		\Aluleth\Core\Installer::rename_tables( self::renamed_tables() );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery
		$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_aluleth_form_schema' ), array( 'meta_key' => '_alf_schema' ) );
		$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_aluleth_imported_from' ), array( 'meta_key' => '_alf_imported_from' ) );
		$wpdb->update( $wpdb->usermeta, array( 'meta_key' => 'aluleth_forms_entries_view' ), array( 'meta_key' => 'alf_entries_view' ) );
		// Payments waiting for PayPal and other short-lived data keep working across the update.
		foreach ( array( '_transient_', '_transient_timeout_' ) as $kind ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_name = REPLACE( option_name, %s, %s ) WHERE option_name LIKE %s', $wpdb->options, $kind . 'alf_', $kind . 'aluleth_forms_', $wpdb->esc_like( $kind . 'alf_' ) . '%' ) );
		}
		// phpcs:enable
		wp_cache_flush();
	}

	/**
	 * Creates a ready-to-use contact form the first time the plugin is activated.
	 *
	 * @return void
	 */
	public static function create_first_form() {
		if ( get_option( 'aluleth_forms_first_form' ) ) {
			return;
		}
		$existing = get_posts(
			array(
				'post_type'      => Plugin::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! $existing ) {
			Forms::create( __( 'Contact form', 'aluleth-forms' ), Schema::contact_form() );
		}
		update_option( 'aluleth_forms_first_form', 1, false );
	}
}
