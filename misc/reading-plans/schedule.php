<?php

class BfoxReadingSchedule extends BfoxOwnedObject {
	public $id = 0;
	public $plan_id = 0;
	public $revision_id = 0;
	public $reading_count = 0;

	private $label = '';
	public $note = '';

	public $is_auto_sync = true;

	private $start_time = 0;
	private $end_time = 0;
	public $is_recurring = false;

	public $time_created = 0;
	public $time_modified = 0;

	private $next_email_time = 0;

	public function __construct($db_data = NULL) {
		if (!is_null($db_data) && is_object($db_data)) {
			$this->id = $db_data->id;
			$this->plan_id = $db_data->plan_id;
			$this->revision_id = $db_data->revision_id;
			$this->reading_count = $db_data->reading_count;

			$this->owner_id = $db_data->owner_id;
			$this->owner_type = $db_data->owner_type;

			$this->label = $db_data->label;
			$this->note = $db_data->note;

			$this->is_auto_sync = $db_data->is_auto_sync;

			$this->start_time = $db_data->start_time;
			$this->end_time = $db_data->end_time;
			$this->is_recurring = $db_data->is_recurring;

			$this->time_created = $db_data->time_created;
			$this->time_modified = $db_data->time_modified;

			$this->next_email_time = $db_data->next_email_time;

			$this->serialized_meta($db_data->meta);
		}
		else {
			$this->start_time = $this->end_time = self::time();
			$this->set_user_owner();
			$this->days_of_week('');
		}
	}

	public function save($update_time = true) {
		global $wpdb, $user_ID;

		if ($update_time) $this->time_modified = time();

		// If we don't have an owner, set the current user as the owner
		if (empty($this->owner_id)) $this->set_user_owner();

		// Make sure the dates are up to date before we save
		$this->update_dates();

		$set = $wpdb->prepare(
			"SET plan_id = %d, revision_id = %d, reading_count = %d, owner_id = %d, owner_type = %d, label = %s, note = %s, is_auto_sync = %d, start_time = %d, end_time = %d, is_recurring = %d, time_modified = %d, next_email_time = %d, meta = %s",
			$this->plan_id, $this->revision_id, $this->reading_count, $this->owner_id, $this->owner_type, $this->label, $this->note, $this->is_auto_sync, $this->start_time, $this->end_time, $this->is_recurring, $this->time_modified, $this->next_email_time, $this->serialized_meta());

		if (empty($this->id)) {
			$this->time_created = $this->time_modified;
			$wpdb->query($wpdb->prepare("INSERT INTO " . self::$table_name . " $set, time_created = %d", $this->time_created));
			$this->id = $wpdb->insert_id;

			$plan = $this->plan();
			$plan->update_user_count();
			$plan->save(false);
		}
		else {
			$wpdb->query($wpdb->prepare("UPDATE " . self::$table_name . " $set WHERE id = %d", $this->id));
		}

		return $this->id;
	}

	/**
	 * @return BfoxReadingPlan
	 */
	public function plan() {
		return BfoxReadingPlan::plan($this->plan_id);
	}

	public function label() {
		if (!empty($this->label)) return $this->label;

		$plan = $this->plan();
		return $plan->name;
	}

	public function set_label($label) {
		$plan = $this->plan();
		if ($label == $plan->name) $label = '';

		$this->label = $label;
	}

	/*
	 * Meta Functions
	 */

	private $_meta = array();

	public function serialized_meta($serialized_meta = null) {
		if (!is_null($serialized_meta)) $this->_meta = (array) unserialize($serialized_meta);
		else $serialized_meta = serialize((array) $this->_meta);

		return $serialized_meta;
	}

	public function meta($key, $value = null) {
		if (!is_null($value)) $this->_meta[$key] = $value;
		return $this->_meta[$key];
	}

	/*
	 * Date/Time Functions
	 */

	public function set_start_date($start_date = '') {
		$old_start_time = $this->start_time;
		$this->start_time = self::time($start_date);

		if ($old_start_time != $this->start_time) $this->_modified_dates = true;
	}

	public function is_active($time = 0) {
		if (!$time) $time = self::time();
		return (($time >= $this->start_time) && ($time - 24 * 60 * 60 <= $this->end_time));
	}

	private $_modified_dates = false;
	private $_reading_times = null;
	private $_latest_reading_id = null;
	private $_latest_reading = null;

	public function update_dates() {
		if ($this->_modified_dates) {
			// Reset cached data
			$this->_modified_dates = false;
			$this->_reading_times = null;
			$this->_latest_reading_id = null;
			$this->_latest_reading = null;

			// Update the end date
			$this->end_time = $this->reading_time(-1);

			// Because the dates have changed, we should send the latest email again
			$this->send_email();
		}
	}

	public function setup_reading_times() {
		$this->_reading_times = self::calculate_reading_times($this->start_time, max(1, $this->reading_count), $this->frequency(), $this->days_of_week_array(), $this->_latest_reading_id);
	}

	public function latest_reading_id() {
		if (is_null($this->_latest_reading_id)) $this->setup_reading_times();
		return $this->_latest_reading_id;
	}

	/**
	 * @param array of BfoxRef $readings
	 * @return BfoxRef
	 */
	private function latest_reading($readings = null) {
		// If there is a readings array passed in, set the latest reading from it
		if (!is_null($readings)) $this->_latest_reading = $readings[$this->latest_reading_id()];

		// If we don't have a cached latest reading, get it
		if (is_null($this->_latest_reading)) {
			$readings = $this->readings();
			$this->_latest_reading = $readings[$this->latest_reading_id()];
		}

		return $this->_latest_reading;
	}

	private function reading_time($reading_id = null) {
		if (is_null($this->_reading_times)) $this->setup_reading_times();

		if (is_null($reading_id)) $reading_id = $this->latest_reading_id();
		if ($reading_id < 0) $reading_id += count($this->_reading_times);
		return $this->_reading_times[$reading_id];
	}

	public function start_date($format = '') {
		return self::date($this->start_time, $format);
	}

	public function end_date($format = '') {
		return self::date($this->end_time, $format);
	}

	public function latest_date($format = '') {
		return $this->reading_date($this->latest_reading_id(), $format);
	}

	public function reading_date($reading_id, $format = '') {
		return self::date($this->reading_time($reading_id), $format);
	}

	public static function date($time = 0, $format = '') {
		if (!$time) $time = self::time();
		if (empty($format)) $format = 'Y-m-d';
		return gmdate($format, $time);
	}

	public static function time($date = '') {
		if (empty($date)) $date = 'today';
		$time = strtotime($date);
		return gmmktime(0, 0, 0, date('n', $time), date('j', $time), date('Y', $time));
	}

	public function readings_in_range($start_date, $end_date) {
		$reading_ids = array();
		$start_time = self::time($start_date);
		$end_time = self::time($end_date);

		if ($start_time <= $this->end_time && $end_time >= $this->start_time) {
			// Start with this schedule's first reading and loop through them until one is in the range
			$reading_id = 0;
			$reading_time = $this->start_time;
			while ($reading_time && $reading_time < $start_time) $reading_time = $this->reading_time(++$reading_id);

			// While the readings are in the range, add them to the array
			while ($reading_time && $reading_time <= $end_time) {
				$reading_ids []= $reading_id;
				$reading_time = $this->reading_time(++$reading_id);
			}
		}

		return $reading_ids;
	}

	public function frequency($value = null) {
		if (!is_null($value)) {
			$old_value = (int) $this->meta('frequency');
			if ($old_value != $value) $this->_modified_dates = true;
		}
		return (int) $this->meta('frequency', $value);
	}

	public function days_of_week($value = null) {
		if (!is_null($value)) {
			if (empty($value)) $value = self::freq_options_default;

			$old_value = (int) $this->meta('days_of_week');
			if ($old_value != $value) $this->_modified_dates = true;
		}
		return $this->meta('days_of_week', $value);
	}

	public function days_of_week_array() {
		return array_fill_keys(str_split($this->days_of_week()), TRUE);
	}

	public static function days_week_array() {
		return array (
			self::days_week_array_normal => array('sun', 'mon', 'tues', 'wed', 'thurs', 'fri', 'sat'),
			self::days_week_array_full => array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'),
			self::days_week_array_short => array('S', 'M', 'T', 'W', 'Th', 'F', 'Sa')
		);
	}

	public function days_week_str($type = self::days_week_array_short, $default_return = '') {
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

	public function frequency_desc() {
		$desc = ucfirst($this->frequency_str(self::frequency_array_daily));
		$days = $this->days_week_str();
		if (!empty($days)) $desc .= ", $days";
		return $desc;
	}

	public function schedule_desc() {
		if ($this->is_scheduled) {
			$desc = $this->start_date('M j, Y') . ' - ' . $this->end_date('M j, Y');
			if ($this->is_recurring) $desc .= ' (recurring)';
			$desc .= " (" . $this->frequency_desc() . ")";
		}
		else $desc = 'Unscheduled';
		return $desc;
	}

	public function update_next_email_time() {
		$old_time = $this->next_email_time;
		$this->next_email_time = $this->reading_time($this->latest_reading_id() + 1);

		// This was a successful update if the next email time increased (or reset to 0)
		$success = (0 == $this->next_email_time || $old_time < $this->next_email_time);
		return $success;
	}

	/*
	 * Readings and Reading Progress
	 */

	private $_is_read = null;
	public function is_read($reading_id) {
		if (is_null($this->_is_read)) {
			global $wpdb;
			$this->_is_read = array_fill_keys($wpdb->get_col($wpdb->prepare('SELECT reading_id FROM ' . self::$progress_table_name . ' WHERE schedule_id = %d AND user_id = %d', $this->id, $this->user_id())), true);
		}
		return (bool) $this->_is_read[$reading_id];
	}

	public function readings() {
		return BfoxReadingPlan::revision_readings($this->revision_id);
	}

	/*
	 * Template Tags
	 */

	public function url($user_id = 0) {
		global $bp;

		if (!$user_id) $user_id = $bp->displayed_user->id;

		if ($user_id && $this->is_user_member($user_id)) $url = bp_core_get_user_domain($user_id);
		else $url = $this->owner_url();

		return bfox_bp_schedule_url($url, $this->id);
	}

	public function delete_url() {
		return bfox_bp_schedule_delete_url($this->owner_url(), $this->id);
	}

	public function note_html() {
		return wpautop($this->note);
	}

	public function frequency_check($target) {
		if ($this->frequency() == $target) return ' checked="checked"';
	}

	public function day_of_week_check($target) {
		$days_of_week_array = $this->days_of_week_array();
		if ($days_of_week_array[$target]) return ' checked="checked"';
	}

	public function updated_status() {
		$created = sprintf(__('Created %s ago', 'bfox'), bp_core_time_since($this->time_created));
		if ($this->time_created != $this->time_modified)
			$created .= sprintf(__(' (updated %s ago)', 'bfox' ), bp_core_time_since($this->time_modified));
		return $created;
	}

	public function latest_reading_link($args = array()) {
		$args['ref'] = $this->latest_reading();
		if (!isset($args['name'])) $args['name'] = BibleMeta::name_short;
		if (!isset($args['disable_tooltip'])) $args['disable_tooltip'] = true;

		return bfox_ref_bible_link($args);
	}

	public function reading_url($reading_id) {
		return $this->url() . ($reading_id + 1) . '/';
	}

	public function mark_read_url($reading_id, $url = null) {
		return $this->reading_url($reading_id) . 'read/';
	}

	public function reading_check_id($reading_id) {
		return "$this->id-$reading_id";
	}

	public function reading_checkbox($reading_id) {
		$id = $this->reading_check_id($reading_id);

		$checked = '';
		if ($this->is_read($reading_id)) {
			self::$checkbox_ids []= $id;
			$checked = ' checked="checked"';
		}

		return "<input type='checkbox' name='bible_reading_plan_checkbox[]' id='bible-reading-$id' value='$id'$checked />";
	}

	public function view_button() {
		$button = '<div class="generic-button reading_plan_schedule-button" id="reading_plan_schedule-button-' . $this->id . '">';
		$button .= '<a href="' . $this->url() . '#view">' . __( 'View Schedule', 'bfox' ) . '</a>';
		$button .= '</div>';
		return $button;
	}

	public function edit_button() {
		$button = '<div class="generic-button reading_plan_schedule-button" id="reading_plan_schedule-button-' . $this->id . '">';
		$button .= '<a href="' . $this->url() . '#edit">' . __( 'Edit Schedule', 'bfox' ) . '</a>';
		$button .= '</div>';
		return $button;
	}

	public function copy_button() {
		$button = '<div class="generic-button reading_plan_copy-button" id="reading_plan_copy-button-' . $this->id . '">';
		$button .= '<a href="">' . __( 'Copy', 'bfox' ) . '</a>';
		$button .= '</div>';
		return $button;
	}

	public function delete_button() {
		$button = '<div class="generic-button reading_plan_delete-button" id="reading_plan_delete-button-' . $this->id . '">';
		$button .= '<a href="' . $this->delete_url() . '#view">' . __( 'Delete Schedule', 'bfox' ) . '</a>';
		$button .= '</div>';
		return $button;
	}

	public function avatar($args = '') {
		return $this->owner_avatar($args);
	}

	/*
	 * Static Management Functions
	 */

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

	const version = 3;
	private static $table_name = '';
	private static $progress_table_name = '';

	public static function init_manager() {
		global $wpdb;
		self::$table_name = $wpdb->base_prefix . 'bfox_reading_schedules';
		self::$progress_table_name = $wpdb->base_prefix . 'bfox_reading_schedule_progress';
	}

	public static function check_install() {
		if (get_site_option(self::$table_name . '_version') < self::version) {
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta('CREATE TABLE ' . self::$table_name . ' (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				plan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				revision_id BIGINT UNSIGNED NOT NULL,
				reading_count MEDIUMINT UNSIGNED NOT NULL,
				owner_id BIGINT(20) UNSIGNED NOT NULL,
				owner_type TINYINT(1) NOT NULL,
				label TINYTEXT NOT NULL,
				note TEXT NOT NULL,
				is_auto_sync BOOLEAN NOT NULL,
				start_time INT NOT NULL,
				end_time INT NOT NULL,
				is_recurring BOOLEAN NOT NULL,
				time_created INT UNSIGNED NOT NULL,
				time_modified INT UNSIGNED NOT NULL,
				next_email_time INT NOT NULL,
				meta TEXT NOT NULL,
				PRIMARY KEY  (id),
				KEY plan_id (plan_id),
				KEY owner (owner_type,owner_id)
			);

			CREATE TABLE ' . self::$progress_table_name . ' (
				schedule_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT(20) UNSIGNED NOT NULL,
				reading_id INT NOT NULL,
				KEY schedule_id (schedule_id)
			);');
			update_site_option(self::$table_name . '_version', self::version);

			self::schedule_emailer();
		}
	}

	private static function where_for_args($args) {
		global $wpdb;

		extract($args);

		$wheres = array();

		$owner_where = self::owner_where_for_args($args);
		if (!empty($owner_where)) $wheres []= $owner_where;

		// Specific IDs
		if (!empty($id)) $wheres []= 'id IN (' . implode(',', (array) $wpdb->escape($id)) . ')';

		// Specific Plan IDs
		if (!empty($plan_id)) $wheres []= 'plan_id IN (' . implode(',', (array) $wpdb->escape($plan_id)) . ')';

		// Filters
		if ($filter) {
			$filter = '%' . like_escape($filter) . '%';
			$wheres []= $wpdb->prepare("(label LIKE %s OR note LIKE %s)", $filter, $filter);
		}

		// Next Email Time
		if ($next_email_time) $wheres []= $wpdb->prepare('0 < next_email_time AND next_email_time <= %d', $next_email_time);

		// Date
		if ($date) {
			if (is_array($date)) {
				list($start_date, $end_date) = $date;
				$start_time = BfoxReadingSchedule::time($start_date);
				$end_time = BfoxReadingSchedule::time($end_date);
				$wheres []= $wpdb->prepare("((start_time BETWEEN %d AND %d) OR (end_time BETWEEN %d AND %d) OR (%d BETWEEN start_time AND end_time) OR (%d BETWEEN start_time AND end_time))",
					$start_time, $end_time, $start_time, $end_time, $start_time, $end_time);
			}
			else {
				// Check if the date starts after the start time and before the end time
				// The end time is adjusted by one day so that if it ends on that day, it will still return
				$time = self::time($date);
				$wheres []= $wpdb->prepare('(start_time <= %d AND %d <= end_time)', $time, $time - 24 * 60 * 60);
			}
		}

		return implode(' AND ', $wheres);
	}

	public static function get($args = array(), &$total_row_count = null) {
		global $wpdb;

		$schedules = array();
		$where = self::where_for_args($args);

		if (!empty($where)) {
			extract($args);

			if ($per_page) {
				$found_rows = 'SQL_CALC_FOUND_ROWS';
				$limit = $wpdb->prepare('LIMIT %d, %d', ($page - 1) * $per_page, $per_page);
			}
			else {
				$limit = $found_rows = '';
			}

			if ($next_email_time) $order_by = 'next_email_time ASC';
			else $order_by = 'start_time DESC';

			$results = (array) $wpdb->get_results("SELECT $found_rows * FROM " . self::$table_name . " WHERE $where ORDER BY $order_by $limit");
			if ($found_rows) $total_row_count = $wpdb->get_var('SELECT FOUND_ROWS()');

			$latest_reading_ids = array();
			$plan_ids = array();
			foreach ($results as $_schedule) {
				$schedule = new BfoxReadingSchedule($_schedule);
				$plan_ids []= $schedule->plan_id;

				if ($cache_latest_readings) $latest_reading_ids[$schedule->revision_id] []= (int) $schedule->latest_reading_id();
				$schedules[$_schedule->id] = $schedule;
			}
			unset($results);

			if (!empty($latest_reading_ids)) {
				$readings = BfoxReadingPlan::get_readings(array('reading_ids' => $latest_reading_ids));
				foreach ($schedules as $schedule) $schedule->latest_reading($readings[$schedule->revision_id]);
			}

			BfoxReadingPlan::cache('schedule', $schedules);

			BfoxReadingPlan::cache_plan_ids($plan_ids);
		}

		return array_keys($schedules);
	}

	/**
	 * @param int $schedule_id
	 * @return BfoxReadingSchedule
	 */
	public static function schedule($schedule_id) {
		global $wpdb;

		if (empty($schedule_id)) return new BfoxReadingSchedule;

		$schedule = BfoxReadingPlan::cache_get('schedule', $schedule_id);
		if (is_null($schedule)) {
			$schedule = new BfoxReadingSchedule($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::$table_name . ' WHERE id = %d', $schedule_id)));
			BfoxReadingPlan::cache('schedule', array($schedule));
		}

		return $schedule;
	}

	public static function delete_schedule(BfoxReadingSchedule $schedule) {
		global $wpdb;
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::$table_name . " WHERE id = %d", $schedule->id));
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::$progress_table_name . " WHERE schedule_id = %d", $schedule->id));

		$plan = $schedule->plan();
		$plan->update_user_count();
		$plan->save(false);
	}

	public static function delete_plan(BfoxReadingPlan $plan) {
		global $wpdb;

		// Delete all the schedules for the given plan and plan owner
		$schedule_ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . self::$table_name . ' WHERE plan_id = %d AND is_auto_sync = 1 AND owner_type = %d AND owner_id = %d', $plan->id, $plan->owner_type, $plan->owner_id));
		if (!empty($schedule_ids)) {
			$wpdb->query('DELETE FROM ' . self::$table_name . ' WHERE id IN (' . implode(',', $schedule_ids) . ')');
			$wpdb->query('DELETE FROM ' . self::$progress_table_name . ' WHERE schedule_id IN (' . implode(',', $schedule_ids) . ')');
		}

		$plan->update_user_count();
	}

	public static function count_users_for_plan_id($plan_id) {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT owner_id) FROM " . self::$table_name . " WHERE plan_id = %d AND owner_type = %d", $plan_id, self::owner_type_user));
	}

	public static function update_schedules_for_plan_id($plan_id, $revision_id, $old_readings, $new_readings) {
		global $wpdb;

		// Get all the schedules that need to be updated
		$results = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::$table_name . ' WHERE plan_id = %d AND is_auto_sync', $plan_id));
		$schedules = array();
		foreach ($results as $result) $schedules[$result->id] = new BfoxReadingSchedule($result);
		$escaped_schedule_ids = implode(',', array_keys($schedules));

		if (!empty($escaped_schedule_ids)) {
			$results = $wpdb->get_results('SELECT * FROM ' . self::$progress_table_name . ' WHERE schedule_id IN (' . $escaped_schedule_ids . ')');

			if (!empty($results)) {
				// Create any array of the bible references for the total progress of each user
				$progress = array();
				foreach ($results as $result) {
					if (!isset($progress[$result->schedule_id][$result->user_id]))
						$progress[$result->schedule_id][$result->user_id] = new BfoxRef;
					$progress[$result->schedule_id][$result->user_id]->add_ref($old_readings[$result->reading_id]);
				}

				// Scan each new reading to see if the reader read it in the previous plan
				$new_progress = array();
				foreach ($progress as $schedule_id => $schedule_progress) {
					foreach ($schedule_progress as $user_id => $user_progress) {
						// $user_progress = new BfoxRef;
						foreach ($new_readings as $new_reading_id => $new_reading) {
							if ($user_progress->contains($new_reading)) $new_progress []= $wpdb->prepare('(%d, %d, %d)', $schedule_id, $user_id, $new_reading_id);
						}
					}
				}

				$wpdb->query('DELETE FROM ' . self::$progress_table_name . ' WHERE schedule_id IN (' . $escaped_schedule_ids . ')');
				$wpdb->query('INSERT INTO ' . self::$progress_table_name . ' (schedule_id, user_id, reading_id) VALUES ' . implode(',', $new_progress));
			}

			$updates = array();
			$new_reading_count = count($new_readings);
			foreach ($schedules as $schedule) {
				// $schedule = new BfoxReadingSchedule;
				$schedule->revision_id = $revision_id;
				if ($new_reading_count != $schedule->reading_count) {
					$schedule->reading_count = $new_reading_count;
					$schedule->update_dates();
				}

				$schedule->save(false);
			}
		}
	}

	private static function calculate_reading_times($start_time, $count, $frequency, $days_of_week, &$latest_reading_id) {

		if (self::frequency_day == $frequency) {
			$select_format = 'w';
			$select_values = $days_of_week;
		}

		$inc_str = '+1 ' . self::frequency_string($frequency);

		$today_time = self::time();

		$times = array();
		$time = $start_time;
		for ($index = 0; $index < $count; $index++) {
			// If we have select_values, increment until we find a selected value
			if (!empty($select_values)) while (!$select_values[date($select_format, $time)]) $time = strtotime($inc_str, $time);

			if ($time <= $today_time) $latest_reading_id = $index;

			$times []= $time;
			$time = strtotime($inc_str, $time);
		}

		return $times;
	}

	public static function verify_permission($ids, $user_id = 0) {
		return parent::verify_permission(self::$table_name, $ids, $user_id);
	}

	private static $frequency_strings_day = array(self::frequency_day => 'day', self::frequency_week => 'week', self::frequency_month => 'month');
	private static $frequency_strings_daily = array(self::frequency_day => 'daily', self::frequency_week => 'weekly', self::frequency_month => 'monthly');
	public static function frequency_string($frequency, $daily = false) {
		if ($daily) return self::$frequency_strings_daily[$frequency];
		else return self::$frequency_strings_day[$frequency];
	}

	private static $checkbox_ids;
	public static function collect_checkbox_ids_start() {
		self::$checkbox_ids = array();
	}

	public static function collect_checkbox_ids_end() {
		$checkbox_ids = self::$checkbox_ids;
		self::$checkbox_ids;
		return $checkbox_ids;
	}

	public static function add_progress($schedule_readings, $user_id) {
		global $wpdb;

		if (!$user_id) return;

		$values = array();
		foreach ($schedule_readings as $schedule_id => $reading_ids) {
			$schedule = BfoxReadingSchedule::schedule($schedule_id);
			foreach ($reading_ids as $reading_id) if ($reading_id < $schedule->reading_count)
				$values []= $wpdb->prepare("(%d, %d, %d)", $schedule_id, $user_id, $reading_id);
		}

		if (!empty($values)) $wpdb->query("INSERT INTO " . self::$progress_table_name . " (schedule_id, user_id, reading_id) VALUES " . implode(',', $values));
	}

	public static function remove_progress($schedule_readings, $user_id) {
		global $wpdb;

		if (!$user_id) return;

		$wheres = array();
		foreach ($schedule_readings as $schedule_id => $reading_ids) {
			if (!empty($reading_ids)) $wheres []= $wpdb->prepare("(schedule_id = %d AND reading_id IN (" . implode(',', (array) $wpdb->escape($reading_ids)) . "))", $schedule_id);
		}

		if (!empty($wheres)) $wpdb->query($wpdb->prepare("DELETE FROM " . self::$progress_table_name . " WHERE user_id = %d AND (" . implode(' OR ', $wheres) . ')', $user_id));
	}

	/*
	 * Email Functions
	 */

	public static function schedule_emailer() {
		wp_schedule_event(self::time('tomorrow'), 'daily', 'bfox_plan_emails_send_action');
	}

	public static function unschedule_emailer() {
		wp_clear_scheduled_hook('bfox_plan_emails_send_action');
	}

	private static function get_email_info($user_ids) {
		global $wpdb;

		$email_info = array();

		if (!empty($user_ids)) {
			// Remove all the users who don't want emails
			$user_ids = array_diff($user_ids, bfox_bp_get_users_with_option_value('notification_plans_readings', 'no', $user_ids));


			if (!empty($user_ids)) {
				// Get all the user email addresses
				$results = $wpdb->get_results("SELECT ID, user_email FROM $wpdb->users WHERE ID IN (" . implode(',', (array) $wpdb->escape($user_ids)) . ") LIMIT " . count($user_ids));
				foreach ($results as $result) $email_info[$result->ID] = $result;
			}
		}

		return $email_info;
	}

	public static function send_emails($per_page = 0) {
		// Get the schedules to send emails for
		$total_schedules_to_update = 0;
		$schedule_ids = apply_filters('bfox_schedule_send_emails_schedule_ids',
			self::get(array('per_page' => $per_page, 'page' => 1, 'next_email_time' => self::time(), 'cache_latest_readings' => true), $total_schedules_to_update),
			$total_schedules_to_update
		);

		// Get the email info we need to send the emails
		$user_ids = array();
		foreach ($schedule_ids as $schedule_id) {
			$schedule = self::schedule($schedule_id);
			$user_ids = array_merge($user_ids, $schedule->user_member_ids());
		}
		$total_email_info = self::get_email_info(array_unique($user_ids));


		if (!empty($total_email_info)) {
			foreach ($schedule_ids as $schedule_id) {
				$schedule = self::schedule($schedule_id);
				$email_info = array_intersect_key($total_email_info, array_flip($schedule->user_member_ids()));

				// Send the email and update the schedule, quit if it doesn't work
				if (!$schedule->send_email($email_info) || !$schedule->save()) {
					// Always return 0 remaining when failing, because we don't want this function to get called again.
					// Since it failed to update the DB, calling this function again could result in an infinite loop.
					return apply_filters('bfox_schedule_send_emails_failed', 0);
				}
			}
		}

		$remaining = max($total_schedules_to_update - count($schedule_ids), 0);
		return apply_filters('bfox_schedule_send_emails', $remaining);
	}

	public function send_email($email_info = null) {
		if (is_null($email_info)) $email_info = self::get_email_info($this->user_member_ids());

		// If there are actually people to send to, send them individual emails
		if (!empty($email_info)) {
			// We have to allow the current user to magically be a member of the plan so we can access all the plan data
			$this->allow_magic_member = true;

			$ref = $this->latest_reading();

			if ($ref && $ref->is_valid()) {
				$ref_str = $ref->get_string();
				$ref_url = bfox_ref_bible_url($ref_str);

				$subject = sprintf(__('[%s] #%d: %s', 'bfox'), $this->label(), $this->latest_reading_id() + 1, $ref->get_string(BibleMeta::name_short));

				$msg_base = sprintf(__("Reading Schedule: %s
Today's Reading: #%d %s

Study this passage: %s
Share your thoughts: %s
Mark passage as read: %s

---------------------
", 'bfox'),
					$this->label(),
					$this->latest_reading_id() + 1,
					$ref_str,
					$ref_url,
					$ref_url . '#whats-new',
					$this->mark_read_url($this->latest_reading_id())
				);

				// Send an email to each user
				foreach ($email_info as $user) {
					$user_id = $user->ID;
					$message = $msg_base;

					$settings_link = bp_core_get_user_domain( $user_id ) .  BP_SETTINGS_SLUG . '/notifications/';
					$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

					wp_mail($user->user_email, $subject, $message);
				}
			}
			// Reset magic member
			$this->allow_magic_member = false;
		}

		return $this->update_next_email_time();
	}
}
BfoxReadingSchedule::init_manager();
add_action('bfox_bp_check_install', 'BfoxReadingSchedule::check_install');

function bfox_bp_plans_get_active_schedule_ids() {
	global $bfox_bp_plans_active_schedule_ids, $user_ID;
	if (!isset($bfox_bp_plans_active_schedule_ids)) {
		if ($user_ID) $bfox_bp_plans_active_schedule_ids = BfoxReadingSchedule::get(BfoxReadingSchedule::add_user_to_args($user_ID, array('date' => BfoxReadingSchedule::date())));
		else $bfox_bp_plans_active_schedule_ids = array();
	}
	return (array) $bfox_bp_plans_active_schedule_ids;
}

/**
 * @return BfoxReadingSchedule
 */
function bfox_bp_schedule(BfoxReadingSchedule $schedule = null) {
	global $bfox_bp_schedule;
	if (!is_null($schedule)) $bfox_bp_schedule = $schedule;
	return $bfox_bp_schedule;
}

function bfox_bp_schedule_send_emails() {
	$limit = 500;

	// Send emails, $limit number of schedules at a time, until there are no more remaining
	while (BfoxReadingSchedule::send_emails($limit)) {
		// Clear the Reading Plan cache each time because we are dealing with large amounts of data
		BfoxReadingPlan::cache_clear();
	}
}
add_action('bfox_plan_emails_send_action', 'bfox_bp_schedule_send_emails');

?>