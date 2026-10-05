<?php
/**
 * Front-end asset loading + dynamic brand CSS variables.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GYDB_Assets {

	/**
	 * Whether the current request should load plugin assets.
	 *
	 * @var bool
	 */
	private static $should_load = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
	}

	/**
	 * Flag that a shortcode on the page needs the assets. Called from shortcodes.
	 */
	public static function enqueue() {
		self::$should_load = true;

		// If shortcodes run after wp_enqueue_scripts (e.g. late do_shortcode),
		// enqueue immediately.
		if ( did_action( 'wp_enqueue_scripts' ) ) {
			self::load_assets();
		}
	}

	/**
	 * Register handles; load now if a shortcode already asked for them.
	 */
	public static function register() {
		wp_register_style(
			'gydb-fonts',
			'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@400;500;600;700&display=swap',
			array(),
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts.
		);

		wp_register_style(
			'gydb-frontend',
			GYDB_URL . 'assets/css/gyd-booking.css',
			array(),
			self::asset_ver( 'assets/css/gyd-booking.css' )
		);

		wp_register_script(
			'gydb-frontend',
			GYDB_URL . 'assets/js/gyd-booking.js',
			array(),
			self::asset_ver( 'assets/js/gyd-booking.js' ),
			true
		);

		$booking_page_id = (int) GYDB_Helpers::get_setting( 'booking_page_id', 0 );
		$booking_url     = GYDB_Helpers::get_booking_url();
		$booking_path    = $booking_url ? wp_parse_url( $booking_url, PHP_URL_PATH ) : '';

		wp_localize_script(
			'gydb-frontend',
			'GYDB',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'gydb_booking' ),
				'bookingUrl'     => $booking_url,
				'bookingPath'    => $booking_path ? untrailingslashit( $booking_path ) : '',
				'bookingPageId'  => $booking_page_id,
				'i18n'           => array(
					'selectDate'    => __( 'Select a date to see available times.', 'gyd-booking' ),
					'bookingWith'   => __( 'Booking with', 'gyd-booking' ),
					'noSlots'       => __( 'No times available on this day. Please choose another date.', 'gyd-booking' ),
					'loading'       => __( 'Loading…', 'gyd-booking' ),
					'chooseSlot'    => __( 'Please choose a time slot.', 'gyd-booking' ),
					'submitting'    => __( 'Submitting…', 'gyd-booking' ),
					'genericError'  => __( 'Something went wrong. Please try again.', 'gyd-booking' ),
					'requiredField' => __( 'Please complete the required fields.', 'gyd-booking' ),
				),
			)
		);

		// Load site-wide when the popup is enabled, so that a "Book Now" link
		// in the theme's nav menu can open it on any page.
		if ( self::$should_load || apply_filters( 'gydb_enable_popup', true, false ) ) {
			self::load_assets();
		}
	}

	/**
	 * Actually enqueue the registered handles + inline brand variables.
	 */
	public static function load_assets() {
		if ( GYDB_Helpers::get_setting( 'load_fonts', 1 ) ) {
			wp_enqueue_style( 'gydb-fonts' );
		}
		wp_enqueue_style( 'gydb-frontend' );
		wp_enqueue_script( 'gydb-frontend' );

		wp_add_inline_style( 'gydb-frontend', self::brand_css_vars() );
	}

	/**
	 * Version string for an asset. Uses the file's modification time so a
	 * changed file ALWAYS busts the browser / CDN cache, even if the plugin
	 * version was not bumped. Falls back to the plugin version.
	 *
	 * @param string $relative_path Path relative to the plugin root.
	 * @return string
	 */
	public static function asset_ver( $relative_path ) {
		$file = GYDB_PATH . ltrim( $relative_path, '/' );
		if ( file_exists( $file ) ) {
			$mtime = filemtime( $file );
			if ( $mtime ) {
				return GYDB_VERSION . '.' . $mtime;
			}
		}
		return GYDB_VERSION;
	}

	/**
	 * Build the :root custom-property block from the saved brand colours so the
	 * plugin matches the site exactly and can be re-skinned from Settings.
	 *
	 * @return string
	 */
	public static function brand_css_vars() {
		$s = GYDB_Helpers::get_settings();

		$map = array(
			'--gyd-navy'       => $s['color_navy'],
			'--gyd-navy-2'     => $s['color_navy2'],
			'--gyd-red'        => $s['color_red'],
			'--gyd-red-dark'   => $s['color_red_dark'],
			'--gyd-gold'       => $s['color_gold'],
			'--gyd-gold-light' => $s['color_gold_light'],
			'--gyd-cream'      => $s['color_cream'],
			'--gyd-cream-2'    => $s['color_cream2'],
			'--gyd-ink'        => $s['color_ink'],
			'--gyd-muted'      => $s['color_muted'],
			'--gyd-line'       => $s['color_line'],
		);

		$lines = array();
		foreach ( $map as $var => $val ) {
			$lines[] = $var . ':' . self::sanitize_color( $val ) . ';';
		}

		return '.gyd-booking{' . implode( '', $lines ) . '}';
	}

	/**
	 * Allow hex colours only; fall back to transparent if malformed.
	 *
	 * @param string $color Raw colour.
	 * @return string
	 */
	private static function sanitize_color( $color ) {
		$color = trim( (string) $color );
		if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ) {
			return $color;
		}
		return 'transparent';
	}
}
