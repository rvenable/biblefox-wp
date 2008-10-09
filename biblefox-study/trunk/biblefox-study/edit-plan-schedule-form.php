<?php

global $bfox_plan, $bfox_schedule;
if (isset($schedule_id))
{
	$schedule = $bfox_schedule->get_schedule($schedule_id);
	$plan_id = $schedule['plan_id'];
//	echo var_dump($bfox_schedule->frequency) . '<br/>';
//	checked($bfox_schedule->frequency['week'], $schedule['frequency']);
//			 echo '0:' .$bfox_schedule->frequency['week'] . '!!' . $schedule['frequency'] . '@<br/>';
}

if (isset($plan_id) && isset($schedule_id)) {
	$heading = __('Edit Reading Schedule');
	$submit_text = __('Edit Reading Schedule');
	$form = '<form name="editschedule" id="editschedule" method="post" action="" class="validate">';
	$form .= '<input type="hidden" name="schedule_id" value="' . $schedule_id . '" />';
	$action = 'editedschedule';
	$nonce_action = 'update-reading-schedule-' . $schedule_id;
	$readings_per_period = $schedule['readings_per_period'];
	$start_date = $schedule['start_date'];

	list($plan) = $bfox_plan->get_plans($plan_id);
	$passages = $bfox_plan->get_plan_text($plan_id);

	// Output the reading schedule at the top of the page
	echo '<div class="wrap">';
	echo '<h2>' . __('View Reading Schedule') . '</h2><br/>';
	$plan_list = $bfox_plan->get_plan_list($plan->id);
	$plan_list->schedule = $schedule;
	echo bfox_echo_plan_list($plan_list);
	echo '</div>';
	
} else {
	$heading = __('Add Reading Schedule');
	$submit_text = __('Add Reading Schedule');
	$form = '<form name="addschedule" id="addschedule" method="post" action="" class="add:the-list: validate">';
	$action = 'addschedule';
	$nonce_action = 'add-reading-schedule';
	$readings_per_period = 1;
	$now = date_create('now');
	$start_date = $now->format('m/d/Y');
	$schedule = array();
	$schedule['frequency'] = $bfox_schedule->frequency['day'];
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
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="schedule_start_date"><?php _e('Start Date') ?></label></th>
			<td><input name="schedule_start_date" id="schedule_start_date" type="text" value="<?php echo $start_date; ?>" size="10" maxlength="20" /><br />
			<?php _e('Set the date at which this schedule will begin.'); ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="schedule_readings_per_period"><?php _e('How many readings at a time?') ?></label></th>
			<td><input name="schedule_readings_per_period" id="schedule_readings_per_period" type="text" value="<?php echo $readings_per_period; ?>" size="4" maxlength="4" /><br />
			<?php _e('Set the number of readings to read at a time.'); ?></td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="schedule_frequency"><?php _e('How often will this plan be read?') ?></label></th>
			<td>
				<input type="radio" name="schedule_frequency" id="schedule_frequency_day" value="day" <?php checked($bfox_schedule->frequency['day'], $schedule['frequency']); ?> />Daily<br/>
				<input type="radio" name="schedule_frequency" id="schedule_frequency_week" value="week" <?php checked($bfox_schedule->frequency['week'], $schedule['frequency']); ?> />Weekly<br/>
				<input type="radio" name="schedule_frequency" id="schedule_frequency_month" value="month" <?php checked($bfox_schedule->frequency['month'], $schedule['frequency']); ?> />Monthly<br/>
            <?php echo _e('Will this plan be read daily, weekly, or monthly?'); ?></td>
		</tr>
	</table>
<p class="submit"><input type="submit" class="button" name="submit" value="<?php echo $submit_text ?>" /></p>
</form>
</div>
