<?php
/**
 * Taxonomy for ChurchSuite event categories, with image support.
 *
 * @package ChurchSuiteEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers taxonomy and term image meta field.
 */
class ChurchSuite_Events_Taxonomy {
	const TAXONOMY           = 'churchsuite_category';
	const META_IMAGE_ID      = '_churchsuite_category_image_id';
	const META_COLOR         = '_churchsuite_category_color';
	const META_EXCERPT       = '_churchsuite_category_excerpt';
	const NONCE_FIELD        = 'churchsuite_category_image_nonce';
	const MEDIA_FIELD_NAME   = 'churchsuite_category_image_id';
	const EXCERPT_FIELD_NAME = 'churchsuite_category_excerpt';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( self::TAXONOMY . '_add_form_fields', array( $this, 'render_add_field' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( $this, 'render_edit_field' ) );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'save_term_image' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'save_term_image' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media' ) );
	}

	/**
	 * Register taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => __( 'ChurchSuite Categories', 'churchsuite-events' ),
			'singular_name'     => __( 'ChurchSuite Category', 'churchsuite-events' ),
			'search_items'      => __( 'Search Categories', 'churchsuite-events' ),
			'all_items'         => __( 'All Categories', 'churchsuite-events' ),
			'edit_item'         => __( 'Edit Category', 'churchsuite-events' ),
			'update_item'       => __( 'Update Category', 'churchsuite-events' ),
			'add_new_item'      => __( 'Add New Category', 'churchsuite-events' ),
			'new_item_name'     => __( 'New Category Name', 'churchsuite-events' ),
			'menu_name'         => __( 'ChurchSuite Categories', 'churchsuite-events' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			ChurchSuite_Events_CPT::POST_TYPE,
			array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'churchsuite-category' ),
			)
		);
	}

	/**
	 * Enqueue media for term form buttons.
	 *
	 * @return void
	 */
	public function enqueue_media() {
		$screen = get_current_screen();
		if ( $screen && $screen->taxonomy === self::TAXONOMY ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'churchsuite-events-term-media',
				CHURCHSUITE_EVENTS_URL . 'assets/term-media.js',
				array( 'jquery' ),
				CHURCHSUITE_EVENTS_VERSION,
				true
			);
		}
	}

	/**
	 * Render add form field.
	 *
	 * @return void
	 */
	public function render_add_field() {
		?>
		<div class="form-field">
			<label for="<?php echo esc_attr( self::MEDIA_FIELD_NAME ); ?>"><?php esc_html_e( 'Category Image', 'churchsuite-events' ); ?></label>
			<input type="hidden" id="<?php echo esc_attr( self::MEDIA_FIELD_NAME ); ?>" name="<?php echo esc_attr( self::MEDIA_FIELD_NAME ); ?>" value="" />
			<div class="churchsuite-category-image-preview"></div>
			<button type="button" class="button churchsuite-category-image-upload"><?php esc_html_e( 'Choose image', 'churchsuite-events' ); ?></button>
			<button type="button" class="button churchsuite-category-image-remove" style="display:none;"><?php esc_html_e( 'Remove image', 'churchsuite-events' ); ?></button>
			<?php wp_nonce_field( self::NONCE_FIELD, self::NONCE_FIELD ); ?>
		</div>
		<div class="form-field">
			<label for="<?php echo esc_attr( self::EXCERPT_FIELD_NAME ); ?>"><?php esc_html_e( 'Category Excerpt', 'churchsuite-events' ); ?></label>
			<textarea id="<?php echo esc_attr( self::EXCERPT_FIELD_NAME ); ?>" name="<?php echo esc_attr( self::EXCERPT_FIELD_NAME ); ?>" rows="4" cols="40"></textarea>
			<p class="description"><?php esc_html_e( 'Used as a fallback excerpt for events in this category.', 'churchsuite-events' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render edit form field.
	 *
	 * @param WP_Term $term Term being edited.
	 * @return void
	 */
	public function render_edit_field( $term ) {
		$image_id  = (int) get_term_meta( $term->term_id, self::META_IMAGE_ID, true );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		$excerpt   = (string) get_term_meta( $term->term_id, self::META_EXCERPT, true );
		?>
		<tr class="form-field term-group-wrap">
			<th scope="row"><label for="<?php echo esc_attr( self::MEDIA_FIELD_NAME ); ?>"><?php esc_html_e( 'Category Image', 'churchsuite-events' ); ?></label></th>
			<td>
				<input type="hidden" id="<?php echo esc_attr( self::MEDIA_FIELD_NAME ); ?>" name="<?php echo esc_attr( self::MEDIA_FIELD_NAME ); ?>" value="<?php echo esc_attr( $image_id ); ?>" />
				<div class="churchsuite-category-image-preview">
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" style="max-width:150px;height:auto;" />
					<?php endif; ?>
				</div>
				<button type="button" class="button churchsuite-category-image-upload"><?php esc_html_e( 'Choose image', 'churchsuite-events' ); ?></button>
				<button type="button" class="button churchsuite-category-image-remove" <?php echo $image_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove image', 'churchsuite-events' ); ?></button>
				<?php wp_nonce_field( self::NONCE_FIELD, self::NONCE_FIELD ); ?>
			</td>
		</tr>
		<tr class="form-field term-group-wrap">
			<th scope="row"><label for="<?php echo esc_attr( self::EXCERPT_FIELD_NAME ); ?>"><?php esc_html_e( 'Category Excerpt', 'churchsuite-events' ); ?></label></th>
			<td>
				<textarea id="<?php echo esc_attr( self::EXCERPT_FIELD_NAME ); ?>" name="<?php echo esc_attr( self::EXCERPT_FIELD_NAME ); ?>" rows="4" cols="40"><?php echo esc_textarea( $excerpt ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Used as a fallback excerpt for events in this category.', 'churchsuite-events' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save term image meta.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_term_image( $term_id ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_FIELD ) ) {
			return;
		}

		$image_id = isset( $_POST[ self::MEDIA_FIELD_NAME ] ) ? absint( $_POST[ self::MEDIA_FIELD_NAME ] ) : 0;
		if ( $image_id ) {
			update_term_meta( $term_id, self::META_IMAGE_ID, $image_id );
		} else {
			delete_term_meta( $term_id, self::META_IMAGE_ID );
		}

		$excerpt = isset( $_POST[ self::EXCERPT_FIELD_NAME ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ self::EXCERPT_FIELD_NAME ] ) ) : '';
		if ( '' !== $excerpt ) {
			update_term_meta( $term_id, self::META_EXCERPT, $excerpt );
		} else {
			delete_term_meta( $term_id, self::META_EXCERPT );
		}
	}

	/**
	 * Find or create a category term.
	 *
	 * @param string $name Category name.
	 * @return int|WP_Error Term ID or error.
	 */
	public function ensure_category( $name ) {
		$name = $this->normalize_name( $name );

		if ( '' === $name ) {
			return 0;
		}

		$existing = term_exists( $name, self::TAXONOMY );
		if ( $existing && isset( $existing['term_id'] ) ) {
			return (int) $existing['term_id'];
		}

		$created = wp_insert_term(
			$name,
			self::TAXONOMY,
			array(
				'slug' => sanitize_title( $name ),
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		return (int) $created['term_id'];
	}

	/**
	 * Store category colour on term meta.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $color   Hex colour.
	 * @return void
	 */
	public function set_color( $term_id, $color ) {
		$color = $this->normalize_color( $color );
		if ( '' === $color ) {
			return;
		}

		update_term_meta( $term_id, self::META_COLOR, $color );
	}

	/**
	 * Get the image attachment ID for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return int
	 */
	public function get_image_id( $term_id ) {
		return (int) get_term_meta( $term_id, self::META_IMAGE_ID, true );
	}

	/**
	 * Get the image URL for a term.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $size    Image size.
	 * @return string
	 */
	public function get_image_url( $term_id, $size = 'full' ) {
		$image_id = $this->get_image_id( $term_id );
		if ( ! $image_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $image_id, $size );

		return $url ? $url : '';
	}

	/**
	 * Get the excerpt for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public function get_excerpt( $term_id ) {
		$excerpt = get_term_meta( $term_id, self::META_EXCERPT, true );
		if ( ! is_scalar( $excerpt ) ) {
			return '';
		}

		return trim( (string) $excerpt );
	}

	/**
	 * Normalize category name from strings or arrays.
	 *
	 * @param mixed $name Input name.
	 * @return string
	 */
	private function normalize_name( $name ) {
		if ( is_array( $name ) ) {
			foreach ( $name as $value ) {
				if ( is_scalar( $value ) ) {
					$value = trim( (string) $value );
					if ( '' !== $value ) {
						$name = $value;
						break;
					}
				}
			}
		}

		if ( ! is_scalar( $name ) ) {
			return '';
		}

		$name = trim( wp_strip_all_tags( (string) $name ) );

		return $name;
	}

	/**
	 * Normalize a hex colour string.
	 *
	 * @param string $color Raw color.
	 * @return string
	 */
	private function normalize_color( $color ) {
		if ( ! is_scalar( $color ) ) {
			return '';
		}

		$color = trim( (string) $color );

		if ( '' === $color ) {
			return '';
		}

		if ( preg_match( '/^#?[0-9a-fA-F]{6}$/', $color ) ) {
			return '#' . ltrim( $color, '#' );
		}

		if ( preg_match( '/^#?[0-9a-fA-F]{3}$/', $color ) ) {
			return '#' . ltrim( $color, '#' );
		}

		return '';
	}
}
