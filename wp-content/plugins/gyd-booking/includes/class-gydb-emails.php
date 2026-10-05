<?php
/**
 * Transactional emails for new bookings.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GYDB_Emails {

	/**
	 * Send notifications for a new booking: admin, mentor and client.
	 *
	 * @param object  $booking Booking row.
	 * @param WP_Post $program Programme.
	 * @param WP_Post $mentor  Mentor.
	 */
	public static function send_new_booking( $booking, $program, $mentor ) {
		$settings = GYDB_Helpers::get_settings();
		$headers  = self::headers();

		$date  = GYDB_Helpers::format_date( $booking->booking_date );
		$time  = GYDB_Helpers::format_time( $booking->booking_time );
		$blog  = get_bloginfo( 'name' );

		// --- Admin + mentor notification ---------------------------------
		if ( ! empty( $settings['notify_admin'] ) ) {
			$recipients = array();
			$admin_mail = sanitize_email( $settings['admin_email'] );
			if ( is_email( $admin_mail ) ) {
				$recipients[] = $admin_mail;
			}
			$mentor_mail = sanitize_email( get_post_meta( $mentor->ID, '_gyd_email', true ) );
			if ( is_email( $mentor_mail ) ) {
				$recipients[] = $mentor_mail;
			}
			$recipients = array_unique( $recipients );

			if ( $recipients ) {
				/* translators: 1: mentor name, 2: programme title */
				$subject = sprintf( __( '[%1$s] New booking: %2$s', 'gyd-booking' ), $blog, $mentor->post_title );

				$lines = array(
					__( 'A new mentor session has been requested.', 'gyd-booking' ),
					'',
					sprintf( '%s: %s', __( 'Reference', 'gyd-booking' ), $booking->reference ),
					sprintf( '%s: %s', __( 'Programme', 'gyd-booking' ), $program->post_title ),
					sprintf( '%s: %s', __( 'Mentor', 'gyd-booking' ), $mentor->post_title ),
					sprintf( '%s: %s at %s', __( 'When', 'gyd-booking' ), $date, $time ),
					'',
					sprintf( '%s: %s', __( 'Name', 'gyd-booking' ), $booking->client_name ),
					sprintf( '%s: %s', __( 'Email', 'gyd-booking' ), $booking->client_email ),
					sprintf( '%s: %s', __( 'Phone', 'gyd-booking' ), $booking->client_phone ? $booking->client_phone : '—' ),
					'',
					__( 'Message:', 'gyd-booking' ),
					$booking->message ? $booking->message : '—',
					'',
					sprintf( '%s %s', __( 'Manage bookings:', 'gyd-booking' ), admin_url( 'admin.php?page=gyd-booking' ) ),
				);

				$body = implode( "\n", $lines );

				wp_mail(
					$recipients,
					$subject,
					self::wrap_html( $subject, nl2br( esc_html( $body ) ) ),
					$headers
				);
			}
		}

		// --- Client confirmation -----------------------------------------
		if ( ! empty( $settings['notify_client'] ) && is_email( $booking->client_email ) ) {
			/* translators: %s: blog name */
			$subject = sprintf( __( 'Your booking request with %s', 'gyd-booking' ), $blog );

			$lines = array(
				sprintf( /* translators: %s: client name */ __( 'Hi %s,', 'gyd-booking' ), $booking->client_name ),
				'',
				__( 'Thank you for requesting a mentor session. Here are your details:', 'gyd-booking' ),
				'',
				sprintf( '%s: %s', __( 'Reference', 'gyd-booking' ), $booking->reference ),
				sprintf( '%s: %s', __( 'Programme', 'gyd-booking' ), $program->post_title ),
				sprintf( '%s: %s', __( 'Mentor', 'gyd-booking' ), $mentor->post_title ),
				sprintf( '%s: %s at %s', __( 'When', 'gyd-booking' ), $date, $time ),
				'',
				__( 'We will confirm your session shortly. If you need to make a change, just reply to this email.', 'gyd-booking' ),
				'',
				$blog,
			);

			$body = implode( "\n", $lines );

			wp_mail(
				$booking->client_email,
				$subject,
				self::wrap_html( __( 'Booking request received', 'gyd-booking' ), nl2br( esc_html( $body ) ) ),
				$headers
			);
		}
	}

	/**
	 * Email headers (HTML + branded From).
	 *
	 * @return string[]
	 */
	private static function headers() {
		$settings   = GYDB_Helpers::get_settings();
		$from_name  = $settings['email_from_name'] ? $settings['email_from_name'] : get_bloginfo( 'name' );
		$from_email = is_email( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );

		return array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);
	}

	/**
	 * Wrap a plain message in a lightweight branded HTML shell.
	 *
	 * @param string $title Heading.
	 * @param string $body  Already-escaped HTML body.
	 * @return string
	 */
	private static function wrap_html( $title, $body ) {
		$navy = GYDB_Helpers::get_setting( 'color_navy', '#0A2540' );
		$red  = GYDB_Helpers::get_setting( 'color_red', '#E41E20' );

		ob_start();
		?>
		<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e5e0d4;border-radius:12px;overflow:hidden;">
			<div style="background:<?php echo esc_attr( $navy ); ?>;padding:24px 28px;">
				<span style="display:inline-block;width:10px;height:28px;background:<?php echo esc_attr( $red ); ?>;vertical-align:middle;margin-right:12px;border-radius:2px;"></span>
				<span style="color:#fff;font-size:18px;font-weight:bold;vertical-align:middle;"><?php echo esc_html( $title ); ?></span>
			</div>
			<div style="padding:28px;color:#1a1a1a;font-size:15px;line-height:1.6;background:#ffffff;">
				<?php echo wp_kses_post( $body ); ?>
			</div>
			<div style="padding:16px 28px;background:#FAF6EF;color:#6b7280;font-size:12px;">
				<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
