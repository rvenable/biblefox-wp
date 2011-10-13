<?php
/**
 * The template for displaying Bible Tools within the admin interface (ie. on Edit Post screen)
 *
 */
?>
<?php if (!empty($_REQUEST['src'])): ?>
	<?php echo bfox_tool_content_for_ref(new BfoxRef($_REQUEST['ref'])); ?>
<?php else: ?>
	<?php load_bfox_template('archive-bfox_tool'); ?>
<?php endif; ?>