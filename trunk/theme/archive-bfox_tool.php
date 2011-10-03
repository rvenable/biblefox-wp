<?php
/**
 * The template for displaying Bible Tool Archive pages.
 *
 * Usually used to create a Bible reader with access to all of the Bible Tools.
 *
 */

if (defined('BP_VERSION')) {
	load_bfox_template('activities/index-bfox_tool');
	exit;
}

get_header(); ?>

<section id="primary">
	<div id="content" role="main">

	<?php if ( have_posts() ) : ?>

		<header class="page-header">
			<h1 class="page-title">
				<?php _e( 'Bible Tools' ); ?>
			</h1>
		</header>

		<?php load_bfox_template('bfox-tooltip'); ?>

	<?php else : ?>

		<article id="post-0" class="post no-results not-found">
			<header class="entry-header">
				<h1 class="entry-title"><?php _e( 'No Bible Tools Found' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<p><?php _e( 'Apologies, but no Bible Tools have been found.' ); ?></p>
			</div><!-- .entry-content -->
		</article><!-- #post-0 -->

	<?php endif; ?>

	</div><!-- #content -->
</section><!-- #primary -->


<?php get_sidebar(); ?>
<?php get_footer(); ?>