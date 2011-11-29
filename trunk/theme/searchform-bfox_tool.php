<?php
/**
 * The template for displaying search forms in Biblefox
 */
?>
	<form method="get" id="searchform" action="<?php echo esc_url( bfox_tool_url() ); ?>" class="bfox-tool-form">
		<label for="s" class="assistive-text"><?php _e( 'Search' ); ?></label>
		<input type="text" class="field" name="ref" id="s" placeholder="<?php esc_attr_e( 'Search' ); ?>" value="<?php echo bfox_ref_str(BibleMeta::name_short) ?>" />
		<?php echo bfox_tool_select(array('attrs' => 'class="bfox-tool-select" name="tool"')); ?>
		<input type="submit" class="submit" id="searchsubmit" value="<?php esc_attr_e( 'Search' ); ?>" />
	</form>
