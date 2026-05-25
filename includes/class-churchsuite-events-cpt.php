<?php
/**
 * Custom post type registration.
 *
 * @package ChurchSuiteEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT handler for ChurchSuite events.
 */
class ChurchSuite_Events_CPT {
	const POST_TYPE          = 'churchsuite_event';
	const META_START         = '_churchsuite_event_start';
	const META_START_TS      = '_churchsuite_event_start_ts';
	const META_END           = '_churchsuite_event_end';
	const META_LOCATION      = '_churchsuite_event_location';
	const META_CATEGORY      = '_churchsuite_event_category';
	const META_CATEGORY_COLOR = '_churchsuite_event_category_color';
	const META_REGISTRATION  = '_churchsuite_event_registration_url';
	const META_CHURCHSUITEID = '_churchsuite_event_id';
	const META_IMAGE_SOURCE  = '_churchsuite_event_image_source';

	/**
	 * Bootstrap hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'post_updated_messages', array( $this, 'messages' ) );
	}

	/**
	 * Register post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => __( 'ChurchSuite Events', 'churchsuite-events' ),
			'singular_name'         => __( 'ChurchSuite Event', 'churchsuite-events' ),
			'add_new'               => __( 'Add New', 'churchsuite-events' ),
			'add_new_item'          => __( 'Add New Event', 'churchsuite-events' ),
			'edit_item'             => __( 'Edit Event', 'churchsuite-events' ),
			'new_item'              => __( 'New Event', 'churchsuite-events' ),
			'view_item'             => __( 'View Event', 'churchsuite-events' ),
			'search_items'          => __( 'Search Events', 'churchsuite-events' ),
			'not_found'             => __( 'No events found.', 'churchsuite-events' ),
			'not_found_in_trash'    => __( 'No events found in Trash.', 'churchsuite-events' ),
			'all_items'             => __( 'All Events', 'churchsuite-events' ),
			'archives'              => __( 'Event Archives', 'churchsuite-events' ),
			'attributes'            => __( 'Event Attributes', 'churchsuite-events' ),
			'insert_into_item'      => __( 'Insert into event', 'churchsuite-events' ),
			'uploaded_to_this_item' => __( 'Uploaded to this event', 'churchsuite-events' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'has_archive'        => true,
			'show_in_rest'       => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-calendar-alt',
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
			'rewrite'            => array(
				'slug'       => 'church-events',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			// Prevent manual creation; events are synced from ChurchSuite.
			'capabilities'       => array(
				'create_posts' => 'do_not_allow',
			),
			'publicly_queryable' => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register meta fields for REST exposure.
	 *
	 * @return void
	 */
	public function register_meta() {
		$meta_fields = array(
			self::META_START        => array(
				'type'         => 'string',
				'description'  => __( 'Event start datetime', 'churchsuite-events' ),
				'show_in_rest' => true,
				'single'       => true,
			),
			self::META_START_TS     => array(
				'type'         => 'integer',
				'description'  => __( 'Event start timestamp', 'churchsuite-events' ),
				'show_in_rest' => true,
				'single'       => true,
			),
			self::META_END          => array(
				'type'         => 'string',
				'description'  => __( 'Event end datetime', 'churchsuite-events' ),
				'show_in_rest' => true,
				'single'       => true,
			),
			self::META_LOCATION     => array(
				'type'         => 'string',
				'description'  => __( 'Event location', 'churchsuite-events' ),
				'show_in_rest' => true,
				'single'       => true,
			),
			self::META_CATEGORY     => array(
				'type'         => 'string',
				'description'  => __( 'Event category', 'churchsuite-events' ),
				'show_in_rest' => true,
				'single'       => true,
			),
			self::META_CATEGORY_COLOR => array(
				'type'         => 'string',
				'description'  => __( 'Event category colour', 'churchsuite-events' ),
				'show_in_rest' => true,
				'single'       => true,
			),
			self::META_REGISTRATION => array(
				'type'         => 'string',
				'description'  => __( 'Registration URL', 'churchsuite-events' ),
				'show_in_rest' => true,
				'single'       => true,
			),
			self::META_CHURCHSUITEID => array(
				'type'         => 'string',
				'description'  => __( 'ChurchSuite event identifier', 'churchsuite-events' ),
				'show_in_rest' => true,
				'single'       => true,
			),
			self::META_IMAGE_SOURCE  => array(
				'type'         => 'string',
				'description'  => __( 'Original ChurchSuite image URL', 'churchsuite-events' ),
				'show_in_rest' => true,
				'single'       => true,
			),
		);

		foreach ( $meta_fields as $key => $args ) {
			register_post_meta( self::POST_TYPE, $key, $args );
		}
	}

	/**
	 * Custom admin messages.
	 *
	 * @param array $messages Core messages.
	 * @return array
	 */
	public function messages( $messages ) {
		$messages[ self::POST_TYPE ] = array(
			1  => __( 'Event updated.', 'churchsuite-events' ),
			4  => __( 'Event updated.', 'churchsuite-events' ),
			6  => __( 'Event published.', 'churchsuite-events' ),
			7  => __( 'Event saved.', 'churchsuite-events' ),
			10 => __( 'Event draft updated.', 'churchsuite-events' ),
		);

		return $messages;
	}
}
