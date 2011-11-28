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

header('Content-Type: ' . feed_content_type('rss-http') . '; charset=' . get_option('blog_charset'), true);

echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>';
?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	<?php do_action('rss2_ns'); do_action('rss2_bfox_plan_readings_ns'); ?>
	>
<channel>
	<title><?php the_title_rss() ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php (is_single()) ? the_permalink_rss() : bloginfo_rss("url") ?></link>
	<description><?php the_excerpt_rss() ?></description>
	<lastBuildDate><?php echo bfox_plan_last_build_date_rss() ?></lastBuildDate>
	<sy:updatePeriod><?php echo bfox_plan_update_period_rss() ?></sy:updatePeriod>
	<sy:updateFrequency><?php echo bfox_plan_update_frequency_rss() ?></sy:updateFrequency>
	<?php do_action('bfox_plan_readings_feed_head'); ?>

<?php while( have_posts()) : the_post(); ?>
	<?php for ($reading_id = bfox_plan_latest_reading(); $reading_id >= 0; $reading_id--): ?>

	<item>
		<title><?php printf(__('Reading #%d', 'bfox'), $reading_id + 1) ?></title>
		<link><?php echo esc_url(bfox_plan_reading_url($reading_id)) ?></link>
		<pubDate><?php echo bfox_plan_reading_gmdate($reading_id, 'D, d M Y H:i:s O') ?></pubDate>
		<dc:creator><?php the_author() ?></dc:creator>
		<guid isPermaLink="false"><?php echo bfox_plan_reading_guid($reading_id); ?></guid>
		<description><![CDATA[<?php echo bfox_plan_reading_content_rss($reading_id) ?>]]></description>
		<content:encoded><![CDATA[<?php echo bfox_plan_reading_content_rss($reading_id) ?>]]></content:encoded>
		<?php do_action('bfox_plan_readings_feed_item'); ?>

	</item>
	<?php endfor ?>
<?php endwhile; ?>
</channel>
</rss>
