<?php

global $bfox_plan;
$bfox_page_url = 'admin.php?page=' . BFOX_MANAGE_PLAN_SUBPAGE;

if ( ! empty($plan_id) ) {
	$heading = __('Edit Reading Plan');
	$submit_text = __('Edit Reading Plan');
	$form = 'name="editplan" id="editplan" method="post" action="" class="validate"';
	$action = 'editedplan';
	$nonce_action = 'update-reading-plan-' . $plan_id;
	$reading_help_text = __('Edit the passages above to modify your reading plan. Each line is a different reading in the plan.');


	list($plan) = $bfox_plan->get_plans($plan_id);
	$start_date = $plan->start_date;
	$frequency = $bfox_plan->frequency[$plan->frequency['day']];
	$passages = $bfox_plan->get_plan_text($plan_id);

	// Output the reading plan at the top of the page
	echo '<div class="wrap">';
	echo '<h2>' . __('View Reading Plan') . " (<a href='$bfox_page_url'>" . __('view all') . '</a>)</h2><br/>';
	echo bfox_echo_plan($plan);
	echo '</div>';

} else {
	$heading = __('Add Reading Plan');
	$submit_text = __('Add Reading Plan');
	$form = 'name="addplan" id="addplan" method="post" action="" class="add:the-list: validate"';
	$action = 'addplan';
	$nonce_action = 'add-reading-plan';
	$start_date = BfoxUtility::format_local_date('today');
	$frequency = $bfox_plan->frequency['day'];
	unset($plan);

	// Default to all days of the week
	$plan->days_of_week = array_fill(0, 7, TRUE);
}

$passage_help_text = __('<p>This allows you to add passages of the Bible to your reading plan in big chunks.</p>
						<p>You can type passages of the bible in the box, and then set how many chapters you want to read at a time. The passages will be cut into sections and added to your reading plan.</p>
						<p>Type any passages in the box above. For instance, to make a reading plan of all the gospels you could type "Matthew, Mark, Luke, John".<br/>
						You can use bible abbreviations (ie. "gen" instead of "Genesis"), and even specify chapters and verses (ie. "gen 1-3").<br/>
						Separate passages can be separated with a comma (\',\'), semicolon (\';\'), or on separate lines.</p>');

?>

<div class="wrap">
<h2><?php echo $heading ?></h2>
<div id="ajax-response"></div>
<form <?php echo $form ?>>
<input type="hidden" name="page" value="<?php echo BFOX_MANAGE_PLAN_SUBPAGE; ?>" />
<input type="hidden" name="action" value="<?php echo $action; ?>" />
<input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>" />
<?php wp_nonce_field($nonce_action); ?>
	<table class="form-table">
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="plan_name"><?php _e('Reading Plan Name') ?></label></th>
			<td><input name="plan_name" id="plan_name" type="text" value="<?php echo attribute_escape($plan->name); ?>" size="40"/><br />
			<?php _e('The name is used to identify the reading plan almost everywhere.'); ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="plan_description"><?php _e('Description') ?></label></th>
			<td><textarea name="plan_description" id="plan_description" rows="2" cols="50" style="width: 97%;"><?php echo attribute_escape($plan->summary); ?></textarea><br />
            <?php _e('Add a short description of your reading plan to let your users know what it is for.'); ?></td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="schedule_start_date"><?php _e('Start Date') ?></label></th>
			<td><input name="schedule_start_date" id="schedule_start_date" type="text" value="<?php echo $start_date; ?>" size="10" maxlength="20" /><br />
			<?php _e('Set the date at which this schedule will begin.'); ?></td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="schedule_frequency"><?php _e('How often will this plan be read?') ?></label></th>
			<td>
				<input type="radio" name="schedule_frequency" id="schedule_frequency_day" value="day" <?php checked($bfox_plan->frequency['day'], $plan->frequency); ?> />Daily<br/>
				<input type="radio" name="schedule_frequency" id="schedule_frequency_week" value="week" <?php checked($bfox_plan->frequency['week'], $plan->frequency); ?> />Weekly<br/>
				<input type="radio" name="schedule_frequency" id="schedule_frequency_month" value="month" <?php checked($bfox_plan->frequency['month'], $plan->frequency); ?> />Monthly<br/>
			<?php echo _e('Will this plan be read daily, weekly, or monthly?'); ?></td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="schedule_frequency_options"><?php _e('Days of the Week') ?></label></th>
			<td>
				<input type="checkbox" name="schedule_frequency_options[]" value="0" <?php checked(TRUE, $plan->days_of_week[0]); ?> />Sunday<br/>
				<input type="checkbox" name="schedule_frequency_options[]" value="1" <?php checked(TRUE, $plan->days_of_week[1]); ?> />Monday<br/>
				<input type="checkbox" name="schedule_frequency_options[]" value="2" <?php checked(TRUE, $plan->days_of_week[2]); ?> />Tuesday<br/>
				<input type="checkbox" name="schedule_frequency_options[]" value="3" <?php checked(TRUE, $plan->days_of_week[3]); ?> />Wednesday<br/>
				<input type="checkbox" name="schedule_frequency_options[]" value="4" <?php checked(TRUE, $plan->days_of_week[4]); ?> />Thursday<br/>
				<input type="checkbox" name="schedule_frequency_options[]" value="5" <?php checked(TRUE, $plan->days_of_week[5]); ?> />Friday<br/>
				<input type="checkbox" name="schedule_frequency_options[]" value="6" <?php checked(TRUE, $plan->days_of_week[6]); ?> />Saturday<br/>
            <?php echo _e('Which days of the week will you be reading?'); ?></td>
		</tr>
<?php if (isset($passages)) : ?>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="plan_passages"><?php _e('Readings') ?></label></th>
			<td><textarea name="plan_passages" id="plan_passages" rows="15" cols="50" style="width: 97%;"><?php echo wp_specialchars($passages); ?></textarea><br />
			<?php echo $reading_help_text; ?></td>
		</tr>
<?php endif; ?>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="plan_group_passages"><?php _e('Add Groups of Passages') ?></label></th>
			<td><textarea name="plan_group_passages" id="plan_group_passages" rows="3" cols="50" style="width: 97%;"></textarea><br />
			<input name="plan_chapters" id="plan_chapters" type="text" value="1" size="4" maxlength="4" /> <?php _e('Chapters per reading'); ?><br />
			<?php echo $passage_help_text; ?></td>
		</tr>
	</table>
<p class="submit"><input type="submit" class="button" name="submit" value="<?php echo $submit_text ?>" /></p>
</form>
</div>
