<?php
/**
 * Uninstall handler.
 *
 * Runs only when WordPress processes a real plugin deletion request.
 * Consent data is kept by default for compliance reasons.
 * Full deletion is enabled manually with the uninstall setting.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_consent_table = get_option( 'bcc_delete_data_on_uninstall' ) === '1';

// Remove plugin options.
delete_option( 'bcc_db_version' );
delete_option( 'bcc_plugin_lang' );
delete_option( 'bcc_banner_enabled' );
delete_option( 'bcc_delete_data_on_uninstall' );

// Remove scheduled hooks.
wp_clear_scheduled_hook( 'bcc_cleanup_db_logs' );
wp_clear_scheduled_hook( 'bcc_cleanup_file_logs' );

// Drop consent table only when the admin explicitly enabled it.
if ( $delete_consent_table ) {
	global $wpdb;

	$table = $wpdb->prefix . 'bcc_cookie_consents';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}