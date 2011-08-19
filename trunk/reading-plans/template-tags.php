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
	$excluded_days_of_week = (array) @$excluded['w'];
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

/**
 * Returns the latest reading id
 *
 * @param integer $post_id
 * @return integer
 */
function bfox_plan_latest_reading($post_id = 0) {
	$reading_data = bfox_plan_reading_data($post_id);
	return $reading_data->latest_reading_id;
}

/*
 * Reading Template Tags
 */

/**
 * Returns a URL for an individual reading in a reading plan
 *
 * @param integer $reading_id
 * @param integer $post_id
 * @return string URL
 */
function bfox_plan_reading_url($reading_id, $post_id = 0) {
	$url = get_post_permalink($post_id);

	// @todo make user options for which url to point to
	if (1/*supports_reading_urls()*/) {
		$reading_id++;

		if (false !== strpos($url, '?')) $url = add_query_arg('reading', $reading_id, $url);
		else $url = user_trailingslashit(trailingslashit($url) . $reading_id);
	}

	return $url;
}

/**
 * Returns a guid for an individual reading in a reading plan
 *
 * @param integer $reading_id
 * @param integer $post_id
 * @return string guid
 */
function bfox_plan_reading_guid($reading_id, $post_id = 0) {
	return add_query_arg('reading', $reading_id, get_the_guid($post_id));
}

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
 * Returns a Bible reference string for the given reading plan reading
 *
 * @param integer $reading_id
 * @param integer $post_id
 * @param string $name
 * @return string
 */
function bfox_plan_reading_ref_str($reading_id, $post_id = 0, $name = '') {
	$ref = bfox_plan_reading_ref($reading_id, $post_id);
	return $ref->get_string($name);
}

/**
 * Returns a formatted date string for the given reading plan reading
 *
 * Result will always be without a timezone. For example, using format 'H:i:s O' will always return '00:00:00 +0000'
 * Thus, use this function when saying things like 'this reading is for this date...' (ex. listing the dates for the schedule)
 *
 * Use the function bfox_plan_reading_gmdate() to get a more precise date.
 * Thus, use bfox_plan_reading_gmdate() when saying things like 'this reading will begin exactly at this time GMT' (ex. for RSS feeds)
 *
 * @param integer $reading_id
 * @param string $format (Default is 'Y-m-d')
 * @param integer $post_id
 * @param string Formatted date string
 */
function bfox_plan_reading_date($reading_id, $format = '', $post_id = 0) {
	$reading_data = bfox_plan_reading_data($post_id);

	if ($reading_id < 0) $reading_id += count($reading_data->dates);

	// If no format, just return the saved date string
	// Otherwise, convert the saved date string to a timestamp and then format that with gmdate()
	// We use gmdate() because it ignores timezones and this function should only return a single day anyway
	if (empty($format)) return $reading_data->dates[$reading_id];
	else return gmdate($format, strtotime($reading_data->dates[$reading_id] . ' +0000'));
}

/**
 * Returns a more universal date for the start of the reading than bfox_plan_reading_date()
 *
 * The hour and date may vary depending on the blog's saved time zone.
 * For example, for date 2010-10-27 in timezone +0500, this function would return 2010-10-26 19:00:00 +0000
 *
 * @param integer $reading_id
 * @param string $format (Default is 'r')
 * @param integer $post_id
 * @param string Formatted date string
 */
function bfox_plan_reading_gmdate($reading_id, $format = 'r', $post_id = 0) {
	$date = bfox_plan_reading_date($reading_id, '', $post_id);
	return gmdate($format, strtotime($date . ' +0000') - get_option('gmt_offset') * 3600);
}

/**
 * Outputs an HTML list of readings for a reading plan
 *
 * @param array $args
 */
function bfox_plan_reading_list($args = array()) {
	$defaults = array(
		'post_id' => 0,
		'user_id' => 0,
		'from_reading' => 0,
		'to_reading' => -1,
		'max_count' => 0,
		'column_class' => 'reading-list-3c-h',
		'date_format' => 'M j, Y',
	);

	extract(wp_parse_args($args, $defaults));

	$reading_count = bfox_plan_reading_count($post_id);

	if (0 > $from_reading) $from_reading += $reading_count;

	// Adjust the max_count
	if (0 >= $max_count) $max_count = $reading_count;
	$max_count = min($max_count, $reading_count - $from_reading);

	// Adjust the to_reading
	if (0 > $to_reading) $to_reading += $from_reading + $max_count;
	if ($to_reading < $from_reading) $to_reading = $from_reading + $max_count - 1;

	// Adjust the count
	$count = $to_reading - $from_reading + 1;
	if ($count > $max_count) {
		$to_reading = $from_reading + $max_count - 1;
		$count = $max_count;
	}

	if ($count): ?>
	<ol class="reading-list <?php echo $column_class ?>" start="<?php echo $from_reading + 1 ?>">
	<?php for ($reading_id = $from_reading; $reading_id <= $to_reading; $reading_id++): ?>
		<li>
			<div class="reading-info">
			<?php if (bfox_plan_is_scheduled($post_id)): ?>
				<span class="reading-date"><?php echo bfox_plan_reading_date($reading_id, $date_format, $post_id) ?></span>
			<?php endif ?>
				<span class="reading-ref"><?php echo bfox_ref_bible_link(array('ref' => bfox_plan_reading_ref($reading_id, $post_id), 'name' => BibleMeta::name_short)) ?></span>
				<span class="reading-note"><?php echo bfox_plan_reading_note($reading_id, $post_id) ?></span>
			</div>
		</li>
	<?php endfor ?>
	</ol>
<?php
	endif;

	return $count;
}

?>