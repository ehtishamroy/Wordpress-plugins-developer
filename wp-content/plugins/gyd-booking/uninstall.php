<?php
/**
 * Uninstall cleanup.
 *
 * Removes plugin-owned data: settings, transients, the bookings table and the
 * DB version flag. Programmes and Mentors (regular posts the client curates,
 * including uploaded photos) are intentionally preserved so content is not
 * destroyed by an accidental delete. Remove them manually if desired.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Options & transients.
delete_option( 'gydb_settings' );
delete_option( 'gydb_db_version' );
delete_option( 'gydb_seeded' );
delete_transient( 'gydb_show_setup_notice' );

// Bookings table.
$table = $wpdb->prefix . 'gyd_bookings';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
