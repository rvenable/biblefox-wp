<?php if (!empty($_REQUEST['src'])): ?>
	<?php echo bfox_tool_content_for_ref(new BfoxRef($_REQUEST['ref'])); ?>
<?php else: ?>
	<?php load_template(get_archive_template()); ?>
<?php endif; ?>