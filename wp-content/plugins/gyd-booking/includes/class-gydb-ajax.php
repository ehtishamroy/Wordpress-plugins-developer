<?php
/**
 * AJAX endpoints for the booking flow (available to logged-out visitors).
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GYDB_Ajax {

	public static function init() {
		$actions = array( 'get_program_view', 'get_slots', 'submit_booking' );
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_gydb_' . $action, array( __CLASS__, $action ) );
			add_action( 'wp_ajax_nopriv_gydb_' . $action, array( __CLASS__, $action ) );
		}
	}

	/**
	 * Verify the shared booking nonce or die with a JSON error.
	 */
	private static function check_nonce() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'gydb_booking' ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please refresh the page and try again.', 'gyd-booking' ) ), 403 );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Programme view: context box + mentor cards
	 * ------------------------------------------------------------------ */
	public static function get_program_view() {
		self::check_nonce();

		$program_id = isset( $_POST['program_id'] ) ? absint( wp_unslash( $_POST['program_id'] ) ) : 0;
		$program    = GYDB_Shortcodes::resolve_program( $program_id );

		if ( ! $program ) {
			wp_send_json_error( array( 'message' => __( 'Programme not found.', 'gyd-booking' ) ) );
		}

		$mentors = GYDB_Helpers::get_mentors_for_program( $program->ID );

		$mentors_html = '';
		if ( empty( $mentors ) ) {
			$mentors_html = GYDB_Shortcodes::notice( __( 'No mentors are available for this programme yet.', 'gyd-booking' ) );
		} else {
			foreach ( $mentors as $mentor ) {
				$mentors_html .= GYDB_Shortcodes::render_mentor_card( $mentor, $program, false );
			}
		}

		wp_send_json_success(
			array(
				'program_id'   => $program->ID,
				'title'        => $program->post_title,
				'context_html' => GYDB_Shortcodes::render_program_context( $program ),
				'mentors_html' => $mentors_html,
				'has_mentors'  => ! empty( $mentors ),
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Available slots for a mentor on a date
	 * ------------------------------------------------------------------ */
	public static function get_slots() {
		self::check_nonce();

		$mentor_id = isset( $_POST['mentor_id'] ) ? absint( wp_unslash( $_POST['mentor_id'] ) ) : 0;
		$date      = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		$mentor = GYDB_Shortcodes::resolve_mentor( $mentor_id );
		if ( ! $mentor ) {
			wp_send_json_error( array( 'message' => __( 'Mentor not found.', 'gyd-booking' ) ) );
		}
		if ( ! self::valid_date( $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid date.', 'gyd-booking' ) ) );
		}

		$slots = self::build_slots( $mentor_id, $date );

		wp_send_json_success(
			array(
				'slots' => $slots,
				'date'  => $date,
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Submit a booking
	 * ------------------------------------------------------------------ */
	public static function submit_booking() {
		self::check_nonce();

		$program_id = isset( $_POST['program_id'] ) ? absint( wp_unslash( $_POST['program_id'] ) ) : 0;
		$mentor_id  = isset( $_POST['mentor_id'] ) ? absint( wp_unslash( $_POST['mentor_id'] ) ) : 0;
		$date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time       = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		// Validate entities.
		$program = GYDB_Shortcodes::resolve_program( $program_id );
		$mentor  = GYDB_Shortcodes::resolve_mentor( $mentor_id );
		if ( ! $program || ! $mentor ) {
			wp_send_json_error( array( 'message' => __( 'Invalid programme or mentor.', 'gyd-booking' ) ) );
		}

		// Confirm the mentor actually belongs to the programme.
		if ( ! in_array( $program->ID, GYDB_Helpers::get_mentor_program_ids( $mentor->ID ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'That mentor is not part of the selected programme.', 'gyd-booking' ) ) );
		}

		// Required fields.
		if ( '' === $name || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name and a valid email address.', 'gyd-booking' ) ) );
		}
		if ( ! self::valid_date( $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid date.', 'gyd-booking' ) ) );
		}
		if ( ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid time slot.', 'gyd-booking' ) ) );
		}

		// Re-validate the slot server-side (availability + not taken).
		$available = wp_list_pluck( self::build_slots( $mentor_id, $date ), 'value' );
		if ( ! in_array( $time, $available, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, that time was just taken or is no longer available. Please pick another slot.', 'gyd-booking' ) ) );
		}

		$booking_id = GYDB_Database::insert(
			array(
				'program_id'   => $program->ID,
				'mentor_id'    => $mentor->ID,
				'client_name'  => $name,
				'client_email' => $email,
				'client_phone' => $phone,
				'booking_date' => $date,
				'booking_time' => $time . ':00',
				'message'      => $message,
				'status'       => 'pending',
			)
		);

		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'We could not save your booking. Please try again.', 'gyd-booking' ) ) );
		}

		$booking = GYDB_Database::get( $booking_id );

		// Fire notifications (admin + mentor + client).
		GYDB_Emails::send_new_booking( $booking, $program, $mentor );

		/**
		 * Fires after a booking is successfully created.
		 *
		 * @param int     $booking_id Booking row ID.
		 * @param object  $booking    Booking row.
		 * @param WP_Post $program    Programme.
		 * @param WP_Post $mentor     Mentor.
		 */
		do_action( 'gydb_booking_created', $booking_id, $booking, $program, $mentor );

		wp_send_json_success(
			array(
				'message'   => GYDB_Helpers::get_setting( 'success_message' ),
				'reference' => $booking->reference,
				'summary'   => sprintf(
					/* translators: 1: mentor, 2: date, 3: time */
					__( '%1$s · %2$s at %3$s', 'gyd-booking' ),
					$mentor->post_title,
					GYDB_Helpers::format_date( $date ),
					GYDB_Helpers::format_time( $time )
				),
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Slot engine
	 * ------------------------------------------------------------------ */

	/**
	 * Build the list of available slots for a mentor on a date.
	 *
	 * @param int    $mentor_id Mentor ID.
	 * @param string $date      Y-m-d.
	 * @return array[] Each: array( 'value' => 'HH:MM', 'label' => '9:00 am' ).
	 */
	public static function build_slots( $mentor_id, $date ) {
		$settings = GYDB_Helpers::get_settings();

		$days = get_post_meta( $mentor_id, '_gyd_avail_days', true );
		$days = is_array( $days ) ? array_map( 'strval', $days ) : array();
		if ( empty( $days ) ) {
			$days = array_map( 'strval', (array) $settings['default_days'] );
		}

		$start    = get_post_meta( $mentor_id, '_gyd_avail_start', true );
		$end      = get_post_meta( $mentor_id, '_gyd_avail_end', true );
		$slot_len = (int) get_post_meta( $mentor_id, '_gyd_slot_len', true );
		$start    = $start ? $start : $settings['default_start'];
		$end      = $end ? $end : $settings['default_end'];
		$slot_len = $slot_len > 0 ? $slot_len : (int) $settings['default_slot_len'];

		// Day-of-week check (0=Sun).
		$weekday = gmdate( 'w', strtotime( $date . ' 00:00:00' ) );
		if ( ! in_array( (string) $weekday, $days, true ) ) {
			return array();
		}

		// Honour the booking window in the site's timezone.
		$tz       = wp_timezone();
		$now      = new DateTime( 'now', $tz );
		$min_time = ( clone $now )->modify( '+' . (int) $settings['min_notice_hours'] . ' hours' );
		$max_time = ( clone $now )->modify( '+' . (int) $settings['max_days_ahead'] . ' days' );

		$start_min = self::to_minutes( $start );
		$end_min   = self::to_minutes( $end );
		if ( $end_min <= $start_min ) {
			return array();
		}

		$taken = GYDB_Database::get_taken_times( $mentor_id, $date );

		$slots = array();
		for ( $m = $start_min; $m + $slot_len <= $end_min; $m += $slot_len ) {
			$hh   = str_pad( (string) intdiv( $m, 60 ), 2, '0', STR_PAD_LEFT );
			$mm   = str_pad( (string) ( $m % 60 ), 2, '0', STR_PAD_LEFT );
			$time = $hh . ':' . $mm;

			if ( in_array( $time, $taken, true ) ) {
				continue;
			}

			// Respect min-notice / max-ahead against the actual slot datetime.
			$slot_dt = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $tz );
			if ( ! $slot_dt || $slot_dt < $min_time || $slot_dt > $max_time ) {
				continue;
			}

			$slots[] = array(
				'value' => $time,
				'label' => GYDB_Helpers::format_time( $time ),
			);
		}

		return $slots;
	}

	/**
	 * Convert HH:MM to minutes.
	 *
	 * @param string $time Time string.
	 * @return int
	 */
	private static function to_minutes( $time ) {
		$parts = explode( ':', (string) $time );
		$h     = isset( $parts[0] ) ? (int) $parts[0] : 0;
		$m     = isset( $parts[1] ) ? (int) $parts[1] : 0;
		return ( $h * 60 ) + $m;
	}

	/**
	 * Validate a Y-m-d date string.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	private static function valid_date( $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return false;
		}
		$parts = explode( '-', $date );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}
}
