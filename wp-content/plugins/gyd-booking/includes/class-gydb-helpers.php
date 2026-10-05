<?php
/**
 * Shared helpers: options, brand tokens, formatting, data getters.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GYDB_Helpers {

	const OPTION_KEY = 'gydb_settings';

	/**
	 * Default plugin settings. Colours mirror the GYD brand so the plugin
	 * looks identical to the hand-built site on any WordPress theme.
	 */
	public static function default_settings() {
		return array(
			'booking_page_id'   => 0,
			'admin_email'       => get_option( 'admin_email' ),
			'notify_admin'      => 1,
			'notify_client'     => 1,
			'email_from_name'   => get_bloginfo( 'name' ),
			'from_email'        => get_option( 'admin_email' ),
			'default_slot_len'  => 45,
			'default_start'     => '09:00',
			'default_end'       => '17:00',
			'default_days'      => array( '1', '2', '3', '4', '5' ), // Mon–Fri.
			'min_notice_hours'  => 24,
			'max_days_ahead'    => 60,
			'success_message'   => __( 'Thank you — your session request has been received. We\'ll confirm your booking by email shortly.', 'gyd-booking' ),
			// Brand tokens (BAYC / GYD design spec v2).
			'color_navy'        => '#0A2540',
			'color_navy2'       => '#143659',
			'color_red'         => '#E41E20',
			'color_red_dark'    => '#C41A1C',
			'color_gold'        => '#D4A017',
			'color_gold_light'  => '#E8BC45',
			'color_cream'       => '#FAF6EF',
			'color_cream2'      => '#F1EBDF',
			'color_ink'         => '#1A1A1A',
			'color_muted'       => '#6B7280',
			'color_line'        => '#E5E0D4',
			'load_fonts'        => 1,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::default_settings() );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is missing.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = '' ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Install defaults on activation without clobbering existing settings.
	 */
	public static function install_default_options() {
		$existing = get_option( self::OPTION_KEY, false );
		if ( false === $existing ) {
			add_option( self::OPTION_KEY, self::default_settings() );
		}
	}

	/**
	 * URL of the booking page, with an optional preselected programme slug.
	 *
	 * @param string $program_slug Programme post slug to preselect.
	 * @return string
	 */
	public static function get_booking_url( $program_slug = '' ) {
		$page_id = (int) self::get_setting( 'booking_page_id', 0 );
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );

		if ( ! $url ) {
			$url = home_url( '/' );
		}

		if ( $program_slug ) {
			$url = add_query_arg( 'gyd_program', rawurlencode( $program_slug ), $url );
		}
		return $url;
	}

	/**
	 * Weekday labels keyed 0 (Sun) – 6 (Sat) to match PHP's date( 'w' ).
	 */
	public static function weekdays() {
		return array(
			'0' => __( 'Sunday', 'gyd-booking' ),
			'1' => __( 'Monday', 'gyd-booking' ),
			'2' => __( 'Tuesday', 'gyd-booking' ),
			'3' => __( 'Wednesday', 'gyd-booking' ),
			'4' => __( 'Thursday', 'gyd-booking' ),
			'5' => __( 'Friday', 'gyd-booking' ),
			'6' => __( 'Saturday', 'gyd-booking' ),
		);
	}

	/**
	 * Booking status labels.
	 */
	public static function statuses() {
		return array(
			'pending'   => __( 'Pending', 'gyd-booking' ),
			'confirmed' => __( 'Confirmed', 'gyd-booking' ),
			'completed' => __( 'Completed', 'gyd-booking' ),
			'cancelled' => __( 'Cancelled', 'gyd-booking' ),
		);
	}

	/**
	 * Resolve a mentor's photo URL. Uses the dedicated photo meta first, then
	 * the featured image, then a branded placeholder flag (empty string).
	 *
	 * @param int    $mentor_id Mentor post ID.
	 * @param string $size      Image size.
	 * @return string Image URL, or empty string for a CSS placeholder.
	 */
	public static function get_mentor_photo_url( $mentor_id, $size = 'medium' ) {
		$photo_id = (int) get_post_meta( $mentor_id, '_gyd_photo_id', true );
		if ( $photo_id ) {
			$url = wp_get_attachment_image_url( $photo_id, $size );
			if ( $url ) {
				return $url;
			}
		}
		if ( has_post_thumbnail( $mentor_id ) ) {
			return (string) get_the_post_thumbnail_url( $mentor_id, $size );
		}
		return '';
	}

	/**
	 * Return the program IDs a mentor is assigned to (array of ints).
	 *
	 * @param int $mentor_id Mentor post ID.
	 * @return int[]
	 */
	public static function get_mentor_program_ids( $mentor_id ) {
		$ids = get_post_meta( $mentor_id, '_gyd_programs', true );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Get published mentors for a given programme, ordered by menu order/title.
	 *
	 * @param int $program_id Programme post ID.
	 * @return WP_Post[]
	 */
	public static function get_mentors_for_program( $program_id ) {
		$program_id = (int) $program_id;
		if ( ! $program_id ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'      => GYDB_MENTOR_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_gyd_programs',
						'value'   => 'i:' . $program_id . ';', // serialized int match.
						'compare' => 'LIKE',
					),
				),
			)
		);

		// Guard: LIKE on serialized data can over-match (e.g. 1 vs 12), so
		// confirm membership precisely in PHP.
		$mentors = array();
		foreach ( $query->posts as $mentor ) {
			if ( in_array( $program_id, self::get_mentor_program_ids( $mentor->ID ), true ) ) {
				$mentors[] = $mentor;
			}
		}
		return $mentors;
	}

	/**
	 * Get all published programmes ordered by menu order.
	 *
	 * @return WP_Post[]
	 */
	public static function get_programs() {
		return get_posts(
			array(
				'post_type'      => GYDB_PROGRAM_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'date'       => 'ASC',
				),
			)
		);
	}

	/**
	 * Two-digit programme number, e.g. "01". Uses the explicit number meta,
	 * falling back to menu order.
	 *
	 * @param WP_Post|int $program Programme.
	 * @return string
	 */
	public static function get_program_number( $program ) {
		$program = get_post( $program );
		if ( ! $program ) {
			return '';
		}
		$num = get_post_meta( $program->ID, '_gyd_number', true );
		if ( '' === $num || null === $num ) {
			$num = $program->menu_order;
		}
		$num = (int) $num;
		return $num > 0 ? str_pad( (string) $num, 2, '0', STR_PAD_LEFT ) : '';
	}

	/**
	 * Short programme description for the booking context box. Uses the
	 * dedicated meta, then the excerpt, then a trimmed version of the content.
	 *
	 * @param WP_Post|int $program Programme.
	 * @return string
	 */
	public static function get_program_short_desc( $program ) {
		$program = get_post( $program );
		if ( ! $program ) {
			return '';
		}
		$desc = (string) get_post_meta( $program->ID, '_gyd_short_desc', true );
		if ( '' !== trim( $desc ) ) {
			return $desc;
		}
		if ( '' !== trim( (string) $program->post_excerpt ) ) {
			return $program->post_excerpt;
		}
		return wp_trim_words( wp_strip_all_tags( $program->post_content ), 40 );
	}

	/**
	 * Format a stored 24h time (HH:MM[:SS]) using the site time format.
	 *
	 * @param string $time Time string.
	 * @return string
	 */
	public static function format_time( $time ) {
		$ts = strtotime( $time );
		if ( ! $ts ) {
			return $time;
		}
		return date_i18n( get_option( 'time_format', 'g:i a' ), $ts );
	}

	/**
	 * Format a stored date (Y-m-d) using the site date format.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	public static function format_date( $date ) {
		$ts = strtotime( $date );
		if ( ! $ts ) {
			return $date;
		}
		return date_i18n( get_option( 'date_format', 'F j, Y' ), $ts );
	}
}
