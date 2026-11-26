<?php
/**
 * Uninstall. Nothing is deleted unless the site owner asked for it in
 * Aluleth › Settings:
 * - "Aluleth Forms": forms, entries, statistics and the plugin's options;
 * - shared data (contacts, consents, connections, send log): only when no
 *   other Aluleth plugin is left on the site.
 *
 * @package Aluleth\Forms
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/core/uninstall.php';

/**
 * Whether the data stays because the complete Aluleth Forms Pro, which includes
 * this plugin, is still installed (only when this plugin is deleted on its own,
 * not when Aluleth Forms Pro deletes its included copy).
 *
 * @return bool
 */
function aluleth_forms_uninstall_kept() {
	return 'free' !== basename( __DIR__ ) && is_readable( WP_PLUGIN_DIR . '/aluleth-forms-pro/free/includes/class-plugin.php' );
}

/**
 * Cleans the current site.
 *
 * @return void
 */
function aluleth_forms_uninstall_site() {
	global $wpdb;
	if ( aluleth_forms_uninstall_kept() ) {
		// Aluleth Forms Pro (complete copy) includes this plugin: its forms, entries and settings keep being used.
		aluleth_core_uninstall( __DIR__ );
		return;
	}
	if ( get_option( 'aluleth_forms_delete_data' ) ) {
		$ids = get_posts(
			array(
				'post_type'      => 'aluleth_form',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
		// Current names, and the names used before 0.19.2.
		foreach ( array( 'aluleth_form_entries', 'aluleth_form_stats', 'alf_entries', 'alf_stats' ) as $aluleth_forms_table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $aluleth_forms_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
		foreach ( array( '\_transient\_aluleth\_forms\_%', '\_transient\_timeout\_aluleth\_forms\_%', '\_transient\_alf\_%', '\_transient\_timeout\_alf\_%' ) as $pattern ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, $pattern ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		delete_metadata( 'user', 0, 'aluleth_forms_entries_view', '', true );
		delete_option( 'aluleth_forms_views_since' );
		delete_option( 'aluleth_forms_views_started' );
		delete_option( 'aluleth_forms_db_version' );
		delete_option( 'aluleth_forms_first_form' );
		delete_option( 'aluleth_forms_delete_data' );
		delete_option( 'aluleth_forms_general' );
		delete_option( 'aluleth_forms_ai' );
		delete_option( 'aluleth_forms_summary_sent' );
	}
	wp_clear_scheduled_hook( 'aluleth_forms_daily' );
	aluleth_core_uninstall( __DIR__ );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $aluleth_forms_site ) {
		switch_to_blog( $aluleth_forms_site );
		aluleth_forms_uninstall_site();
		restore_current_blog();
	}
} else {
	aluleth_forms_uninstall_site();
}
