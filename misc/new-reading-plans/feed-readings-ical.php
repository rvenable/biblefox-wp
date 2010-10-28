<?php
/**
 * RSS2 Feed Template for displaying RSS2 Reading Plan Readings feed.
 */

function bfox_plan_reading_content_rss($reading_id, $post_id = 0) {
	$note = bfox_plan_reading_note($reading_id, $post_id);
	if ($note) $note = '<br/>' . $note;
	$content = sprintf(
		__('<p>Bible Passage: <a href="%s">%s</a>%s</p>', 'bfox'),
		bfox_plan_reading_url($reading_id), bfox_plan_reading_ref_str($reading_id), $note
		);

	$content = str_replace(']]>', ']]&gt;', $content);
	return $content;
}

function bfox_plan_update_period_rss($post_id = 0) {
	$freqs = bfox_plan_schedule_frequencies();
	$key = bfox_plan_meta('frequency', $post_id);
	return apply_filters('bfox_plan_update_period_rss', $freqs[$key]['adjective']);
}

function bfox_plan_update_frequency_rss($post_id = 0) {
	return apply_filters('bfox_plan_update_frequency_rss', (int) bfox_plan_meta('per_day', $post_id));
}

function bfox_plan_last_build_date_rss($format = 'D, d M Y H:i:s +0000', $post_id = 0) {
	$last_reading_date = bfox_plan_reading_gmdate(bfox_plan_latest_reading(), $format);
	$last_reading_time = strtotime($last_reading_date);

	$post = get_post($post_id);
	$last_save_date = mysql2date($format, $post->post_modified_gmt, false);
	$last_save_time = strtotime($last_save_date);

	if ($last_save_time > $last_reading_time) return $last_save_date;
	else return $last_reading_date;
}

function bfox_plan_ical_print_array($arr) {
	foreach ($arr as $key => $value) {
		echo "$key:$value
";
	}
}

header('Content-Type: text/calendar; charset=' . get_option('blog_charset'), true);

bfox_plan_ical_print_array(array(
	'BEGIN' => 'VCALENDAR',
	'PRODID' => '-//Google Inc//Google Calendar 70.9054//EN',
	'VERSION' => '2.0',
	'CALSCALE' => 'GREGORIAN',
	'METHOD' => 'PUBLISH',
	'X-WR-CALNAME' => get_the_title_rss(),
	'X-WR-TIMEZONE' => get_option('timezone_string'),
	'X-WR-CALDESC' => get_the_excerpt(),
));

while( have_posts()) {
	the_post();
	for ($reading_id = bfox_plan_reading_count() - 1; $reading_id >= 0; $reading_id--) {
		$arr = array(
			'BEGIN' => 'VEVENT',
			'DTSTART;VALUE=DATE' => bfox_plan_reading_date($reading_id, 'Ymd'),
			'DTEND;VALUE=DATE' => date('Ymd', strtotime(bfox_plan_reading_date($reading_id, 'Ymd') . ' + 1 day')),
			'DTSTAMP' => bfox_plan_reading_gmdate($reading_id, 'Ymd\THis\Z'),
			'UID' => bfox_plan_reading_guid($reading_id),
			'URL' => esc_url(bfox_plan_reading_url($reading_id)),
			'CREATED' => bfox_plan_reading_gmdate($reading_id, 'Ymd\THis\Z'),
			'SUMMARY' => sprintf(__('#%d: %s', 'bfox'), $reading_id + 1, bfox_plan_reading_ref_str($reading_id, 0, BibleMeta::name_short)),
			'LAST-MODIFIED' => bfox_plan_reading_gmdate($reading_id, 'Ymd\THis\Z'),
			'LOCATION' => '',
			'SEQUENCE' => 0,
			'STATUS' => 'CONFIRMED',
			'TRANSP' => 'OPAQUE',
			'END' => 'VEVENT'
		);
		bfox_plan_ical_print_array($arr);
	}
}
echo "END:VCALENDAR
";
?>