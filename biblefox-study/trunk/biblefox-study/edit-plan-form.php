<?php
if ( ! empty($plan_id) ) {
	$heading = __('Edit Reading Plan');
	$submit_text = __('Edit Reading Plan');
	$form = '<form name="editplan" id="editplan" method="post" action="' . $bfox_page_url . '" class="validate">';
	$action = 'editedplan';
	$nonce_action = 'update-reading-plan-' . $plan_id;
	$passage_help_text = __('Which passages of the bible would you like to read?<br/>
							Edit the passages above to modify your reading plan. Each line is a different reading in the plan.');

	global $bfox_plan;

	list($plan) = $bfox_plan->get_plans($plan_id);
	$passages = $bfox_plan->get_plan_text($plan_id);

	// Output the reading plan at the top of the page
	echo '<div class="wrap">';
	echo '<h2>' . __('View Reading Plan') . '</h2><br/>';
	$plan_list = $bfox_plan->get_plan_list($plan->id);
	echo bfox_echo_plan_list($plan_list);
	echo '</div>';
	
} else {
	$heading = __('Add Reading Plan');
	$submit_text = __('Add Reading Plan');
	$form = '<form name="addplan" id="addplan" method="post" action="' . $bfox_page_url . '" class="add:the-list: validate">';
	$action = 'addplan';
	$nonce_action = 'add-reading-plan';
	$passage_help_text = __('Which passages of the bible would you like to read?<br/>
							Type any passages in the box above. For instance, to make a reading plan of all the gospels you could type "Matthew, Mark, Luke, John".<br/>
							You can use bible abbreviations (ie. "gen" instead of "Genesis"), and even specify chapters and verses (ie. "gen 1-3").');
	unset($plan);
}
?>

<div class="wrap">
<h2><?php echo $heading ?></h2>
<div id="ajax-response"></div>
<?php echo $form ?>
<input type="hidden" name="action" value="<?php echo $action ?>" />
<input type="hidden" name="plan_id" value="<?php echo $plan_id ?>" />
<?php wp_nonce_field($nonce_action); ?>
	<table class="form-table">
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="plan_name"><?php _e('Reading Plan Name') ?></label></th>
			<td><input name="plan_name" id="plan_name" type="text" value="<?php echo attribute_escape($plan->name); ?>" size="40" aria-required="true" /><br />
            <?php _e('The name is used to identify the reading plan almost everywhere.'); ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="plan_description"><?php _e('Description') ?></label></th>
			<td><textarea name="plan_description" id="plan_description" rows="5" cols="50" style="width: 97%;"><?php echo wp_specialchars($plan->summary); ?></textarea><br />
            <?php _e('The description is not prominent by default, however some themes may show it.'); ?></td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="plan_passages"><?php _e('Passages') ?></label></th>
			<td><textarea name="plan_passages" id="plan_passages" rows="10" cols="50" style="width: 97%;" aria-required="true"><?php echo wp_specialchars($passages); ?></textarea><br />
            <?php echo $passage_help_text; ?></td>
		</tr>
<?php if (empty($plan_id)) : ?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="plan_chapters"><?php _e('Chapters Per Reading') ?></label></th>
			<td><input name="plan_chapters" id="plan_chapters" type="text" value="1" size="4" /><br />
			<?php _e('Set the number of chapters to read at a time. The passages specified in the "Passages" section will be divided into readings based on how large this number is. If this is not set, it will default to one chapter per reading.'); ?></td>
		</tr>
<?php endif; ?>
	</table>
<p class="submit"><input type="submit" class="button" name="submit" value="<?php echo $submit_text ?>" /></p>
</form>
</div>
