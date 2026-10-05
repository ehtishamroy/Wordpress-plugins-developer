<?php
/**
 * Custom post types: Programmes and Mentors.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GYDB_CPT {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register both post types.
	 */
	public static function register() {
		self::register_programs();
		self::register_mentors();
	}

	/**
	 * Programme CPT — the nine GYD programmes.
	 */
	private static function register_programs() {
		$labels = array(
			'name'               => __( 'Programmes', 'gyd-booking' ),
			'singular_name'      => __( 'Programme', 'gyd-booking' ),
			'menu_name'          => __( 'GYD Programmes', 'gyd-booking' ),
			'add_new'            => __( 'Add Programme', 'gyd-booking' ),
			'add_new_item'       => __( 'Add New Programme', 'gyd-booking' ),
			'edit_item'          => __( 'Edit Programme', 'gyd-booking' ),
			'new_item'           => __( 'New Programme', 'gyd-booking' ),
			'view_item'          => __( 'View Programme', 'gyd-booking' ),
			'search_items'       => __( 'Search Programmes', 'gyd-booking' ),
			'not_found'          => __( 'No programmes found', 'gyd-booking' ),
			'not_found_in_trash' => __( 'No programmes found in Trash', 'gyd-booking' ),
			'all_items'          => __( 'All Programmes', 'gyd-booking' ),
		);

		register_post_type(
			GYDB_PROGRAM_CPT,
			array(
				'labels'              => $labels,
				'public'              => true,
				'show_in_menu'        => 'gyd-booking',
				'show_in_rest'        => true,
				'has_archive'         => false,
				'hierarchical'        => false,
				'menu_icon'           => 'dashicons-welcome-learn-more',
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
				'rewrite'             => array( 'slug' => 'programme' ),
				'exclude_from_search' => false,
			)
		);
	}

	/**
	 * Mentor CPT — one per mentor, each assigned to one or more programmes.
	 */
	private static function register_mentors() {
		$labels = array(
			'name'               => __( 'Mentors', 'gyd-booking' ),
			'singular_name'      => __( 'Mentor', 'gyd-booking' ),
			'menu_name'          => __( 'Mentors', 'gyd-booking' ),
			'add_new'            => __( 'Add Mentor', 'gyd-booking' ),
			'add_new_item'       => __( 'Add New Mentor', 'gyd-booking' ),
			'edit_item'          => __( 'Edit Mentor', 'gyd-booking' ),
			'new_item'           => __( 'New Mentor', 'gyd-booking' ),
			'view_item'          => __( 'View Mentor', 'gyd-booking' ),
			'search_items'       => __( 'Search Mentors', 'gyd-booking' ),
			'not_found'          => __( 'No mentors found', 'gyd-booking' ),
			'not_found_in_trash' => __( 'No mentors found in Trash', 'gyd-booking' ),
			'all_items'          => __( 'All Mentors', 'gyd-booking' ),
		);

		register_post_type(
			GYDB_MENTOR_CPT,
			array(
				'labels'       => $labels,
				'public'       => true,
				'show_in_menu' => 'gyd-booking',
				'show_in_rest' => true,
				'has_archive'  => false,
				'hierarchical' => false,
				'menu_icon'    => 'dashicons-groups',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
				'rewrite'      => array( 'slug' => 'mentor' ),
			)
		);
	}
}
