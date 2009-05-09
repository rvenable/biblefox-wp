<?php

define(BFOX_TABLE_READING_PLANS, BFOX_BASE_TABLE_PREFIX . 'reading_plans');
define(BFOX_TABLE_READING_LISTS, BFOX_BASE_TABLE_PREFIX . 'reading_lists');
define(BFOX_TABLE_READINGS, BFOX_BASE_TABLE_PREFIX . 'readings');
define(BFOX_TABLE_READING_SCHEDULES, BFOX_BASE_TABLE_PREFIX . 'reading_schedules');

class BfoxReadingInfo
{
	public $id = 0;
	public $owner = 0;
	public $owner_type = 0;
	public $is_private = FALSE;

	public function __construct($values = NULL)
	{
		if (is_object($values)) $this->set_from_db($values);
	}

	public function set_from_db(stdClass $db_data)
	{
		$this->id = $db_data->id;
		$this->owner = $db_data->owner;
		$this->owner_type = $db_data->owner_type;
		$this->is_private = $db_data->is_private;
	}

	public function owner_name()
	{
		switch ($this->owner_type)
		{
			case BfoxPlans::owner_type_blog: return get_blog_option($this->owner, 'blogname');
			case BfoxPlans::owner_type_user: return get_author_name($this->owner);
		}
	}

	public function owner_link()
	{
		$name = $this->owner_name();
		switch ($this->owner_type)
		{
			case BfoxPlans::owner_type_blog: return "<a href='" . get_blog_option($this->owner, 'siteurl') . "'>" . $name . "</a>";
			case BfoxPlans::owner_type_user:
				$user = get_userdata($user_id);
				return "<a href='" . $user->url . "'>" . $user->display_name . "</a>";
		}
	}
}

class BfoxReadingPlan extends BfoxReadingInfo {
	public $list_id = 0;
	public $schedule_id = 0;

	public function set_from_db(stdClass $db_data) {
		parent::set_from_db($db_data);
		$this->list_id = $db_data->list_id;
		$this->schedule_id = $db_data->schedule_id;
	}
}

class BfoxReadingList extends BfoxReadingInfo
{
	public $name = '';
	public $description = '';
	public $readings = array();

	public function set_from_db(stdClass $db_data)
	{
		parent::set_from_db($db_data);
		$this->name = $db_data->name;
		$this->description = $db_data->description;
	}

	public function set_reading(BibleRefs $refs, $reading_id = -1)
	{
		if ($refs->is_valid()) {
			// If the reading id is not already there, we should just add it to the end
			if (!isset($this->readings[$reading_id])) $this->readings []= $refs;
			else $this->readings[$reading_id] = $refs;
		}
	}

	public function add_verses($reading_id, $verse_start, $verse_end)
	{
		if (!isset($this->readings[$reading_id])) $this->readings[$reading_id] = new BibleRefs();
		$this->readings[$reading_id]->add_seq($verse_start, $verse_end);
	}

	public function set_readings_by_strings($strings)
	{
		if (is_string($strings)) $strings = explode("\n", $strings);

		$this->readings = array();
		foreach ($strings as $str) $this->set_reading(RefManager::get_from_str($str));
	}

	public function add_passages($passages, $chunk_size)
	{
		$refs = RefManager::get_from_str($passages);
		$chunks = $refs->get_sections($chunk_size);
		foreach ($chunks as $chunk) $this->set_reading($chunk);
	}

	public function ref_string()
	{
		$ref_str = '';

		if (!empty($this->readings))
		{
			$refs = new BibleRefs();
			foreach ($this->readings as $reading) $refs->add_seqs($reading->get_seqs());
			if ($refs->is_valid()) $ref_str = $refs->get_string();
		}

		return $ref_str;
	}

	public function reading_strings($name = BibleMeta::name_normal) {
		$strings = array();
		foreach ($this->readings as $reading) $strings []= $reading->get_string($name);
		return $strings;
	}

	public function reading_count()
	{
		return count($this->readings);
	}
}

class BfoxReadingSchedule extends BfoxReadingInfo
{
	public $list = NULL;
	public $list_id = 0;
	public $start_date = '';
	public $end_date = '';
	public $is_recurring = FALSE;
	public $frequency = 0;
	public $frequency_options = '';

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

	public function __construct($values = NULL)
	{
		if (is_object($values)) $this->set_from_db($values);
	}

	public function set_from_db(stdClass $db_data)
	{
		parent::set_from_db($db_data);
		$this->list_id = $db_data->list_id;
		$this->start_date = $db_data->start_date;
		$this->end_date = $db_data->end_date;
		$this->is_recurring = $db_data->is_recurring;
		$this->frequency = $db_data->frequency;
		$this->frequency_options = $db_data->frequency_options;
	}

	public function set_list(BfoxReadingList $list)
	{
		$this->list = $list;
		$this->list_id = $list->id;
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

	public function days_week_str($type = self::days_week_array_short)
	{
		if (self::frequency_day == $this->frequency) {
			if (self::days_week_array_short != $type) $glue = ', ';

			$day_strs = array();
			$strings = self::days_week_array();
			$days = $this->freq_options_array();
			foreach ($days as $day => $is_valid) if ($is_valid) $day_strs []= $strings[$type][$day];
			return implode($glue, $day_strs);
		}
	}

	public function freq_options_array() {
		return array_fill_keys(str_split($this->frequency_options), TRUE);
	}

	public function frequency_desc()
	{
		return ucfirst($this->frequency_str(self::frequency_array_daily)) . ', ' . $this->days_week_str();
	}

	public function start_str($format = self::date_format_normal)
	{
		return date($format, strtotime($this->start_date));
	}

	public function end_str($format = self::date_format_normal)
	{
		return date($format, strtotime($this->end_date));
	}

	public function get_dates($count)
	{
		if (self::frequency_day == $this->frequency)
		{
			$select_format = 'w';
			$select_values = $this->freq_options_array();
		}

		$inc_str = '+1 ' . $this->frequency_str();

		$dates = array();
		$date = strtotime($this->start_date);
		for ($index = 0; $index < $count; $index++)
		{
			// If we have select_values, increment until we find a selected value
			if (!empty($select_values)) while (!$select_values[date($select_format, $date)]) $date = strtotime($inc_str, $date);

			$dates []= $date;
			$date = strtotime($inc_str, $date);
		}

		return $dates;
	}

	public function current_date_index($dates, $now = 0) {

		// If now is empty, get now according to the local blog settings, formatted as an integer number of seconds
		if (empty($now)) $now = strtotime(BfoxUtility::format_local_date('today'));

		foreach ($dates as $index => $date) {
			if ($date > $now) break;
			else $current = $index;
		}

		if (isset($current)) return $current;
		else return -1;
	}
}

class BfoxReadingScheduleGlobal
{

}

class BfoxPlans {
	const table_plans = BFOX_TABLE_READING_PLANS;
	const table_lists = BFOX_TABLE_READING_LISTS;
	const table_readings = BFOX_TABLE_READINGS;
	const table_schedules = BFOX_TABLE_READING_SCHEDULES;

	const owner_type_blog = 0;
	const owner_type_user = 1;
	const owner_type_external = 2;

	public static function create_tables() {
		// Note: for blog_id and user_id (aka. owner) see WP's implementation in wp-admin/includes/schema.php

		BfoxUtility::create_table(self::table_plans, "
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner BIGINT(20) UNSIGNED NOT NULL,
			owner_type TINYINT(2) NOT NULL,
			list_id BIGINT UNSIGNED NOT NULL,
			schedule_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id)");

		BfoxUtility::create_table(self::table_lists, "
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			description TEXT NOT NULL,
			owner BIGINT(20) UNSIGNED NOT NULL,
			owner_type TINYINT(2) NOT NULL,
			is_private BOOLEAN NOT NULL,
			PRIMARY KEY  (id)");

		BfoxUtility::create_table(self::table_readings, "
			list_id BIGINT UNSIGNED NOT NULL,
			reading_id MEDIUMINT UNSIGNED NOT NULL,
			verse_begin MEDIUMINT UNSIGNED NOT NULL,
			verse_end MEDIUMINT UNSIGNED NOT NULL");

		BfoxUtility::create_table(self::table_schedules, "
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner BIGINT(20) UNSIGNED NOT NULL,
			owner_type TINYINT(2) NOT NULL,
			is_private BOOLEAN NOT NULL,
			list_id BIGINT UNSIGNED NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NOT NULL,
			is_recurring BOOLEAN NOT NULL,
			frequency TINYINT UNSIGNED NOT NULL,
			frequency_options VARCHAR(255) NOT NULL,
			PRIMARY KEY  (id)");
	}

	public static function save_plan(BfoxReadingPlan &$plan) {
		global $wpdb;

		$set = $wpdb->prepare(
			"SET owner = %d, owner_type = %d, list_id = %d, schedule_id = %d",
			$plan->owner, $plan->owner_type, $plan->list_id, $plan->schedule_id);

		if (empty($plan->id)) $wpdb->query("INSERT INTO " . self::table_plans . " $set");
		else $wpdb->query($wpdb->prepare("UPDATE " . self::table_plans . " $set WHERE id = %d", $plan->id));

		if (empty($plan->id)) $plan->id = $wpdb->insert_id;
	}

	public static function save_list(BfoxReadingList &$list) {
		global $wpdb;

		$set = $wpdb->prepare(
			"SET name = %s, description = %s, owner = %d, owner_type = %d, is_private = %d",
			$list->name, $list->description, $list->owner, $list->owner_type, $list->is_private);

		if (empty($list->id)) $wpdb->query("INSERT INTO " . self::table_lists . " $set");
		else $wpdb->query($wpdb->prepare("UPDATE " . self::table_lists . " $set WHERE id = %d", $list->id));

		if (empty($list->id)) $list->id = $wpdb->insert_id;
		else $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table_readings . ' WHERE list_id = %d', $list->id));

		if (!empty($list->readings)) {
			$values = array();
			foreach ($list->readings as $reading_id => $reading)
				foreach ($reading->get_seqs() as $seq)
					$values []= $wpdb->prepare('(%d, %d, %d, %d)', $list->id, $reading_id, $seq->start, $seq->end);

			$wpdb->query($wpdb->prepare("
				INSERT INTO " . self::table_readings . "
				(list_id, reading_id, verse_begin, verse_end)
				VALUES " . implode(',', $values)));
		}
	}

	public static function save_schedule(BfoxReadingSchedule &$schedule) {
		global $wpdb;

		$set = $wpdb->prepare(
			"SET owner = %d, owner_type = %d, is_private = %d, list_id = %d, start_date = %s, end_date = %s, is_recurring = %d, frequency = %d, frequency_options = %s",
			$schedule->owner, $schedule->owner_type, $schedule->is_private, $schedule->list_id, $schedule->start_date, $schedule->end_date, $schedule->is_recurring, $schedule->frequency, $schedule->frequency_options);

		if (empty($schedule->id)) $wpdb->query("INSERT INTO " . self::table_schedules . " $set");
		else $wpdb->query($wpdb->prepare("UPDATE " . self::table_schedules . " $set WHERE id = %d", $schedule->id));

		if (empty($schedule->id)) $schedule->id = $wpdb->insert_id;
	}

	public static function get_owner_plans($owner, $owner_type) {
		global $wpdb;

		$plans = array();

		if (!empty($owner)) {
			// Get the plans from the DB
			$results = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table_plans . ' WHERE (owner = %d AND owner_type = %d)', $owner, $owner_type));

			// Create each BfoxReadingSchedule instance
			foreach ($results as $result) $plans []= new BfoxReadingPlan($result);
		}

		return $plans;
	}

	public static function get_plan($plan_id) {
		global $wpdb;
		return new BfoxReadingPlan($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table_plans . ' WHERE id = %d', $plan_id)));
	}

	public static function get_lists($list_ids, $owner = 0, $owner_type = self::owner_type_blog) {
		global $wpdb;

		$lists = array();

		$wheres = array();
		if (!empty($list_ids)) {
			$ids = array();
			foreach ($list_ids as $list_id) if (!empty($list_id)) $ids []= $wpdb->prepare('%d', $list_id);
			if (!empty($ids)) $wheres []= 'id IN (' . implode(',', $ids) . ')';
		}
		if (!empty($owner)) $wheres []= $wpdb->prepare("(owner = %d AND owner_type = %d)", $owner, $owner_type);

		if (!empty($wheres)) {
			// Get the list info from the DB
			$results = $wpdb->get_results('SELECT * FROM ' . self::table_lists . ' WHERE ' . implode(' OR ', $wheres));

			// Create each BfoxReadingList instance
			$ids = array();
			foreach ($results as $result) {
				$lists[$result->id] = new BfoxReadingList($result);
				$ids []= $wpdb->prepare('%d', $result->id);
			}

			// Get the reading info from the DB
			$readings = $wpdb->get_results('SELECT * FROM ' . self::table_readings . ' WHERE list_id IN (' . implode(',', $ids) . ')');


			// Add all the readings to the reading list
			foreach ($readings as $reading) $lists[$reading->list_id]->add_verses($reading->reading_id, $reading->verse_begin, $reading->verse_end);
		}

		return $lists;
	}

	public static function get_list($list_id) {
		$lists = self::get_lists(array($list_id));
		if (isset($lists[$list_id])) return $lists[$list_id];
		else return new BfoxReadingList();
	}

	public static function get_schedules($schedule_ids, $owner = 0, $owner_type = self::owner_type_blog) {
		global $wpdb;

		$schedules = array();

		$wheres = array();
		if (!empty($list_ids)) {
			$ids = array();
			foreach ($schedule_ids as $schedule_id) if (!empty($schedule_id)) $ids []= $wpdb->prepare('%d', $schedule_id);
			if (!empty($ids)) $wheres []= 'id IN (' . implode(',', $ids) . ')';
		}
		if (!empty($owner)) $wheres []= $wpdb->prepare("(owner = %d AND owner_type = %d)", $owner, $owner_type);

		if (!empty($wheres)) {
			// Get the plans from the DB
			$results = $wpdb->get_results('SELECT * FROM ' . self::table_schedules . ' WHERE ' . implode(' OR ', $wheres));

			// Create each BfoxReadingSchedule instance
			foreach ($results as $result) $schedules[$result->id] = new BfoxReadingSchedule($result);
		}

		return $schedules;
	}

	public static function get_schedule($schedule_id) {
		global $wpdb;
		return new BfoxReadingSchedule($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table_schedules . ' WHERE id = %d', $schedule_id)));
	}
}

	/*
	 Class for common plan functionality
	 */
	class Plan
	{
		protected $plan_table_name;
		protected $data_table_name;

		function get_data_table_name() { return $this->data_table_name; }

		function are_tables_installed()
		{
			global $wpdb;
			return ((!isset($this->plan_table_name) || ($wpdb->get_var("SHOW TABLES LIKE '$this->plan_table_name'") == $this->plan_table_name)) &&
					(!isset($this->data_table_name) || ($wpdb->get_var("SHOW TABLES LIKE '$this->data_table_name'") == $this->data_table_name)) &&
					(!isset($this->user_table_name) || ($wpdb->get_var("SHOW TABLES LIKE '$this->user_table_name'") == $this->user_table_name)));
		}

		function get_plan_text($plan_id)
		{
			$refs = $this->get_plan_refs($plan_id);
			$text = '';
			foreach ($refs->unread as $refs)
				$text .= $refs->get_string() . "\n";
			return $text;
		}

		function get_plan_refs($plan_id)
		{
			// Return the cache if it is set
			if (isset($this->cache['plan_refs'][$plan_id])) return $this->cache['plan_refs'][$plan_id];

			$unread = array();
			$read = array();

			if (isset($this->data_table_name))
			{
				global $wpdb;

				// Get an ordered array of all the plan data for this plan
				$results = $wpdb->get_results($wpdb->prepare("SELECT * from $this->data_table_name
															 WHERE plan_id = %d
															 ORDER BY period_id ASC, ref_id ASC, verse_start ASC",
															 $plan_id));

				// For each line of plan data, organize it into BibleRefs according to its period ID
				$unread_sets = array();
				$read_sets = array();
				foreach ($results as $result)
				{
					// If we have a new period ID
					// Then we should update any BibleRef information for the old period ID and begin using the new period ID
					if (!isset($period_id) || ($period_id != $result->period_id))
					{
						// If an old period ID is set, then we need to convert its set info to BibleRefs
						if (isset($period_id))
						{
							if (0 < count($unread_sets)) $unread[$period_id] = RefManager::get_from_sets($unread_sets);
							if (0 < count($read_sets)) $read[$period_id] = RefManager::get_from_sets($read_sets);
							$unread_sets = array();
							$read_sets = array();
						}
						$period_id = $result->period_id;
					}

					// This verse set is either read or unread
					if (isset($result->is_read) && $result->is_read)
					{
						$read_sets[] = array($result->verse_start, $result->verse_end);
						if (!isset($first_unread)) $last_read = $period_id;
					}
					else
					{
						$unread_sets[] = array($result->verse_start, $result->verse_end);
						if (!isset($first_unread)) $first_unread = $period_id;
					}
				}

				// Convert any remaining sets to BibleRefs
				if (0 < count($unread_sets)) $unread[$period_id] = RefManager::get_from_sets($unread_sets);
				if (0 < count($read_sets)) $read[$period_id] = RefManager::get_from_sets($read_sets);

			}

			$group = array();
			$group['unread'] = $unread;
			$group['read'] = $read;
			$group['first_unread'] = $first_unread;
			$group['last_read'] = $last_read;

			// Cache the group off
			$this->cache['plan_refs'][$plan_id] = (object) $group;
			return $this->cache['plan_refs'][$plan_id];
		}

		function get_plan_ids()
		{
			global $wpdb;
			$plan_ids = $wpdb->get_col("SELECT plan_id from $this->data_table_name GROUP BY plan_id");
			if (is_array($plan_ids)) return $plan_ids;
			return array();
		}

		function get_plans($plan_id = 0)
		{
			if (isset($this->plan_table_name))
			{
				global $wpdb;
				if (!empty($plan_id)) $where = $wpdb->prepare('WHERE id = %d', $plan_id);
				$plans = $wpdb->get_results("SELECT * from $this->plan_table_name $where");

				if (isset($this->blog_id))
				{
					foreach ($plans as &$plan)
					{
						$refs = $this->get_plan_refs($plan->id);
						$plan->refs = $refs->unread;
						$plan->dates = $this->get_dates($plan, count($plan->refs));
					}
				}
			}

			if (is_array($plans)) return $plans;
			return array();
		}

		function delete_plan_data($plan_id)
		{
			global $wpdb;
			if (isset($this->data_table_name))
				$wpdb->query($wpdb->prepare("DELETE FROM $this->data_table_name WHERE plan_id = %d", $plan_id));
			unset($this->cache['plan_refs'][$plan_id]);
		}

		function delete($plan_id)
		{
			global $wpdb;
			$this->delete_plan_data($plan_id);
			if (isset($this->plan_table_name))
				$wpdb->query($wpdb->prepare("DELETE FROM $this->plan_table_name WHERE id = %d", $plan_id));
		}
	}

	/*
	 Class for managing the plans stored on a per blog basis, which are used as the source for plans used by individuals
	 */
	class PlanBlog extends Plan
	{
		protected $user_table_name;
		protected $blog_id;
		public $frequency = array('day', 'week', 'month');

		function PlanBlog($local_blog_id = 0)
		{
			global $blog_id;
			if (0 == $local_blog_id) $local_blog_id = $blog_id;
			$this->blog_id = $local_blog_id;

			$prefix = bfox_get_blog_table_prefix($this->blog_id);
			$this->plan_table_name = $prefix . 'reading_plan';
			$this->data_table_name = $prefix . 'reading_plan_data';
			$this->user_table_name = $prefix . 'reading_plan_users';

			$this->frequency = array_merge($this->frequency, array_flip($this->frequency));
		}

		function create_tables()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

			$sql = '';

			if (isset($this->plan_table_name))
			{
				$sql .= "CREATE TABLE $this->plan_table_name (
				id bigint(20) unsigned NOT NULL auto_increment,
				name varchar(128),
				summary text,
				start_date varchar(16),
				end_date varchar(16),
				frequency int,
				frequency_options varchar(256),
				PRIMARY KEY  (id)
				);";
			}

			if (isset($this->data_table_name))
			{
				$sql .= "CREATE TABLE $this->data_table_name (
				id bigint(20) unsigned NOT NULL auto_increment,
				plan_id int,
				period_id int,
				ref_id int,
				verse_start int,
				verse_end int,
				due_date datetime,
				PRIMARY KEY  (id)
				);";
			}

			if (isset($this->user_table_name))
			{
				$sql .= "CREATE TABLE $this->user_table_name (
				plan_id bigint(20),
				user int
				);";
			}

			if ('' != $sql)
			{
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
			}
		}

		// This function is only for upgrading to the new db schema
		function reset_end_dates()
		{
			global $wpdb;
			define('DIEONDBERROR', 'die!');
			$wpdb->show_errors(true);
			$query = $wpdb->prepare("UPDATE $this->plan_table_name SET start_date = %s, end_date = %s, frequency_options = '' WHERE end_date IS NULL", date('m/d/Y'), date('m/d/Y'));
			echo $query . '<br/>';
			$wpdb->query($query);
		}

		function add_user_to_plan($plan_id, $user_id)
		{
			if (isset($this->user_table_name))
			{
				global $wpdb;
				if ($wpdb->get_var($wpdb->prepare("SELECT user FROM $this->user_table_name WHERE plan_id = %d AND user = %d", $plan_id, $user_id)) != $user_id)
					$wpdb->query($wpdb->prepare("INSERT INTO $this->user_table_name (plan_id, user) VALUES (%d, %d)", $plan_id, $user_id));
			}
		}

		function get_plan_users($plan_id)
		{
			if (isset($this->user_table_name))
			{
				global $wpdb;
				$users = $wpdb->get_col($wpdb->prepare("SELECT user FROM $this->user_table_name WHERE plan_id = %d GROUP BY user", $plan_id));
			}

			if (is_array($users)) return $users;
			return array();
		}

		function add_new_plan($plan)
		{
			if (isset($this->plan_table_name))
			{
				global $wpdb;

				// If the table doesn't exist, create it
				if ($wpdb->get_var("SHOW TABLES LIKE '$this->plan_table_name'") != $this->plan_table_name)
					$this->create_tables();

				// Calculate the end date
				$dates = $this->get_dates($plan, count($plan->refs_array));
				if (0 < count($dates))
					$plan->end_date = date('m/d/Y', $dates[count($dates) - 1]);
				else
					$plan->end_date = date('m/d/Y', current_time('timestamp'));

				// Update the plan table
				if (!isset($plan->name) || ('' == $plan->name)) $plan->name = 'Plan ' . $plan_id;
				$insert = $wpdb->prepare("INSERT INTO $this->plan_table_name
										 (name, summary, start_date, end_date, frequency, frequency_options)
										 VALUES (%s, %s, %s, %s, %d, %s)",
										 $plan->name, $plan->summary, $plan->start_date, $plan->end_date, $plan->frequency, $plan->frequency_options);

				// Insert and get the plan ID
				$wpdb->query($insert);
				$plan_id = $wpdb->insert_id;

				// Update the data table
				$this->insert_refs_array($plan_id, $plan->refs_array);

				return $plan_id;
			}
		}

		function edit_plan($plan)
		{
			if (isset($this->plan_table_name) && isset($plan->id))
			{
				global $wpdb;

				// Calculate the end date
				$dates = $this->get_dates($plan, count($plan->refs_array));
				if (0 < count($dates))
					$plan->end_date = date('m/d/Y', $dates[count($dates) - 1]);
				else
					$plan->end_date = date('m/d/Y', current_time('timestamp'));

				// Update the plan table
				$set_array = array();
				if (isset($plan->name)) $set_array[] = $wpdb->prepare('name = %s', $plan->name);
				if (isset($plan->summary)) $set_array[] = $wpdb->prepare('summary = %s', $plan->summary);
				if (isset($plan->start_date)) $set_array[] = $wpdb->prepare('start_date = %s', $plan->start_date);
				if (isset($plan->end_date)) $set_array[] = $wpdb->prepare('end_date = %s', $plan->end_date);
				if (isset($plan->frequency)) $set_array[] = $wpdb->prepare('frequency = %d', $plan->frequency);
				if (isset($plan->frequency_options)) $set_array[] = $wpdb->prepare('frequency_options = %s', $plan->frequency_options);

				$wpdb->show_errors(true);
				if (0 < count($set_array))
				{
					$wpdb->query($wpdb->prepare("UPDATE $this->plan_table_name
												SET " . implode(', ', $set_array) .
												" WHERE id = %d",
												$plan->id));
				}

				// If we changed the bible refs, we need to update the data table
				if (isset($plan->refs_array))
				{
					// Delete any old ref data in the data table
					$this->delete_plan_data($plan->id);

					// Update the data table with the new refs
					$this->insert_refs_array($plan->id, $plan->refs_array);

					// Get all the users which are tracking this plan
					$users = $this->get_plan_users($plan->id);

					// For each user, correct their tracking data
					foreach ($users as $user)
					{
						$progress = new PlanProgress($user);
						$progress->track_plan($this->blog_id, $plan->id);
					}
				}

				// If this plan is not finished, and we haven't scheduled the emails action, then we should schedule it
				// The scheduled timestamp should be 'today' but in our blog's time
				// TODO2: If the the blog's time settings change, this needs to be rescheduled
				if (!$plan->is_finished && !wp_next_scheduled('bfox_plan_emails_send_action'))
					wp_schedule_event((int) BfoxUtility::format_local_date('today', 'U'), 'daily', 'bfox_plan_emails_send_action');
			}
		}

		function insert_refs_array($plan_id, $plan_refs_array)
		{
			if (isset($this->data_table_name))
			{
				global $wpdb;

				$period_id = 0;
				foreach ($plan_refs_array as $plan_refs)
				{
					$ref_id = 0;
					foreach ($plan_refs->get_sets() as $unique_ids)
					{
						$insert = $wpdb->prepare("INSERT INTO $this->data_table_name (plan_id, period_id, ref_id, verse_start, verse_end) VALUES (%d, %d, %d, %d, %d)", $plan_id, $period_id, $ref_id, $unique_ids[0], $unique_ids[1]);
						$wpdb->query($insert);
						$ref_id++;
					}

					$period_id++;
				}
			}
		}

		/*
		function get_plan_list($plan_id, $add_progress = FALSE)
		{
			$orig_refs_object = $this->get_plan_refs($plan_id);
			$plan_list = array();
			$plan_list['original'] = $orig_refs_object->unread;

			if ($add_progress)
			{
				// Get the plan progress for the current user
				global $bfox_plan_progress;
				$user_plan_id = $bfox_plan_progress->get_plan_id($this->blog_id, $plan_id);
				if (isset($user_plan_id))
				{
					$refs_object = $bfox_plan_progress->get_plan_refs($user_plan_id);
					$plan_list['unread'] = $refs_object->unread;
					$plan_list['read'] = $refs_object->read;
				}
			}

			return (object) $plan_list;
		}
		 */

		function is_valid_date($date, $plan)
		{
			$is_valid = TRUE;
			if ($this->frequency['day'] == $plan->frequency)
			{
				$is_valid = (TRUE === $plan->days_of_week[date('w', $date)]);
			}
			return $is_valid;
		}

		function get_dates(&$plan, $count = 0)
		{
			// Turn the frequency options into an array
			if ('' == $plan->frequency_options) $plan->frequency_options = '0123456';
			$plan->days_of_week = array_fill_keys(str_split($plan->frequency_options), TRUE);

			// Get today according to the local blog settings, formatted as an integer number of seconds
			$now = (int) date('U', strtotime(BfoxUtility::format_local_date('today')));

			$frequency_str = $this->frequency[$plan->frequency];
			$dates = array();
			$date = strtotime($plan->start_date);
			for ($index = 0; $index < $count + 1; $index++)
			{
				if ((0 < $index) || !$this->is_valid_date($date, $plan))
				{
					// Increment the date until
					$inc_count = 0;
					do
					{
						$date = strtotime('+1 ' . $frequency_str, $date);
						$inc_count++;
					}
					while (!$this->is_valid_date($date, $plan) && ($inc_count < 7));
				}

				// If the date is later than today, we can try to set the current and next readings
				// Otherwise, if the date is today, we can set today's reading
				$unix_date = (int) date('U', $date);
				if ($now < $unix_date)
				{
					if (!isset($plan->next_reading)) $plan->next_reading = $index;
					if (!isset($plan->current_reading) && (0 <= $index - 1)) $plan->current_reading = $index - 1;
				}
				else if ($now == $unix_date)
				{
					$plan->todays_reading = $index;
				}

				if ($index < $count)
					$dates[] = $date;
			}

			// Calculate whether this is finished
			// Note: this is based off of the $dates array, so will vary for any given plan depending on the $count parameter passed in
			$plan->is_finished = TRUE;
			if (!empty($dates) && (((int) date('U', $dates[count($dates) - 1])) >= $now)) $plan->is_finished = FALSE;

			return $dates;
		}

		/**
		 * Send all of today's emails for a given reading plan
		 *
		 * @param unknown_type $plan
		 */
		function send_plan_emails($plan)
		{
			// Create the email content
			$refs = $plan->refs[$plan->todays_reading];
			$subject = "$plan->name (Reading " . ($plan->todays_reading + 1) . "): " . $refs->get_string();
			$headers = "content-type:text/html";

			// Create the email message

			$blog = 'Share your thoughts about this reading: ' . BfoxBlog::ref_write_link($refs->get_string(), 'Add a blog entry');
			$instructions = "If you would not like to receive reading plan emails, go to your " . BfoxBlog::admin_link('profile.php#bfox_email_readings', 'profile page') . ", uncheck the 'Email Readings' option and click 'Update Profile'.";

			$message = "<p>The following email contains today's scripture reading for the '$plan->name' reading plan.<br/>$instructions</p>";
			$message .= "<h2><a href='" . BfoxBlog::reading_plan_url($plan->id, $plan->todays_reading) . "'>$subject</a></h2><p>$blog</p><hr/>";
			$message .= BfoxBlog::get_verse_content_email($refs);
			$message .= "<hr/><p>$blog</p>";

			// If this isn't the first reading, we should show any blog activity since the previous reading
			if (0 < $plan->todays_reading)
			{
				$discussions = '';
				$discussions .= bfox_get_discussions(array('min_date' => date('Y-m-d', $plan->dates[$plan->todays_reading - 1])));
				if (!empty($discussions)) $message .= "<div><h3>Recent Blog Activity</h3>$discussions</div>";
			}

			// Add the removal instructions again
			$message .= "<hr/><p>$instructions</p>";

			// Check each user in this blog to see if they want to receive emails
			// If they want to, send them an email
			$success = array();
			$failed = array();
			$blog_users = get_users_of_blog();
			foreach ($blog_users as $user)
			{
				if ('true' == get_user_option('bfox_email_readings', $user->user_id))
				{
					$result = wp_mail($user->user_email, $subject, "<html>$message</html>", $headers);
					if ($result) $success[] = $user->user_email;
					else $failed[] = $user->user_email;
				}
			}

			// Send a log message to the admin email with info about the emails that were just sent
			$message = "<p>The following message was sent to these users:<br/>Successful: " . implode(', ', $success) . '<br/>Failed: ' . implode(', ', $failed) . "</p><hr/>$message";
			// TODO3: get_site_option() is a WPMU-only function
			wp_mail(get_site_option('admin_email'), $subject, "<html>$message</html>", $headers);
		}

		/**
		 * Send all the reading plan emails for this blog
		 *
		 */
		function send_emails()
		{
			$plans = $this->get_plans();
			$not_finished_count = 0;
			foreach ($plans as $plan)
			{
				// We can only send out emails if there is an actual reading for today
				// So, first check it the plan is finished, if it is stop the email scheduled event
				// Otherwise, see if there is a reading for today, and if so, send it
				if (!$plan->is_finished)
				{
					$not_finished_count++;
					if (isset($plan->todays_reading))
					{
						$this->send_plan_emails($plan);
					}
				}
			}

			// If there aren't any unfinished plans, then we might as well get rid of the email action
			if (0 == $not_finished_count)
			{
				wp_clear_scheduled_hook('bfox_plan_emails_send_action');
			}
		}
	}

	/**
	 * Send the reading emails for this blog
	 *
	 */
	function bfox_plan_emails_send()
	{
		global $bfox_plan;
		$bfox_plan->send_emails();
	}
	add_action('bfox_plan_emails_send_action', 'bfox_plan_emails_send');

	/*
	 Class for managing the plans stored for individual users
	 */
	class PlanProgress extends Plan
	{
		protected $user_id;

		function PlanProgress($user_id = 0)
		{
			global $user_ID;
			if (0 == $user_id) $user_id = $user_ID;
			if (0 < $user_id)
			{
				$this->plan_table_name = BFOX_BASE_TABLE_PREFIX . "u{$user_id}_reading_plan";
				$this->data_table_name = BFOX_BASE_TABLE_PREFIX . "u{$user_id}_reading_plan_progress";
				$this->user_id = $user_id;

				// If the table doesn't exist, create it
				global $wpdb;
				if ($wpdb->get_var("SHOW TABLES LIKE '$this->plan_table_name'") != $this->plan_table_name)
					$this->create_tables();
			}
			else
			{
				unset($this->plan_table_name);
				unset($this->data_table_name);
			}
		}

		function create_tables()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

			$sql = '';

			if (isset($this->plan_table_name))
			{
				$sql .= "CREATE TABLE $this->plan_table_name (
				id bigint(20) unsigned NOT NULL auto_increment,
				blog_id int,
				original_plan_id int,
				PRIMARY KEY  (id)
				);";
			}

			if (isset($this->data_table_name))
			{
				$sql .= "CREATE TABLE $this->data_table_name (
				id bigint(20) unsigned NOT NULL auto_increment,
				plan_id int,
				period_id int,
				ref_id int,
				verse_start int,
				verse_end int,
				is_read boolean,
				PRIMARY KEY  (id)
				);";
			}

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		function track_plan($blog_id, $original_plan_id)
		{
			if (isset($this->plan_table_name))
			{
				global $wpdb;

				// Add this user to the blog's plan records
				$bfox_plan = new PlanBlog($blog_id);
				$bfox_plan->add_user_to_plan($original_plan_id, $this->user_id);

				// Check if we are already tracking this plan
				// If we aren't, then we should begin tracking it
				// If we are, then we should delete the old data (but remember what we have already read)
				$plan_id = $this->get_plan_id($blog_id, $original_plan_id);
				if (!isset($plan_id))
				{
					// Update the plan table
					$insert = $wpdb->prepare("INSERT INTO $this->plan_table_name
											 (blog_id, original_plan_id)
											 VALUES (%d, %d)",
											 $blog_id, $original_plan_id);

					// Insert and get the plan ID
					$wpdb->query($insert);
					$plan_id = $wpdb->insert_id;
				}
				else
				{
					// Get the references which this user has already marked as read
					$old_refs = $this->get_plan_refs($plan_id);

					// Delete all their old tracking data
					$this->delete_plan_data($plan_id);
				}

				// Update the data table
				if (isset($plan_id) && isset($this->data_table_name))
				{
					global $wpdb;
					$src_table = $bfox_plan->get_data_table_name();

					if ($wpdb->get_var("SHOW TABLES LIKE '$src_table'") == $src_table)
					{
						$insert = $wpdb->prepare("INSERT INTO $this->data_table_name
												 (plan_id, period_id, ref_id, verse_start, verse_end, is_read)
												 SELECT %d, $src_table.period_id, $src_table.ref_id, $src_table.verse_start, $src_table.verse_end, FALSE
												 FROM $src_table
												 WHERE plan_id = %d",
												 $plan_id,
												 $original_plan_id);
						$wpdb->query($insert);
					}
				}

				// For each scripture they had read previously, mark as read again
				if (isset($old_refs))
					foreach ($old_refs->read as $refs) $this->mark_as_read($refs, $plan_id);

				// Return the newly inserted plan id
				return $plan_id;
			}
		}

		function get_plan_id($blog_id, $original_plan_id)
		{
			if (isset($this->plan_table_name))
			{
				global $wpdb;
				return $wpdb->get_var($wpdb->prepare("SELECT id
													 FROM $this->plan_table_name
													 WHERE blog_id = %d
													 AND original_plan_id = %d",
													 $blog_id,
													 $original_plan_id));
			}
		}

		function mark_as_read(BibleRefs $refs, $plan_id = NULL)
		{
			global $wpdb;
			if (!is_null($plan_id)) $plan_where = $wpdb->prepare('plan_id = %d AND', $plan_id);

			foreach ($refs->get_sets() as $unique_ids)
			{
				$read_start = $unique_ids[0];
				$read_end = $unique_ids[1];

				// Find all plan refs, where one of the unique ids is between the start and end verse
				// or where both the start and end verse are inside of the unique ids
				$select = $wpdb->prepare("SELECT *
										 FROM $this->data_table_name
										 WHERE $plan_where is_read = FALSE
										 AND ((%d BETWEEN verse_start AND verse_end)
										   OR (%d BETWEEN verse_start AND verse_end)
										   OR (%d < verse_start AND %d > verse_end))",
										 $read_start,
										 $read_end,
										 $read_start, $read_end);
				$plans = $wpdb->get_results($select);

				foreach ($plans as $plan)
				{
					$plan_start = $plan->verse_start;
					$plan_end = $plan->verse_end;

					// If we started reading before or when the plan started
					// Otherwise we must have started reading after the plan started
					if ($read_start <= $plan_start)
					{
						// We started reading before or when the plan started, so...
						// If we finished reading after or when the plan ended
						// Then we read the whole plan
						// Otherwise, we read the first portion of the plan
						if ($read_end >= $plan_end)
						{
							// We read all of the plan
							$read = array($plan_start, $plan_end);
						}
						else
						{
							// We read the first portion
							// The last portion is still unread
							$read = array($plan_start, $read_end);
							$unread1 = array($read_end + 1, $plan_end);
						}
					}
					else
					{
						// We started reading after the plan started, so...
						// If we finished reading after or when the plan ended
						// Then we read the last portion of the plan
						// Otherwise, we read in the middle of the plan
						if ($read_end >= $plan_end)
						{
							// We read the last portion
							// The first portion is still unread
							$read = array($read_start, $plan_end);
							$unread1 = array($plan_start, $read_start - 1);
						}
						else
						{
							// We read a middle portion
							// The first and last portions are still unread
							$read = array($read_start, $read_end);
							$unread1 = array($plan_start, $read_start - 1);
							$unread2 = array($read_end + 1, $plan_end);
						}
					}

					// We should definitely have found some section which was read
					if (isset($read))
					{
						$update = $wpdb->prepare("UPDATE $this->data_table_name SET is_read = TRUE, verse_start = %d, verse_end = %d WHERE id = %d", $read[0], $read[1], $plan->id);
						$wpdb->query($update);
						if (isset($unread1))
						{
							$insert = $wpdb->prepare("INSERT INTO $this->data_table_name
													 (plan_id, period_id, ref_id, verse_start, verse_end, is_read)
													 VALUES (%d, %d, %d, %d, %d, FALSE)",
													 $plan->plan_id, $plan->period_id, $plan->ref_id, $unread1[0], $unread1[1]);
							if (isset($unread2))
								$insert .= $wpdb->prepare(", (%d, %d, %d, %d, %d, FALSE)", $plan->plan_id, $plan->period_id, $plan->ref_id, $unread2[0], $unread2[1]);
							$wpdb->query($insert);
						}
					}
					unset($read);
					unset($unread1);
					unset($unread2);
				}
			}
		}

		/*
		function get_read_status($plan_refs, $read_refs)
		{
			/*
			 $plan_refs ordered by start, end
			 $read_refs ordered by start and there can't have overlapping references

			 a1,a2
			 skip all that end before a1
			 everything else that starts before or at a2 overlaps

			 everything else that ends before or at a2 overlaps
			 everything else that ends after a2 overlaps if it starts before or at a2
			 while
			$read_ref = array_pop($read_refs);
			$start_index = 0;
			$end_index = 0;
			$count = count($read_refs);
			foreach ($plan_refs as $plan_ref)
			{
				// Skip every passage that ends before the reading starts
				while (($start_index < $count) && ($read_refs[$start_index]->end < $plan_ref->end)) $start_index++;
				while (($end_index < $count) && ($read_refs[$end_index]->start <= $plan_ref->end)) $end_index++;
				$unread_start = $plan_ref->start;
				$unread_end = $plan_ref->end;
				for ($index = $start_index; $index < $end_index; $index++)
				{
					$new_read_ref = new BibleRefs;
					if ($unread_start < $read_refs[$index]->start)
					{
						$new_unread_ref = new BibleRefs;
						$new_unread_ref->start = $unread_start;
						$new_unread_ref->end = $read_refs[$index]->start - 1;
						$divs[] = $new_unread_ref;

						$new_read_ref->start = $read_refs[$index]->start;
					}
					else
					{
						$new_read_ref->start = $unread_start;
					}

					$new_read_ref->end = $read_refs[$index]->end;
					$new_read_ref->date = $read_refs[$index]->date;
					$unread_start = $new_read_ref->end + 1;
					$divs[] = $new_read_ref;
				}
				foreach (
				if ($plan_start < $read_start)
				{
					if ($plan_end < $read_start)
				}
			}
		}
		 */
	}

	global $bfox_plan;
	$bfox_plan = new PlanBlog();
	global $bfox_plan_progress;
	$bfox_plan_progress = new PlanProgress();

	/**
	 * Called before loading the manage reading plan admin page
	 *
	 * Performs all the user's reading plan edit requests before loading the page
	 *
	 */
	function bfox_manage_reading_plans_load()
	{
		global $bfox_plan, $blog_id, $bfox_plan_editor;
		$bfox_page_url = 'admin.php?page=' . BFOX_MANAGE_PLAN_SUBPAGE;

		require_once BFOX_PLANS_DIR . '/edit.php';
		$bfox_plan_editor = new BfoxPlanEdit($blog_id, BfoxPlans::owner_type_blog, $bfox_page_url);
		$bfox_plan_editor->page_load();
		add_action('admin_head', 'BfoxPlanEdit::add_head');

		/*$action = $_POST['action'];
		if ( isset($_POST['deleteit']) && isset($_POST['delete']) )
			$action = 'bulk-delete';

		switch($action)
		{

		case 'addplan':

			check_admin_referer('add-reading-plan');

			if ( !current_user_can(BFOX_USER_LEVEL_MANAGE_PLANS) )
				wp_die(__('Cheatin&#8217; uh?'));

			$refs = RefManager::get_from_str((string) $_POST['plan_group_passages']);
			$section_size = (int) $_POST['plan_chapters'];
			if ($section_size == 0) $section_size = 1;

			$plan = array();
			$plan['name'] = stripslashes($_POST['plan_name']);
			$plan['summary'] = stripslashes($_POST['plan_description']);
			$plan['refs_array'] = $refs->get_sections($section_size);
			$plan['start_date'] = BfoxUtility::format_local_date($_POST['schedule_start_date']);
			$plan['frequency'] = $bfox_plan->frequency[$_POST['schedule_frequency']];
			$plan['frequency_options'] = implode('', (array) $_POST['schedule_frequency_options']);
			$plan_id = $bfox_plan->add_new_plan((object) $plan);
			wp_redirect(add_query_arg(array('action' => 'edit', 'plan_id' => $plan_id, 'message' => 1), $bfox_page_url));

			exit;
		break;

		case 'bulk-delete':
			check_admin_referer('bulk-reading-plans');

			if ( !current_user_can(BFOX_USER_LEVEL_MANAGE_PLANS) )
				wp_die( __('You are not allowed to delete reading plans.') );

			foreach ( (array) $_POST['delete'] as $plan_id ) {
				$bfox_plan->delete($plan_id);
			}

			wp_redirect(add_query_arg('message', 2, $bfox_page_url));
			exit();

		break;

		case 'editedplan':
			$plan_id = (int) $_POST['plan_id'];
			check_admin_referer('update-reading-plan-' . $plan_id);

			if ( !current_user_can(BFOX_USER_LEVEL_MANAGE_PLANS) )
				wp_die(__('Cheatin&#8217; uh?'));

			$old_refs = $bfox_plan->get_plan_refs($plan_id);
			$text = trim((string) $_POST['plan_passages']);
			$sections = explode("\n", $text);

			$group_refs = RefManager::get_from_str((string) $_POST['plan_group_passages']);
			$section_size = (int) $_POST['plan_chapters'];
			if ($section_size == 0) $section_size = 1;

			$plan = array();
			$plan['id'] = $plan_id;
			$plan['name'] = stripslashes($_POST['plan_name']);
			$plan['summary'] = stripslashes($_POST['plan_description']);
			$plan['refs_array'] = array();
			$plan['start_date'] = BfoxUtility::format_local_date($_POST['schedule_start_date']);
			$plan['frequency'] = $bfox_plan->frequency[$_POST['schedule_frequency']];
			$plan['frequency_options'] = implode('', (array) $_POST['schedule_frequency_options']);

			// Create the refs array
			$index = 0;
			$is_edited = false;
			foreach ($sections as $section)
			{
				$section = trim($section);

				// Determine if the text we got from input is different from the text already saved for this plan
				if (!isset($old_refs->unread[$index]) || ($old_refs->unread[$index]->get_string() != $section))
					$is_edited = true;

				$refs = RefManager::get_from_str($section);
				if ($refs->is_valid()) $plan['refs_array'][] = $refs;
				$index++;
			}

			// If we didn't actually make any changes to the refs_array then there is no need to send it
		/*	if (!$is_edited && (count($old_refs->unread) == count($plan['refs_array'])))
				unset($plan['refs_array']);*/

			// Add the group chunk refs to the refs array
			/*$plan['refs_array'] = array_merge($plan['refs_array'], $group_refs->get_sections($section_size));

			$bfox_plan->edit_plan((object) $plan);

			wp_redirect(add_query_arg(array('action' => 'edit', 'plan_id' => $plan_id, 'message' => 3), $bfox_page_url));

			exit;
		break;
		}*/
	}

	/**
	 * Outputs the reading plan management admin page
	 *
	 */
	function bfox_manage_reading_plans()
	{
		global $bfox_plan_editor;
		$bfox_plan_editor->content();

		/*$messages[1] = __('Reading Plan added.');
		$messages[2] = __('Reading Plan deleted.');
		$messages[3] = __('Reading Plan updated.');
		$messages[4] = __('Reading Plan not added.');
		$messages[5] = __('Reading Plan not updated.');

		if (isset($_GET['message']) && ($msg = (int) $_GET['message'])): ?>
			<div id="message" class="updated fade"><p><?php echo $messages[$msg]; ?></p></div>
			<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
		endif;

		switch($_GET['action'])
		{
		case 'edit':
			$plan_id = (int) $_GET['plan_id'];
			include('edit-plan-form.php');
			break;

		default:
			include('manage-plans.php');
			break;
		}*/
	}

?>