<?php
/**
 * Weekly calendar block for ChurchSuite events.
 *
 * @package ChurchSuiteEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the weekly calendar block.
 */
class ChurchSuite_Events_Calendar_Block {
	const BLOCK_NAME = 'churchsuite-events/calendar';
	const REST_NAMESPACE = 'churchsuite-events/v1';
	const REST_ROUTE = '/calendar';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register block assets and dynamic render callback.
	 *
	 * @return void
	 */
	public function register_block() {
		$editor_handle = 'churchsuite-events-calendar-editor';
		$script_handle = 'churchsuite-events-calendar';
		$style_handle  = 'churchsuite-events-calendar';

		wp_register_script(
			$editor_handle,
			trailingslashit( CHURCHSUITE_EVENTS_URL ) . 'assets/calendar-block.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			CHURCHSUITE_EVENTS_VERSION,
			true
		);

		wp_register_script(
			$script_handle,
			trailingslashit( CHURCHSUITE_EVENTS_URL ) . 'assets/calendar-view.js',
			array(),
			CHURCHSUITE_EVENTS_VERSION,
			true
		);

		wp_register_style(
			$style_handle,
			trailingslashit( CHURCHSUITE_EVENTS_URL ) . 'assets/calendar.css',
			array(),
			CHURCHSUITE_EVENTS_VERSION
		);

		register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'     => 2,
				'editor_script'   => $editor_handle,
				'script'          => $script_handle,
				'editor_style'    => $style_handle,
				'style'           => $style_handle,
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Register public REST endpoint for week navigation.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_calendar' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'week_start' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);
	}

	/**
	 * Render the calendar block.
	 *
	 * @return string
	 */
	public function render_block() {
		$week_start = $this->get_week_start();
		$category   = $this->get_selected_category_slug();
		$data       = $this->get_calendar_data( $week_start, $category );

		return $this->render_calendar_shell( $data, $category );
	}

	/**
	 * REST callback for a calendar week.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function rest_get_calendar( $request ) {
		$week_start = $this->get_week_start( $request->get_param( 'week_start' ) );
		$category   = sanitize_title( (string) $request->get_param( 'category' ) );
		$data       = $this->get_calendar_data( $week_start, $category );

		return rest_ensure_response(
			array(
				'weekStart' => $data['week_start'],
				'weekLabel' => $data['week_label'],
				'html'      => $this->render_week_grid( $data ),
			)
		);
	}

	/**
	 * Get all data needed to render a week.
	 *
	 * @param DateTimeImmutable $week_start Week start date.
	 * @param string            $category   Optional category slug.
	 * @return array
	 */
	private function get_calendar_data( DateTimeImmutable $week_start, $category = '' ) {
		$timezone = wp_timezone();
		$week_end = $week_start->modify( '+6 days' )->setTime( 23, 59, 59 );
		$days     = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$day = $week_start->modify( '+' . $i . ' days' );
			$days[ $day->format( 'Y-m-d' ) ] = array(
				'date'      => $day->format( 'Y-m-d' ),
				'label'     => wp_date( 'D', $day->getTimestamp(), $timezone ),
				'fullLabel' => wp_date( get_option( 'date_format' ), $day->getTimestamp(), $timezone ),
				'dayNumber' => wp_date( 'j', $day->getTimestamp(), $timezone ),
				'events'    => array(),
			);
		}

		$events = $this->query_events( $week_start->getTimestamp(), $week_end->getTimestamp(), $category );

		foreach ( $events as $event ) {
			$event_date = wp_date( 'Y-m-d', $event['timestamp'], $timezone );
			if ( isset( $days[ $event_date ] ) ) {
				$days[ $event_date ]['events'][] = $event;
			}
		}

		return array(
			'week_start' => $week_start->format( 'Y-m-d' ),
			'week_label' => sprintf(
				/* translators: 1: week start date, 2: week end date. */
				__( '%1$s to %2$s', 'churchsuite-events' ),
				wp_date( 'j M', $week_start->getTimestamp(), $timezone ),
				wp_date( 'j M Y', $week_end->getTimestamp(), $timezone )
			),
			'days'       => array_values( $days ),
		);
	}

	/**
	 * Query events in the selected week.
	 *
	 * @param int    $start_ts Week start timestamp.
	 * @param int    $end_ts   Week end timestamp.
	 * @param string $category Optional category slug.
	 * @return array
	 */
	private function query_events( $start_ts, $end_ts, $category = '' ) {
		$args = array(
			'post_type'              => ChurchSuite_Events_CPT::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'orderby'                => 'meta_value_num',
			'order'                  => 'ASC',
			'meta_key'               => ChurchSuite_Events_CPT::META_START_TS,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			'meta_query'             => array(
				array(
					'key'     => ChurchSuite_Events_CPT::META_START_TS,
					'value'   => array( $start_ts, $end_ts ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				),
			),
		);

		if ( '' !== $category ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => ChurchSuite_Events_Taxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( $category ),
				),
			);
		}

		$query  = new WP_Query( $args );
		$events = array();

		foreach ( $query->posts as $post ) {
			$events[] = $this->format_event( $post );
		}

		wp_reset_postdata();

		return $events;
	}

	/**
	 * Format a post for the calendar renderer.
	 *
	 * @param WP_Post $post Event post.
	 * @return array
	 */
	private function format_event( WP_Post $post ) {
		$post_id   = (int) $post->ID;
		$timestamp = (int) get_post_meta( $post_id, ChurchSuite_Events_CPT::META_START_TS, true );
		$location  = $this->normalize_meta_value( get_post_meta( $post_id, ChurchSuite_Events_CPT::META_LOCATION, true ) );
		$category  = $this->get_event_category( $post_id );

		return array(
			'id'        => $post_id,
			'title'     => get_the_title( $post ),
			'url'       => get_permalink( $post ),
			'timestamp' => $timestamp,
			'time'      => $timestamp > 0 ? wp_date( get_option( 'time_format' ), $timestamp ) : '',
			'location'  => $location,
			'category'  => $category['name'],
			'color'     => $category['color'],
		);
	}

	/**
	 * Render the block shell.
	 *
	 * @param array  $data     Calendar data.
	 * @param string $category Optional category slug.
	 * @return string
	 */
	private function render_calendar_shell( $data, $category = '' ) {
		$current_week_start = $this->get_week_start()->format( 'Y-m-d' );

		ob_start();
		?>
		<div class="churchsuite-events-calendar" data-rest-url="<?php echo esc_url( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ); ?>" data-week-start="<?php echo esc_attr( $data['week_start'] ); ?>" data-current-week-start="<?php echo esc_attr( $current_week_start ); ?>" data-category="<?php echo esc_attr( $category ); ?>">
			<div class="churchsuite-events-calendar__toolbar">
				<button type="button" class="churchsuite-events-calendar__nav" data-calendar-action="previous"><?php esc_html_e( 'Previous week', 'churchsuite-events' ); ?></button>
				<div class="churchsuite-events-calendar__heading" aria-live="polite">
					<span class="churchsuite-events-calendar__title"><?php esc_html_e( 'Events this week', 'churchsuite-events' ); ?></span>
					<span class="churchsuite-events-calendar__range" data-calendar-range><?php echo esc_html( $data['week_label'] ); ?></span>
				</div>
				<div class="churchsuite-events-calendar__actions">
					<button type="button" class="churchsuite-events-calendar__today" data-calendar-action="today"><?php esc_html_e( 'This week', 'churchsuite-events' ); ?></button>
					<button type="button" class="churchsuite-events-calendar__nav" data-calendar-action="next"><?php esc_html_e( 'Next week', 'churchsuite-events' ); ?></button>
				</div>
			</div>
			<div class="churchsuite-events-calendar__status" data-calendar-status role="status" aria-live="polite"></div>
			<div class="churchsuite-events-calendar__grid-wrap" data-calendar-grid>
				<?php echo $this->render_week_grid( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render the seven-day grid.
	 *
	 * @param array $data Calendar data.
	 * @return string
	 */
	private function render_week_grid( $data ) {
		ob_start();
		?>
		<div class="churchsuite-events-calendar__grid">
			<?php foreach ( $data['days'] as $day ) : ?>
				<section class="churchsuite-events-calendar__day" aria-label="<?php echo esc_attr( $day['fullLabel'] ); ?>">
					<header class="churchsuite-events-calendar__day-header">
						<span class="churchsuite-events-calendar__day-name"><?php echo esc_html( $day['label'] ); ?></span>
						<span class="churchsuite-events-calendar__day-number"><?php echo esc_html( $day['dayNumber'] ); ?></span>
					</header>
					<div class="churchsuite-events-calendar__events">
						<?php if ( empty( $day['events'] ) ) : ?>
							<p class="churchsuite-events-calendar__empty"><?php esc_html_e( 'No events', 'churchsuite-events' ); ?></p>
						<?php else : ?>
							<?php foreach ( $day['events'] as $event ) : ?>
								<?php echo $this->render_event_card( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render one event card.
	 *
	 * @param array $event Event data.
	 * @return string
	 */
	private function render_event_card( $event ) {
		$style = '';
		if ( ! empty( $event['color'] ) ) {
			$style = sprintf( ' style="--churchsuite-event-color:%s;"', esc_attr( $event['color'] ) );
		}

		ob_start();
		?>
		<article class="churchsuite-events-calendar__event"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( ! empty( $event['time'] ) ) : ?>
				<time class="churchsuite-events-calendar__event-time" datetime="<?php echo esc_attr( wp_date( DATE_W3C, (int) $event['timestamp'] ) ); ?>"><?php echo esc_html( $event['time'] ); ?></time>
			<?php endif; ?>
			<h3 class="churchsuite-events-calendar__event-title">
				<a href="<?php echo esc_url( $event['url'] ); ?>"><?php echo esc_html( $event['title'] ); ?></a>
			</h3>
			<?php if ( ! empty( $event['location'] ) ) : ?>
				<p class="churchsuite-events-calendar__event-location"><?php echo esc_html( $event['location'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $event['category'] ) ) : ?>
				<p class="churchsuite-events-calendar__event-category"><?php echo esc_html( $event['category'] ); ?></p>
			<?php endif; ?>
		</article>
		<?php

		return ob_get_clean();
	}

	/**
	 * Calculate the start date for a requested/current week.
	 *
	 * @param string $date Optional ISO date.
	 * @return DateTimeImmutable
	 */
	private function get_week_start( $date = '' ) {
		$timezone = wp_timezone();
		$date     = is_scalar( $date ) ? trim( (string) $date ) : '';

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$base = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );
			$base = $base instanceof DateTimeImmutable ? $base : new DateTimeImmutable( 'now', $timezone );
		} else {
			$base = new DateTimeImmutable( 'now', $timezone );
		}

		$base          = $base->setTime( 0, 0, 0 );
		$start_of_week = (int) get_option( 'start_of_week', 1 );
		$current_day   = (int) $base->format( 'w' );
		$diff          = ( $current_day - $start_of_week + 7 ) % 7;

		return $base->modify( '-' . $diff . ' days' );
	}

	/**
	 * Get selected category slug from the current URL.
	 *
	 * @return string
	 */
	private function get_selected_category_slug() {
		$key = '';
		if ( isset( $_GET[ ChurchSuite_Events_Category_Filter_Block::QUERY_PARAM ] ) ) {
			$key = ChurchSuite_Events_Category_Filter_Block::QUERY_PARAM;
		} elseif ( isset( $_GET[ ChurchSuite_Events_Category_Filter_Block::LEGACY_QUERY_PARAM ] ) ) {
			$key = ChurchSuite_Events_Category_Filter_Block::LEGACY_QUERY_PARAM;
		}

		if ( '' === $key || is_array( $_GET[ $key ] ) ) {
			return '';
		}

		return sanitize_title( wp_unslash( $_GET[ $key ] ) );
	}

	/**
	 * Get event category name and colour.
	 *
	 * @param int $post_id Event post ID.
	 * @return array{name:string,color:string}
	 */
	private function get_event_category( $post_id ) {
		$terms = wp_get_object_terms( $post_id, ChurchSuite_Events_Taxonomy::TAXONOMY );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array(
				'name'  => $this->normalize_meta_value( get_post_meta( $post_id, ChurchSuite_Events_CPT::META_CATEGORY, true ) ),
				'color' => $this->normalize_color( get_post_meta( $post_id, ChurchSuite_Events_CPT::META_CATEGORY_COLOR, true ) ),
			);
		}

		$term  = $terms[0];
		$color = get_term_meta( $term->term_id, ChurchSuite_Events_Taxonomy::META_COLOR, true );
		if ( '' === $color ) {
			$color = get_post_meta( $post_id, ChurchSuite_Events_CPT::META_CATEGORY_COLOR, true );
		}

		return array(
			'name'  => $term->name,
			'color' => $this->normalize_color( $color ),
		);
	}

	/**
	 * Normalize scalar or array meta values for display.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	private function normalize_meta_value( $value ) {
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
	 * Normalize a hex colour value.
	 *
	 * @param mixed $color Raw colour.
	 * @return string
	 */
	private function normalize_color( $color ) {
		if ( ! is_scalar( $color ) ) {
			return '';
		}

		$color = trim( (string) $color );
		if ( preg_match( '/^#?[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color ) ) {
			return '#' . ltrim( $color, '#' );
		}

		return '';
	}
}
