<?php
/**
 * Default archive template for ChurchSuite events.
 *
 * @package ChurchSuiteEvents
 */

// Only used for classic themes; block themes will use their own templates.
if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
	return;
}

get_header();
?>
<main id="primary" class="site-main">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Events', 'churchsuite-events' ); ?></h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="events-archive">
			<?php
			while ( have_posts() ) :
				the_post();
				$start_raw = get_post_meta( get_the_ID(), ChurchSuite_Events_CPT::META_START, true );
				$loc_raw   = get_post_meta( get_the_ID(), ChurchSuite_Events_CPT::META_LOCATION, true );
				$start     = is_array( $start_raw ) ? implode( ', ', array_filter( array_map( 'trim', $start_raw ) ) ) : $start_raw;
				$loc       = is_array( $loc_raw ) ? implode( ', ', array_filter( array_map( 'trim', $loc_raw ) ) ) : $loc_raw;
				?>
				<article <?php post_class(); ?>>
					<h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php if ( $start ) : ?>
						<p><strong><?php esc_html_e( 'Starts:', 'churchsuite-events' ); ?></strong> <?php echo esc_html( $start ); ?></p>
					<?php endif; ?>
					<?php if ( $loc ) : ?>
						<p><strong><?php esc_html_e( 'Location:', 'churchsuite-events' ); ?></strong> <?php echo esc_html( $loc ); ?></p>
					<?php endif; ?>
					<?php the_excerpt(); ?>
				</article>
				<?php
			endwhile;

			the_posts_pagination();
			?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'No events found.', 'churchsuite-events' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
