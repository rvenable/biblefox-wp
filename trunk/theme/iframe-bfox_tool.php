<?php
/**
 * The template for displaying Bible Tool iFrames.
 *
 * iFrames are used to display Bible Tool content, while allowing the user to select which tool they want to use.
 *
 */
?>

<div class="bfox-tool-iframe">
	<div class='passage-nav'>
		<?php echo bfox_ref_link(bfox_previous_chapter_ref_str(), array('class' => 'ref_prev', 'tooltip' => false)); ?>
		<?php echo bfox_ref_link(bfox_next_chapter_ref_str(), array('class' => 'ref_next', 'tooltip' => false)); ?>
	</div>
	<?php echo bfox_tool_iframe_select() ?>

	<?php the_selected_bfox_tool_post(); // Resets post data to the bible tool that is currently selected ?>
	<iframe class="bfox-iframe bfox-tooltip-iframe" src="<?php echo bfox_tool_source_url() ?>"></iframe>
</div>
