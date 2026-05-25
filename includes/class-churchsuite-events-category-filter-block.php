<?php
/**
 * Category filter block for ChurchSuite event Query Loops.
 *
 * @package ChurchSuiteEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the category filter block.
 */
class ChurchSuite_Events_Category_Filter_Block {
	const BLOCK_NAME          = 'churchsuite-events/category-filter';
	const QUERY_PARAM         = 'churchsuite_event_category';
	const LEGACY_QUERY_PARAM  = 'churchsuite_category';
	const ANCHOR_ID           = 'churchsuite-event-category-filter';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register block assets and dynamic render callback.
	 *
	 * @return void
	 */
	public function register_block() {
		$editor_handle = 'churchsuite-events-category-filter-editor';
		$style_handle  = 'churchsuite-events-category-filter';

		wp_register_script(
			$editor_handle,
			trailingslashit( CHURCHSUITE_EVENTS_URL ) . 'assets/category-filter-block.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			CHURCHSUITE_EVENTS_VERSION,
			true
		);

		wp_register_style(
			$style_handle,
			trailingslashit( CHURCHSUITE_EVENTS_URL ) . 'assets/category-filter.css',
			array(),
			CHURCHSUITE_EVENTS_VERSION
		);

		register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'     => 2,
				'editor_script'   => $editor_handle,
				'editor_style'    => $style_handle,
				'style'           => $style_handle,
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Render the category filter dropdown.
	 *
	 * @return string
	 */
	public function render_block() {
		$terms = get_terms(
			array(
				'taxonomy'   => ChurchSuite_Events_Taxonomy::TAXONOMY,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$current_slug = $this->get_selected_slug();
		$action       = $this->get_form_action();

		ob_start();
		?>
		<form id="<?php echo esc_attr( self::ANCHOR_ID ); ?>" class="churchsuite-event-category-filter" action="<?php echo esc_url( $action ); ?>" method="get">
			<?php echo $this->get_preserved_query_inputs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<label class="churchsuite-event-category-filter__label" for="churchsuite-event-category-filter-select">
				<?php esc_html_e( 'Filter by category', 'churchsuite-events' ); ?>
			</label>
			<div class="churchsuite-event-category-filter__controls">
				<select id="churchsuite-event-category-filter-select" name="<?php echo esc_attr( self::QUERY_PARAM ); ?>" class="churchsuite-event-category-filter__select">
					<option value=""><?php esc_html_e( 'All categories', 'churchsuite-events' ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_slug, $term->slug ); ?>>
							<?php echo esc_html( $term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="churchsuite-event-category-filter__submit">
					<?php esc_html_e( 'Apply', 'churchsuite-events' ); ?>
				</button>
			</div>
		</form>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get the selected category slug from the current request.
	 *
	 * @return string
	 */
	private function get_selected_slug() {
		$key = $this->get_selected_query_param_key();
		if ( '' === $key ) {
			return '';
		}

		$value = wp_unslash( $_GET[ $key ] );
		if ( is_array( $value ) ) {
			return '';
		}

		return sanitize_title( $value );
	}

	/**
	 * Find the current filter query parameter key.
	 *
	 * @return string
	 */
	private function get_selected_query_param_key() {
		if ( isset( $_GET[ self::QUERY_PARAM ] ) ) {
			return self::QUERY_PARAM;
		}

		if ( isset( $_GET[ self::LEGACY_QUERY_PARAM ] ) ) {
			return self::LEGACY_QUERY_PARAM;
		}

		return '';
	}

	/**
	 * Get the current URL without query parameters for form submission.
	 *
	 * @return string
	 */
	private function get_form_action() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = $request_uri ? strtok( $request_uri, '?' ) : '';

		return home_url( $path ) . '#' . self::ANCHOR_ID;
	}

	/**
	 * Preserve unrelated scalar query parameters, excluding filters and pagination.
	 *
	 * @return string
	 */
	private function get_preserved_query_inputs() {
		if ( empty( $_GET ) ) {
			return '';
		}

		$output = '';
		foreach ( $_GET as $key => $value ) {
			$key = sanitize_key( $key );

			if ( self::QUERY_PARAM === $key || self::LEGACY_QUERY_PARAM === $key || 'paged' === $key || preg_match( '/^query-\d+-page$/', $key ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				continue;
			}

			$output .= sprintf(
				'<input type="hidden" name="%s" value="%s" />',
				esc_attr( $key ),
				esc_attr( sanitize_text_field( wp_unslash( $value ) ) )
			);
		}

		return $output;
	}
}
