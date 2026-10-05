<?php
/**
 * Shortcodes + shared render helpers (reused by AJAX).
 *
 * Shortcodes:
 *   [gyd_programs]      Programme listing (cards or rows) with Book links.
 *   [gyd_mentors]       Mentor cards for one programme.
 *   [gyd_booking]       The full booking experience.
 *   [gyd_book_button]   A single "Book Now" button → booking page.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GYDB_Shortcodes {

	public static function init() {
		add_shortcode( 'gyd_programs', array( __CLASS__, 'programs' ) );
		add_shortcode( 'gyd_mentors', array( __CLASS__, 'mentors' ) );
		add_shortcode( 'gyd_booking', array( __CLASS__, 'booking' ) );
		add_shortcode( 'gyd_book_button', array( __CLASS__, 'book_button' ) );
	}

	/* ================================================================== *
	 * [gyd_programs]
	 * ================================================================== */

	/**
	 * Render the programme listing.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function programs( $atts ) {
		$atts = shortcode_atts(
			array(
				'layout'  => 'cards', // cards | rows.
				'columns' => '3',     // 2 | 3 | 4 (cards only).
				'book'    => '1',     // show Book link/button.
			),
			$atts,
			'gyd_programs'
		);

		GYDB_Assets::enqueue();

		$programs = GYDB_Helpers::get_programs();
		if ( empty( $programs ) ) {
			return self::notice( __( 'No programmes have been added yet.', 'gyd-booking' ) );
		}

		ob_start();
		echo '<div class="gyd-booking gydb-programs gydb-layout-' . esc_attr( $atts['layout'] ) . '">';

		if ( 'rows' === $atts['layout'] ) {
			echo '<div class="gydb-program-rows">';
			$i = 0;
			foreach ( $programs as $program ) {
				echo self::render_program_row( $program, ( $i % 2 === 1 ), ( '1' === $atts['book'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within.
				$i++;
			}
			echo '</div>';
		} else {
			$cols = in_array( $atts['columns'], array( '2', '3', '4' ), true ) ? $atts['columns'] : '3';
			echo '<div class="gydb-card-grid gydb-cols-' . esc_attr( $cols ) . '">';
			foreach ( $programs as $program ) {
				echo self::render_program_card( $program, ( '1' === $atts['book'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within.
			}
			echo '</div>';
		}

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * A single programme card (matches .program-card from the design spec).
	 *
	 * @param WP_Post $program Programme.
	 * @param bool    $book    Show the Book link.
	 * @return string
	 */
	public static function render_program_card( $program, $book = true ) {
		$number  = GYDB_Helpers::get_program_number( $program );
		$tagline = get_post_meta( $program->ID, '_gyd_tagline', true );
		$is_new  = get_post_meta( $program->ID, '_gyd_is_new', true );
		$img     = get_the_post_thumbnail_url( $program->ID, 'large' );
		$url     = GYDB_Helpers::get_booking_url( $program->post_name );

		ob_start();
		?>
		<div class="gydb-program-card">
			<div class="gydb-card-img"<?php echo $img ? ' style="background-image:url(' . esc_url( $img ) . ');"' : ''; ?>>
				<?php if ( ! $img ) : ?><span class="gydb-img-label"><?php esc_html_e( 'IMAGE', 'gyd-booking' ); ?></span><?php endif; ?>
			</div>
			<div class="gydb-card-body">
				<div class="gydb-card-num">
					<?php echo esc_html( $number ); ?><?php echo $is_new ? ' · ' . esc_html__( 'NEW', 'gyd-booking' ) : ''; ?>
				</div>
				<h4><?php echo esc_html( $program->post_title ); ?></h4>
				<?php if ( $tagline ) : ?><div class="gydb-card-tagline"><?php echo esc_html( $tagline ); ?></div><?php endif; ?>
				<?php if ( $book ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" class="gydb-card-link"><?php esc_html_e( 'Book this →', 'gyd-booking' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * A single programme row (matches .program-row from programs.html).
	 *
	 * @param WP_Post $program Programme.
	 * @param bool    $alt     Alternate (gold number) styling.
	 * @param bool    $book    Show the Book button.
	 * @return string
	 */
	public static function render_program_row( $program, $alt = false, $book = true ) {
		$number   = GYDB_Helpers::get_program_number( $program );
		$category = get_post_meta( $program->ID, '_gyd_category', true );
		$tagline  = get_post_meta( $program->ID, '_gyd_tagline', true );
		$url      = GYDB_Helpers::get_booking_url( $program->post_name );

		ob_start();
		?>
		<div class="gydb-program-row<?php echo $alt ? ' gydb-alt' : ''; ?>">
			<div class="gydb-big-num"><?php echo esc_html( $number ); ?></div>
			<div class="gydb-row-body">
				<?php if ( $category ) : ?><div class="gydb-prog-category"><?php echo esc_html( $category ); ?></div><?php endif; ?>
				<h3><?php echo esc_html( $program->post_title ); ?></h3>
				<?php if ( $tagline ) : ?><div class="gydb-tagline"><?php echo esc_html( $tagline ); ?></div><?php endif; ?>
			</div>
			<?php if ( $book ) : ?>
				<div class="gydb-row-action"><a href="<?php echo esc_url( $url ); ?>" class="gydb-btn gydb-btn-navy"><?php esc_html_e( 'Book Now', 'gyd-booking' ); ?></a></div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ================================================================== *
	 * [gyd_book_button]
	 * ================================================================== */

	/**
	 * A single Book Now button linking to the booking page.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function book_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'program' => '',
				'text'    => __( 'Book Now', 'gyd-booking' ),
				'style'   => 'primary', // primary | navy | outline.
			),
			$atts,
			'gyd_book_button'
		);

		GYDB_Assets::enqueue();

		$slug = '';
		if ( $atts['program'] ) {
			$program = self::resolve_program( $atts['program'] );
			if ( $program ) {
				$slug = $program->post_name;
			}
		}

		$style   = in_array( $atts['style'], array( 'primary', 'navy', 'outline' ), true ) ? $atts['style'] : 'primary';
		$classes = 'gydb-btn gydb-btn-' . $style;

		return '<span class="gyd-booking"><a href="' . esc_url( GYDB_Helpers::get_booking_url( $slug ) ) . '" class="' . esc_attr( $classes ) . '">' . esc_html( $atts['text'] ) . '</a></span>';
	}

	/* ================================================================== *
	 * [gyd_mentors]
	 * ================================================================== */

	/**
	 * Render mentor cards for a programme (standalone).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function mentors( $atts ) {
		$atts = shortcode_atts(
			array(
				'program' => '',
				'columns' => '3',
				'action'  => 'book', // book (links to booking page) | schedule (inline, requires [gyd_booking]).
			),
			$atts,
			'gyd_mentors'
		);

		GYDB_Assets::enqueue();

		$program = self::resolve_program( $atts['program'] );
		if ( ! $program ) {
			return self::notice( __( 'Please specify a valid programme for the mentors list.', 'gyd-booking' ) );
		}

		$mentors = GYDB_Helpers::get_mentors_for_program( $program->ID );
		if ( empty( $mentors ) ) {
			return self::notice( __( 'No mentors are available for this programme yet.', 'gyd-booking' ) );
		}

		$cols = in_array( $atts['columns'], array( '2', '3', '4' ), true ) ? $atts['columns'] : '3';

		ob_start();
		echo '<div class="gyd-booking gydb-mentors-wrap">';
		echo '<div class="gydb-mentor-grid gydb-cols-' . esc_attr( $cols ) . '">';
		foreach ( $mentors as $mentor ) {
			echo self::render_mentor_card( $mentor, $program, ( 'book' === $atts['action'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within.
		}
		echo '</div></div>';
		return ob_get_clean();
	}

	/**
	 * A single mentor card (matches .mentor-card from book.html).
	 *
	 * @param WP_Post $mentor     Mentor.
	 * @param WP_Post $program    Programme context.
	 * @param bool    $link_to_page When true, the button links to the booking
	 *                              page; otherwise it triggers the inline flow.
	 * @return string
	 */
	public static function render_mentor_card( $mentor, $program, $link_to_page = false ) {
		$role  = get_post_meta( $mentor->ID, '_gyd_role', true );
		$bio   = get_post_meta( $mentor->ID, '_gyd_bio', true );
		$photo = GYDB_Helpers::get_mentor_photo_url( $mentor->ID, 'medium' );

		ob_start();
		?>
		<div class="gydb-mentor-card" data-mentor-id="<?php echo esc_attr( $mentor->ID ); ?>" data-mentor-name="<?php echo esc_attr( $mentor->post_title ); ?>">
			<div class="gydb-mentor-photo"<?php echo $photo ? ' style="background-image:url(' . esc_url( $photo ) . ');"' : ''; ?>>
				<?php if ( ! $photo ) : ?><span class="gydb-photo-label"><?php esc_html_e( 'PHOTO', 'gyd-booking' ); ?></span><?php endif; ?>
			</div>
			<h4><?php echo esc_html( $mentor->post_title ); ?></h4>
			<?php if ( $role ) : ?><div class="gydb-mentor-role"><?php echo esc_html( $role ); ?></div><?php endif; ?>
			<?php if ( $bio ) : ?><p class="gydb-mentor-bio"><?php echo esc_html( $bio ); ?></p><?php endif; ?>
			<?php if ( $link_to_page ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'gyd_mentor', $mentor->ID, GYDB_Helpers::get_booking_url( $program->post_name ) ) ); ?>" class="gydb-btn gydb-btn-navy gydb-btn-block"><?php esc_html_e( 'View schedule', 'gyd-booking' ); ?></a>
			<?php else : ?>
				<button type="button" class="gydb-btn gydb-btn-navy gydb-btn-block gydb-pick-mentor" data-mentor-id="<?php echo esc_attr( $mentor->ID ); ?>"><?php esc_html_e( 'View schedule', 'gyd-booking' ); ?></button>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Programme context box (matches .sel-prog from book.html).
	 *
	 * @param WP_Post $program Programme.
	 * @return string
	 */
	public static function render_program_context( $program ) {
		$number   = GYDB_Helpers::get_program_number( $program );
		$category = get_post_meta( $program->ID, '_gyd_category', true );
		$desc     = GYDB_Helpers::get_program_short_desc( $program );

		ob_start();
		?>
		<div class="gydb-sel-prog">
			<div class="gydb-sel-label">
				<?php
				/* translators: %s: programme number */
				echo esc_html( sprintf( __( 'Selected programme · Programme %s', 'gyd-booking' ), $number ) );
				?>
			</div>
			<h4><?php echo esc_html( $program->post_title ); ?><?php echo $category ? ' — ' . esc_html( $category ) : ''; ?></h4>
			<?php if ( $desc ) : ?><p><?php echo esc_html( $desc ); ?></p><?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ================================================================== *
	 * [gyd_booking]  — the full experience
	 * ================================================================== */

	/**
	 * Render the complete booking app.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function booking( $atts ) {
		$atts = shortcode_atts(
			array(
				'program'     => '',
				'show_steps'  => '1',
				'show_picker' => '1',
			),
			$atts,
			'gyd_booking'
		);

		GYDB_Assets::enqueue();

		// Resolve a preselected programme from the attribute or the URL.
		$program = self::resolve_program( $atts['program'] );
		if ( ! $program && isset( $_GET['gyd_program'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$program = self::resolve_program( sanitize_title( wp_unslash( $_GET['gyd_program'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$preselect_mentor = 0;
		if ( isset( $_GET['gyd_mentor'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$preselect_mentor = absint( wp_unslash( $_GET['gyd_mentor'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$settings = GYDB_Helpers::get_settings();
		$tz       = wp_timezone();
		$now      = new DateTime( 'now', $tz );
		$min_date = ( clone $now )->modify( '+' . (int) $settings['min_notice_hours'] . ' hours' )->format( 'Y-m-d' );
		$max_date = ( clone $now )->modify( '+' . (int) $settings['max_days_ahead'] . ' days' )->format( 'Y-m-d' );

		ob_start();
		?>
		<div class="gyd-booking gydb-app"
			data-program="<?php echo $program ? esc_attr( $program->ID ) : ''; ?>"
			data-mentor="<?php echo esc_attr( $preselect_mentor ); ?>"
			data-min-date="<?php echo esc_attr( $min_date ); ?>"
			data-max-date="<?php echo esc_attr( $max_date ); ?>">

			<?php if ( '1' === $atts['show_steps'] ) : ?>
				<?php echo self::render_steps(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
			<?php endif; ?>

			<?php if ( '1' === $atts['show_picker'] ) : ?>
			<div class="gydb-stage gydb-stage-pick"<?php echo $program ? ' hidden' : ''; ?>>
				<div class="gydb-marker"><div class="gydb-marker-body">
					<span class="gydb-eyebrow"><?php esc_html_e( 'Start here', 'gyd-booking' ); ?></span>
					<h2><?php esc_html_e( 'Pick your programme.', 'gyd-booking' ); ?></h2>
					<p class="gydb-lead"><?php esc_html_e( 'Choose a programme to see available mentors and schedule.', 'gyd-booking' ); ?></p>
				</div></div>
				<div class="gydb-card-grid gydb-cols-3">
					<?php foreach ( GYDB_Helpers::get_programs() as $p ) : ?>
						<?php echo self::render_picker_card( $p ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Mentors stage -->
			<div class="gydb-stage gydb-stage-mentors"<?php echo $program ? '' : ' hidden'; ?>>
				<div class="gydb-program-context">
					<?php if ( $program ) : ?>
						<?php echo self::render_program_context( $program ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
					<?php endif; ?>
				</div>
				<div class="gydb-mentors-head">
					<h3><?php esc_html_e( 'Choose your mentor', 'gyd-booking' ); ?></h3>
					<p class="gydb-muted"><?php esc_html_e( 'Every mentor has a short bio so you know who you’ll be speaking with.', 'gyd-booking' ); ?></p>
				</div>
				<div class="gydb-mentor-grid gydb-cols-3 gydb-mentor-target">
					<?php
					if ( $program ) {
						$mentors = GYDB_Helpers::get_mentors_for_program( $program->ID );
						if ( empty( $mentors ) ) {
							echo self::notice( __( 'No mentors are available for this programme yet.', 'gyd-booking' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							foreach ( $mentors as $mentor ) {
								echo self::render_mentor_card( $mentor, $program, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
						}
					}
					?>
				</div>
				<?php if ( '1' === $atts['show_picker'] ) : ?>
					<p class="gydb-change-prog"><button type="button" class="gydb-link-btn gydb-back-to-pick">← <?php esc_html_e( 'Choose a different programme', 'gyd-booking' ); ?></button></p>
				<?php endif; ?>
			</div>

			<!-- Booking form stage -->
			<div class="gydb-stage gydb-stage-form" hidden>
				<p class="gydb-back"><button type="button" class="gydb-link-btn gydb-back-to-mentors">← <?php esc_html_e( 'Back to mentors', 'gyd-booking' ); ?></button></p>
				<div class="gydb-form-card">
					<div class="gydb-booking-summary">
						<div class="gydb-summary-photo"></div>
						<div>
							<div class="gydb-summary-prog"></div>
							<h4 class="gydb-summary-mentor"></h4>
							<div class="gydb-summary-role"></div>
						</div>
					</div>

					<form class="gydb-form" novalidate>
						<div class="gydb-form-field">
							<label for="gydb-date"><?php esc_html_e( 'Choose a date', 'gyd-booking' ); ?> <span class="gydb-req">*</span></label>
							<input type="date" id="gydb-date" name="gydb_date" min="<?php echo esc_attr( $min_date ); ?>" max="<?php echo esc_attr( $max_date ); ?>" required>
						</div>

						<div class="gydb-form-field">
							<label><?php esc_html_e( 'Available times', 'gyd-booking' ); ?> <span class="gydb-req">*</span></label>
							<div class="gydb-slots" aria-live="polite">
								<p class="gydb-slots-hint"><?php esc_html_e( 'Select a date to see available times.', 'gyd-booking' ); ?></p>
							</div>
							<input type="hidden" name="gydb_time" id="gydb-time" value="">
						</div>

						<div class="gydb-form-row">
							<div class="gydb-form-field">
								<label for="gydb-name"><?php esc_html_e( 'Your name', 'gyd-booking' ); ?> <span class="gydb-req">*</span></label>
								<input type="text" id="gydb-name" name="gydb_name" required>
							</div>
							<div class="gydb-form-field">
								<label for="gydb-email"><?php esc_html_e( 'Email', 'gyd-booking' ); ?> <span class="gydb-req">*</span></label>
								<input type="email" id="gydb-email" name="gydb_email" required>
							</div>
						</div>

						<div class="gydb-form-field">
							<label for="gydb-phone"><?php esc_html_e( 'Phone (optional)', 'gyd-booking' ); ?></label>
							<input type="tel" id="gydb-phone" name="gydb_phone">
						</div>

						<div class="gydb-form-field">
							<label for="gydb-message"><?php esc_html_e( 'What would you like to work on? (optional)', 'gyd-booking' ); ?></label>
							<textarea id="gydb-message" name="gydb_message" rows="4"></textarea>
						</div>

						<input type="hidden" name="gydb_program_id" id="gydb-program-id" value="<?php echo $program ? esc_attr( $program->ID ) : ''; ?>">
						<input type="hidden" name="gydb_mentor_id" id="gydb-mentor-id" value="">

						<div class="gydb-form-error" role="alert" hidden></div>

						<button type="submit" class="gydb-btn gydb-btn-primary gydb-submit"><?php esc_html_e( 'Confirm booking', 'gyd-booking' ); ?></button>
					</form>
				</div>
			</div>

			<!-- Success stage -->
			<div class="gydb-stage gydb-stage-success" hidden>
				<div class="gydb-success-card">
					<div class="gydb-success-check" aria-hidden="true">✓</div>
					<h3><?php esc_html_e( 'Booking received', 'gyd-booking' ); ?></h3>
					<p class="gydb-success-msg"><?php echo esc_html( $settings['success_message'] ); ?></p>
					<p class="gydb-success-ref"></p>
					<button type="button" class="gydb-btn gydb-btn-navy gydb-book-another"><?php esc_html_e( 'Book another session', 'gyd-booking' ); ?></button>
				</div>
			</div>

			<div class="gydb-loading" hidden><span class="gydb-spinner"></span></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * The four-step "how booking works" flow.
	 *
	 * @return string
	 */
	public static function render_steps() {
		$steps = array(
			array( __( 'Choose a programme', 'gyd-booking' ), __( 'Pick the programme that fits what you want to work on.', 'gyd-booking' ) ),
			array( __( 'Read programme info', 'gyd-booking' ), __( 'See a short description of the programme above the schedule.', 'gyd-booking' ) ),
			array( __( 'Pick a mentor', 'gyd-booking' ), __( 'Browse mentor bios and photos — find someone whose experience fits.', 'gyd-booking' ) ),
			array( __( 'Schedule your time', 'gyd-booking' ), __( 'Choose a date and time. You’ll get a confirmation by email.', 'gyd-booking' ) ),
		);

		ob_start();
		?>
		<div class="gydb-marker"><div class="gydb-marker-body">
			<span class="gydb-eyebrow"><?php esc_html_e( 'How booking works', 'gyd-booking' ); ?></span>
			<h2><?php esc_html_e( 'Four simple steps.', 'gyd-booking' ); ?></h2>
		</div></div>
		<div class="gydb-step-flow">
			<?php foreach ( $steps as $step ) : ?>
				<div class="gydb-step">
					<h5><?php echo esc_html( $step[0] ); ?></h5>
					<p><?php echo esc_html( $step[1] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * A clickable programme picker card inside the booking app.
	 *
	 * @param WP_Post $program Programme.
	 * @return string
	 */
	public static function render_picker_card( $program ) {
		$number  = GYDB_Helpers::get_program_number( $program );
		$tagline = get_post_meta( $program->ID, '_gyd_tagline', true );
		$is_new  = get_post_meta( $program->ID, '_gyd_is_new', true );
		$img     = get_the_post_thumbnail_url( $program->ID, 'large' );

		ob_start();
		?>
		<div class="gydb-program-card gydb-pick-program" data-program-id="<?php echo esc_attr( $program->ID ); ?>" role="button" tabindex="0">
			<div class="gydb-card-img"<?php echo $img ? ' style="background-image:url(' . esc_url( $img ) . ');"' : ''; ?>>
				<?php if ( ! $img ) : ?><span class="gydb-img-label"><?php esc_html_e( 'IMAGE', 'gyd-booking' ); ?></span><?php endif; ?>
			</div>
			<div class="gydb-card-body">
				<div class="gydb-card-num"><?php echo esc_html( $number ); ?><?php echo $is_new ? ' · ' . esc_html__( 'NEW', 'gyd-booking' ) : ''; ?></div>
				<h4><?php echo esc_html( $program->post_title ); ?></h4>
				<?php if ( $tagline ) : ?><div class="gydb-card-tagline"><?php echo esc_html( $tagline ); ?></div><?php endif; ?>
				<span class="gydb-card-link"><?php esc_html_e( 'Book this →', 'gyd-booking' ); ?></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ================================================================== *
	 * Utilities
	 * ================================================================== */

	/**
	 * Resolve a programme from a slug, ID or title-ish string.
	 *
	 * @param string|int $value Slug or ID.
	 * @return WP_Post|null
	 */
	public static function resolve_program( $value ) {
		$value = is_string( $value ) ? trim( $value ) : $value;
		if ( '' === $value || null === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			$post = get_post( (int) $value );
			return ( $post && GYDB_PROGRAM_CPT === $post->post_type && 'publish' === $post->post_status ) ? $post : null;
		}

		$post = get_page_by_path( sanitize_title( $value ), OBJECT, GYDB_PROGRAM_CPT );
		return ( $post && 'publish' === $post->post_status ) ? $post : null;
	}

	/**
	 * Resolve a mentor by ID.
	 *
	 * @param int $id Mentor ID.
	 * @return WP_Post|null
	 */
	public static function resolve_mentor( $id ) {
		$post = get_post( (int) $id );
		return ( $post && GYDB_MENTOR_CPT === $post->post_type && 'publish' === $post->post_status ) ? $post : null;
	}

	/**
	 * A small branded inline notice.
	 *
	 * @param string $message Text.
	 * @return string
	 */
	public static function notice( $message ) {
		return '<div class="gyd-booking"><p class="gydb-notice">' . esc_html( $message ) . '</p></div>';
	}
}
