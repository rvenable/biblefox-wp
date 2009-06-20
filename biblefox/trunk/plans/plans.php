<?php

define(BFOX_TABLE_READING_PLANS, BFOX_BASE_TABLE_PREFIX . 'reading_plans');
define(BFOX_TABLE_READING_SUBS, BFOX_BASE_TABLE_PREFIX . 'reading_subs');
define(BFOX_TABLE_READINGS, BFOX_BASE_TABLE_PREFIX . 'readings');

class BfoxReadingPlan {
	const date_format_normal = 'M j, Y';
	const date_format_fixed = 'Y-m-d';

	const frequency_day = 0;
	const frequency_week = 1;
	const frequency_month = 2;

	const frequency_array_day = 0;
	const frequency_array_daily = 1;

	const days_week_array_normal = 0;
	const days_week_array_full = 1;
	const days_week_array_short = 2;

	const freq_options_default = '0123456';
	const reading_id_invalid = -1;

	public $id = 0;
	public $is_private = FALSE;
	public $copied_from_id = 0;

	public $name = '';
	public $description = '';
	public $readings = array();

	public $is_scheduled = TRUE;
	private $start_time = 0;
	private $end_time = 0;
	public $is_recurring = FALSE;
	public $frequency = 0;
	private $frequency_options = self::freq_options_default;

	private $dates = array();
	public $current_reading_id = self::reading_id_invalid;

	private $history_refs = NULL;

	public function __construct($values = NULL) {
		if (is_object($values)) $this->set_from_db($values);
		if (empty($this->start_time)) $this->set_start_date();
	}

	public function set_from_db(stdClass $db_data) {
		$this->id = $db_data->id;
		$this->copied_from_id = $db_data->copied_from_id;
		$this->is_private = $db_data->is_private;

		$this->name = $db_data->name;
		$this->description = $db_data->description;

		$this->is_scheduled = $db_data->is_scheduled;
		$this->set_start_date($db_data->start_date);
		$this->end_time = strtotime($db_data->end_date);
		$this->is_recurring = $db_data->is_recurring;
		$this->frequency = $db_data->frequency;
		$this->set_freq_options($db_data->frequency_options);
	}

	public function set_as_copy() {
		$this->copied_from_id = $this->id;
		$this->id = 0;
		$this->name = "Copy of $this->name";
	}

	/**
	 * Called when all the initializing data has been set for a plan.
	 *
	 * Finishes off the unset data for the plan, such as sorting the readings and setting dates
	 */
	public function finish_setting_plan() {
		// Sort the readings
		ksort($this->readings);

		// If there is a schedule, perform all the date calculations
		if ($this->is_scheduled) {
			$reading_count = count($this->readings);

			// Get the dates for all the readings + 1 (the +1 is for the end date)
			$this->dates = $this->get_dates($reading_count + 1);

			// Set the end_time using the last date (-1 day)
			$this->end_time = strtotime('-1 day', $this->dates[$reading_count]);

			// Set the index of the current reading
			$this->current_reading_id = self::current_date_index($this->dates);

			// If the end date is the current reading, this has ended
			if ($reading_count == $this->current_reading_id) $this->current_reading_id = self::reading_id_invalid;
		}
		else $this->current_reading_id = self::reading_id_invalid;
	}

	public function is_current() {
		return (self::reading_id_invalid != $this->current_reading_id);
	}

	/*
	 * Reading List Functions
	 */

	public function desc_html() {
		return wpautop($this->description);
	}

	public function set_reading(BfoxRefs $refs, $reading_id = -1) {
		if ($refs->is_valid()) {
			// If the reading id is not already there, we should just add it to the end
			if (!isset($this->readings[$reading_id])) $this->readings []= $refs;
			else $this->readings[$reading_id] = $refs;
		}
	}

	public function add_verses($reading_id, $verse_start, $verse_end) {
		if (!isset($this->readings[$reading_id])) $this->readings[$reading_id] = new BfoxRefs;
		$this->readings[$reading_id]->add_seq(BfoxSequence($verse_start, $verse_end));
	}

	public function set_readings_by_strings($strings) {
		if (is_string($strings)) $strings = explode("\n", $strings);

		$this->readings = array();
		foreach ((array) $strings as $str) $this->set_reading(new BfoxRefs($str));
	}

	public function add_passages($passages, $chunk_size) {
		$refs = new BfoxRefs($passages);
		$chunks = $refs->get_sections($chunk_size);
		foreach ($chunks as $chunk) $this->set_reading($chunk);
	}

	public function ref_string() {
		$ref_str = '';

		if (!empty($this->readings)) {
			$refs = new BfoxRefs;
			foreach ($this->readings as $reading) $refs->add_refs($reading);
			if ($refs->is_valid()) $ref_str = $refs->get_string();
		}

		return $ref_str;
	}

	public function reading_strings($name = BibleMeta::name_normal) {
		$strings = array();
		foreach ($this->readings as $reading) $strings []= $reading->get_string($name);
		return $strings;
	}

	/*
	 * Schedule Functions
	 */

	public function set_start_date($start_date = '') {
		if (empty($start_date)) $start_date = 'today';
		$this->start_time = strtotime($start_date);
	}

	public function set_freq_options($options) {
		if (is_array($options)) $options = implode('', $options);
		if (empty($options)) $options = self::freq_options_default;
		$this->frequency_options = $options;
	}

	public static function frequency_array() {
		return array (
			self::frequency_array_day => array(self::frequency_day => 'day', self::frequency_week => 'week', self::frequency_month => 'month'),
			self::frequency_array_daily => array(self::frequency_day => 'daily', self::frequency_week => 'weekly', self::frequency_month => 'monthly')
		);
	}

	public function frequency_str($type = self::frequency_array_day)
	{
		$strings = self::frequency_array();
		return $strings[$type][$this->frequency];
	}

	public static function days_week_array() {
		return array (
			self::days_week_array_normal => array('sun', 'mon', 'tues', 'wed', 'thurs', 'fri', 'sat'),
			self::days_week_array_full => array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'),
			self::days_week_array_short => array('S', 'M', 'T', 'W', 'Th', 'F', 'Sa')
		);
	}

	public function days_week_str($type = self::days_week_array_short, $default_return = '')
	{
		if (self::frequency_day == $this->frequency) {
			if (empty($this->frequency_options) || (self::freq_options_default == $this->frequency_options)) return $default_return;
			else {
				if (self::days_week_array_short != $type) $glue = ', ';

				$day_strs = array();
				$strings = self::days_week_array();
				$days = $this->freq_options_array();
				foreach ($days as $day => $is_valid) if ($is_valid) $day_strs []= $strings[$type][$day];
				return implode($glue, $day_strs);
			}
		}
	}

	public function freq_options_array() {
		return array_fill_keys(str_split($this->frequency_options), TRUE);
	}

	public function get_freq_options() {
		return $this->frequency_options;
	}

	public function frequency_desc() {
		$desc = ucfirst($this->frequency_str(self::frequency_array_daily));
		$days = $this->days_week_str();
		if (!empty($days)) $desc .= ", $days";
		return $desc;
	}

	public function time($index = 0) {
		return $this->dates[$index];
	}

	public function date($index = 0, $format = self::date_format_fixed) {
		return date($format, $this->dates[$index]);
	}

	public function start_date($format = self::date_format_fixed) {
		return date($format, $this->start_time);
	}

	public function end_date($format = self::date_format_fixed) {
		return date($format, $this->end_time);
	}

	public function history_start_date($format = self::date_format_fixed) {
		// We start tracking history from one week before the start of the plan
		// NOTE: if we change to be the exact date, there will be time zone issues (right now there are issues, but they're obfuscated by the week buffer time)
		return date($format, strtotime('-1 week', $this->start_time));
	}

	private function get_dates($count) {

		if (self::frequency_day == $this->frequency) {
			$select_format = 'w';
			$select_values = $this->freq_options_array();
		}

		$inc_str = '+1 ' . $this->frequency_str();

		$dates = array();
		$date = $this->start_time;
		for ($index = 0; $index < $count; $index++) {
			// If we have select_values, increment until we find a selected value
			if (!empty($select_values)) while (!$select_values[date($select_format, $date)]) $date = strtotime($inc_str, $date);

			$dates []= $date;
			$date = strtotime($inc_str, $date);
		}

		return $dates;
	}

	private static function current_date_index($dates, $now = 0) {

		// If now is empty, get now according to the local blog settings, formatted as an integer number of seconds
		if (empty($now)) $now = strtotime(date(self::date_format_fixed, BfoxUtility::adjust_time(time())));

		foreach ($dates as $index => $date) {
			if ($date > $now) break;
			else $current = $index;
		}

		if (isset($current)) return $current;
		else return -1;
	}

	/*
	 * History related
	 */

	public function set_history($history_array) {

		// Create the history refs
		$history_refs = new BfoxRefs;

		// Accumulate all the history references since the starting date of this plan
		$start_time = strtotime($this->history_start_date());
		foreach ($history_array as $history) if ($history->time >= $start_time) $history_refs->add_refs($history->refs);

		if ($history_refs->is_valid()) {
			$this->history_refs = $history_refs;
			return TRUE;
		}
		return FALSE;
	}

	public function get_history_refs() {
		if ($this->history_refs instanceof BfoxRefs) return $this->history_refs;
		return new BfoxRefs;
	}

	public function get_unread(BfoxRefs $reading) {
		$unread = new BfoxRefs($reading);
		if ($this->history_refs instanceof BfoxRefs) $unread->sub_refs($this->history_refs);
		return $unread;
	}
}

class BfoxReadingSub {

	public $plan_id = 0;
	public $user_id = 0;
	public $user_type = BfoxPlans::user_type_user;
	public $is_subscribed = FALSE;
	public $is_owned = FALSE;
	public $is_finished = FALSE;

	public function __construct($values = NULL, $plan_id = NULL, $user_id = NULL, $user_type = NULL) {
		if (is_object($values)) $this->set_from_db($values);

		// If a plan or user are passed in, then we must use them
		if (!is_null($plan_id)) $this->plan_id = $plan_id;
		if (!is_null($user_id)) $this->user_id = $user_id;
		if (!is_null($user_type)) $this->user_type = $user_type;
	}

	public function set_from_db(stdClass $db_data) {
		$this->plan_id = $db_data->plan_id;
		$this->user_id = $db_data->user_id;
		$this->user_type = $db_data->user_type;
		$this->is_subscribed = $db_data->is_subscribed;
		$this->is_owned = $db_data->is_owned;
		$this->is_finished = $db_data->is_finished;
	}

	public function user_name() {
		if (!empty($this->user_id)) {
			switch ($this->user_type)
			{
				case BfoxPlans::user_type_blog: return get_blog_option($this->user_id, 'blogname');
				case BfoxPlans::user_type_user: return get_author_name($this->user_id);
			}
		}
	}

	public function user_link() {
		if (!empty($this->user_id)) {
			$name = $this->user_name();
			switch ($this->user_type)
			{
				case BfoxPlans::user_type_blog: return "<a href='" . get_blogaddress_by_id($this->user_id) . "'>" . $name . "</a>";
				case BfoxPlans::user_type_user:
					$user = get_userdata($user_id);
					return "<a href='" . $user->url . "'>" . $user->display_name . "</a>";
			}
		}
	}

	public function is_visible(BfoxReadingPlan $plan) {
		// We can only view plans that we have subscribed to or aren't private
		// We also make sure that the plan we were passed is actually the right plan for this subscription
		return ($plan->id == $this->plan_id) && ($this->is_subscribed || $this->is_owned || !$plan->is_private);
	}
}

class BfoxReadingPlanGlobal {
}

class BfoxPlans {
	const table_plans = BFOX_TABLE_READING_PLANS;
	const table_subs = BFOX_TABLE_READING_SUBS;
	const table_readings = BFOX_TABLE_READINGS;

	const user_type_user = 0;
	const user_type_blog = 1;

	public static function create_tables() {

		BfoxUtility::create_table(self::table_plans, "
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			copied_from_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			name TINYTEXT NOT NULL,
			description TEXT NOT NULL,
			is_private BOOLEAN NOT NULL,
			is_scheduled BOOLEAN NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NOT NULL,
			is_recurring BOOLEAN NOT NULL,
			frequency TINYINT UNSIGNED NOT NULL,
			frequency_options TINYTEXT NOT NULL,
			PRIMARY KEY  (id)");

		BfoxUtility::create_table(self::table_readings, "
			plan_id BIGINT UNSIGNED NOT NULL,
			reading_id MEDIUMINT UNSIGNED NOT NULL,
			verse_begin MEDIUMINT UNSIGNED NOT NULL,
			verse_end MEDIUMINT UNSIGNED NOT NULL");

		// Note: for blog_id and user_id (aka. user) see WP's implementation in wp-admin/includes/schema.php
		BfoxUtility::create_table(self::table_subs, "
			user_id BIGINT(20) UNSIGNED NOT NULL,
			user_type TINYINT(1) NOT NULL,
			plan_id BIGINT UNSIGNED NOT NULL,
			is_subscribed BOOLEAN NOT NULL,
			is_owned BOOLEAN NOT NULL,
			is_finished BOOLEAN NOT NULL,
			PRIMARY KEY  (user_id, user_type, plan_id)");
	}

	public static function save_plan(BfoxReadingPlan &$plan) {
		global $wpdb;

		$set = $wpdb->prepare(
			"SET copied_from_id = %d, name = %s, description = %s, is_private = %d, is_scheduled = %d, start_date = %s, end_date = %s, is_recurring = %d, frequency = %d, frequency_options = %s",
			$plan->copied_from_id, $plan->name, $plan->description, $plan->is_private, $plan->is_scheduled, $plan->start_date(), $plan->end_date(), $plan->is_recurring, $plan->frequency, $plan->get_freq_options());

		if (empty($plan->id)) $wpdb->query("INSERT INTO " . self::table_plans . " $set");
		else {
			$wpdb->query($wpdb->prepare("UPDATE " . self::table_plans . " $set WHERE id = %d", $plan->id));
			$wpdb->query($wpdb->prepare("DELETE FROM " . self::table_readings . " WHERE plan_id = %d", $plan->id));
		}

		if (empty($plan->id)) $plan->id = $wpdb->insert_id;

		if (!empty($plan->readings)) {
			$values = array();
			foreach ($plan->readings as $reading_id => $reading)
				foreach ($reading->get_seqs() as $seq)
					$values []= $wpdb->prepare('(%d, %d, %d, %d)', $plan->id, $reading_id, $seq->start, $seq->end);

			$wpdb->query($wpdb->prepare("
				INSERT INTO " . self::table_readings . "
				(plan_id, reading_id, verse_begin, verse_end)
				VALUES " . implode(',', $values)));
		}
	}

	public static function get_plans($plan_ids) {
		global $wpdb;

		$plans = array();

		$ids = array();
		if (!empty($plan_ids)) foreach ($plan_ids as $plan_id) if (!empty($plan_id)) $ids []= $wpdb->prepare('%d', $plan_id);

		if (!empty($ids)) {
			// Get the plan info from the DB
			$results = $wpdb->get_results('SELECT * FROM ' . self::table_plans . ' WHERE id IN (' . implode(',', $ids) . ')');

			// Create each BfoxReadingPlan instance
			$ids = array();
			foreach ($results as $result) {
				$plans[$result->id] = new BfoxReadingPlan($result);
				$ids []= $wpdb->prepare('%d', $result->id);
			}

			if (!empty($ids)) {
				// Get the reading info from the DB
				$readings = $wpdb->get_results('SELECT * FROM ' . self::table_readings . ' WHERE plan_id IN (' . implode(',', $ids) . ')');

				// Add all the readings to the reading plan
				foreach ($readings as $reading) $plans[$reading->plan_id]->add_verses($reading->reading_id, $reading->verse_begin, $reading->verse_end);

				foreach ($plans as &$plan) $plan->finish_setting_plan();
			}
		}

		return $plans;
	}

	public static function get_plan($plan_id) {
		if (!empty($plan_id)) {
			$plans = self::get_plans(array($plan_id));
			if (isset($plans[$plan_id])) return $plans[$plan_id];
		}
		return new BfoxReadingPlan();
	}

	public static function delete_plan(BfoxReadingPlan $plan) {
		global $wpdb;
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::table_plans . " WHERE id = %d", $plan->id));
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::table_readings . " WHERE plan_id = %d", $plan->id));
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::table_subs . " WHERE plan_id = %d", $plan->id));
	}

	public static function save_sub(BfoxReadingSub &$sub) {
		global $wpdb;

		// Only save if it is either subscribed or owned,
		// Otherwise the subscription is invalid and should be deleted
		if ($sub->is_subscribed || $sub->is_owned) $wpdb->query($wpdb->prepare("REPLACE INTO " . self::table_subs . "
			SET plan_id = %d, user_id = %d, user_type = %d, is_subscribed = %d, is_owned = %d, is_finished = %d",
			$sub->plan_id, $sub->user_id, $sub->user_type, $sub->is_subscribed, $sub->is_owned, $sub->is_finished));

		else $wpdb->query($wpdb->prepare("DELETE FROM " . self::table_subs . "
			WHERE plan_id = %d AND user_id = %d AND user_type = %d",
			$sub->plan_id, $sub->user_id, $sub->user_type));
	}

	private static function get_subs($where, $unique_plan_ids = FALSE) {
		global $wpdb;

		// Get the subscription info from the DB
		$results = $wpdb->get_results("SELECT * FROM " . self::table_subs . " WHERE $where");

		// Create each BfoxReadingSub instance
		$subs = array();

		// If the plan ids are unique, we can use them as keys, otherwise just use a non-associative array
		if ($unique_plan_ids) foreach ($results as $result) $subs[$result->plan_id] = new BfoxReadingSub($result);
		else foreach ($results as $result) $subs []= new BfoxReadingSub($result);

		return $subs;
	}

	public static function get_user_subs($user_id, $user_type) {
		global $wpdb;
		return self::get_subs($wpdb->prepare('user_id = %d AND user_type = %d', $user_id, $user_type), TRUE);
	}

	public static function get_plan_subs(BfoxReadingPlan $plan) {
		global $wpdb;
		return self::get_subs($wpdb->prepare('plan_id = %d', $plan->id));
	}

	public static function get_sub(BfoxReadingPlan $plan, $user_id, $user_type) {
		global $wpdb;
		// Return the plan from the DB, if there is no data in the DB, the new sub will still be set for this plan and user
		return new BfoxReadingSub($wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table_subs . " WHERE plan_id = %d AND user_id = %d AND user_type = %d", $plan->id, $user_id, $user_type)), $plan->id, $user_id, $user_type);
	}

	public static function get_user_plans($user_id, $user_type) {
		$subs = BfoxPlans::get_user_subs($user_id, $user_type);

		foreach ($subs as $sub) $plan_ids []= $sub->plan_id;

		$plans = BfoxPlans::get_plans($plan_ids);

		return array($plans, $subs);
	}
}

?>