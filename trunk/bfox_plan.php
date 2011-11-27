<?php

require_once BFOX_REF_DIR . '/bfox_plan_parser.php';
require_once BFOX_REF_DIR . '/bfox_plan_scheduler.php';

function bfox_plans_create_post_type() {
	//TODO: add plans directory template archive - see: http://www.ballyhooblog.com/custom-post-types-wordpress-30-with-template-archives/

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
			'rewrite' => array('slug' => 'reading-plans'),
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

	add_meta_box('bfox-plan-view', __('View Readings', 'bfox'), 'bfox_plans_view_meta_box_cb', 'bfox_plan', 'normal', 'high');
	add_meta_box('bfox-plan-content', __('Edit Readings', 'bfox'), 'bfox_plans_content_meta_box_cb', 'bfox_plan', 'normal', 'high');
	add_meta_box('bfox-plan-append', __('Append Chapters in Bulk', 'bfox'), 'bfox_plans_append_meta_box_cb', 'bfox_plan', 'normal', 'high');
	add_meta_box('bfox-plan-schedule', __('Edit Schedule', 'bfox'), 'bfox_plans_edit_schedule_meta_box_cb', 'bfox_plan', 'side', 'low');
}

/*
 * Meta Box Callbacks
 */

function bfox_plans_view_meta_box_cb() {
	?>
<?php if (bfox_plan_reading_list(array('column_class' => 'reading-list-4c-h'))): ?>
	<p><?php _e('In total, this reading plan covers all of the following passages:', 'bfox') ?> <?php echo bfox_ref_link(bfox_plan_total_ref_str(0, BibleMeta::name_short)) ?></p>
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
//add_filter('page_row_actions', 'bfox_plans_table_row_actions', 10, 2);

/*
 * Saving Reading Plans
 */

function bfox_plan_admin_init_append_content() {
	if (isset($_POST['content']) && !empty($_POST['bfox-plan-append']) && !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
		$parser = new BfoxPlanParser;
		$parser->parsePassagesIntoReadings($_POST['bfox-plan-append'], $_POST['bfox-plan-chunk-size']);
		$readings = $parser->readingRefStrings();
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
		$scheduler = new BfoxPlanScheduler;
		$scheduler->setStartDate($_POST['schedule-start']);

		// If the start date is a real date, then save it in Y-m-d form
		// Otherwise, save an empty string as the start date (which means no schedule is being used)
		if ($scheduler->startTime()) {

			// Schedule Frequency
			$scheduler->setFrequency($_POST['schedule-frequency']);
			$scheduler->setReadingsPerDate($_POST['schedule-per-day']);

			// Excluded Dates
			$new_excludes = $_POST['schedule-exclude'];
			$new_excludes = explode("\n", str_replace(array(';'), "\n", $new_excludes));
			foreach ($new_excludes as $new_exclude) {
				$multi = explode(':', $new_exclude);
				if (1 == count($multi)) $scheduler->excludeDate($multi[0]);
				else $scheduler->excludeDateRange($multi);
			}

			// Days of the Week exclusions
			$scheduler->setDaysOfWeek($_POST['schedule-days']);

			bfox_plan_update_meta('start_date', $scheduler->startDate(), $post_id);
			bfox_plan_update_meta('frequency', $scheduler->frequency(), $post_id);
			bfox_plan_update_meta('per_day', $scheduler->readingsPerDate(), $post_id);
			bfox_plan_update_meta('excluded_days', $scheduler->excludedDates(), $post_id);
		}
		else bfox_plan_update_meta('start_date', '', $post_id);

		bfox_plan_update_reading_data($post_id, $scheduler);
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
function bfox_plan_update_reading_data($post_id = 0, $scheduler = null) {
	$post = get_post($post_id);
	if (!$scheduler) $scheduler = bfox_plan_scheduler_for_post($post_id);

	$reading_data = new stdClass();
	$reading_data->dates = array();
	$reading_data->latest_reading_id = -1;

	$parser = new BfoxPlanParser;
	$parser->parseContent($post->post_content, "\n");
	$reading_data->refs = $parser->readingRefs;
	$reading_data->leftovers = $parser->readingLeftovers;

	if ($scheduler->startTime()) {
		$scheduler->pushNumDates(count($reading_data->refs));
		$reading_data->dates = $scheduler->dates();
		$reading_data->latest_reading_id = $scheduler->latestDateIndex();
	}

	wp_cache_set($post_id, $reading_data, 'bfox_plan_reading_data');

	return $reading_data;
}

function bfox_plan_scheduler_for_post($post_id) {
	$scheduler = new BfoxPlanScheduler;
	$scheduler->setStartDate(bfox_plan_meta('start_date', $post_id));
	if ($scheduler->startTime()) {
		$scheduler->setFrequency(bfox_plan_meta('frequency', $post_id));
		$scheduler->setReadingsPerDate(bfox_plan_meta('per_day', $post_id));
		$scheduler->setExcludedDates(bfox_plan_meta('excluded_days', $post_id));
	}
	return $scheduler;
}

/*
 * WP Query Functions
 */

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
		if (isset($query->query_vars['reading'])) $query->bfox_plan_reading = (int) $query->query_vars['reading'];
		else if ($query->is_single && $query->query_vars['page']) $query->bfox_plan_reading = (int) trim($query->query_vars['page'], '/');
	}
}
add_action('parse_query', 'bfox_plan_parse_query');

// Replace the_content() results with a better formatted reading plan
function bfox_plan_replace_content($content) {
	global $_bfox_plan_already_replacing_content;
	if (!$_bfox_plan_already_replacing_content && 'bfox_plan' == get_post_type()) {
		// Make sure we don't recursively replace the content, if the_content() is called within template
		$_bfox_plan_already_replacing_content = true;

		ob_start();
		load_bfox_template('content-bfox_plan');
		$content = ob_get_clean();

		$_bfox_plan_already_replacing_content = false;
	}
	return $content;
}
add_filter('the_content', 'bfox_plan_replace_content');

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

function bfox_plan_reading_shortcode($atts) {
	// [bible-reading plan="cbr" intersects="old"]

	extract( shortcode_atts( array(
		'post_id' => 0,
		'reading' => 'latest',
		'intersects' => '',
		'tool' => '',
	), $atts ) );

	if (empty($post_id)) return;

	if ($reading == 'latest') {
		$reading = bfox_plan_latest_reading($post_id);
	}

	$reading_id = intval($reading);
	if ($reading_id >= bfox_plan_reading_count($post_id)) return;

	$ref_str = bfox_plan_reading_ref_str($reading_id, $post_id);

	if (!empty($intersects)) {
		$ref = new BfoxRef($ref_str);
		$sub = new BibleGroupPassage('bible');
		$sub->sub_ref(new BibleGroupPassage($intersects));
		$ref->sub_ref($sub);
		$ref_str = $ref->get_string();
	}

	$link = bfox_ref_link($ref_str);
	$content = $link;

	if (!empty($tool)) {
		$passage = bfox_tool_shortcode(array('tool' => $tool, 'ref' => $ref_str));
		$content = "$link $passage";
	}

	return $content;
}
add_shortcode('bible-reading', 'bfox_plan_reading_shortcode');

?>