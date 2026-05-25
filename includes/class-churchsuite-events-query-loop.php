<?php
/**
 * Query Loop integration for upcoming ChurchSuite events.
 *
 * @package ChurchSuiteEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds an upcoming-events Query Loop variation and filtering.
 */
class ChurchSuite_Events_Query_Loop {
	/**
	 * Query Loop variation namespace.
	 */
	const VARIATION_NAMESPACE = 'churchsuite-events/upcoming';

	/**
	 * Custom query flag used by the block editor preview.
	 */
	const QUERY_FLAG = 'churchsuiteUpcoming';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'query_loop_block_query_vars', array( $this, 'filter_query_loop_vars' ), 10, 2 );
		add_filter( 'rest_' . ChurchSuite_Events_CPT::POST_TYPE . '_query', array( $this, 'filter_rest_query_vars' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Filter front-end Query Loop queries for the variation.
	 *
	 * @param array    $query Query vars.
	 * @param WP_Block $block Block instance.
	 * @return array
	 */
	public function filter_query_loop_vars( $query, $block ) {
		$block_query = isset( $block->context['query'] ) && is_array( $block->context['query'] ) ? $block->context['query'] : array();
		$namespace   = isset( $block->parsed_block['attrs']['namespace'] ) ? $block->parsed_block['attrs']['namespace'] : '';
		$is_event_query = ChurchSuite_Events_CPT::POST_TYPE === ( $query['post_type'] ?? '' )
			|| ChurchSuite_Events_CPT::POST_TYPE === ( $block_query['postType'] ?? '' );

		$query = $this->normalize_event_ordering( $query, $is_event_query );
		$query = $this->apply_category_filter( $query, $is_event_query );

		if ( ! $this->is_upcoming_query( $block_query, $namespace ) ) {
			return $query;
		}

		return $this->apply_upcoming_constraints( $query );
	}

	/**
	 * Filter REST preview queries for the variation.
	 *
	 * @param array           $args    REST query args.
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function filter_rest_query_vars( $args, $request ) {
		$namespace = $request->get_param( 'namespace' );
		$flag      = $request->get_param( self::QUERY_FLAG );
		$args      = $this->normalize_event_ordering( $args, true );
		$args      = $this->apply_category_filter( $args, true );

		if ( self::VARIATION_NAMESPACE !== $namespace && ! rest_sanitize_boolean( $flag ) ) {
			return $args;
		}

		return $this->apply_upcoming_constraints( $args );
	}

	/**
	 * Enqueue block editor integration.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets() {
		$handle = 'churchsuite-events-query-loop';
		$src    = trailingslashit( CHURCHSUITE_EVENTS_URL ) . 'assets/query-loop.js';

		wp_enqueue_script(
			$handle,
			$src,
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-dom-ready', 'wp-element', 'wp-hooks', 'wp-i18n' ),
			CHURCHSUITE_EVENTS_VERSION,
			true
		);

		wp_add_inline_script(
			$handle,
			'window.ChurchSuiteEventsQueryLoop = ' . wp_json_encode(
				array(
					'namespace' => self::VARIATION_NAMESPACE,
					'postType'  => ChurchSuite_Events_CPT::POST_TYPE,
					'flag'      => self::QUERY_FLAG,
				)
			),
			'before'
		);
	}

	/**
	 * Decide whether the current query should be constrained to upcoming events.
	 *
	 * @param array  $query     Block query settings.
	 * @param string $namespace Block namespace attribute.
	 * @return bool
	 */
	private function is_upcoming_query( $query, $namespace ) {
		if ( ! is_array( $query ) ) {
			return false;
		}

		if ( ChurchSuite_Events_CPT::POST_TYPE !== ( $query['postType'] ?? '' ) ) {
			return false;
		}

		if ( self::VARIATION_NAMESPACE === $namespace ) {
			return true;
		}

		return ! empty( $query[ self::QUERY_FLAG ] );
	}

	/**
	 * Apply date constraints so only events starting today or later are returned.
	 *
	 * @param array $query Query vars.
	 * @return array
	 */
	private function apply_upcoming_constraints( $query ) {
		$today = current_datetime()->setTime( 0, 0, 0 )->getTimestamp();
		$meta_query = isset( $query['meta_query'] ) && is_array( $query['meta_query'] ) ? $query['meta_query'] : array();

		$query['meta_key'] = ChurchSuite_Events_CPT::META_START_TS;
		$query['orderby']  = 'meta_value_num';
		$query['order']    = 'ASC';
		$meta_query[] = array(
			'key'     => ChurchSuite_Events_CPT::META_START_TS,
			'value'   => $today,
			'compare' => '>=',
			'type'    => 'NUMERIC',
		);
		$query['meta_query'] = $meta_query;

		return $query;
	}

	/**
	 * Apply the URL category filter to ChurchSuite event queries.
	 *
	 * @param array $query          Query vars.
	 * @param bool  $is_event_query Whether this is a ChurchSuite event query.
	 * @return array
	 */
	private function apply_category_filter( $query, $is_event_query ) {
		if ( ! $is_event_query ) {
			return $query;
		}

		$term = $this->get_requested_category_term();
		if ( ! $term ) {
			return $query;
		}

		$tax_query = isset( $query['tax_query'] ) && is_array( $query['tax_query'] ) ? $query['tax_query'] : array();
		$tax_query[] = array(
			'taxonomy' => ChurchSuite_Events_Taxonomy::TAXONOMY,
			'field'    => 'term_id',
			'terms'    => array( (int) $term->term_id ),
		);

		$query['tax_query'] = $tax_query;

		return $query;
	}

	/**
	 * Resolve the requested category slug to a valid term.
	 *
	 * @return WP_Term|null
	 */
	private function get_requested_category_term() {
		$key = '';
		if ( isset( $_GET[ ChurchSuite_Events_Category_Filter_Block::QUERY_PARAM ] ) ) {
			$key = ChurchSuite_Events_Category_Filter_Block::QUERY_PARAM;
		} elseif ( isset( $_GET[ ChurchSuite_Events_Category_Filter_Block::LEGACY_QUERY_PARAM ] ) ) {
			$key = ChurchSuite_Events_Category_Filter_Block::LEGACY_QUERY_PARAM;
		}

		if ( '' === $key ) {
			return null;
		}

		$value = wp_unslash( $_GET[ $key ] );
		if ( is_array( $value ) ) {
			return null;
		}

		$slug = sanitize_title( $value );
		if ( '' === $slug ) {
			return null;
		}

		$term = get_term_by( 'slug', $slug, ChurchSuite_Events_Taxonomy::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		return $term;
	}

	/**
	 * Make date ordering for event queries use the actual event start timestamp.
	 *
	 * @param array $query Query vars.
	 * @return array
	 */
	private function normalize_event_ordering( $query, $is_event_query = false ) {
		if ( ! $is_event_query && ChurchSuite_Events_CPT::POST_TYPE !== ( $query['post_type'] ?? '' ) ) {
			return $query;
		}

		if ( 'date' !== ( $query['orderby'] ?? '' ) ) {
			return $query;
		}

		$query['meta_key'] = ChurchSuite_Events_CPT::META_START_TS;
		$query['orderby']  = 'meta_value_num';

		return $query;
	}
}
