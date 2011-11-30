<?php
/**
 * The template for displaying Bible Tools within the admin interface (ie. on Edit Post screen)
 *
 */

$tool = active_bfox_tool();
?>
		<article id="bfox_bible-<?php echo $tool->shortName; ?>" class="bfox_bible">
			<header class="entry-header">
				<h1 class="entry-title"><?php echo bfox_ref_link(bfox_ref_str()); ?> - <?php echo $tool->longName; ?></h1>
			</header><!-- .entry-header -->

			<nav class='passage-nav'>
				<div class="nav-previous"><?php echo bfox_ref_link(bfox_previous_chapter_ref_str()); ?></div>
				<div class="nav-next"><?php echo bfox_ref_link(bfox_next_chapter_ref_str()); ?></div>
			</nav><!-- #nav-above -->

			<div class="entry-content">
				<?php $tool->echoContentForRef(bfox_ref()); ?>
			</div><!-- .entry-content -->

			<nav class='passage-nav'>
				<div class="nav-previous"><?php echo bfox_ref_link(bfox_previous_chapter_ref_str()); ?></div>
				<div class="nav-next"><?php echo bfox_ref_link(bfox_next_chapter_ref_str()); ?></div>
			</nav><!-- #nav-above -->

		</article><!-- #bfox_bible-<?php echo $tool->shortName; ?> -->
