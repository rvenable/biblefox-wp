<?php

/*
 * Plan Template Tags
 */

/**
 * Returns whether the plan has readings
 * @param integer $post_id
 * @return bool Whether the plan has readings
 */
function bfox_plan_has_readings($post_id = 0) {
	$reading_data = bfox_plan_reading_data($post_id);
	return !empty($reading_data->refs);
}

/**
 * Returns a BfoxRef for the total of all Bible references in the reading plan
 * @param integer $post_id
 * @return BfoxRef $total_ref
 */
function bfox_plan_total_ref($post_id = 0) {
	$total_ref = new BfoxRef;

	$reading_data = bfox_plan_reading_data($post_id);
	foreach ($reading_data->refs as $ref) $total_ref->add_ref($ref);

	return $total_ref;
}

/*
 * Schedule Template Tags
 */

/**
 * Returns whether the plan is scheduled
 * @param integer $post_id
 * @return bool Whether the plan is scheduled
 */
function bfox_plan_is_scheduled($post_id = 0) {
	$start_date = bfox_plan_meta('start_date', $post_id);
	return !empty($start_date);
}

/**
 * Returns whether the given day of the week is included for use in the schedule
 *
 * @param integer $day Number representing a day (0-6, where 0 is Sunday and 6 is Saturday)
 * @param integer $post_id
 * @return bool Whether the day is included in the schedule
 */
function bfox_plan_is_day_included($day, $post_id = 0) {
	$excluded = bfox_plan_meta('excluded_days', $post_id);
	$excluded_days_of_week = (array) $excluded['w'];
	return !in_array((int) $day, $excluded_days_of_week);
}

/**
 * Returns a string for displaying which dates the schedule is manually excluding
 *
 * @param integer $post_id
 * @return string
 */
function bfox_plan_excluded_dates_text($post_id = 0) {
	$excluded = bfox_plan_meta('excluded_days', $post_id);
	$excluded_dates = (array) $excluded['Y-m-d'];
	foreach ($excluded_dates as &$exclude) {
		if (is_array($exclude)) $exclude = implode(':', $exclude);
	}
	return implode("\n", $excluded_dates);
}

/*
 * Reading Template Tags
 */

/**
 * Returns the number of readings for this reading plan
 * @param integer $post_id
 * @return integer
 */
function bfox_plan_reading_count($post_id = 0) {
	$reading_data = bfox_plan_reading_data($post_id);
	return count($reading_data->refs);
}

/**
 * Returns the note text for the given reading plan reading
 *
 * @param integer $reading_id
 * @param integer $post_id
 * @param string
 */
function bfox_plan_reading_note($reading_id, $post_id = 0) {
	$reading_data = bfox_plan_reading_data($post_id);
	return apply_filters('bfox_plan_reading_note', $reading_data->leftovers[$reading_id], $reading_id, $post_id);
}
	function _bfox_plan_reading_note_trim($note) {
		return trim($note, ":;- \t\n\r\0\x0B");
	}
	add_filter('bfox_plan_reading_note', '_bfox_plan_reading_note_trim', 9);

/**
 * Returns the BfoxRef for the given reading plan reading
 *
 * @param integer $reading_id
 * @param integer $post_id
 * @return BfoxRef
 */
function bfox_plan_reading_ref($reading_id, $post_id = 0) {
	$reading_data = bfox_plan_reading_data($post_id);
	return $reading_data->refs[$reading_id];
}

/**
 * Returns a formatted date string for the given reading plan reading
 *
 * @param integer $reading_id
 * @param string $format (Default is 'Y-m-d')
 * @param integer $post_id
 * @param string Formatted date string
 */
function bfox_plan_reading_date($reading_id, $format = '', $post_id = 0) {
	$reading_data = bfox_plan_reading_data($post_id);

	if ($reading_id < 0) $reading_id += count($reading_data->dates);

	if (empty($format)) return $reading_data->dates[$reading_id];
	else return date($format, strtotime($reading_data->dates[$reading_id]));
}

?>