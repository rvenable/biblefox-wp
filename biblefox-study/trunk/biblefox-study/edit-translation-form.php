<?php

global $bfox_translations;

if ( !empty($trans_id) ) {
	$heading = __('Edit Translation');
	$submit_text = __('Edit Translation');
	$no_file_text = __('No changes');
	$form = 'name="edittrans" id="edittrans" method="post" action="" class="validate"';
	$action = 'editedtrans';
	$nonce_action = 'update-translation-' . $trans_id;

	$trans = $bfox_translations->get_translation($trans_id);

} else {
	$heading = __('Add Translation');
	$submit_text = __('Add Translation');
	$no_file_text = __('None');
	$form = 'name="addtrans" id="addtrans" method="post" action="" class="add:the-list: validate"';
	$action = 'addtrans';
	$nonce_action = 'add-translation';
	unset($trans);
}

$trans_files = $bfox_translations->get_translation_files();

?>

<div class="wrap">
<h2><?php echo $heading ?></h2>
<div id="ajax-response"></div>
<form <?php echo $form; ?>>
<input type="hidden" name="page" value="<?php echo BFOX_TRANSLATION_SUBPAGE; ?>" />
<input type="hidden" name="action" value="<?php echo $action; ?>" />
<input type="hidden" name="trans_id" value="<?php echo $trans_id; ?>" />
<?php wp_nonce_field($nonce_action); ?>
	<table class="form-table">
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="short_name"><?php _e('Short Name') ?></label></th>
			<td>
				<input name="short_name" id="short_name" type="text" value="<?php echo attribute_escape($trans->short_name); ?>" size="8" maxlength="7" /><br />
            	<?php _e('The abbreviated name for the translation.'); ?>
            </td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="long_name"><?php _e('Long Name') ?></label></th>
			<td>
				<input name="long_name" id="long_name" type="text" value="<?php echo attribute_escape($trans->long_name); ?>" size="40" maxlength="127" /><br />
            	<?php _e('The full name for the translation.'); ?>
            </td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="is_enabled"><?php _e('Enable this translation?') ?></label></th>
			<td>
				<input type="checkbox" name="is_enabled" id="is_enabled" value="1" <?php checked($trans->is_enabled, 1); ?> />
            </td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="trans_file"><?php _e('Source data file') ?></label></th>
			<td>
				<input type="radio" name="trans_file" id="trans_file_none" value="" checked="checked" /><label for="trans_file_none"><?php echo $no_file_text ?></label><br />
				<?php foreach ($trans_files as $index => $file): ?>
				<input type="radio" name="trans_file" id="trans_file_<?php echo $index ?>" value="<?php echo $file ?>" /><label for="trans_file_<?php echo $index ?>"><?php echo $file ?></label><br />
				<? endforeach; ?>
            	<?php _e('The source file for adding the translation data to the database. These files listed here are stored in the translation directory of the biblefox plugin.<br/>
            	<strong>If you select a data file, expect the changes to take a few moments as the file is parsed.</strong><br/>
            	If no source data is selected, the translation will not have any bible verses.'); ?>
            </td>
		</tr>
	</table>
<p class="submit"><input type="submit" class="button" name="submit" value="<?php echo $submit_text ?>" /></p>
</form>
</div>
