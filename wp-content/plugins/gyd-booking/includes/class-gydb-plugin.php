<?php
/**
 * Main plugin orchestrator — wires the components together.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GYDB_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var GYDB_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get / create the singleton.
	 *
	 * @return GYDB_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot all components.
	 */
	private function __construct() {
		// Translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Components.
		GYDB_CPT::init();
		GYDB_Meta::init();
		GYDB_Assets::init();
		GYDB_Shortcodes::init();
		GYDB_Ajax::init();

		if ( is_admin() ) {
			GYDB_Admin::init();
		}

		// Settings / plugin-list links.
		add_filter( 'plugin_action_links_' . GYDB_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Load the text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'gyd-booking', false, dirname( GYDB_BASENAME ) . '/languages' );
	}

	/**
	 * Add Settings / Help links to the plugins list.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$custom = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=gyd-booking-settings' ) ) . '">' . esc_html__( 'Settings', 'gyd-booking' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=gyd-booking-help' ) ) . '">' . esc_html__( 'Help', 'gyd-booking' ) . '</a>',
		);
		return array_merge( $custom, $links );
	}
}
