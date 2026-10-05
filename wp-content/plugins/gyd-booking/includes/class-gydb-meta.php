<?php
/**
 * Meta boxes for Programmes and Mentors.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GYDB_Meta {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_boxes' ) );
		add_action( 'save_post_' . GYDB_PROGRAM_CPT, array( __CLASS__, 'save_program' ), 10, 2 );
		add_action( 'save_post_' . GYDB_MENTOR_CPT, array( __CLASS__, 'save_mentor' ), 10, 2 );

		// Helpful admin columns.
		add_filter( 'manage_' . GYDB_MENTOR_CPT . '_posts_columns', array( __CLASS__, 'mentor_columns' ) );
		add_action( 'manage_' . GYDB_MENTOR_CPT . '_posts_custom_column', array( __CLASS__, 'mentor_column_content' ), 10, 2 );
		add_filter( 'manage_' . GYDB_PROGRAM_CPT . '_posts_columns', array( __CLASS__, 'program_columns' ) );
		add_action( 'manage_' . GYDB_PROGRAM_CPT . '_posts_custom_column', array( __CLASS__, 'program_column_content' ), 10, 2 );
	}

	/**
	 * Register the meta boxes.
	 */
	public static function add_boxes() {
		add_meta_box(
			'gydb_program_details',
			__( 'Programme details', 'gyd-booking' ),
			array( __CLASS__, 'render_program_box' ),
			GYDB_PROGRAM_CPT,
			'normal',
			'high'
		);

		add_meta_box(
			'gydb_mentor_profile',
			__( 'Mentor profile', 'gyd-booking' ),
			array( __CLASS__, 'render_mentor_profile_box' ),
			GYDB_MENTOR_CPT,
			'normal',
			'high'
		);

		add_meta_box(
			'gydb_mentor_availability',
			__( 'Availability & scheduling', 'gyd-booking' ),
			array( __CLASS__, 'render_mentor_availability_box' ),
			GYDB_MENTOR_CPT,
			'normal',
			'default'
		);

		add_meta_box(
			'gydb_mentor_programs',
			__( 'Assigned programmes', 'gyd-booking' ),
			array( __CLASS__, 'render_mentor_programs_box' ),
			GYDB_MENTOR_CPT,
			'side',
			'default'
		);
	}

	/* ------------------------------------------------------------------ *
	 * Programme box
	 * ------------------------------------------------------------------ */

	/**
	 * Render the programme details box.
	 *
	 * @param WP_Post $post Programme.
	 */
	public static function render_program_box( $post ) {
		wp_nonce_field( 'gydb_save_program', 'gydb_program_nonce' );

		$category   = get_post_meta( $post->ID, '_gyd_category', true );
		$tagline    = get_post_meta( $post->ID, '_gyd_tagline', true );
		$number     = get_post_meta( $post->ID, '_gyd_number', true );
		$short_desc = get_post_meta( $post->ID, '_gyd_short_desc', true );
		$is_new     = get_post_meta( $post->ID, '_gyd_is_new', true );
		?>
		<p>
			<label for="gyd_number"><strong><?php esc_html_e( 'Programme number', 'gyd-booking' ); ?></strong></label><br>
			<input type="number" min="0" id="gyd_number" name="gyd_number" value="<?php echo esc_attr( $number ); ?>" class="small-text">
			<span class="description"><?php esc_html_e( 'Shown as 01, 02, … on cards. Leave blank to use the "Order" attribute.', 'gyd-booking' ); ?></span>
		</p>
		<p>
			<label for="gyd_category"><strong><?php esc_html_e( 'Category / focus', 'gyd-booking' ); ?></strong></label><br>
			<input type="text" id="gyd_category" name="gyd_category" value="<?php echo esc_attr( $category ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Innovation, Technology & Entrepreneurship', 'gyd-booking' ); ?>">
		</p>
		<p>
			<label for="gyd_tagline"><strong><?php esc_html_e( 'Tagline', 'gyd-booking' ); ?></strong></label><br>
			<input type="text" id="gyd_tagline" name="gyd_tagline" value="<?php echo esc_attr( $tagline ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Imagine. Create. Transform.', 'gyd-booking' ); ?>">
		</p>
		<p>
			<label for="gyd_short_desc"><strong><?php esc_html_e( 'Short description', 'gyd-booking' ); ?></strong></label><br>
			<textarea id="gyd_short_desc" name="gyd_short_desc" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'One or two sentences. Used on the booking page only when the main editor above is empty.', 'gyd-booking' ); ?>"><?php echo esc_textarea( $short_desc ); ?></textarea>
		</p>
		<p>
			<label>
				<input type="checkbox" name="gyd_is_new" value="1" <?php checked( $is_new, '1' ); ?>>
				<?php esc_html_e( 'Mark this programme as “New” (adds a NEW badge).', 'gyd-booking' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Tip: the large editor above is the full “About this programme” text shown on the booking page (what the programme is about). The Featured Image is used for the programme card.', 'gyd-booking' ); ?></p>
		<?php
	}

	/**
	 * Save programme meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_program( $post_id, $post ) {
		if ( ! self::can_save( $post_id, 'gydb_program_nonce', 'gydb_save_program' ) ) {
			return;
		}

		update_post_meta( $post_id, '_gyd_number', isset( $_POST['gyd_number'] ) ? absint( $_POST['gyd_number'] ) : 0 );
		update_post_meta( $post_id, '_gyd_category', isset( $_POST['gyd_category'] ) ? sanitize_text_field( wp_unslash( $_POST['gyd_category'] ) ) : '' );
		update_post_meta( $post_id, '_gyd_tagline', isset( $_POST['gyd_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['gyd_tagline'] ) ) : '' );
		update_post_meta( $post_id, '_gyd_short_desc', isset( $_POST['gyd_short_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gyd_short_desc'] ) ) : '' );
		update_post_meta( $post_id, '_gyd_is_new', isset( $_POST['gyd_is_new'] ) ? '1' : '' );
	}

	/* ------------------------------------------------------------------ *
	 * Mentor profile box (photo + role + bio + email)
	 * ------------------------------------------------------------------ */

	/**
	 * Render the mentor profile box.
	 *
	 * @param WP_Post $post Mentor.
	 */
	public static function render_mentor_profile_box( $post ) {
		wp_nonce_field( 'gydb_save_mentor', 'gydb_mentor_nonce' );

		$role     = get_post_meta( $post->ID, '_gyd_role', true );
		$bio      = get_post_meta( $post->ID, '_gyd_bio', true );
		$email    = get_post_meta( $post->ID, '_gyd_email', true );
		$photo_id = (int) get_post_meta( $post->ID, '_gyd_photo_id', true );
		$photo    = $photo_id ? wp_get_attachment_image_url( $photo_id, 'medium' ) : '';
		?>
		<div class="gydb-field-grid">
			<div class="gydb-photo-field">
				<label><strong><?php esc_html_e( 'Mentor photo', 'gyd-booking' ); ?></strong></label>
				<div class="gydb-photo-preview" style="<?php echo $photo ? '' : 'display:none;'; ?>">
					<img src="<?php echo esc_url( $photo ); ?>" alt="" style="max-width:140px;height:auto;border-radius:50%;">
				</div>
				<input type="hidden" id="gyd_photo_id" name="gyd_photo_id" value="<?php echo esc_attr( $photo_id ); ?>">
				<p>
					<button type="button" class="button gydb-upload-photo"><?php esc_html_e( 'Select / upload photo', 'gyd-booking' ); ?></button>
					<button type="button" class="button-link gydb-remove-photo" style="<?php echo $photo ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'gyd-booking' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'A square headshot works best. This is the face clients see on the booking page.', 'gyd-booking' ); ?></p>
			</div>

			<div class="gydb-text-fields">
				<p>
					<label for="gyd_role"><strong><?php esc_html_e( 'Role / title', 'gyd-booking' ); ?></strong></label><br>
					<input type="text" id="gyd_role" name="gyd_role" value="<?php echo esc_attr( $role ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Media & Journalism Lead', 'gyd-booking' ); ?>">
				</p>
				<p>
					<label for="gyd_email"><strong><?php esc_html_e( 'Mentor email (for booking notifications)', 'gyd-booking' ); ?></strong></label><br>
					<input type="email" id="gyd_email" name="gyd_email" value="<?php echo esc_attr( $email ); ?>" class="large-text" placeholder="mentor@example.org">
				</p>
				<p>
					<label for="gyd_bio"><strong><?php esc_html_e( 'Short bio', 'gyd-booking' ); ?></strong></label><br>
					<textarea id="gyd_bio" name="gyd_bio" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'A few lines about the mentor’s background and focus areas.', 'gyd-booking' ); ?>"><?php echo esc_textarea( $bio ); ?></textarea>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the mentor availability box.
	 *
	 * @param WP_Post $post Mentor.
	 */
	public static function render_mentor_availability_box( $post ) {
		$settings = GYDB_Helpers::get_settings();

		$days      = get_post_meta( $post->ID, '_gyd_avail_days', true );
		$start     = get_post_meta( $post->ID, '_gyd_avail_start', true );
		$end       = get_post_meta( $post->ID, '_gyd_avail_end', true );
		$slot_len  = get_post_meta( $post->ID, '_gyd_slot_len', true );
		$new_entry = ! metadata_exists( 'post', $post->ID, '_gyd_avail_days' );

		// Sensible defaults for brand-new mentors.
		if ( $new_entry ) {
			$days     = $settings['default_days'];
			$start    = $settings['default_start'];
			$end      = $settings['default_end'];
			$slot_len = $settings['default_slot_len'];
		}
		if ( ! is_array( $days ) ) {
			$days = array();
		}
		?>
		<p><strong><?php esc_html_e( 'Available days', 'gyd-booking' ); ?></strong></p>
		<p>
			<?php foreach ( GYDB_Helpers::weekdays() as $num => $label ) : ?>
				<label style="display:inline-block;margin-right:14px;">
					<input type="checkbox" name="gyd_avail_days[]" value="<?php echo esc_attr( $num ); ?>" <?php checked( in_array( (string) $num, array_map( 'strval', $days ), true ) ); ?>>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</p>
		<p>
			<label for="gyd_avail_start"><strong><?php esc_html_e( 'Day starts', 'gyd-booking' ); ?></strong></label>
			<input type="time" id="gyd_avail_start" name="gyd_avail_start" value="<?php echo esc_attr( $start ? $start : '09:00' ); ?>">
			&nbsp;&nbsp;
			<label for="gyd_avail_end"><strong><?php esc_html_e( 'Day ends', 'gyd-booking' ); ?></strong></label>
			<input type="time" id="gyd_avail_end" name="gyd_avail_end" value="<?php echo esc_attr( $end ? $end : '17:00' ); ?>">
			&nbsp;&nbsp;
			<label for="gyd_slot_len"><strong><?php esc_html_e( 'Session length (minutes)', 'gyd-booking' ); ?></strong></label>
			<input type="number" min="15" step="5" id="gyd_slot_len" name="gyd_slot_len" value="<?php echo esc_attr( $slot_len ? $slot_len : 45 ); ?>" class="small-text">
		</p>
		<p class="description"><?php esc_html_e( 'The booking page builds time slots automatically from these settings and hides any slot that is already booked.', 'gyd-booking' ); ?></p>
		<?php
	}

	/**
	 * Render the "assigned programmes" side box.
	 *
	 * @param WP_Post $post Mentor.
	 */
	public static function render_mentor_programs_box( $post ) {
		$assigned = GYDB_Helpers::get_mentor_program_ids( $post->ID );
		$programs = GYDB_Helpers::get_programs();
		?>
		<?php if ( empty( $programs ) ) : ?>
			<p class="description"><?php esc_html_e( 'No programmes yet. Create programmes first, then assign this mentor to them.', 'gyd-booking' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Tick every programme this mentor supports.', 'gyd-booking' ); ?></p>
			<ul style="max-height:260px;overflow:auto;margin-top:8px;">
				<?php foreach ( $programs as $program ) : ?>
					<li>
						<label>
							<input type="checkbox" name="gyd_programs[]" value="<?php echo esc_attr( $program->ID ); ?>" <?php checked( in_array( $program->ID, $assigned, true ) ); ?>>
							<?php echo esc_html( $program->post_title ); ?>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save all mentor meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_mentor( $post_id, $post ) {
		if ( ! self::can_save( $post_id, 'gydb_mentor_nonce', 'gydb_save_mentor' ) ) {
			return;
		}

		update_post_meta( $post_id, '_gyd_role', isset( $_POST['gyd_role'] ) ? sanitize_text_field( wp_unslash( $_POST['gyd_role'] ) ) : '' );
		update_post_meta( $post_id, '_gyd_bio', isset( $_POST['gyd_bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gyd_bio'] ) ) : '' );
		update_post_meta( $post_id, '_gyd_email', isset( $_POST['gyd_email'] ) ? sanitize_email( wp_unslash( $_POST['gyd_email'] ) ) : '' );
		update_post_meta( $post_id, '_gyd_photo_id', isset( $_POST['gyd_photo_id'] ) ? absint( $_POST['gyd_photo_id'] ) : 0 );

		// Programmes (array of IDs).
		$program_ids = array();
		if ( isset( $_POST['gyd_programs'] ) && is_array( $_POST['gyd_programs'] ) ) {
			$program_ids = array_map( 'absint', wp_unslash( $_POST['gyd_programs'] ) );
			$program_ids = array_values( array_filter( $program_ids ) );
		}
		update_post_meta( $post_id, '_gyd_programs', $program_ids );

		// Availability.
		$days = array();
		if ( isset( $_POST['gyd_avail_days'] ) && is_array( $_POST['gyd_avail_days'] ) ) {
			foreach ( wp_unslash( $_POST['gyd_avail_days'] ) as $d ) {
				$d = (string) intval( $d );
				if ( array_key_exists( $d, GYDB_Helpers::weekdays() ) ) {
					$days[] = $d;
				}
			}
		}
		update_post_meta( $post_id, '_gyd_avail_days', $days );
		update_post_meta( $post_id, '_gyd_avail_start', isset( $_POST['gyd_avail_start'] ) ? self::sanitize_time( wp_unslash( $_POST['gyd_avail_start'] ) ) : '09:00' );
		update_post_meta( $post_id, '_gyd_avail_end', isset( $_POST['gyd_avail_end'] ) ? self::sanitize_time( wp_unslash( $_POST['gyd_avail_end'] ) ) : '17:00' );

		$slot = isset( $_POST['gyd_slot_len'] ) ? absint( $_POST['gyd_slot_len'] ) : 45;
		update_post_meta( $post_id, '_gyd_slot_len', max( 15, $slot ) );
	}

	/* ------------------------------------------------------------------ *
	 * Admin columns
	 * ------------------------------------------------------------------ */

	public static function mentor_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['gyd_photo']    = __( 'Photo', 'gyd-booking' );
				$new['gyd_role']     = __( 'Role', 'gyd-booking' );
				$new['gyd_programs'] = __( 'Programmes', 'gyd-booking' );
			}
		}
		return $new;
	}

	public static function mentor_column_content( $column, $post_id ) {
		if ( 'gyd_photo' === $column ) {
			$url = GYDB_Helpers::get_mentor_photo_url( $post_id, 'thumbnail' );
			if ( $url ) {
				echo '<img src="' . esc_url( $url ) . '" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:50%;">';
			} else {
				echo '<span style="color:#999;">—</span>';
			}
		} elseif ( 'gyd_role' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_gyd_role', true ) );
		} elseif ( 'gyd_programs' === $column ) {
			$ids   = GYDB_Helpers::get_mentor_program_ids( $post_id );
			$names = array();
			foreach ( $ids as $id ) {
				$title = get_the_title( $id );
				if ( $title ) {
					$names[] = $title;
				}
			}
			echo $names ? esc_html( implode( ', ', $names ) ) : '<span style="color:#999;">—</span>';
		}
	}

	public static function program_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['gyd_number']  = __( 'No.', 'gyd-booking' );
				$new['gyd_tagline'] = __( 'Tagline', 'gyd-booking' );
				$new['gyd_mentors'] = __( 'Mentors', 'gyd-booking' );
			}
		}
		return $new;
	}

	public static function program_column_content( $column, $post_id ) {
		if ( 'gyd_number' === $column ) {
			echo esc_html( GYDB_Helpers::get_program_number( $post_id ) );
		} elseif ( 'gyd_tagline' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_gyd_tagline', true ) );
		} elseif ( 'gyd_mentors' === $column ) {
			$count = count( GYDB_Helpers::get_mentors_for_program( $post_id ) );
			echo esc_html( (string) $count );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Utilities
	 * ------------------------------------------------------------------ */

	/**
	 * Shared save guard: nonce, autosave and capability checks.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $nonce_name POST key for the nonce.
	 * @param string $action     Nonce action.
	 * @return bool
	 */
	private static function can_save( $post_id, $nonce_name, $action ) {
		if ( ! isset( $_POST[ $nonce_name ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), $action ) ) {
			return false;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Sanitise an HH:MM time string.
	 *
	 * @param string $value Raw value.
	 * @return string HH:MM or ''.
	 */
	private static function sanitize_time( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) {
			return $value;
		}
		return '';
	}
}
