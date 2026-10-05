<?php
/**
 * Plugin Name:       GYD Booking — Programmes & Mentors
 * Plugin URI:        https://gydnetwork.org.uk
 * Description:        A complete, dynamic booking system for Global Youth Development. Create programmes and mentors (with photos & bios), let young people pick a programme, choose a mentor and book a 1-to-1 session — all styled to match the GYD navy / red / gold / cream brand. Display anywhere with shortcodes.
 * Version:           1.0.4
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Global Youth Development Organisation CIC
 * Author URI:        https://gydnetwork.org.uk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gyd-booking
 * Domain Path:       /languages
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */
define( 'GYDB_VERSION', '1.0.4' );
define( 'GYDB_DB_VERSION', '1.0.0' );
define( 'GYDB_FILE', __FILE__ );
define( 'GYDB_PATH', plugin_dir_path( __FILE__ ) );
define( 'GYDB_URL', plugin_dir_url( __FILE__ ) );
define( 'GYDB_BASENAME', plugin_basename( __FILE__ ) );
define( 'GYDB_PROGRAM_CPT', 'gyd_program' );
define( 'GYDB_MENTOR_CPT', 'gyd_mentor' );

/* -------------------------------------------------------------------------
 * Includes
 * ---------------------------------------------------------------------- */
require_once GYDB_PATH . 'includes/class-gydb-helpers.php';
require_once GYDB_PATH . 'includes/class-gydb-database.php';
require_once GYDB_PATH . 'includes/class-gydb-cpt.php';
require_once GYDB_PATH . 'includes/class-gydb-meta.php';
require_once GYDB_PATH . 'includes/class-gydb-assets.php';
require_once GYDB_PATH . 'includes/class-gydb-shortcodes.php';
require_once GYDB_PATH . 'includes/class-gydb-ajax.php';
require_once GYDB_PATH . 'includes/class-gydb-emails.php';
require_once GYDB_PATH . 'includes/class-gydb-seed.php';
require_once GYDB_PATH . 'includes/class-gydb-admin.php';
require_once GYDB_PATH . 'includes/class-gydb-plugin.php';

/* -------------------------------------------------------------------------
 * Activation / Deactivation
 * ---------------------------------------------------------------------- */

/**
 * Runs on activation: create the bookings table, register CPTs so rewrite
 * rules are known, seed the nine real programmes + sample mentors, create a
 * booking page, then flush rewrite rules.
 */
function gydb_activate() {
	GYDB_Database::create_table();

	// CPTs must be registered before flushing rewrite rules.
	GYDB_CPT::register();

	// Default options first, so seeding can build on them.
	GYDB_Helpers::install_default_options();

	// Seed real programme data + sample mentors + booking page (idempotent).
	GYDB_Seed::run();

	// Show the one-time setup notice for two weeks.
	set_transient( 'gydb_show_setup_notice', 1, 2 * WEEK_IN_SECONDS );

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'gydb_activate' );

/**
 * Runs on deactivation: just flush rewrite rules. Data is preserved so the
 * client never loses programmes, mentors or bookings by toggling the plugin.
 */
function gydb_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'gydb_deactivate' );

/* -------------------------------------------------------------------------
 * Boot
 * ---------------------------------------------------------------------- */
GYDB_Plugin::instance();
