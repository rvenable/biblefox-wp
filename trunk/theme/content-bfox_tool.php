<?php
/**
 * The template for displaying Bible Tools within the admin interface (ie. on Edit Post screen)
 *
 */

$tool = active_bfox_tool();

// Push a Bible reference link action:
// All Bible reference links will update the search form
push_bfox_ref_link_action(bfox_ref_link_action_update_form('form#searchform'));

?>
		<article id="bfox_bible-<?php echo $tool->shortName; ?>" class="bfox_bible">
			<header class="entry-header">
				<h1 class="entry-title"><?php echo $tool->longName; ?></h1>
			</header><!-- .entry-header -->

			<nav class='passage-nav'>
				<div class="nav-previous"><?php echo bfox_ref_link(bfox_previous_chapter_ref_str()); ?></div>
				<div class="nav-next"><?php echo bfox_ref_link(bfox_next_chapter_ref_str()); ?></div>
			</nav><!-- #nav-above -->

			<div class="entry-content">
				<?php $tool->echoContentForRef(bfox_ref()); ?>
			</div><!-- .entry-content -->

		</article><!-- #bfox_bible-<?php echo $tool->shortName; ?> -->

<?php

// Pop the Bible reference link action
pop_bfox_ref_link_action();

?>