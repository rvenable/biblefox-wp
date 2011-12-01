<?php
/**
 * The template for displaying Bible Tools on the Edit Post admin screen.
 *
 */

$ref_str = bfox_ref_str();

?>
	<?php push_bfox_ref_link_defaults(bfox_ref_link_defaults_update_selector('#bfox-tool-ref-global')); // Bible links update #bfox-tool-ref-global ?>

	<?php if (empty($ref_str)): ?>
		<p>This post currently has no bible references.</p>
	<?php else: ?>
		<p>This post is currently referencing: <?php echo bfox_ref_links(bfox_ref()) ?></p>
	<?php endif ?>

		<p>Add more bible references by typing them into the post, or adding them to the post tags.</p>

		<div id="bible-form" class="bfox-tool-form">
			<input type="text" id="bfox-tool-ref-global" class="field bfox-tool-ref bfox-tool-ref-refresh" name="ref" placeholder="<?php esc_attr_e( 'Search' ); ?>" value="<?php echo bfox_ref_str(BibleMeta::name_short) ?>" />
			<select class="bfox-tool-name" id="bfox-tool-name-main" name="tool"><?php echo bfox_tool_select_options(); ?></select>
		</div>

		<div class="depends-bfox-tool-ref-global depends-bfox-tool-name-main" data-url="<?php echo bfox_tool_context_ajax_url('main'); ?>">
			<?php /* Content loaded later by AJAX (speeds up page load) */ // load_bfox_template('content-bfox_tool'); ?>
		</div>

	<?php pop_bfox_ref_link_defaults(); // #bfox-tool-ref-global ?>
