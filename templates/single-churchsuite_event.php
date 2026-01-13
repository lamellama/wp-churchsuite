<?php
/**
 * Default single template for ChurchSuite events.
 *
 * @package ChurchSuiteEvents
 */

/**
 * Render header/footer compatibly for block themes (no header.php/footer.php).
 */
// Only used for classic themes; block themes will use their own templates.
if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
	return;
}

get_header();

while ( have_posts() ) :
	the_post();

	$start_raw = get_post_meta( get_the_ID(), ChurchSuite_Events_CPT::META_START, true );
	$end_raw   = get_post_meta( get_the_ID(), ChurchSuite_Events_CPT::META_END, true );
	$loc_raw   = get_post_meta( get_the_ID(), ChurchSuite_Events_CPT::META_LOCATION, true );
	$link      = get_post_meta( get_the_ID(), ChurchSuite_Events_CPT::META_REGISTRATION, true );

	$normalize = function ( $value ) {
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
			return (string) $value;
		}

		return '';
	};

	$start = $normalize( $start_raw );
	$end   = $normalize( $end_raw );
	$loc   = $normalize( $loc_raw );
	?>
	<main id="primary" class="site-main">
		<article <?php post_class(); ?>>
			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header>

			<div class="entry-meta">
				<?php if ( $start ) : ?>
					<p><strong><?php esc_html_e( 'Starts:', 'churchsuite-events' ); ?></strong> <?php echo esc_html( $start ); ?></p>
				<?php endif; ?>
				<?php if ( $end ) : ?>
					<p><strong><?php esc_html_e( 'Ends:', 'churchsuite-events' ); ?></strong> <?php echo esc_html( $end ); ?></p>
				<?php endif; ?>
				<?php if ( $loc ) : ?>
					<p><strong><?php esc_html_e( 'Location:', 'churchsuite-events' ); ?></strong> <?php echo esc_html( $loc ); ?></p>
				<?php endif; ?>
				<?php if ( $link ) : ?>
					<p><a class="button" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Sign up', 'churchsuite-events' ); ?></a></p>
				<?php endif; ?>
			</div>

			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</article>
	</main>
	<?php
endwhile;

get_footer();
