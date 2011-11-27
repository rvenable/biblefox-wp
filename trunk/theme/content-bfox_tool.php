<?php
/**
 * The template for displaying Bible Tools within the admin interface (ie. on Edit Post screen)
 *
 */

$tool = active_bfox_tool();

?>
		<article id="bfox_bible-<?php echo $tool->shortName; ?>" class="bfox_bible">
			<header class="entry-header">
				<h1 class="entry-title"><?php echo $tool->longName; ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<?php $tool->echoContentForRef(bfox_ref()); ?>
			</div><!-- .entry-content -->

		</article><!-- #bfox_bible-<?php echo $tool->shortName; ?> -->
