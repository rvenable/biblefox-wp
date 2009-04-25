<?php

if (!empty($blog_id)) {
	$heading = $submit_text = __('Edit Commentary');
	$com = $coms[$blog_id];
} else {
	$heading = $submit_text = __('Add Commentary');

	$com = (object) NULL;
	$com->blogname = $_GET['name'];
	$com->url = $_GET['url'];
	$com->is_enabled = TRUE;
}

?>

<div class="wrap" id="editcom">
<h2><?php echo $heading ?></h2>
<div id="ajax-response"></div>
<form method="post" action="<?php echo BfoxQuery::post_url() ?>">
<input type="hidden" name="<?php echo BfoxQuery::var_page ?>" value="<?php echo BfoxQuery::page_commentary ?>" />
<input type="hidden" name="action" value="update" />
<?php wp_nonce_field('update-commentary'); ?>
	<table class="form-table">
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="name"><?php _e('Name') ?></label></th>
			<td>
				<input name="name" id="name" type="text" value="<?php echo attribute_escape($com->name); ?>" size="40" maxlength="127" /><br />
            	<?php _e('The name for this commentary blog.'); ?>
            </td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="url"><?php _e('URL') ?></label></th>
			<td>
				<input name="url" id="url" type="text" value="<?php echo attribute_escape($com->feed_url); ?>" size="40" maxlength="127" /><br />
            	<?php _e('The web address for this bible commentary feed.'); ?>
            </td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="is_enabled"><?php _e('Enable this commentary?') ?></label></th>
			<td>
				<input type="checkbox" name="is_enabled" id="is_enabled" value="1" <?php if ($com->is_enabled) echo 'checked="checked"' ?> />
            </td>
		</tr>
	</table>
<p class="submit"><input type="submit" class="button" name="submit" value="<?php echo $submit_text ?>" /></p>
</form>
</div>
