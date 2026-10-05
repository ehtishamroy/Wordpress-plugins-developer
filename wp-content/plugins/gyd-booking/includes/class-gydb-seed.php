<?php
/**
 * One-time data seeding: the nine real GYD programmes, sample mentors, and a
 * booking page. Safe to run repeatedly — it never duplicates existing data.
 *
 * @package GYD_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GYDB_Seed {

	/**
	 * Run the full seed.
	 */
	public static function run() {
		$program_ids = self::seed_programs();
		self::seed_mentors( $program_ids );
		self::seed_booking_page();
		update_option( 'gydb_seeded', GYDB_VERSION );
	}

	/**
	 * The nine real programmes (name, category, tagline, short + long copy).
	 *
	 * @return array
	 */
	private static function program_data() {
		return array(
			array(
				'slug'     => 'global-youth-development',
				'title'    => 'Global Youth Development',
				'number'   => 1,
				'category' => 'Non-Formal Education & Personal Development',
				'tagline'  => 'Learn. Grow. Lead.',
				'short'    => 'Learn. Grow. Lead. One-to-one mentoring that builds confidence, life skills and a personal plan for what comes next — outside the classroom, at your own pace.',
				'content'  => 'Global Youth Development is our flagship personal-development programme. Through non-formal education and one-to-one mentoring, young people build the confidence, communication and life skills that formal schooling often leaves out — setting personal goals and taking real steps towards them.',
			),
			array(
				'slug'     => 'global-youth-innovation',
				'title'    => 'Global Youth Innovation',
				'number'   => 2,
				'category' => 'Innovation, Technology & Entrepreneurship',
				'tagline'  => 'Imagine. Create. Transform.',
				'short'    => 'Imagine. Create. Transform. Work with mentors in technology and entrepreneurship to turn an idea into a prototype, a project or a venture.',
				'content'  => 'Global Youth Innovation helps young people turn ideas into reality. Mentors from technology, design and entrepreneurship guide you from first concept through prototyping, validation and launch — whether you are building an app, a social enterprise or a creative project.',
			),
			array(
				'slug'     => 'global-youth-voices-rights',
				'title'    => 'Global Youth Voices & Rights',
				'number'   => 3,
				'category' => 'Civic & Democratic Participation',
				'tagline'  => 'Speak. Listen. Be Heard.',
				'short'    => 'Speak. Listen. Be Heard. Learn how to take part in civic life, understand your rights, and make your voice count in the decisions that affect you.',
				'content'  => 'Global Youth Voices & Rights supports young people to participate in civic and democratic life. Mentors help you understand your rights, develop advocacy and public-speaking skills, and engage confidently with the institutions and decisions that shape your community.',
			),
			array(
				'slug'     => 'global-youth-media',
				'title'    => 'Global Youth Media',
				'number'   => 4,
				'category' => 'Critical Thinking & Media Literacy',
				'tagline'  => 'Question. Check. Think.',
				'short'    => 'Question. Check. Think. Mentor sessions help young people navigate today\'s information environment — spotting misinformation, evaluating sources, and using digital media responsibly.',
				'content'  => 'Global Youth Media builds critical thinking and media literacy. Mentors help young people navigate today\'s information environment — spotting misinformation, fact-checking and evaluating sources, and creating and using digital media responsibly.',
			),
			array(
				'slug'     => 'global-youth-culture',
				'title'    => 'Global Youth Culture',
				'number'   => 5,
				'category' => 'Culture, Identity & Heritage',
				'tagline'  => 'Know. Connect. Celebrate.',
				'short'    => 'Know. Connect. Celebrate. Explore identity, heritage and culture with mentors who help you understand where you come from and share it with pride.',
				'content'  => 'Global Youth Culture celebrates identity, heritage and belonging. Mentors help young people explore their own culture and that of others, build intercultural understanding, and take pride in the stories and traditions that shape who they are.',
			),
			array(
				'slug'     => 'global-youth-exchange',
				'title'    => 'Global Youth Exchange',
				'number'   => 6,
				'category' => 'International Learning & Exchange',
				'tagline'  => 'Connect. Discover. Learn.',
				'short'    => 'Connect. Discover. Learn. Prepare for international learning and exchange — new countries, new perspectives, and the skills to make the most of them.',
				'content'  => 'Global Youth Exchange opens doors to international learning. Mentors prepare young people for exchanges and cross-border collaboration — building the language, cultural and practical skills to thrive in new environments and bring fresh perspectives home.',
			),
			array(
				'slug'     => 'global-youth-community',
				'title'    => 'Global Youth Community',
				'number'   => 7,
				'category' => 'Community Participation',
				'tagline'  => 'Connect. Contribute. Create Change.',
				'short'    => 'Connect. Contribute. Create Change. Get involved in your community with mentors who help you design and deliver projects that make a real difference.',
				'content'  => 'Global Youth Community turns good intentions into local impact. Mentors help young people design and deliver community projects — from first idea to real-world delivery — building the teamwork, planning and leadership skills that create lasting change.',
			),
			array(
				'slug'     => 'global-youth-ambassador',
				'title'    => 'Global Youth Ambassador',
				'number'   => 8,
				'category' => 'Leadership, Representation & Global Networks',
				'tagline'  => 'Represent. Connect. Inspire. Lead.',
				'short'    => 'Represent. Connect. Inspire. Lead. Step into a leadership role, represent your peers, and join a global network of young changemakers.',
				'content'  => 'Global Youth Ambassador develops the next generation of youth leaders. Mentors support ambassadors to represent their peers, build advanced leadership and networking skills, and connect to a global community of young changemakers.',
			),
			array(
				'slug'     => 'erasmus-plus-programme',
				'title'    => 'Erasmus+ Programme',
				'number'   => 9,
				'category' => 'EU-Funded Youth Mobility',
				'tagline'  => 'Travel. Learn. Grow.',
				'short'    => 'Travel. Learn. Grow. Our newest programme — EU-funded mobility opportunities with mentor support to help you apply, prepare and make the most of the experience.',
				'content'  => 'The Erasmus+ Programme connects young people to EU-funded mobility and learning opportunities. Mentors guide you through eligibility, applications and preparation so you can travel, learn and grow with confidence.',
				'is_new'   => true,
			),
		);
	}

	/**
	 * Create programmes that do not already exist (matched by slug).
	 *
	 * @return array slug => post ID
	 */
	private static function seed_programs() {
		$ids = array();

		foreach ( self::program_data() as $data ) {
			$existing = get_page_by_path( $data['slug'], OBJECT, GYDB_PROGRAM_CPT );
			if ( $existing ) {
				$ids[ $data['slug'] ] = $existing->ID;
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => GYDB_PROGRAM_CPT,
					'post_status'  => 'publish',
					'post_title'   => $data['title'],
					'post_name'    => $data['slug'],
					'post_content' => $data['content'],
					'post_excerpt' => $data['short'],
					'menu_order'   => $data['number'],
				)
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_gyd_number', $data['number'] );
				update_post_meta( $post_id, '_gyd_category', $data['category'] );
				update_post_meta( $post_id, '_gyd_tagline', $data['tagline'] );
				update_post_meta( $post_id, '_gyd_short_desc', $data['short'] );
				if ( ! empty( $data['is_new'] ) ) {
					update_post_meta( $post_id, '_gyd_is_new', '1' );
				}
				$ids[ $data['slug'] ] = $post_id;
			}
		}

		return $ids;
	}

	/**
	 * Sample mentors so the booking page works out of the box. These are
	 * clearly flagged as samples — the client edits names/photos/bios or
	 * deletes them and adds real mentors. Only seeded once (if none exist).
	 *
	 * @param array $program_ids slug => ID map.
	 */
	private static function seed_mentors( array $program_ids ) {
		// Only seed if there are no mentors at all.
		$existing = get_posts(
			array(
				'post_type'      => GYDB_MENTOR_CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $existing ) ) {
			return;
		}

		$settings = GYDB_Helpers::default_settings();

		$mentors = array(
			// Global Youth Development.
			array(
				'name'     => 'Dr. Jonah Clarke',
				'role'     => 'Youth Development Lead',
				'bio'      => '12+ years in non-formal education and youth coaching. Helps young people set goals, build confidence and plan their next step.',
				'programs' => array( 'global-youth-development' ),
			),
			array(
				'name'     => 'Amira Hassan',
				'role'     => 'Life Skills Mentor',
				'bio'      => 'Facilitator specialising in communication, resilience and personal leadership for ages 14–25.',
				'programs' => array( 'global-youth-development' ),
			),
			array(
				'name'     => 'Henri Dubois',
				'role'     => 'Personal Growth Coach',
				'bio'      => 'Certified coach supporting young people through transitions — school, work and everything in between.',
				'programs' => array( 'global-youth-development' ),
			),
			// Global Youth Innovation.
			array(
				'name'     => 'Dr. Priya Nair',
				'role'     => 'Innovation & Technology Mentor',
				'bio'      => 'Product lead and lecturer in design thinking. Guides young innovators from idea to working prototype.',
				'programs' => array( 'global-youth-innovation' ),
			),
			array(
				'name'     => 'Emir Yilmaz',
				'role'     => 'Entrepreneurship Mentor',
				'bio'      => 'Founder and startup advisor. Specialises in validation, pitching and early-stage social enterprise.',
				'programs' => array( 'global-youth-innovation' ),
			),
			// Global Youth Media.
			array(
				'name'     => 'Sarah Bennett',
				'role'     => 'Media & Journalism Lead',
				'bio'      => '15+ years in investigative journalism across the BBC and Reuters. Specialises in media literacy workshops for ages 16–25.',
				'programs' => array( 'global-youth-media' ),
			),
			array(
				'name'     => 'Daniel Okafor',
				'role'     => 'Digital Rights Advocate',
				'bio'      => 'Policy adviser at a UK media literacy charity. Runs small-group sessions on misinformation and online safety.',
				'programs' => array( 'global-youth-media' ),
			),
			array(
				'name'     => 'Lucia Romano',
				'role'     => 'Youth Media Trainer',
				'bio'      => 'Independent trainer with the National Union of Journalists. Focus areas: fact-checking, source verification and bias.',
				'programs' => array( 'global-youth-media' ),
			),
		);

		$order = 1;
		foreach ( $mentors as $mentor ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => GYDB_MENTOR_CPT,
					'post_status' => 'publish',
					'post_title'  => $mentor['name'],
					'menu_order'  => $order++,
				)
			);

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			// Map program slugs to IDs.
			$pids = array();
			foreach ( $mentor['programs'] as $slug ) {
				if ( isset( $program_ids[ $slug ] ) ) {
					$pids[] = (int) $program_ids[ $slug ];
				}
			}

			update_post_meta( $post_id, '_gyd_role', $mentor['role'] );
			update_post_meta( $post_id, '_gyd_bio', $mentor['bio'] );
			update_post_meta( $post_id, '_gyd_programs', $pids );
			update_post_meta( $post_id, '_gyd_photo_id', 0 );
			update_post_meta( $post_id, '_gyd_avail_days', $settings['default_days'] );
			update_post_meta( $post_id, '_gyd_avail_start', $settings['default_start'] );
			update_post_meta( $post_id, '_gyd_avail_end', $settings['default_end'] );
			update_post_meta( $post_id, '_gyd_slot_len', $settings['default_slot_len'] );
			update_post_meta( $post_id, '_gyd_sample', '1' );
		}
	}

	/**
	 * Create a "Book a Session" page containing the booking shortcode, and save
	 * its ID in settings so Book Now buttons point to it immediately.
	 */
	private static function seed_booking_page() {
		$settings = get_option( GYDB_Helpers::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Already configured and the page still exists? Leave it.
		if ( ! empty( $settings['booking_page_id'] ) && get_post( (int) $settings['booking_page_id'] ) ) {
			return;
		}

		// Reuse an existing page that already has the shortcode.
		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => '[gyd_booking',
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			$page_id = (int) $existing[0];
		} else {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Book a Session', 'gyd-booking' ),
					'post_name'    => 'book',
					'post_content' => '[gyd_booking]',
				)
			);
		}

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$settings = wp_parse_args( $settings, GYDB_Helpers::default_settings() );
			$settings['booking_page_id'] = (int) $page_id;
			update_option( GYDB_Helpers::OPTION_KEY, $settings );
		}
	}
}
