<?php
/**
 * The Template for displaying all single posts.
 *
 * @package WordPress
 * @subpackage Twenty_Eleven
 * @since Twenty Eleven 1.0
 */

get_header(); ?>

		<div id="primary">
			<div id="content" role="main">

				<?php while ( have_posts() ) : the_post(); ?>

					<nav id="nav-single">
						<h3 class="assistive-text"><?php _e( 'Post navigation', 'twentyeleven' ); ?></h3>
						<span class="nav-previous"><?php previous_post_link( '%link', __( '<span class="meta-nav">&larr;</span> Previous', 'twentyeleven' ) ); ?></span>
						<span class="nav-next"><?php next_post_link( '%link', __( 'Next <span class="meta-nav">&rarr;</span>', 'twentyeleven' ) ); ?></span>
					</nav><!-- #nav-single -->

					<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
						<header class="entry-header">
							<h1 class="entry-title"><?php the_title(); ?></h1>

							<?php if ( 'post' == get_post_type() ) : ?>
							<div class="entry-meta">
								<?php twentyeleven_posted_on(); ?>
							</div><!-- .entry-meta -->
							<?php endif; ?>
						</header><!-- .entry-header -->

						<div class="entry-content">
							<?php
							/* For posts of type bfox_plan, the post content contains the list of readings,
							 * but it is usually better to use bfox_plan_reading_list() to output a better formatted list
							 */
							// the_content();
							?>
							<?php
							/* It's not a bad idea to use a bfox_plan excerpt here, to give a description of the Reading Plan */
							// the_excerpt();
							?>

							<?php
							/* Call bfox_plan_reading_list() to output the readings
							 * Use the 'column_class' to set how many columns it should display with. These correspond to CSS classes styled in style-bfox_plan.css
							 * See style-bfox_plan.css for the different options available (or create your own in your theme's style.css)
							 */ ?>
							<?php if (bfox_plan_reading_list(array('column_class' => 'reading-list-2c-h'))): ?>
								<p><?php _e('In total, this reading plan covers all of the following passages:', 'bfox') ?> <?php echo bfox_ref_link(bfox_plan_total_ref_str(0, BibleMeta::name_short)) ?></p>
							<?php else: ?>
								<p><?php _e('This reading plan doesn\'t currently have any readings.', 'bfox') ?></p>
							<?php endif ?>

							<?php wp_link_pages( array( 'before' => '<div class="page-link"><span>' . __( 'Pages:', 'twentyeleven' ) . '</span>', 'after' => '</div>' ) ); ?>
						</div><!-- .entry-content -->

						<footer class="entry-meta">
							<?php
								/* translators: used between list items, there is a space after the comma */
								$categories_list = get_the_category_list( __( ', ', 'twentyeleven' ) );

								/* translators: used between list items, there is a space after the comma */
								$tag_list = get_the_tag_list( '', __( ', ', 'twentyeleven' ) );
								if ( '' != $tag_list ) {
									$utility_text = __( 'This entry was posted in %1$s and tagged %2$s by <a href="%6$s">%5$s</a>. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', 'twentyeleven' );
								} elseif ( '' != $categories_list ) {
									$utility_text = __( 'This entry was posted in %1$s by <a href="%6$s">%5$s</a>. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', 'twentyeleven' );
								} else {
									$utility_text = __( 'This entry was posted by <a href="%6$s">%5$s</a>. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', 'twentyeleven' );
								}

								printf(
									$utility_text,
									$categories_list,
									$tag_list,
									esc_url( get_permalink() ),
									the_title_attribute( 'echo=0' ),
									get_the_author(),
									esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) )
								);
							?>
							<?php edit_post_link( __( 'Edit', 'twentyeleven' ), '<span class="edit-link">', '</span>' ); ?>

							<?php if ( get_the_author_meta( 'description' ) && is_multi_author() ) : // If a user has filled out their description and this is a multi-author blog, show a bio on their entries ?>
							<div id="author-info">
								<div id="author-avatar">
									<?php echo get_avatar( get_the_author_meta( 'user_email' ), apply_filters( 'twentyeleven_author_bio_avatar_size', 68 ) ); ?>
								</div><!-- #author-avatar -->
								<div id="author-description">
									<h2><?php printf( esc_attr__( 'About %s', 'twentyeleven' ), get_the_author() ); ?></h2>
									<?php the_author_meta( 'description' ); ?>
									<div id="author-link">
										<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>" rel="author">
											<?php printf( __( 'View all posts by %s <span class="meta-nav">&rarr;</span>', 'twentyeleven' ), get_the_author() ); ?>
										</a>
									</div><!-- #author-link	-->
								</div><!-- #author-description -->
							</div><!-- #entry-author-info -->
							<?php endif; ?>
						</footer><!-- .entry-meta -->
					</article><!-- #post-<?php the_ID(); ?> -->

					<?php comments_template( '', true ); ?>

				<?php endwhile; // end of the loop. ?>

			</div><!-- #content -->
		</div><!-- #primary -->

<?php get_footer(); ?>