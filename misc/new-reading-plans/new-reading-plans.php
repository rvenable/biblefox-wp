<?php
/*************************************************************************
Plugin Name: Biblefox Bible Reading Plans (NEW)
Plugin URI: http://dev.biblefox.com/biblefox-for-wordpress/
Description: Requires Biblefox for WordPress to be activated. Adds reading plans.
Version: 0.1
Author: Biblefox.com, rvenable
Author URI: http://biblefox.com
License: General Public License version 2
Requires at least: WP 3.0, BuddyPress 1.2
Tested up to: WP 3.0, BuddyPress 1.2.4.1
*************************************************************************/

define('BFOX_PLANS_DIR', dirname(__FILE__));
define('BFOX_PLANS_URL', WP_PLUGIN_URL . '/new-reading-plans');

require_once BFOX_PLANS_DIR . '/template-tags.php';

function bfox_plans_create_post_type() {
	//TODO: add plans directory template archive - see: http://www.ballyhooblog.com/custom-post-types-wordpress-30-with-template-archives/
	wp_register_style('bfox-plan-reading-lists', BFOX_PLANS_URL . '/reading-lists.css');
	wp_register_script('bfox-plan-ajax', BFOX_PLANS_URL . '/ajax.js');

	register_post_type('bfox_plan',
		array(
			'description' => __('Bible Reading Plans', 'bfox'),
			'labels' => array(
				'name' => __('Reading Plans', 'bfox'),
				'singular_name' => __('Reading Plan', 'bfox'),
				'edit_item' => __('Edit Reading Plan', 'bfox'),
				'new_item' => __('New Reading Plan', 'bfox'),
				'view_item' => __('View Reading Plan', 'bfox'),
				'parent_item_colon' => __('Parent Plan:', 'bfox'),
			),
			'public' => true,
			'rewrite' => array('slug' => 'plans'),
			'hierarchical' => true, // Enable hierarchies for copying reading plans
			'supports' => array('title', 'excerpt', 'revisions', 'thumbnail'),
			'register_meta_box_cb' => 'bfox_plans_register_meta_box_cb',
		)
	);

	add_feed('readings-rss', 'bfox_plan_do_feed_readings');
	add_feed('readings-ical', 'bfox_plan_do_feed_readings');
}
add_action('init', 'bfox_plans_create_post_type');

function bfox_plans_register_meta_box_cb() {
	wp_enqueue_style('bfox-plan-reading-lists');
	wp_enqueue_script('bfox-plan-ajax');

	add_meta_box('bfox-plan-view', __('View Readings', 'bfox'), 'bfox_plans_view_meta_box_cb', 'bfox_plan', 'normal', 'high');
	add_meta_box('bfox-plan-content', __('Edit Readings', 'bfox'), 'bfox_plans_content_meta_box_cb', 'bfox_plan', 'normal', 'core');
	add_meta_box('bfox-plan-append', __('Append Chapters in Bulk', 'bfox'), 'bfox_plans_append_meta_box_cb', 'bfox_plan', 'advanced', 'low');
	add_meta_box('bfox-plan-schedule', __('Edit Schedule', 'bfox'), 'bfox_plans_edit_schedule_meta_box_cb', 'bfox_plan', 'side', 'low');
}

function bfox_plans_setup_follow() {
	$redirect = false;

	if ($_REQUEST['bfox_plan_follow']) {
		$post_id = $_REQUEST['bfox_plan_follow'];
		check_admin_referer("bfox_plan_follow-$post_id");
		bfox_plan_user_set_follow(true, $post_id);
		$redirect = true;
	}
	else if ($_REQUEST['bfox_plan_unfollow']) {
		$post_id = $_REQUEST['bfox_plan_unfollow'];
		check_admin_referer("bfox_plan_unfollow-$post_id");
		bfox_plan_user_set_follow(false, $post_id);
		$redirect = true;
	}

	if ($redirect) {
		wp_redirect(remove_query_arg(array('bfox_plan_follow', 'bfox_plan_unfollow', '_wpnonce')));
		exit;
	}
}
add_action('init', 'bfox_plans_setup_follow');

function bfox_plans_adjust_content_for_copy() {
	global $post;

	if ($_REQUEST['post'] && ('copy-plan' == $_REQUEST['action'] || 'custom-schedule' == $_REQUEST['action'])) {
		$parent_post = get_post($_REQUEST['post']);
		$post->post_title = $parent_post->post_title;
		$post->post_content = $parent_post->post_content;
		$post->post_excerpt = $parent_post->post_excerpt;

		// Add a 'copy' to the end of the title for copies
		if ('copy-plan' == $_REQUEST['action']) $post->post_title .= ' copy';

		// Custom schedules set the original post as their parent
		if ('custom-schedule' == $_REQUEST['action']) {
			// If the original post has a parent, use its parent, otherwise use the post
			$post->post_parent = $parent_post->post_parent ? $parent_post->post_parent : $parent_post->ID;

			// If the post type doesn't support page attributes it doesn't have a place for the parent id, so add a hidden input for it
			if (!post_type_supports('bfox_plan', 'page-attributes')) add_action('dbx_post_sidebar', 'bfox_plans_hidden_parent_id');
		}
	}
}
add_action('add_meta_boxes_bfox_plan', 'bfox_plans_adjust_content_for_copy');

/*
 * Meta Box Callbacks
 */

function bfox_plans_view_meta_box_cb() {
	?>
<?php if (bfox_plan_reading_list(array('from_reading' => bfox_plan_latest_reading(), 'column_class' => 'reading-list-4c-h'))): ?>
	<p><?php _e('In total, this reading plan covers all of the following passages:', 'bfox') ?> <?php echo bfox_ref_bible_link(array('ref' => bfox_plan_total_ref(), 'name' => BibleMeta::name_short)) ?></p>
<?php else: ?>
	<p><?php _e('This reading plan doesn\'t currently have any readings. Enter some Bible references in the \'Edit Readings\' box to add some readings. You can also use the \'Append Chapters in Bulk\' box to automatically add multiple readings quickly.', 'bfox') ?></p>
<?php endif ?>
	<?php
}

function bfox_plans_content_meta_box_cb() {
	global $post;
	?>
	<textarea name="content" id="content" cols="40" rows="10"><?php echo $post->post_content ?></textarea>
	<p><?php _e('Enter all the readings for this reading plan here. Each line that contains at least one Bible reference will be considered an individual reading in the reading plan. Lines that do not contain Bible references will not be part of the reading plan, but can be used for adding additional information to the plan.', 'bfox') ?></p>
	<p><?php _e('You can also add titles/notes to individual readings. Any text on the same line that is not a part of the Bible reference will be part of the note.', 'bfox') ?></p>
	<?php
}

function bfox_plans_hidden_parent_id() {
	global $post;
	?>
	<input type="hidden" name="parent_id" value="<?php echo $post->post_parent ?>" />
	<?php
}

function bfox_plans_append_meta_box_cb() {
	global $post;
	?>
	<textarea name="bfox-plan-append" id="bfox-plan-append" rows="5" style="width: 100%; margin: 0;"></textarea>
	<p>
	<label for="bfox-plan-chunk-size"><?php _e( 'Chapters per reading', 'bfox' ) ?></label>
	<input type="text" name="bfox-plan-chunk-size" id="bfox-plan-chunk-size" value="1" />
	</p>
	<p><?php _e('Add some scripture by typing in the chapters you want to read, then enter how many chapters you want to read per reading.
					A reading plan will then automatically be created for you.', 'bfox') ?></p>
	<p><?php _e('For your reference, here is a list of abbreviations for all the books in the Bible (you can just paste these into the box above to make a reading plan for the whole Bible or large chunks of it):', 'bfox') ?></p>
	<p>Gen; Exo; Lev; Num; Deut; Josh; Judg; Ruth; 1Sam; 2Sam; 1Ki; 2Ki; 1Chr; 2Chr; Ezra; Neh; Esth; Job; Ps; Prov; Ecc; Song; Isa; Jer; Lam; Ezek; Dan; Hos; Joel; Amos; Obad; Jnh; Mic; Nah; Hab; Zeph; Hag; Zech; Mal; </p>
	<p>Matt; Mark; Luke; John; Acts; Rom; 1Cor; 2Cor; Gal; Eph; Phil; Col; 1Th; 2Th; 1Tim; 2Tim; Tit; Phm; Heb; Jm; 1Pet; 2Pet; 1Jn; 2Jn; 3Jn; Jude; Rev;</p>
	<?php
}

function bfox_plans_edit_schedule_meta_box_cb() {
	global $post;

	wp_nonce_field('bfox', 'bfox_plan_edit_schedule_nonce');

	?>
	<p><?php _e('You can add an optional schedule to your reading plan.', 'bfox') ?></p>

	<h4><?php _e('Start Date', 'bfox') ?></h4>
	<p><?php _e('Set the date you want to start reading this reading plan (in YYYY-MM-DD format - ex. \'2010-12-31\')', 'bfox') ?></p>
	<p><input type="text" name="schedule-start" id="schedule-start" class="datepicker" value="<?php echo bfox_plan_meta('start_date') ?>" /></p>
	<p><?php _e('Use YYYY-MM-DD format (ex. \'2010-12-31\')', 'bfox') ?></p>

	<h4><?php _e('Reading Frequency', 'bfox') ?></h4>
	<p><?php _e('How often will you read?', 'bfox') ?></p>
	<p>
	<?php foreach (bfox_plan_schedule_frequencies() as $key => $frequency): ?>
			<label><input type="radio" name="schedule-frequency" value="<?php echo $key ?>"<?php checked($key, bfox_plan_meta('frequency')) ?> /> <?php echo $frequency['label'] ?></label>
	<?php endforeach ?>
	</p>

	<h4><?php _e('Readings Per Day', 'bfox') ?></h4>
	<p><?php _e( 'How many readings will you read at a time?', 'bfox' ) ?></p>
	<p><input type="text" name="schedule-per-day" id="schedule-per-day" value="1" /></p>

	<h4><?php _e('Days of the Week', 'bfox') ?></h4>
	<p><?php _e( 'Which days of the week will you read?', 'bfox' ) ?></p>
	<p>
		<label><input type="checkbox" name="schedule-days[]" value="0"<?php checked(bfox_plan_is_day_included(0)) ?>/> <?php _e( 'Su', 'bfox' ) ?></label>
		<label><input type="checkbox" name="schedule-days[]" value="1"<?php checked(bfox_plan_is_day_included(1)) ?>/> <?php _e( 'M', 'bfox' ) ?></label>
		<label><input type="checkbox" name="schedule-days[]" value="2"<?php checked(bfox_plan_is_day_included(2)) ?>/> <?php _e( 'Tu', 'bfox' ) ?></label>
		<label><input type="checkbox" name="schedule-days[]" value="3"<?php checked(bfox_plan_is_day_included(3)) ?>/> <?php _e( 'W', 'bfox' ) ?></label>
		<label><input type="checkbox" name="schedule-days[]" value="4"<?php checked(bfox_plan_is_day_included(4)) ?>/> <?php _e( 'Th', 'bfox' ) ?></label>
		<label><input type="checkbox" name="schedule-days[]" value="5"<?php checked(bfox_plan_is_day_included(5)) ?>/> <?php _e( 'F', 'bfox' ) ?></label>
		<label><input type="checkbox" name="schedule-days[]" value="6"<?php checked(bfox_plan_is_day_included(6)) ?>/> <?php _e( 'Sa', 'bfox' ) ?></label>
	</p>

	<h4><?php _e('Excluded Dates', 'bfox') ?></h4>
	<textarea name="schedule-exclude" id="schedule-exclude" cols="28" rows="5"><?php echo bfox_plan_excluded_dates_text() ?></textarea><br/>
	<p><?php _e('You can exclude dates by entering them here in YYYY-MM-DD format (ex. \'2010-12-31\'). Separate individual dates by semicolons (\';\') or put them on separate lines. You can also create date ranges by using a colon in between two dates (ex. \'2010-12-31:2011-01-01\')', 'bfox') ?></p>
	<?php
}

function bfox_plans_table_row_actions($actions, $post) {
	if ('bfox_plan' == $post->post_type) {
		if (bfox_plan_is_followed($post->ID)) $actions['follow'] = '<a href="' . bfox_plan_admin_follow_url(0, $post->ID) . '">' . __('Unfollow', 'bfox') . '</a>';
		else $actions['follow'] = '<a href="' . bfox_plan_admin_follow_url(1, $post->ID) . '">' . __('Follow', 'bfox') . '</a>';
		$actions['copy'] = '<a href="' . bfox_plan_admin_copy_url($post->ID) . '">' . __('Copy', 'bfox') . '</a>';
		$actions['custom-schedule'] = '<a href="' . bfox_plan_admin_custom_schedule_url($post->ID) . '">' . __('New&nbsp;Schedule', 'bfox') . '</a>';
	}
	return $actions;
}
add_filter('page_row_actions', 'bfox_plans_table_row_actions', 10, 2);

/*
 * Saving Reading Plans
 */

function bfox_plan_admin_init_append_content() {
	if (isset($_POST['content']) && !empty($_POST['bfox-plan-append']) && !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
		$readings = bfox_plan_parse_readings_from_passages($_POST['bfox-plan-append'], $_POST['bfox-plan-chunk-size']);
		if (!empty($readings)) {
			if (!empty($_POST['content'])) array_unshift($readings, $_POST['content']);
			$_POST['content'] = implode("\n", $readings);
		}
	}
}
add_action('admin_init', 'bfox_plan_admin_init_append_content');

function bfox_plan_save_post($post_id, $post) {
	if ('bfox_plan' == $_POST['post_type']) {
		// See: http://codex.wordpress.org/Function_Reference/add_meta_box

		// verify this came from the our screen and with proper authorization,
		// because save_post can be triggered at other times
		if ( !wp_verify_nonce( $_POST['bfox_plan_edit_schedule_nonce'], 'bfox' )) {
			return $post_id;
		}

		// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
		// to do anything
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return $post_id;

		// Check permissions
		if ( 'page' == $_POST['post_type'] ) {
			if ( !current_user_can( 'edit_page', $post_id ) )
				return $post_id;
		} else {
			if ( !current_user_can( 'edit_post', $post_id ) )
				return $post_id;
		}

		// Start Date
		$start_date = $_POST['schedule-start'];

		// If the start date is a real date, then save it in Y-m-d form
		// Otherwise, save an empty string as the start date (which means no schedule is being used)
		if ($time = strtotime($start_date)) {
			$start_date = date('Y-m-d', $time);
			bfox_plan_update_meta('start_date', $start_date, $post_id);
		}
		else bfox_plan_update_meta('start_date', '', $post_id);

		// Schedule Frequency
		$frequency = $_POST['schedule-frequency'];
		$freqs = bfox_plan_schedule_frequencies();
		if (!isset($freqs[$frequency])) $frequency = 'daily';
		bfox_plan_update_meta('frequency', $frequency, $post_id);

		$per_day = max(1, $_POST['schedule-per-day']);
		bfox_plan_update_meta('per_day', $per_day, $post_id);

		// Excluded Dates
		$exclude = array();
		$exclude['Y-m-d'] = array();
		$exclude['w'] = array();

		$new_excludes = $_POST['schedule-exclude'];
		$new_excludes = explode("\n", str_replace(array(';'), "\n", $new_excludes));
		foreach ($new_excludes as $new_exclude) {
			$is_valid = true;

			$multi = explode(':', $new_exclude);
			foreach ($multi as &$ex) {
				$time = strtotime($ex);
				$is_valid = $is_valid && $time; // Make sure this is an actual time
				$ex = date('Y-m-d', $time);
			}

			// If every date was an actual date, save the new exclusion
			if ($is_valid) {
				if (1 == count($multi)) $exclude['Y-m-d'] []= $multi[0];
				else $exclude['Y-m-d'] []= $multi;
			}
		}

		// Days of the Week exclusions
		$all_days_of_week = array(0, 1, 2, 3, 4, 5, 6);
		$days_of_week = (array) $_POST['schedule-days'];
		if (0 == count($days_of_week)) $days_of_week = $all_days_of_week;

		foreach ($all_days_of_week as $day) {
			if (!in_array($day, $days_of_week)) $exclude['w'] []= $day;
		}

		bfox_plan_update_meta('excluded_days', $exclude);

		bfox_plan_update_reading_data($post_id);
	}
}
add_action('save_post', 'bfox_plan_save_post', 10, 2);

/*
 * Reading Plan Meta Data functions
 */

function bfox_plan_meta($key, $post_id = 0) {
	if (empty($post_id)) $post_id = $GLOBALS['post']->ID;
	$value = get_post_meta($post_id, '_bfox_plan_' . $key, true);

	if (empty($value)) {
		$defaults = array(
			'frequency' => 'daily',
			'per_day' => 1,
		);
		$value = $defaults[$key];
	}
	return $value;
}

function bfox_plan_update_meta($key, $value, $post_id = 0) {
	if (empty($post_id)) $post_id = $GLOBALS['post']->ID;
	return update_post_meta($post_id, '_bfox_plan_' . $key, $value);
}

function bfox_plan_schedule_frequencies() {
	return apply_filters('bfox_plan_schedule_frequencies', array(
		'daily' => array(
			'label' => __('Daily', 'bfox'),
			'name' => 'day',
			'adjective' => 'daily',
			'increment' => '+1 day',
		),
		'weekly' => array(
			'label' => __('Weekly', 'bfox'),
			'name' => 'week',
			'adjective' => 'weekly',
			'increment' => '+1 week',
		),
		'monthly' => array(
			'label' => __('Monthly', 'bfox'),
			'name' => 'month',
			'adjective' => 'monthly',
			'increment' => '+1 month',
		),
	));
}

/*
 * Reading Data functions
 */

/**
 * Returns the reading data for the given reading plan
 *
 * Reading Data is an object with members:
 * 	$reading_data->refs			Array of Bible References for each reading
 * 	$reading_data->leftovers	Array of any text leftover from parsing out the Bible References on the same line as the reading (Used for notes)
 * 	$reading_data->dates		Array of dates (as strings) for the corresponding readings (only valid if there is a schedule)
 *
 * @param integer $post_id
 * @return object Reading Data
 */
function bfox_plan_reading_data($post_id = 0) {
	if (empty($post_id)) $post_id = $GLOBALS['post']->ID;
	$reading_data = wp_cache_get($post_id, 'bfox_plan_reading_data');
	if (!$reading_data) $reading_data = bfox_plan_update_reading_data($post_id);
	return $reading_data;
}

/**
 * Called to update the cached reading data for given reading plan
 *
 * @param integer $post_id
 * @return object Reading Data (@see bfox_plan_reading_data() for description)
 */
function bfox_plan_update_reading_data($post_id = 0) {
	$post = get_post($post_id);

	$reading_data = new stdClass();
	$reading_data->refs = array();
	$reading_data->leftovers = array();
	$reading_data->dates = array();
	$reading_data->latest_reading_id = -1;

	_bfox_plan_parse_content($post->post_content, $reading_data);

	$start_date = bfox_plan_meta('start_date', $post_id);
	if (!empty($start_date) && strtotime($start_date)) {
		$frequency = bfox_plan_meta('frequency', $post_id);
		$days_of_week = bfox_plan_meta('days_of_week', $post_id);
		$per_day = bfox_plan_meta('per_day', $post_id);
		$excluded = bfox_plan_meta('excluded_days', $post_id);

		_bfox_plan_calculate_dates($start_date, count($reading_data->refs), $frequency, $per_day, $excluded, $reading_data);
	}

	wp_cache_set($post_id, $reading_data, 'bfox_plan_reading_data');

	return $reading_data;
}

	function _bfox_plan_parse_content($content, $reading_data) {
		if (is_string($content)) $content = explode("\n", $content);

		foreach ((array) $content as $str) {
			$parser = new BfoxRefParser;
			$parser->total_ref = new BfoxRef; // Save total_ref
			$parser->leftovers = ''; // Save leftovers if not null
			$parser->max_level = 2; // Include all book abbreviations

			$leftovers = $parser->parse_string($str);

			if ($parser->total_ref->is_valid()) {
				$reading_data->refs []= $parser->total_ref;
				$reading_data->leftovers []= $leftovers;
			}
		}
	}

	function _bfox_plan_calculate_dates($start_date, $count, $frequency, $per_day, $excluded, $reading_data) {

		$freqs = bfox_plan_schedule_frequencies();
		$inc_str = $freqs[$frequency]['increment'];

		$today_time = strtotime(date('Y-m-d'));

		$per_day_remaining = $per_day;

		$time = strtotime($start_date);
		for ($index = 0; $index < $count; $index++) {
			// Remember the original time we started this with, since we will be adjusting the time
			$original_time = $time;

			// If we have select_values, increment until we find a selected value
			//if (!empty($select_values)) while (!$select_values[date($select_format, $time)]) $time = strtotime($inc_str, $time);
			while (_bfox_plan_is_time_excluded($time, $excluded)) $time = strtotime('+1 day', $time);

			if ($time <= $today_time) $reading_data->latest_reading_id = $index;

			$reading_data->dates []= date('Y-m-d', $time);

			$per_day_remaining--;

			// We prefer to increment the original time instead of the adjusted time
			// This prevents dates gradually rolling back in some instances
			// For instance, a monthly plan that is only read on Sundays might start on the 1st of the month,
			// but the second month be on the 2nd (because the 1st isn't a Sunday).
			// We want the remaining months to continue to shoot for the 1st, so we increment on the original time
			$adjusted_time = $time;
			$time = strtotime($inc_str, $original_time);

			// If the incremented original time is less than the adjusted time that we just used for this date,
			// then we need to increment on the adjusted time (this guarantees we are always moving forward)
			if ($time <= $adjusted_time) $time = strtotime($inc_str, $adjusted_time);
		}
	}

		function _bfox_plan_is_time_excluded($time, &$excluded) {
			foreach ((array) $excluded as $format => $array) {
				$formatted_date = date($format, $time);
				foreach ((array) $array as $excluded_date) {
					if (is_array($excluded_date)) {
						if ($excluded_date[0] <= $formatted_date && $formatted_date <= $excluded_date[1])
							return true;
					}
					else {
						if ($excluded_date == $formatted_date) return true;
					}
				}
			}
		}

function bfox_plan_parse_readings_from_passages($passages, $chunk_size) {
	$chunk_size = max(1, $chunk_size);

	//$ref = new BfoxRef($passages);
	$ref = BfoxRefParser::with_groups($passages); // Allow groups to be used

	$readings = array();
	if ($ref->is_valid()) {
		$chunks = $ref->get_sections($chunk_size);

		foreach ($chunks as $chunk) if ($chunk->is_valid()) $readings []= $chunk->get_string();
	}

	return $readings;
}

/*
 * Reading Status Functions
 */

function bfox_plan_set_user_for_reading_statuses($user_id) {
	global $bfox_reading_statuses_user_id;
	$bfox_reading_statuses_user_id = $user_id;
}

function bfox_plan_get_user_for_reading_statuses() {
	global $bfox_reading_statuses_user_id, $user_ID;
	if (!$bfox_reading_statuses_user_id) return $user_ID;
	return $bfox_reading_statuses_user_id;
}

function bfox_plan_user_reading_statuses($user_id = 0) {
	if (!$user_id) $user_id = bfox_plan_get_user_for_reading_statuses();
	return (array) get_user_meta($user_id, 'bfox_reading_statuses', true);
}

function bfox_plan_reading_statuses($user_id = 0, $post_id = 0) {
	if (!$post_id) $post_id = $GLOBALS['post']->ID;
	$statuses = bfox_plan_user_reading_statuses($user_id);

	if (isset($statuses[$post_id])) return (array) $statuses[$post_id];
	else return false;
}

function bfox_plan_update_reading_status($value, $reading_id, $user_id, $post_id) {
	if ($user_id && $post_id) {
		$statuses = (array) get_user_meta($user_id, 'bfox_reading_statuses', true);
		if ($value) $statuses[$post_id][$reading_id] = $value;
		else unset($statuses[$post_id][$reading_id]);

		if (empty($statuses[$post_id])) unset($statuses[$post_id]);

		update_user_meta($user_id, 'bfox_reading_statuses', $statuses);
	}
}

function bfox_plan_user_followed_plans($user_id = 0) {
	if (!$user_id) $user_id = bfox_plan_get_user_for_reading_statuses();
	return (array) get_user_meta($user_id, 'bfox_followed_plans', true);
}

function bfox_plan_user_set_follow($follow, $post_id, $user_id = 0) {
	if (!$user_id) $user_id = bfox_plan_get_user_for_reading_statuses();
	$follow = (int) $follow;

	$plans = bfox_plan_user_followed_plans($user_id);

	if ($follow && !isset($plans[$post_id])) {
		$plans[$post_id] = true;
	}
	else if (!$follow && isset($plans[$post_id])) {
		unset($plans[$post_id]);
	}

	update_user_meta($user_id, 'bfox_followed_plans', $plans);
}

function bfox_plan_ajax_post_reading_status() {
	if (wp_verify_nonce($_POST['nonce'], 'bfox')) {
		global $user_ID;
		$ids = (array) $_POST['status_id'];
		foreach ($ids as $id) {
			list($user_id, $post_id, $reading_id) = explode('-', str_replace('bfox-reading-status-', '', $id));
			if ($user_id && $user_id == $user_ID) bfox_plan_update_reading_status($_POST['checked'] && ($_POST['checked'] != 'false'), $reading_id, $user_id, $post_id);
		}
	}
	exit;
}
add_action('wp_ajax_bfox_plan_post_reading_status', 'bfox_plan_ajax_post_reading_status');

/*
 * WP Query Functions
 */

function bfox_plan_ids_for_user($user_id = 0) {
	$statuses = (array) bfox_plan_user_reading_statuses($user_id);
	return (array) array_keys($statuses);
}

function bfox_plan_query_for_user($user_id = 0) {
	return array(
		'post_type' => 'bfox_plan',
		'post__in' => bfox_plan_ids_for_user($user_id),
	);
}

function bfox_plan_query_root() {
	return array(
		'post_type' => 'bfox_plan',
		'post_parent' => 0,
	);
}

function is_bfox_plan() {
	global $wp_query;
	return $wp_query->is_bfox_plan;
}

function is_bfox_plan_reading() {
	return (bool) bfox_plan_query_reading();
}

function bfox_plan_query_reading() {
	global $wp_query;
	return $wp_query->bfox_plan_reading;
}

function bfox_plan_parse_query($query) {
	if ('bfox_plan' == $query->query_vars['post_type']) {
		$query->is_bfox_plan = true;

		// Try to get a reading number from the reading or page query vars
		// (page is used so we don't have to modify rewrite rules to have queries like plans/[reading-plan-name]/[reading-num]/)
		if ($query->query_vars['reading']) $query->bfox_plan_reading = (int) $query->query_vars['reading'];
		else if ($query->is_single && $query->query_vars['page']) $query->bfox_plan_reading = (int) trim($query->query_vars['page'], '/');
	}
}
add_action('parse_query', 'bfox_plan_parse_query');

/*
 * Theme Template Functions
 */

function bfox_plan_reading_template() {
	$templates = array('single-bfox_plan_reading.php', 'single-bfox_plan.php', 'single.php');
	return apply_filters('bfox_plan_reading_template', locate_template($templates));
}

function bfox_plan_template_redirect() {
	if (is_bfox_plan_reading()) {
		// @todo redirect to the Bible page if the user has this option set
		if (0/*check user option*/) {
			wp_redirect(bfox_ref_blog_url(bfox_plan_reading_ref_str(bfox_plan_query_reading() - 1)));
			exit;
		}

		if ( $template = apply_filters( 'template_include', bfox_plan_reading_template() ) ) {
			include( $template );
			exit;
		}
	}
}
add_action('template_redirect', 'bfox_plan_template_redirect');

function bfox_plan_do_feed_readings() {
	if (is_single()) {
		global $post;
		if ('bfox_plan' == $post->post_type) {
			$feed = get_query_var( 'feed' );
			if ('readings-rss' == $feed) load_template(BFOX_PLANS_DIR . '/feed-readings.php');
			else if ('readings-ical' == $feed) load_template(BFOX_PLANS_DIR . '/feed-readings-ical.php');
		}
	}
}

?>