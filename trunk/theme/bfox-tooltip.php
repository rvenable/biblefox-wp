<?php //twentyeleven_content_nav( 'nav-above' ); ?>

<div class="bfox-tooltip-bible">
	<select class="bfox-iframe-select">

	<?php /* Start the Loop */ ?>
	<?php while ( have_posts() ) : the_post(); ?>
		<option name="<?php ?>" value="<?php echo bfox_tool_source_url() ?>"><?php the_title() ?></option>
	<?php endwhile; ?>

	</select>

<?php //the_selected_bfox_tool_post(); // Resets post data to the bible tool that is currently selected ?>

	<iframe class="bfox-iframe bfox-tooltip-iframe" src="<?php echo bfox_tool_source_url() ?>"></iframe>
</div>

<?php //twentyeleven_content_nav( 'nav-below' ); ?>
