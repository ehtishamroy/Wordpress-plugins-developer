<?php
/**
 * Bookings data layer: custom table, CRUD and slot availability.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GYDB_Database {

	/**
	 * Fully-qualified bookings table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'gyd_bookings';
	}

	/**
	 * Create (or upgrade) the bookings table via dbDelta.
	 */
	public static function create_table() {
		global $wpdb;

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			program_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			mentor_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			client_name VARCHAR(190) NOT NULL DEFAULT '',
			client_email VARCHAR(190) NOT NULL DEFAULT '',
			client_phone VARCHAR(60) NOT NULL DEFAULT '',
			booking_date DATE NOT NULL,
			booking_time TIME NOT NULL,
			message TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			reference VARCHAR(20) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY mentor_slot (mentor_id, booking_date, booking_time),
			KEY program_id (program_id),
			KEY status (status),
			KEY booking_date (booking_date)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'gydb_db_version', GYDB_DB_VERSION );
	}

	/**
	 * Ensure the table exists / is current. Cheap guard called on admin_init.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'gydb_db_version' ) !== GYDB_DB_VERSION ) {
			self::create_table();
		}
	}

	/**
	 * Insert a booking.
	 *
	 * @param array $data Sanitised booking fields.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$defaults = array(
			'program_id'   => 0,
			'mentor_id'    => 0,
			'client_name'  => '',
			'client_email' => '',
			'client_phone' => '',
			'booking_date' => '',
			'booking_time' => '',
			'message'      => '',
			'status'       => 'pending',
			'reference'    => self::generate_reference(),
			'created_at'   => current_time( 'mysql' ),
		);
		$data = wp_parse_args( $data, $defaults );

		$ok = $wpdb->insert(
			self::table(),
			array(
				'program_id'   => (int) $data['program_id'],
				'mentor_id'    => (int) $data['mentor_id'],
				'client_name'  => $data['client_name'],
				'client_email' => $data['client_email'],
				'client_phone' => $data['client_phone'],
				'booking_date' => $data['booking_date'],
				'booking_time' => $data['booking_time'],
				'message'      => $data['message'],
				'status'       => $data['status'],
				'reference'    => $data['reference'],
				'created_at'   => $data['created_at'],
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get a single booking row by ID.
	 *
	 * @param int $id Booking ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Update a booking's status.
	 *
	 * @param int    $id     Booking ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		$allowed = array_keys( GYDB_Helpers::statuses() );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		return (bool) $wpdb->update(
			self::table(),
			array( 'status' => $status ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a booking.
	 *
	 * @param int $id Booking ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Is a specific mentor slot already taken (not cancelled)?
	 *
	 * @param int    $mentor_id Mentor post ID.
	 * @param string $date      Y-m-d.
	 * @param string $time      HH:MM:SS or HH:MM.
	 * @return bool
	 */
	public static function is_slot_taken( $mentor_id, $date, $time ) {
		global $wpdb;
		$table = self::table();
		if ( strlen( $time ) === 5 ) {
			$time .= ':00';
		}
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE mentor_id = %d AND booking_date = %s AND booking_time = %s AND status != 'cancelled'", // phpcs:ignore WordPress.DB.PreparedSQL
				(int) $mentor_id,
				$date,
				$time
			)
		);
		return (int) $count > 0;
	}

	/**
	 * Get the taken times (HH:MM) for a mentor on a date.
	 *
	 * @param int    $mentor_id Mentor post ID.
	 * @param string $date      Y-m-d.
	 * @return string[] Array of HH:MM strings.
	 */
	public static function get_taken_times( $mentor_id, $date ) {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT booking_time FROM {$table} WHERE mentor_id = %d AND booking_date = %s AND status != 'cancelled'", // phpcs:ignore WordPress.DB.PreparedSQL
				(int) $mentor_id,
				$date
			)
		);
		return array_map(
			static function ( $t ) {
				return substr( $t, 0, 5 );
			},
			(array) $rows
		);
	}

	/**
	 * Query bookings for the admin list.
	 *
	 * @param array $args status, mentor_id, program_id, search, orderby, order, per_page, page.
	 * @return array { items: object[], total: int }
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$args = wp_parse_args(
			$args,
			array(
				'status'     => '',
				'mentor_id'  => 0,
				'program_id' => 0,
				'search'     => '',
				'orderby'    => 'created_at',
				'order'      => 'DESC',
				'per_page'   => 20,
				'page'       => 1,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['mentor_id'] ) {
			$where[]  = 'mentor_id = %d';
			$params[] = (int) $args['mentor_id'];
		}
		if ( $args['program_id'] ) {
			$where[]  = 'program_id = %d';
			$params[] = (int) $args['program_id'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(client_name LIKE %s OR client_email LIKE %s OR reference LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		// Whitelist orderby/order.
		$allowed_orderby = array( 'created_at', 'booking_date', 'client_name', 'status', 'id' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = ( strtoupper( $args['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

		// Total.
		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$query_params   = $params;
		$query_params[] = $per_page;
		$query_params[] = $offset;

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$query_params
			)
		);

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Count bookings grouped by status (for the admin dashboard pills).
	 *
	 * @return array status => count
	 */
	public static function counts_by_status() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status" ); // phpcs:ignore WordPress.DB
		$out   = array( 'all' => 0 );
		foreach ( (array) $rows as $row ) {
			$out[ $row->status ] = (int) $row->c;
			$out['all']         += (int) $row->c;
		}
		return $out;
	}

	/**
	 * Generate a short human-friendly booking reference, e.g. GYD-7F3A9C.
	 *
	 * @return string
	 */
	public static function generate_reference() {
		return 'GYD-' . strtoupper( substr( wp_generate_password( 8, false, false ), 0, 6 ) );
	}
}
