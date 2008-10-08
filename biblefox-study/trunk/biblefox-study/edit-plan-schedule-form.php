<?php
	
if ( ! empty($schedule_id) ) {
	$heading = __('Edit Reading Schedule');
	$submit_text = __('Edit Reading Schedule');
	$form = '<form name="editschedule" id="editschedule" method="post" action="" class="validate">';
	$action = 'editedschedule';
	$nonce_action = 'update-reading-schedule-' . $schedule_id;
	global $bfox_plan;
	list($plan) = $bfox_plan->get_plans($plan_id);
	$passages = $bfox_plan->get_plan_text($plan_id);

	// Output the reading schedule at the top of the page
	echo '<div class="wrap">';
	echo '<h2>' . __('View Reading Schedule') . '</h2><br/>';
	$plan_list = $bfox_plan->get_plan_list($plan->id);
	echo bfox_echo_plan_list($plan_list);
	echo '</div>';
	
} else {
	$heading = __('Add Reading Schedule');
	$submit_text = __('Add Reading Schedule');
	$form = '<form name="addschedule" id="addschedule" method="post" action="" class="add:the-list: validate">';
	$action = 'addschedule';
	$nonce_action = 'add-reading-schedule';
	unset($plan);
}
?>

<div class="wrap">
<h2><?php echo $heading ?></h2>
<div id="ajax-response"></div>
<?php echo $form ?>
<input type="hidden" name="page" value="<?php echo BFOX_MANAGE_PLAN_SUBPAGE; ?>" />
<input type="hidden" name="action" value="<?php echo $action ?>" />
<input type="hidden" name="plan_id" value="<?php echo $plan_id ?>" />
<?php wp_nonce_field($nonce_action); ?>
	<table class="form-table">
		<tr class="form-field">
			<th scope="row" valign="top"><label for="schedule_readings_per_period"><?php _e('How many readings at a time?') ?></label></th>
			<td><input name="schedule_readings_per_period" id="schedule_readings_per_period" type="text" value="1" size="4" maxlength="4" /><br />
			<?php _e('Set the number of readings to read at a time.'); ?></td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="schedule_frequency"><?php _e('How often will this plan be read?') ?></label></th>
			<td>
				<input type="radio" name="schedule_frequency" value="day">Daily<br/>
				<input type="radio" name="schedule_frequency" value="week">Weekly<br/>
				<input type="radio" name="schedule_frequency" value="month">Monthly<br/>
            <?php echo _e('Will this plan be read daily, weekly, or monthly?'); ?></td>
		</tr>
	</table>
<p class="submit"><input type="submit" class="button" name="submit" value="<?php echo $submit_text ?>" /></p>
</form>
</div>
