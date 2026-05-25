<?php
/**
 * Front-end templates and patterns.
 *
 * @package ChurchSuiteEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register block patterns and provide fallback templates.
 */
class ChurchSuite_Events_Templates {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_pattern' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_filter( 'post_thumbnail_id', array( $this, 'maybe_use_category_image' ), 10, 2 );
		add_filter( 'get_the_excerpt', array( $this, 'maybe_use_category_excerpt' ), 10, 2 );
		add_filter( 'single_template', array( $this, 'single_template' ) );
		add_filter( 'archive_template', array( $this, 'archive_template' ) );
	}

	/**
	 * Register helper shortcodes for meta output.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode(
			'churchsuite_event_meta',
			function( $atts ) {
				$atts = shortcode_atts(
					array(
						'key'  => '',
						'link' => '0',
					),
					$atts,
					'churchsuite_event_meta'
				);

				if ( empty( $atts['key'] ) ) {
					return '';
				}

				$post_id = get_the_ID();
				if ( ChurchSuite_Events_CPT::POST_TYPE !== get_post_type( $post_id ) ) {
					return '';
				}

				$value = get_post_meta( $post_id, $atts['key'], true );

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
					$value = implode( ', ', $clean );
				} elseif ( is_scalar( $value ) ) {
					$value = (string) $value;
				} else {
					$value = '';
				}

				if ( '' === $value ) {
					return '';
				}

				if ( '1' === $atts['link'] && filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return sprintf(
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
						esc_url( $value ),
						esc_html__( 'Sign up', 'churchsuite-events' )
					);
				}

				return esc_html( $value );
			}
		);

		add_shortcode(
			'churchsuite_event_category',
			function( $atts ) {
				$atts = shortcode_atts(
					array(
						'field' => 'description', // description|name|color|image.
						'size'  => 'full',
					),
					$atts,
					'churchsuite_event_category'
				);

				$post_id = get_the_ID();
				if ( ChurchSuite_Events_CPT::POST_TYPE !== get_post_type( $post_id ) ) {
					return '';
				}

				$terms = wp_get_object_terms( $post_id, ChurchSuite_Events_Taxonomy::TAXONOMY );
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					return '';
				}

				$term = $terms[0];
				$field = $atts['field'];

				switch ( $field ) {
					case 'name':
						return esc_html( $term->name );
					case 'color':
						$taxonomy = ChurchSuite_Events_Plugin::instance()->taxonomy();
						$color    = $taxonomy ? get_term_meta( $term->term_id, ChurchSuite_Events_Taxonomy::META_COLOR, true ) : '';
						return $color ? esc_html( $color ) : '';
					case 'image':
						$taxonomy = ChurchSuite_Events_Plugin::instance()->taxonomy();
						$url      = $taxonomy ? $taxonomy->get_image_url( $term->term_id, $atts['size'] ) : '';
						return $url ? esc_url( $url ) : '';
					case 'image_tag':
						// Prefer featured image; otherwise fall back to category image.
						if ( has_post_thumbnail( $post_id ) ) {
							return get_the_post_thumbnail(
								$post_id,
								$atts['size'],
								array(
									'class' => 'churchsuite-event-image',
									'style' => 'max-width:100%;height:auto;display:block;',
								)
							);
						}
						$taxonomy = ChurchSuite_Events_Plugin::instance()->taxonomy();
						$url      = $taxonomy ? $taxonomy->get_image_url( $term->term_id, $atts['size'] ) : '';
						if ( ! $url ) {
							return '';
						}
						$alt = $term->name ? esc_attr( $term->name ) : '';
						return sprintf(
							'<img src="%s" alt="%s" class="churchsuite-event-image" style="max-width:100%%;height:auto;display:block;" />',
							esc_url( $url ),
							$alt
						);
					case 'description':
					default:
						return $term->description ? wp_kses_post( wpautop( $term->description ) ) : '';
				}
			}
		);
	}

	/**
	 * Register Query Loop block pattern.
	 *
	 * @return void
	 */
	public function register_pattern() {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		register_block_pattern(
			'churchsuite-events/query-loop',
			array(
				'title'       => __( 'ChurchSuite Events List', 'churchsuite-events' ),
				'description' => __( 'Displays ChurchSuite events in a Query Loop.', 'churchsuite-events' ),
				'categories'  => array( 'query' ),
				'content'     => $this->pattern_content(),
			)
		);
	}

	/**
	 * Provide single template fallback.
	 *
	 * @param string $template Existing template.
	 * @return string
	 */
	public function single_template( $template ) {
		// Let block themes handle layout via the Site Editor.
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return $template;
		}

		if ( ! is_singular( ChurchSuite_Events_CPT::POST_TYPE ) ) {
			return $template;
		}

		$candidate = trailingslashit( CHURCHSUITE_EVENTS_PATH ) . 'templates/single-churchsuite_event.php';
		if ( file_exists( $candidate ) ) {
			return $candidate;
		}

		return $template;
	}

	/**
	 * Provide archive template fallback.
	 *
	 * @param string $template Existing template.
	 * @return string
	 */
	public function archive_template( $template ) {
		// Let block themes handle layout via the Site Editor.
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return $template;
		}

		if ( ! is_post_type_archive( ChurchSuite_Events_CPT::POST_TYPE ) ) {
			return $template;
		}

		$candidate = trailingslashit( CHURCHSUITE_EVENTS_PATH ) . 'templates/archive-churchsuite_event.php';
		if ( file_exists( $candidate ) ) {
			return $candidate;
		}

		return $template;
	}

	/**
	 * Query Loop pattern markup.
	 *
	 * @return string
	 */
	private function pattern_content() {
		return <<<HTML
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
<!-- wp:heading {"textAlign":"left","level":3} -->
<h3 class="wp-block-heading" id="churchsuite-events-heading">Upcoming events</h3>
<!-- /wp:heading -->
<!-- wp:query {"queryId":1,"query":{"perPage":6,"pages":0,"offset":0,"postType":"churchsuite_event","order":"asc","orderBy":"date","inherit":false,"churchsuiteUpcoming":true},"namespace":"churchsuite-events/upcoming","displayLayout":{"type":"list"},"align":"wide"} -->
<div class="wp-block-query alignwide">
<!-- wp:post-template -->
<!-- wp:group {"style":{"spacing":{"margin":{"bottom":"16px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-bottom:16px">
<!-- wp:post-featured-image {"isLink":true} /-->
<!-- wp:post-title {"isLink":true} /-->
<!-- wp:post-excerpt {"moreText":"Read more"} /-->
<!-- wp:paragraph {"className":"event-meta","fontSize":"small"} -->
<p class="event-meta has-small-font-size"><strong>Starts:</strong> <!-- wp:shortcode -->[churchsuite_event_meta key="_churchsuite_event_start"]<!-- /wp:shortcode --></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"event-meta","fontSize":"small"} -->
<p class="event-meta has-small-font-size"><strong>Location:</strong> <!-- wp:shortcode -->[churchsuite_event_meta key="_churchsuite_event_location"]<!-- /wp:shortcode --></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"event-meta","fontSize":"small"} -->
<p class="event-meta has-small-font-size"><strong>Sign up:</strong> <!-- wp:shortcode -->[churchsuite_event_meta key="_churchsuite_event_registration_url" link="1"]<!-- /wp:shortcode --></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>No events found.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
</div>
<!-- /wp:group -->
HTML;
	}

	/**
	 * If no featured image, fall back to first category image for this CPT.
	 *
	 * @param int|false     $thumbnail_id Current thumbnail id.
	 * @param int|WP_Post   $post         Post object or ID.
	 * @return int|false
	 */
	public function maybe_use_category_image( $thumbnail_id, $post ) {
		$post_id = $post instanceof WP_Post ? $post->ID : $post;

		if ( $thumbnail_id || ! $post_id ) {
			return $thumbnail_id;
		}

		if ( get_post_type( $post_id ) !== ChurchSuite_Events_CPT::POST_TYPE ) {
			return $thumbnail_id;
		}

		$plugin   = ChurchSuite_Events_Plugin::instance();
		$taxonomy = $plugin ? $plugin->taxonomy() : null;

		if ( ! $taxonomy ) {
			return $thumbnail_id;
		}

		$terms = wp_get_object_terms( $post_id, ChurchSuite_Events_Taxonomy::TAXONOMY );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $thumbnail_id;
		}

		foreach ( $terms as $term ) {
			$image_id = $taxonomy->get_image_id( $term->term_id );
			if ( $image_id ) {
				return $image_id;
			}
		}

		return $thumbnail_id;
	}

	/**
	 * If no excerpt, fall back to first category excerpt for this CPT.
	 *
	 * @param string      $excerpt Current excerpt.
	 * @param WP_Post|int $post    Post object or ID.
	 * @return string
	 */
	public function maybe_use_category_excerpt( $excerpt, $post ) {
		if ( ! empty( $excerpt ) ) {
			return $excerpt;
		}

		$post_id = $post instanceof WP_Post ? $post->ID : $post;
		if ( ! $post_id || get_post_type( $post_id ) !== ChurchSuite_Events_CPT::POST_TYPE ) {
			return $excerpt;
		}

		$plugin   = ChurchSuite_Events_Plugin::instance();
		$taxonomy = $plugin ? $plugin->taxonomy() : null;
		if ( ! $taxonomy ) {
			return $excerpt;
		}

		$terms = wp_get_object_terms( $post_id, ChurchSuite_Events_Taxonomy::TAXONOMY );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $excerpt;
		}

		foreach ( $terms as $term ) {
			$category_excerpt = $taxonomy->get_excerpt( $term->term_id );
			if ( '' !== $category_excerpt ) {
				return $category_excerpt;
			}
		}

		return $excerpt;
	}
}
