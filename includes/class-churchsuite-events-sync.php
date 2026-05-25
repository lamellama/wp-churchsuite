<?php
/**
 * Fetch and sync events from ChurchSuite JSON feed.
 *
 * @package ChurchSuiteEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles pulling events and writing to CPT.
 */
class ChurchSuite_Events_Sync {
	const TRANSIENT_KEY = 'churchsuite_events_feed';
	const CRON_HOOK     = 'churchsuite_events_cron_sync';

	/**
	 * Settings dependency.
	 *
	 * @var ChurchSuite_Events_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param ChurchSuite_Events_Settings $settings Settings helper.
	 */
	public function __construct( ChurchSuite_Events_Settings $settings ) {
		$this->settings = $settings;

		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
		add_action( 'init', array( $this, 'ensure_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'sync' ) );
		add_action( 'admin_post_churchsuite_events_sync', array( $this, 'handle_manual_sync' ) );
	}

	/**
	 * Add custom schedule interval that matches cache TTL.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedule( $schedules ) {
		$interval = $this->get_cache_ttl();

		$schedules['churchsuite_events_interval'] = array(
			'interval' => $interval,
			'display'  => sprintf(
				/* translators: %d seconds. */
				__( 'ChurchSuite Events (%d seconds)', 'churchsuite-events' ),
				$interval
			),
		);

		return $schedules;
	}

	/**
	 * Ensure cron is scheduled.
	 *
	 * @return void
	 */
	public function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'churchsuite_events_interval', self::CRON_HOOK );
		}
	}

	/**
	 * Handle manual sync form submission.
	 *
	 * @return void
	 */
	public function handle_manual_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'churchsuite-events' ) );
		}

		check_admin_referer( 'churchsuite_events_sync' );

		$result = $this->sync( true );

		$redirect = add_query_arg(
			array(
				'page'               => 'churchsuite-events',
				'churchsuite_synced' => is_wp_error( $result ) ? '0' : '1',
				'churchsuite_error'  => is_wp_error( $result ) ? rawurlencode( $result->get_error_message() ) : '',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Sync events from remote feed.
	 *
	 * @param bool $force Force bypassing transient cache.
	 * @return array|WP_Error Summary or error.
	 */
	public function sync( $force = false ) {
		$feed = $this->fetch_feed( $force );
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		$events = isset( $feed['events'] ) && is_array( $feed['events'] ) ? $feed['events'] : $feed;

		if ( empty( $events ) || ! is_array( $events ) ) {
			return new WP_Error( 'churchsuite_empty', __( 'No events returned from ChurchSuite.', 'churchsuite-events' ) );
		}

		$created = 0;
		$updated = 0;

		foreach ( $events as $event ) {
			$did_update = $this->upsert_event( $event );

			if ( true === $did_update ) {
				++$updated;
			} elseif ( is_numeric( $did_update ) ) {
				++$created;
			}
		}

		return array(
			'created' => $created,
			'updated' => $updated,
		);
	}

	/**
	 * Fetch feed with transient caching.
	 *
	 * @param bool $force Force bypassing cache.
	 * @return array|WP_Error
	 */
	private function fetch_feed( $force = false ) {
		$account_id = $this->settings->get( 'account_id' );

		if ( empty( $account_id ) ) {
			return new WP_Error( 'churchsuite_no_account', __( 'ChurchSuite account ID is not configured.', 'churchsuite-events' ) );
		}

		$cached = get_transient( self::TRANSIENT_KEY );
		if ( $cached && ! $force ) {
			return $cached;
		}

		$endpoint = sprintf( 'https://%s.churchsuite.com/embed/calendar/json', $account_id );
		$url      = add_query_arg(
			array(
				'account_id' => $account_id,
			),
			$endpoint
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'user-agent' => 'ChurchSuiteEventsPlugin/' . CHURCHSUITE_EVENTS_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'churchsuite_http_error',
				sprintf(
					/* translators: %d HTTP status code. */
					__( 'ChurchSuite request failed with status %d.', 'churchsuite-events' ),
					$code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data || json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'churchsuite_json_error', __( 'Could not decode ChurchSuite response.', 'churchsuite-events' ) );
		}

		set_transient( self::TRANSIENT_KEY, $data, $this->get_cache_ttl() );

		return $data;
	}

	/**
	 * Insert or update a single event.
	 *
	 * @param array $event Raw event data.
	 * @return bool|int True if updated, post ID if created, or false on failure.
	 */
	private function upsert_event( $event ) {
		$unique_id = $this->extract_value( $event, array( 'id', 'event_id', 'uuid' ) );

		$existing = $unique_id ? $this->find_existing_by_id( $unique_id ) : 0;

		$start     = $this->extract_value( $event, array( 'start_time', 'start', 'datetime_start', 'date_start', 'start_date' ) );
		$end       = $this->extract_value( $event, array( 'end_time', 'end', 'datetime_end', 'date_end', 'end_date' ) );
		$timestamp = $this->parse_datetime( $start );

		$excerpt = $this->extract_value( $event, array( 'summary', 'excerpt' ), '' );
		if ( '' === trim( (string) $excerpt ) && $existing ) {
			$existing_post = get_post( $existing );
			if ( $existing_post && ! empty( $existing_post->post_excerpt ) ) {
				$excerpt = $existing_post->post_excerpt;
			}
		}

		$postarr = array(
			'post_type'    => ChurchSuite_Events_CPT::POST_TYPE,
			'post_title'   => $this->extract_value( $event, array( 'name', 'title' ), __( 'Untitled event', 'churchsuite-events' ) ),
			'post_content' => $this->extract_value( $event, array( 'description', 'details', 'content' ), '' ),
			'post_excerpt' => $excerpt,
			'post_status'  => 'publish',
		);

		if ( $timestamp ) {
			$publish_timestamp = $this->get_publish_timestamp( $timestamp );
			$postarr['edit_date']     = true;
			$postarr['post_status']   = $publish_timestamp > time() ? 'future' : 'publish';
			$postarr['post_date']     = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $publish_timestamp ) );
			$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $publish_timestamp );
		}

		if ( $existing ) {
			$postarr['ID'] = $existing;
			wp_update_post( $postarr );
			$post_id = $existing;
		} else {
			$post_id = wp_insert_post( $postarr );
		}

		if ( is_wp_error( $post_id ) || 0 === $post_id ) {
			return false;
		}

		update_post_meta( $post_id, ChurchSuite_Events_CPT::META_START, $start );
		update_post_meta( $post_id, ChurchSuite_Events_CPT::META_START_TS, $timestamp ? $timestamp : '' );
		update_post_meta( $post_id, ChurchSuite_Events_CPT::META_END, $end );
		update_post_meta( $post_id, ChurchSuite_Events_CPT::META_LOCATION, $this->extract_value( $event, array( 'location', 'site', 'venue' ) ) );
		$category_value   = $this->extract_value( $event, array( 'category', 'category_name' ) );
		$category_parsed  = $this->parse_category_parts( $category_value );
		update_post_meta( $post_id, ChurchSuite_Events_CPT::META_CATEGORY, $category_parsed['name'] );
		update_post_meta( $post_id, ChurchSuite_Events_CPT::META_CATEGORY_COLOR, $category_parsed['color'] );
		update_post_meta( $post_id, ChurchSuite_Events_CPT::META_REGISTRATION, $this->extract_value( $event, array( 'signup_url', 'registration_url', 'url', 'link' ) ) );

		if ( $unique_id ) {
			update_post_meta( $post_id, ChurchSuite_Events_CPT::META_CHURCHSUITEID, $unique_id );
		}

		$image_url     = $this->extract_image_url( $event );
		$set_image     = $this->maybe_set_thumbnail( $post_id, $image_url );
		$category_term = $this->assign_category_term( $post_id, $category_parsed['name'], $category_parsed['color'] );

		// Fallback excerpt: if none set, use category excerpt when available.
		if ( '' === trim( (string) $postarr['post_excerpt'] ) && $category_term ) {
			$taxonomy = ChurchSuite_Events_Plugin::instance()->taxonomy();
			if ( $taxonomy ) {
				$category_excerpt = $taxonomy->get_excerpt( $category_term );
				if ( '' !== $category_excerpt ) {
					wp_update_post(
						array(
							'ID'           => $post_id,
							'post_excerpt' => $category_excerpt,
						)
					);
				}
			}
		}

		// Fallback: if no featured image and the category has an image, use it.
		if ( ! $set_image && ! has_post_thumbnail( $post_id ) && $category_term ) {
			$this->maybe_set_category_thumbnail( $post_id, $category_term );
		}

		return $existing ? true : $post_id;
	}

	/**
	 * Find existing event by ChurchSuite ID.
	 *
	 * @param string $unique_id Identifier.
	 * @return int Post ID or 0.
	 */
	private function find_existing_by_id( $unique_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => ChurchSuite_Events_CPT::POST_TYPE,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => ChurchSuite_Events_CPT::META_CHURCHSUITEID,
				'meta_value'     => $unique_id,
				'post_status'    => 'any',
			)
		);

		if ( ! empty( $query->posts ) ) {
			return (int) $query->posts[0];
		}

		return 0;
	}

	/**
	 * Extract first available value from candidate keys.
	 *
	 * @param array $source Event array.
	 * @param array $keys Candidate keys.
	 * @param mixed $default Default if none found.
	 * @return mixed
	 */
	private function extract_value( $source, $keys, $default = '' ) {
		foreach ( $keys as $key ) {
			if ( isset( $source[ $key ] ) && '' !== $source[ $key ] ) {
				return $source[ $key ];
			}
		}

		return $default;
	}

	/**
	 * Get cache TTL respecting minimum.
	 *
	 * @return int
	 */
	private function get_cache_ttl() {
		$ttl = (int) $this->settings->get( 'cache_ttl', HOUR_IN_SECONDS );

		return max( 300, $ttl );
	}

	/**
	 * Calculate when an event should become visible.
	 *
	 * @param int $event_timestamp Event start timestamp.
	 * @return int
	 */
	private function get_publish_timestamp( $event_timestamp ) {
		$lead_days = max( 0, (int) $this->settings->get( 'publish_lead_days', 14 ) );

		return max( 1, $event_timestamp - ( $lead_days * DAY_IN_SECONDS ) );
	}

	/**
	 * Try to convert a date string to timestamp.
	 *
	 * @param string $value Date string.
	 * @return int|false
	 */
	private function parse_datetime( $value ) {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return false;
		}

		$timestamp = strtotime( $value );

		return $timestamp ? $timestamp : false;
	}

	/**
	 * Assign taxonomy term for category if available.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $category_name Category name.
	 * @return void
	 */
	private function assign_category_term( $post_id, $category_name, $category_color = '' ) {
		$category_name = $this->normalize_scalar_or_join( $category_name );

		if ( '' === $category_name ) {
			return 0;
		}

		if ( ! class_exists( 'ChurchSuite_Events_Taxonomy' ) ) {
			return 0;
		}

		$taxonomy = ChurchSuite_Events_Taxonomy::TAXONOMY;

		$term_id = ChurchSuite_Events_Plugin::instance()->taxonomy()->ensure_category( $category_name );
		if ( is_wp_error( $term_id ) || ! $term_id ) {
			return 0;
		}

		wp_set_object_terms( $post_id, array( (int) $term_id ), $taxonomy, false );

		// Persist colour to term meta for reuse.
		if ( '' !== $category_color ) {
			ChurchSuite_Events_Plugin::instance()->taxonomy()->set_color( (int) $term_id, $category_color );
		}

		return (int) $term_id;
	}

	/**
	 * Extract an image URL from a variety of possible keys.
	 *
	 * @param array $event Event payload.
	 * @return string
	 */
	private function extract_image_url( $event ) {
		if ( isset( $event['image'] ) && is_array( $event['image'] ) ) {
			if ( ! empty( $event['image']['src'] ) && filter_var( $event['image']['src'], FILTER_VALIDATE_URL ) ) {
				return $event['image']['src'];
			}
			if ( ! empty( $event['image']['url'] ) && filter_var( $event['image']['url'], FILTER_VALIDATE_URL ) ) {
				return $event['image']['url'];
			}
		}

		$candidates = array( 'image', 'image_url', 'image_src', 'imageThumb', 'imagethumb' );
		foreach ( $candidates as $key ) {
			if ( ! empty( $event[ $key ] ) && is_string( $event[ $key ] ) && filter_var( $event[ $key ], FILTER_VALIDATE_URL ) ) {
				return $event[ $key ];
			}
		}

		return '';
	}

	/**
	 * Set featured image if available and not already set to this source.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $image_url Image URL.
	 * @return void
	 */
	private function maybe_set_thumbnail( $post_id, $image_url ) {
		if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$current_source = get_post_meta( $post_id, ChurchSuite_Events_CPT::META_IMAGE_SOURCE, true );
		$current_thumb  = get_post_thumbnail_id( $post_id );

		// If already set from same source, skip.
		if ( $current_thumb && $current_source === $image_url ) {
			return true;
		}

		// Do not override existing thumbnails set manually without source match.
		if ( $current_thumb && $current_source !== $image_url ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return false;
		}

		set_post_thumbnail( $post_id, $attachment_id );
		update_post_meta( $post_id, ChurchSuite_Events_CPT::META_IMAGE_SOURCE, $image_url );

		return true;
	}

	/**
	 * Normalize a value to a scalar string, or join array values.
	 *
	 * @param mixed $value Value from feed.
	 * @return string
	 */
	private function normalize_scalar_or_join( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$item = trim( (string) $item );
					if ( '' !== $item ) {
						$clean[] = $item;
					}
				}
			}
			return implode( ', ', $clean );
		}

		if ( is_scalar( $value ) ) {
			return trim( (string) $value );
		}

		return '';
	}

	/**
	 * Parse category information into name and colour.
	 *
	 * @param mixed $value Raw category value.
	 * @return array{name:string,color:string}
	 */
	private function parse_category_parts( $value ) {
		$parts = array();

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$item = trim( (string) $item );
					if ( '' !== $item ) {
						$parts[] = $item;
					}
				}
			}
		} elseif ( is_scalar( $value ) ) {
			$string = trim( (string) $value );
			if ( '' !== $string ) {
				// Split by comma if combined.
				$parts = array_map( 'trim', explode( ',', $string ) );
				$parts = array_filter( $parts, 'strlen' );
			}
		}

		$name  = '';
		$color = '';

		if ( ! empty( $parts ) ) {
			// Detect colour as last item if hex-like.
			$last = end( $parts );
			if ( preg_match( '/^#?[0-9a-fA-F]{3,6}$/', $last ) ) {
				$color = '#' . ltrim( $last, '#' );
				array_pop( $parts );
			}

			// Find first non-numeric string as name; otherwise fallback to first.
			foreach ( $parts as $part ) {
				if ( ! is_numeric( $part ) ) {
					$name = $part;
					break;
				}
			}

			if ( '' === $name && ! empty( $parts ) ) {
				$name = (string) $parts[0];
			}
		}

		return array(
			'name'  => $name,
			'color' => $color,
		);
	}

	/**
	 * If a category has an image, use it as the featured image when none is set.
	 *
	 * @param int $post_id Post ID.
	 * @param int $term_id Term ID.
	 * @return void
	 */
	private function maybe_set_category_thumbnail( $post_id, $term_id ) {
		if ( $term_id <= 0 ) {
			return;
		}

		$taxonomy = ChurchSuite_Events_Plugin::instance()->taxonomy();
		if ( ! $taxonomy ) {
			return;
		}

		$image_id = $taxonomy->get_image_id( $term_id );
		if ( ! $image_id ) {
			return;
		}

		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		set_post_thumbnail( $post_id, $image_id );
	}
}
