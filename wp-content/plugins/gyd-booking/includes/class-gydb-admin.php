<?php
/**
 * Admin: menu, bookings management, settings, CSV export, asset loading.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GYDB_Admin {

	const MENU_SLUG = 'gyd-booking';
	const CAP       = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_init', array( 'GYDB_Database', 'maybe_upgrade' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Menu
	 * ------------------------------------------------------------------ */
	public static function menu() {
		add_menu_page(
			__( 'GYD Booking', 'gyd-booking' ),
			__( 'GYD Booking', 'gyd-booking' ),
			self::CAP,
			self::MENU_SLUG,
			array( __CLASS__, 'render_bookings_page' ),
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Bookings', 'gyd-booking' ),
			__( 'Bookings', 'gyd-booking' ),
			self::CAP,
			self::MENU_SLUG,
			array( __CLASS__, 'render_bookings_page' )
		);

		// Programmes & Mentors are attached here via each CPT's show_in_menu.

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'gyd-booking' ),
			__( 'Settings', 'gyd-booking' ),
			self::CAP,
			'gyd-booking-settings',
			array( __CLASS__, 'render_settings_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Shortcodes & Help', 'gyd-booking' ),
			__( 'Shortcodes & Help', 'gyd-booking' ),
			self::CAP,
			'gyd-booking-help',
			array( __CLASS__, 'render_help_page' )
		);
	}

	/* ------------------------------------------------------------------ *
	 * Assets
	 * ------------------------------------------------------------------ */
	public static function enqueue( $hook ) {
		$screen = get_current_screen();

		// Admin stylesheet on all plugin screens.
		$is_plugin_screen = ( $screen && in_array( $screen->post_type, array( GYDB_PROGRAM_CPT, GYDB_MENTOR_CPT ), true ) )
			|| ( false !== strpos( (string) $hook, 'gyd-booking' ) );

		if ( $is_plugin_screen ) {
			wp_enqueue_style( 'gydb-admin', GYDB_URL . 'assets/css/gyd-admin.css', array(), GYDB_VERSION );
		}

		// Colour picker on the settings screen.
		if ( false !== strpos( (string) $hook, 'gyd-booking-settings' ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".gydb-color-field").wpColorPicker();});' );
		}

		// Media uploader only on the mentor editor.
		if ( $screen && GYDB_MENTOR_CPT === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'gydb-admin', GYDB_URL . 'assets/js/gyd-admin.js', array( 'jquery' ), GYDB_VERSION, true );
			wp_localize_script(
				'gydb-admin',
				'GYDBAdmin',
				array(
					'title'  => __( 'Select mentor photo', 'gyd-booking' ),
					'button' => __( 'Use this photo', 'gyd-booking' ),
				)
			);
		}
	}

	/* ------------------------------------------------------------------ *
	 * Row actions (status change / delete) + CSV export
	 * ------------------------------------------------------------------ */
	public static function handle_actions() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		// Export.
		if ( isset( $_GET['page'], $_GET['gydb_export'] ) && self::MENU_SLUG === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'gydb_export' );
			self::export_csv();
		}

		// Dismiss the setup notice.
		if ( isset( $_GET['gydb_dismiss_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'gydb_dismiss_notice' );
			delete_transient( 'gydb_show_setup_notice' );
			wp_safe_redirect( remove_query_arg( array( 'gydb_dismiss_notice', '_wpnonce' ) ) );
			exit;
		}

		// Single-row status / delete.
		if ( isset( $_GET['gydb_action'], $_GET['booking'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action  = sanitize_key( wp_unslash( $_GET['gydb_action'] ) );
			$booking = absint( wp_unslash( $_GET['booking'] ) );

			check_admin_referer( 'gydb_booking_action_' . $booking );

			if ( 'delete' === $action ) {
				GYDB_Database::delete( $booking );
				self::redirect_with_notice( 'deleted' );
			} elseif ( 0 === strpos( $action, 'status_' ) ) {
				$status = substr( $action, 7 );
				GYDB_Database::update_status( $booking, $status );
				self::redirect_with_notice( 'updated' );
			}
		}
	}

	/**
	 * Redirect back to the bookings list with a notice flag.
	 *
	 * @param string $notice Notice key.
	 */
	private static function redirect_with_notice( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'gydb_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Bookings list page
	 * ------------------------------------------------------------------ */
	public static function render_bookings_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$per_page = 20;
		$result   = GYDB_Database::query(
			array(
				'status'   => $status,
				'search'   => $search,
				'per_page' => $per_page,
				'page'     => $paged,
			)
		);
		$items  = $result['items'];
		$total  = $result['total'];
		$counts = GYDB_Database::counts_by_status();
		$pages  = max( 1, (int) ceil( $total / $per_page ) );

		$statuses = GYDB_Helpers::statuses();
		?>
		<div class="wrap gydb-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'gyd-booking' ); ?></h1>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'gydb_export' => 1 ), admin_url( 'admin.php' ) ), 'gydb_export' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'gyd-booking' ); ?></a>
			<hr class="wp-header-end">

			<?php self::render_admin_notice(); ?>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG ), admin_url( 'admin.php' ) ) ); ?>" class="<?php echo '' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'gyd-booking' ); ?> <span class="count">(<?php echo esc_html( $counts['all'] ); ?>)</span></a></li>
				<?php foreach ( $statuses as $key => $label ) : ?>
					<?php $c = isset( $counts[ $key ] ) ? $counts[ $key ] : 0; ?>
					| <li><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'status' => $key ), admin_url( 'admin.php' ) ) ); ?>" class="<?php echo $status === $key ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?> <span class="count">(<?php echo esc_html( $c ); ?>)</span></a></li>
				<?php endforeach; ?>
			</ul>

			<form method="get" class="gydb-search-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">
				<?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email or reference', 'gyd-booking' ); ?>">
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'gyd-booking' ); ?></button>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped gydb-bookings-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Ref', 'gyd-booking' ); ?></th>
						<th><?php esc_html_e( 'Client', 'gyd-booking' ); ?></th>
						<th><?php esc_html_e( 'Programme', 'gyd-booking' ); ?></th>
						<th><?php esc_html_e( 'Mentor', 'gyd-booking' ); ?></th>
						<th><?php esc_html_e( 'When', 'gyd-booking' ); ?></th>
						<th><?php esc_html_e( 'Status', 'gyd-booking' ); ?></th>
						<th><?php esc_html_e( 'Received', 'gyd-booking' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'gyd-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No bookings found.', 'gyd-booking' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $row->reference ); ?></strong></td>
							<td>
								<?php echo esc_html( $row->client_name ); ?><br>
								<a href="mailto:<?php echo esc_attr( $row->client_email ); ?>"><?php echo esc_html( $row->client_email ); ?></a>
								<?php if ( $row->client_phone ) : ?><br><span class="description"><?php echo esc_html( $row->client_phone ); ?></span><?php endif; ?>
								<?php if ( $row->message ) : ?><br><span class="gydb-msg" title="<?php echo esc_attr( $row->message ); ?>">“<?php echo esc_html( wp_trim_words( $row->message, 12 ) ); ?>”</span><?php endif; ?>
							</td>
							<td><?php echo esc_html( get_the_title( $row->program_id ) ); ?></td>
							<td><?php echo esc_html( get_the_title( $row->mentor_id ) ); ?></td>
							<td><?php echo esc_html( GYDB_Helpers::format_date( $row->booking_date ) ); ?><br><span class="description"><?php echo esc_html( GYDB_Helpers::format_time( $row->booking_time ) ); ?></span></td>
							<td><span class="gydb-badge gydb-badge-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( isset( $statuses[ $row->status ] ) ? $statuses[ $row->status ] : $row->status ); ?></span></td>
							<td><span class="description"><?php echo esc_html( GYDB_Helpers::format_date( $row->created_at ) ); ?></span></td>
							<td class="gydb-row-actions">
								<?php echo self::status_links( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<a class="gydb-delete" href="<?php echo esc_url( self::action_url( 'delete', $row->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this booking?', 'gyd-booking' ) ); ?>');"><?php esc_html_e( 'Delete', 'gyd-booking' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $pages,
								'prev_text' => '‹',
								'next_text' => '›',
							)
						)
					);
					?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Build quick status-change links for a row.
	 *
	 * @param object $row Booking.
	 * @return string
	 */
	private static function status_links( $row ) {
		$out = array();
		foreach ( GYDB_Helpers::statuses() as $key => $label ) {
			if ( $key === $row->status ) {
				continue;
			}
			$out[] = '<a href="' . esc_url( self::action_url( 'status_' . $key, $row->id ) ) . '">' . esc_html( $label ) . '</a>';
		}
		return '<span class="gydb-status-links">' . implode( ' · ', $out ) . '</span><br>';
	}

	/**
	 * Build a nonced action URL for a booking row.
	 *
	 * @param string $action Action key.
	 * @param int    $id     Booking ID.
	 * @return string
	 */
	private static function action_url( $action, $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'gydb_action' => $action,
					'booking'     => (int) $id,
				),
				admin_url( 'admin.php' )
			),
			'gydb_booking_action_' . (int) $id
		);
	}

	private static function render_admin_notice() {
		if ( ! isset( $_GET['gydb_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$notice = sanitize_key( wp_unslash( $_GET['gydb_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map    = array(
			'deleted' => __( 'Booking deleted.', 'gyd-booking' ),
			'updated' => __( 'Booking updated.', 'gyd-booking' ),
		);
		if ( isset( $map[ $notice ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $notice ] ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ *
	 * CSV export
	 * ------------------------------------------------------------------ */
	private static function export_csv() {
		$result = GYDB_Database::query( array( 'per_page' => 100000, 'page' => 1 ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=gyd-bookings-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Reference', 'Name', 'Email', 'Phone', 'Programme', 'Mentor', 'Date', 'Time', 'Status', 'Message', 'Received' ) );

		foreach ( $result['items'] as $row ) {
			fputcsv(
				$out,
				array(
					$row->reference,
					$row->client_name,
					$row->client_email,
					$row->client_phone,
					get_the_title( $row->program_id ),
					get_the_title( $row->mentor_id ),
					$row->booking_date,
					substr( $row->booking_time, 0, 5 ),
					$row->status,
					$row->message,
					$row->created_at,
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Settings
	 * ------------------------------------------------------------------ */
	public static function register_settings() {
		register_setting(
			'gydb_settings_group',
			GYDB_Helpers::OPTION_KEY,
			array( __CLASS__, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitise the settings array.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$defaults = GYDB_Helpers::default_settings();
		$current  = GYDB_Helpers::get_settings();
		$out      = $current;

		$out['booking_page_id']  = isset( $input['booking_page_id'] ) ? absint( $input['booking_page_id'] ) : 0;
		$out['admin_email']      = isset( $input['admin_email'] ) && is_email( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : $defaults['admin_email'];
		$out['from_email']       = isset( $input['from_email'] ) && is_email( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : $defaults['from_email'];
		$out['email_from_name']  = isset( $input['email_from_name'] ) ? sanitize_text_field( $input['email_from_name'] ) : $defaults['email_from_name'];
		$out['notify_admin']     = empty( $input['notify_admin'] ) ? 0 : 1;
		$out['notify_client']    = empty( $input['notify_client'] ) ? 0 : 1;
		$out['load_fonts']       = empty( $input['load_fonts'] ) ? 0 : 1;
		$out['default_slot_len'] = isset( $input['default_slot_len'] ) ? max( 15, absint( $input['default_slot_len'] ) ) : $defaults['default_slot_len'];
		$out['default_start']    = isset( $input['default_start'] ) ? self::sanitize_time( $input['default_start'], '09:00' ) : $defaults['default_start'];
		$out['default_end']      = isset( $input['default_end'] ) ? self::sanitize_time( $input['default_end'], '17:00' ) : $defaults['default_end'];
		$out['min_notice_hours'] = isset( $input['min_notice_hours'] ) ? absint( $input['min_notice_hours'] ) : $defaults['min_notice_hours'];
		$out['max_days_ahead']   = isset( $input['max_days_ahead'] ) ? max( 1, absint( $input['max_days_ahead'] ) ) : $defaults['max_days_ahead'];
		$out['success_message']  = isset( $input['success_message'] ) ? sanitize_textarea_field( $input['success_message'] ) : $defaults['success_message'];

		$days = array();
		if ( isset( $input['default_days'] ) && is_array( $input['default_days'] ) ) {
			foreach ( $input['default_days'] as $d ) {
				$d = (string) intval( $d );
				if ( array_key_exists( $d, GYDB_Helpers::weekdays() ) ) {
					$days[] = $d;
				}
			}
		}
		$out['default_days'] = $days ? $days : $defaults['default_days'];

		// Brand colours.
		$color_keys = array( 'color_navy', 'color_navy2', 'color_red', 'color_red_dark', 'color_gold', 'color_gold_light', 'color_cream', 'color_cream2', 'color_ink', 'color_muted', 'color_line' );
		foreach ( $color_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$color = sanitize_hex_color( $input[ $key ] );
				$out[ $key ] = $color ? $color : $defaults[ $key ];
			}
		}

		return $out;
	}

	private static function sanitize_time( $value, $fallback ) {
		$value = trim( (string) $value );
		return preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ? $value : $fallback;
	}

	public static function render_settings_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$s    = GYDB_Helpers::get_settings();
		$days = array_map( 'strval', (array) $s['default_days'] );
		?>
		<div class="wrap gydb-admin">
			<h1><?php esc_html_e( 'GYD Booking — Settings', 'gyd-booking' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'gydb_settings_group' ); ?>
				<?php $name = GYDB_Helpers::OPTION_KEY; ?>

				<h2 class="title"><?php esc_html_e( 'Booking page & emails', 'gyd-booking' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gydb-booking-page"><?php esc_html_e( 'Booking page', 'gyd-booking' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $name ) . '[booking_page_id]',
									'id'                => 'gydb-booking-page',
									'selected'          => (int) $s['booking_page_id'],
									'show_option_none'  => __( '— Select a page —', 'gyd-booking' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'The page that contains the [gyd_booking] shortcode. “Book Now” buttons link here.', 'gyd-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label><?php esc_html_e( 'Notifications', 'gyd-booking' ); ?></label></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[notify_admin]" value="1" <?php checked( $s['notify_admin'], 1 ); ?>> <?php esc_html_e( 'Email the admin & mentor when a booking is made', 'gyd-booking' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[notify_client]" value="1" <?php checked( $s['notify_client'], 1 ); ?>> <?php esc_html_e( 'Send the client a confirmation email', 'gyd-booking' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gydb-admin-email"><?php esc_html_e( 'Admin notification email', 'gyd-booking' ); ?></label></th>
						<td><input type="email" id="gydb-admin-email" class="regular-text" name="<?php echo esc_attr( $name ); ?>[admin_email]" value="<?php echo esc_attr( $s['admin_email'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="gydb-from-name"><?php esc_html_e( 'Email “from” name', 'gyd-booking' ); ?></label></th>
						<td><input type="text" id="gydb-from-name" class="regular-text" name="<?php echo esc_attr( $name ); ?>[email_from_name]" value="<?php echo esc_attr( $s['email_from_name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="gydb-from-email"><?php esc_html_e( 'Email “from” address', 'gyd-booking' ); ?></label></th>
						<td><input type="email" id="gydb-from-email" class="regular-text" name="<?php echo esc_attr( $name ); ?>[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="gydb-success"><?php esc_html_e( 'Success message', 'gyd-booking' ); ?></label></th>
						<td><textarea id="gydb-success" class="large-text" rows="2" name="<?php echo esc_attr( $name ); ?>[success_message]"><?php echo esc_textarea( $s['success_message'] ); ?></textarea></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Default scheduling', 'gyd-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Used for new mentors and as a fallback. Each mentor can override these on their own profile.', 'gyd-booking' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Default available days', 'gyd-booking' ); ?></th>
						<td>
							<?php foreach ( GYDB_Helpers::weekdays() as $num => $label ) : ?>
								<label style="display:inline-block;margin-right:14px;"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[default_days][]" value="<?php echo esc_attr( $num ); ?>" <?php checked( in_array( (string) $num, $days, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default day hours', 'gyd-booking' ); ?></th>
						<td>
							<input type="time" name="<?php echo esc_attr( $name ); ?>[default_start]" value="<?php echo esc_attr( $s['default_start'] ); ?>">
							&nbsp;→&nbsp;
							<input type="time" name="<?php echo esc_attr( $name ); ?>[default_end]" value="<?php echo esc_attr( $s['default_end'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gydb-slot-len"><?php esc_html_e( 'Default session length (min)', 'gyd-booking' ); ?></label></th>
						<td><input type="number" min="15" step="5" id="gydb-slot-len" name="<?php echo esc_attr( $name ); ?>[default_slot_len]" value="<?php echo esc_attr( $s['default_slot_len'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="gydb-notice"><?php esc_html_e( 'Minimum notice (hours)', 'gyd-booking' ); ?></label></th>
						<td><input type="number" min="0" id="gydb-notice" name="<?php echo esc_attr( $name ); ?>[min_notice_hours]" value="<?php echo esc_attr( $s['min_notice_hours'] ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'How far in advance a slot must be booked.', 'gyd-booking' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><label for="gydb-ahead"><?php esc_html_e( 'Booking window (days ahead)', 'gyd-booking' ); ?></label></th>
						<td><input type="number" min="1" id="gydb-ahead" name="<?php echo esc_attr( $name ); ?>[max_days_ahead]" value="<?php echo esc_attr( $s['max_days_ahead'] ); ?>" class="small-text"></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Brand colours', 'gyd-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Pre-filled with the GYD palette. Change here to re-skin the booking UI site-wide.', 'gyd-booking' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					$color_fields = array(
						'color_navy'       => __( 'Navy (primary)', 'gyd-booking' ),
						'color_navy2'      => __( 'Navy 2', 'gyd-booking' ),
						'color_red'        => __( 'Red (accent)', 'gyd-booking' ),
						'color_red_dark'   => __( 'Red dark', 'gyd-booking' ),
						'color_gold'       => __( 'Gold (highlight)', 'gyd-booking' ),
						'color_gold_light' => __( 'Gold light', 'gyd-booking' ),
						'color_cream'      => __( 'Cream (background)', 'gyd-booking' ),
						'color_cream2'     => __( 'Cream 2', 'gyd-booking' ),
						'color_ink'        => __( 'Ink (text)', 'gyd-booking' ),
						'color_muted'      => __( 'Muted', 'gyd-booking' ),
						'color_line'       => __( 'Line / border', 'gyd-booking' ),
					);
					foreach ( $color_fields as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="gydb-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="text" id="gydb-<?php echo esc_attr( $key ); ?>" class="gydb-color-field" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ $key ] ); ?>"></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Google Fonts', 'gyd-booking' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[load_fonts]" value="1" <?php checked( $s['load_fonts'], 1 ); ?>> <?php esc_html_e( 'Load Space Grotesk + DM Sans from Google Fonts', 'gyd-booking' ); ?></label></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Help / shortcodes page
	 * ------------------------------------------------------------------ */
	public static function render_help_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$booking_page = (int) GYDB_Helpers::get_setting( 'booking_page_id' );
		?>
		<div class="wrap gydb-admin gydb-help">
			<h1><?php esc_html_e( 'Shortcodes & Help', 'gyd-booking' ); ?></h1>

			<div class="gydb-help-card">
				<h2><?php esc_html_e( 'Installed version', 'gyd-booking' ); ?></h2>
				<p>
					<strong><?php echo esc_html( GYDB_VERSION ); ?></strong> —
					<?php esc_html_e( 'if this number does not match the version you just uploaded, the old plugin files are still on the server. Re-upload, then clear any caching plugin and hard-refresh the page (Ctrl/Cmd + Shift + R).', 'gyd-booking' ); ?>
				</p>
			</div>

			<div class="gydb-help-card">
				<h2><?php esc_html_e( 'Make the header “Book Now” open the popup', 'gyd-booking' ); ?></h2>
				<p><?php esc_html_e( 'Any link pointing at your booking page opens the popup automatically — including a menu item. Nothing to configure.', 'gyd-booking' ); ?></p>
				<p><?php printf( wp_kses_post( __( 'To force any other button or link to open it, give it the CSS class <code>gyd-book-now</code> (Appearance → Menus → Screen Options → tick “CSS Classes”).', 'gyd-booking' ) ) ); ?></p>
			</div>

			<div class="gydb-help-card">
				<h2><?php esc_html_e( 'Quick start', 'gyd-booking' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'The nine programmes and a “Book a Session” page were created automatically on activation.', 'gyd-booking' ); ?></li>
					<li><?php printf( wp_kses_post( __( 'Add your mentors under <strong>GYD Booking → Mentors</strong> — set a photo, role, bio, and tick which programmes they support.', 'gyd-booking' ) ) ); ?></li>
					<li><?php esc_html_e( 'Put the [gyd_programs] shortcode on your Programmes page, and [gyd_booking] on your booking page.', 'gyd-booking' ); ?></li>
					<li><?php esc_html_e( 'That’s it — clients pick a programme, choose a mentor, and book a time.', 'gyd-booking' ); ?></li>
				</ol>
				<?php if ( $booking_page ) : ?>
					<p><a class="button button-secondary" href="<?php echo esc_url( get_permalink( $booking_page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View booking page', 'gyd-booking' ); ?></a>
					<a class="button" href="<?php echo esc_url( get_edit_post_link( $booking_page ) ); ?>"><?php esc_html_e( 'Edit booking page', 'gyd-booking' ); ?></a></p>
				<?php endif; ?>
			</div>

			<div class="gydb-help-card">
				<h2><?php esc_html_e( 'Shortcodes', 'gyd-booking' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Shortcode', 'gyd-booking' ); ?></th><th><?php esc_html_e( 'What it does', 'gyd-booking' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>[gyd_booking]</code></td><td><?php esc_html_e( 'The full booking experience: steps, programme picker, mentor cards and the scheduling form. Reads ?gyd_program= from the URL to preselect a programme.', 'gyd-booking' ); ?></td></tr>
						<tr><td><code>[gyd_booking program="global-youth-media"]</code></td><td><?php esc_html_e( 'Booking experience preselected to one programme.', 'gyd-booking' ); ?></td></tr>
						<tr><td><code>[gyd_programs]</code></td><td><?php esc_html_e( 'A grid of programme cards, each linking to the booking page. Add layout="rows" for the numbered list style, or columns="2|3|4".', 'gyd-booking' ); ?></td></tr>
						<tr><td><code>[gyd_mentors program="global-youth-innovation"]</code></td><td><?php esc_html_e( 'Mentor cards for one programme (photo, role, bio, Book button).', 'gyd-booking' ); ?></td></tr>
						<tr><td><code>[gyd_book_button program="global-youth-media" text="Book Now"]</code></td><td><?php esc_html_e( 'A single Book Now button that links to the booking page (optionally preselecting a programme).', 'gyd-booking' ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<div class="gydb-help-card">
				<h2><?php esc_html_e( 'Programme slugs', 'gyd-booking' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Programme', 'gyd-booking' ); ?></th><th><?php esc_html_e( 'Slug', 'gyd-booking' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( GYDB_Helpers::get_programs() as $p ) : ?>
							<tr><td><?php echo esc_html( $p->post_title ); ?></td><td><code><?php echo esc_html( $p->post_name ); ?></code></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * One-time admin notice pointing to setup after activation.
	 */
	public static function setup_notice() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		if ( ! get_transient( 'gydb_show_setup_notice' ) ) {
			return;
		}
		$dismiss_url = wp_nonce_url( add_query_arg( 'gydb_dismiss_notice', 1 ), 'gydb_dismiss_notice' );
		?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'GYD Booking is ready.', 'gyd-booking' ); ?></strong>
				<?php esc_html_e( 'The nine programmes, sample mentors and a booking page were created for you.', 'gyd-booking' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gyd-booking-help' ) ); ?>"><?php esc_html_e( 'Open the setup guide →', 'gyd-booking' ); ?></a>
				&nbsp;·&nbsp;
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'gyd-booking' ); ?></a>
			</p>
		</div>
		<?php
	}
}
