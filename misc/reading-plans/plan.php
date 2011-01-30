<?php

abstract class BfoxOwnedObject {
	const owner_type_user = 0;
	const owner_type_group = 1;
	//const owner_type_blog = 2;

	public $owner_id = 0;
	public $owner_type = 0;

	private $_owner = null;
	private $_user_owners = null;
	private $_user_members = null;
	private $_user_id = null;

	public function set_user_owner($user_id = 0) {
		if (!$user_id) $user_id = $GLOBALS['user_ID'];
		$this->set_owner(self::owner_type_user, $user_id);
	}

	public function set_owner($owner_type, $owner_id) {
		$this->owner_type = $owner_type;
		$this->owner_id = $owner_id;

		$this->_owner = $this->_user_owners = $this->_user_members = $this->_user_id = null;
	}

	private function owner() {
		if (is_null($this->owner)) {
			if (self::owner_type_user == $this->owner_type) $this->_owner = bp_core_get_core_userdata($this->owner_id);
			elseif (self::owner_type_group == $this->owner_type) $this->_owner = new BP_Groups_Group($this->owner_id);
		}

		return $this->_owner;
	}

	public function is_user_owner($user_id = 0) {
		if (!$user_id) $user_id = $GLOBALS['user_ID'];

		if (self::owner_type_user == $this->owner_type) return ($user_id == $this->owner_id);
		if (self::owner_type_group == $this->owner_type) {
			if (is_null($this->_user_owners)) {
				$admins = groups_get_group_admins($this->owner_id);
				$this->_user_owners = array();
				foreach ($admins as $admin) $this->_user_owners[$admin->user_id] = true;
			}
			return $this->_user_owners[$user_id];
		}
		return false;
	}

	public function user_member_ids() {
		if (is_null($this->_user_members)) {
			if (self::owner_type_group == $this->owner_type) {
				$this->_user_members = (array) BP_Groups_Member::get_group_member_ids($this->owner_id);
			}
			else {
				$this->_user_members = array($this->owner_id);
			}
		}

		return $this->_user_members;
	}

	public function is_user_member($user_id = 0) {
		if (!$user_id) {
			// A magic member is when we don't have a user signed in, but we want to let them have access to the owner's info anyway
			// This is used for cron jobs, so that we can, for instance, email group data
			if ($this->allow_magic_member) return true;
			else $user_id = $GLOBALS['user_ID'];
		}
		return in_array($user_id, $this->user_member_ids());
	}

	public function user_id() {
		if (self::owner_type_user == $this->owner_type) return $this->owner_id;

		if (is_null($this->_user_id)) {
			global $user_ID;
			if ($this->is_user_member($user_ID)) $this->_user_id = $user_ID;
		}
		return $this->_user_id;
	}

	/*
	 * Template Tags
	 */

	public function owner_name() {
		if (self::owner_type_user == $this->owner_type) return bp_core_get_username($this->owner_id);
		if (self::owner_type_group == $this->owner_type) {
			$group = $this->owner();

			// Hide names for hidden groups
			if ('hidden' == $group->status && !$this->is_user_member()) return __('Hidden Group', 'bfox');

			return $group->name;
		}
	}

	public function owner_url() {
		if (self::owner_type_user == $this->owner_type) return bp_core_get_user_domain($this->owner_id);
		if (self::owner_type_group == $this->owner_type) {
			global $bp;

			$group = $this->owner();

			// Hide URLs for hidden groups
			if ('hidden' == $group->status && !$this->is_user_member()) return $bp->root_domain . '/' . $bp->groups->slug . '/';

			return bp_get_group_permalink($group);
		}
	}

	public function owner_string() {
		if (self::owner_type_user == $this->owner_type) return __('Member', 'bfox');
		if (self::owner_type_group == $this->owner_type) return __('Group', 'bfox');
	}

	public function owner_link() {
		return '<a href="' . $this->owner_url() . '">' . $this->owner_name() . '</a>';
	}

	public function owner_select_id() {
		if (self::owner_type_user == $this->owner_type) return false;
		return $this->owner_type . '-' . $this->owner_id;
	}

	public function owner_avatar($args = '') {
		$defaults = array(
			'type' => 'full',
			'width' => false,
			'height' => false
		);

		$r = wp_parse_args($args, $defaults);
		extract($r, EXTR_SKIP);

		$args = array('item_id' => $this->owner_id, 'type' => $type, 'width' => $width, 'height' => $height);

		if (self::owner_type_user == $this->owner_type) $args['object'] = 'user';
		elseif (self::owner_type_group == $this->owner_type) {
			$args['object'] = 'group';

			// Hide avatars for hidden groups
			$group = $this->owner();
			if ('hidden' == $group->status && !$this->is_user_member()) $args['avatar_dir'] = 'identicon';
		}

		return apply_filters('bfox_owner_avatar', bp_core_fetch_avatar($args));
	}

	/*
	 * Static Management Functions
	 */

	protected static function owner_where_for_args($args) {
		global $wpdb;

		extract($args);

		$wheres = array();

		if (!empty($user_id)) $wheres []= "(owner_type = " . self::owner_type_user . " AND owner_id IN (" . implode(',', (array) $wpdb->escape($user_id)) . "))";
		if (!empty($group_id)) $wheres []= "(owner_type = " . self::owner_type_group . " AND owner_id IN (" . implode(',', (array) $wpdb->escape($group_id)) . "))";
		if (!empty($owner_id) && isset($owner_type)) $wheres []= $wpdb->prepare("(owner_type = %d AND owner_id IN (" . implode(',', (array) $wpdb->escape($owner_id)) . "))", $owner_type);

		if (!empty($wheres)) return '(' . implode(' OR ', $wheres) . ')';
		else {
			if (isset($args['user_id']) || isset($args['group_id']) || isset($args['owner_id'])) return 'false';
			else return '';
		}
	}

	public static function verify_permission($table_name, $ids, $user_id = 0) {
		global $wpdb;

		if (!empty($ids)) {
			if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

			$results = $wpdb->get_results("SELECT * FROM " . $table_name . " WHERE id IN (" . implode(',', (array) $wpdb->escape($ids)) . ")");
			foreach ($results as $result) {
				if (self::owner_type_user == $result->owner_type) {
					if ($result->owner_id != $user_id) return false;
				}
				elseif (self::owner_type_group == $this->owner_type) {
					if (!groups_is_user_admin($user_id, $group_id)) return false;
				}
				else return false;
			}
		}
		return true;
	}

	public static function add_user_to_args($user_id, $args = array()) {
		$groups = groups_get_user_groups($user_id);

		$args['user_id'] = $user_id;
		$args['group_id'] = $groups['groups'];

		return $args;
	}

	public static function add_current_owner_to_args($args = array()) {
		global $bp;

		if ($bp->groups->current_group->id) $args['group_id'] = $bp->groups->current_group->id;
		elseif ($bp->displayed_user->id) $args = self::add_user_to_args($bp->displayed_user->id, $args);
		else $args = self::add_user_to_args($bp->loggedin_user->id, $args);

		return $args;
	}
}

class BfoxReadingPlan extends BfoxOwnedObject {
	public $id = 0;
	public $copied_from_id = 0;

	public $revision_num = 0;
	public $revision_id = 0;
	public $reading_count = 0;

	public $is_published = false;
	public $user_count = 0;

	public $slug = '';
	public $name = '';
	public $description = '';

	public $time_created = 0;
	public $time_modified = 0;

	public $is_deleted = false;

	public function __construct($db_data = NULL) {
		if (!is_null($db_data) && is_object($db_data)) {
			$this->id = $db_data->id;
			$this->copied_from_id = $db_data->copied_from_id;

			$this->owner_id = $db_data->owner_id;
			$this->owner_type = $db_data->owner_type;

			$this->revision_num = $db_data->revision_num;
			$this->revision_id = $db_data->revision_id;
			$this->reading_count = $db_data->reading_count;

			$this->is_published = $db_data->is_published;
			$this->user_count = $db_data->user_count;

			$this->slug = $db_data->slug;
			$this->name = $db_data->name;
			$this->description = $db_data->description;

			$this->time_created = $db_data->time_created;
			$this->time_modified = $db_data->time_modified;

			$this->is_deleted = $db_data->is_deleted;
		}
		else {
			$this->set_user_owner();
		}
	}

	public function can_save() {
		return !empty($this->name);
	}

	public function save($update_time = true) {
		global $wpdb, $user_ID;

		if ($update_time) $this->time_modified = time();

		if (!$this->can_save()) return false;

		if (empty($this->owner_id)) $this->set_user_owner();

		if (empty($this->id) || empty($this->slug)) $this->slug = self::create_slug($this->name);

		$set = $wpdb->prepare(
			"SET copied_from_id = %d, owner_id = %d, owner_type = %d, revision_num = %d, revision_id = %d, reading_count = %d, is_published = %d, user_count = %d, slug = %s, name = %s, description = %s, time_modified = %d, is_deleted = %d",
			$this->copied_from_id, $this->owner_id, $this->owner_type, $this->revision_num, $this->revision_id, $this->reading_count, $this->is_published, $this->user_count, $this->slug, $this->name, $this->description, $this->time_modified, $this->is_deleted);

		if (empty($this->id)) {
			$this->time_created = $this->time_modified;
			$wpdb->query($wpdb->prepare("INSERT INTO " . self::$table_name . " $set, time_created = %d", $this->time_created));
			$this->id = $wpdb->insert_id;
		}
		else {
			$wpdb->query($wpdb->prepare("UPDATE " . self::$table_name . " $set WHERE id = %d", $this->id));
		}

		return $this->id;
	}

	public function update_readings($new_readings) {
		global $wpdb;

		if (!$this->can_save()) return false;

		$old_readings = $this->readings();

		$this->revision_num++;
		$this->revision_id = self::register_revision_num($this->id, $this->revision_num);

		$this->time_modified = self::save_readings($this->revision_id, $new_readings);
		$this->reading_count = count($new_readings);

		// We need to save the plan now because we have updated the revision table
		// and if we don't save the plan, they might get out of sync
		$this->save(false);

		// Update any reading schedule progress for this plan
		BfoxReadingSchedule::update_schedules_for_plan_id($this->id, $this->revision_id, $old_readings, $new_readings);
	}

	public function set_as_copy($new_owner_id, $new_owner_type) {
		$this->copied_from_id = $this->id;
		$this->id = 0;
		$this->name = "Copy of $this->name";
		$this->owner_id = $new_owner_id;
		$this->owner_type = $new_owner_type;
		$this->is_published = false;
	}

	public function readings() {
		return self::revision_readings($this->revision_id);
	}

	public function new_schedule() {
		$schedule = new BfoxReadingSchedule();
		$schedule->plan_id = $this->id;
		$schedule->revision_id = $this->revision_id;
		$schedule->reading_count = $this->reading_count;
		return $schedule;
	}

	public function update_user_count() {
		$this->user_count = BfoxReadingSchedule::count_users_for_plan_id($this->id);
	}

	/*
	 * Template Tags
	 */

	public function url() {
		return bfox_bp_plan_url($this->slug);
	}

	public function link() {
		return '<a href="' . $this->url() . '">' . $this->name . '</a>';
	}

	public function schedule_url() {
		return bfox_bp_plan_url($this->slug) . 'add-schedule/';
	}

	public function desc_html() {
		return wpautop($this->description);
	}

	public function updated_status() {
/*		$created = sprintf(__('Created %s ago', 'bfox'), bp_core_time_since($this->time_created));
		if ($this->time_created != $this->time_modified)
			$created .= sprintf(__(' (updated %s ago)', 'bfox' ), bp_core_time_since($this->time_modified));
		return $created;
*/
		return sprintf(__('updated %s ago', 'bfox' ), bp_core_time_since($this->time_modified));
	}

	public function published_status() {
		if ($this->is_deleted) return __('This plan has been deleted', 'bfox');
		return $this->is_published ? __('Published', 'bfox') : __('Not Published', 'bfox');
	}

	public function schedule_button() {
		$button = '<div class="generic-button reading_plan_schedule-button" id="reading_plan_schedule-button-' . $this->id . '">';
		$button .= '<a href="' . $this->schedule_url() . '">' . __( 'Add to Schedule', 'bfox' ) . '</a>';
		$button .= '</div>';
		return $button;
	}

	public function edit_button() {
		$button = '<div class="generic-button reading_plan_schedule-button" id="reading_plan_schedule-button-' . $this->id . '">';
		$button .= '<a href="' . $this->url() . 'edit-readings/">' . __( 'Edit Readings', 'bfox' ) . '</a>';
		$button .= '</div>';
		return $button;
	}

	public function copy_button() {
		$button = '<div class="generic-button reading_plan_copy-button" id="reading_plan_copy-button-' . $this->id . '">';
		$button .= '<a href="' . $this->url() . 'copy/">' . __( 'Copy', 'bfox' ) . '</a>';
		$button .= '</div>';
		return $button;
	}

	public function delete_button() {
		if ($this->is_deleted) return '';

		$button = '<div class="generic-button reading_plan_delete-button" id="reading_plan_delete-button-' . $this->id . '">';
		$button .= '<a href="' . $this->url() . 'delete/">' . __( 'Delete', 'bfox' ) . '</a>';
		$button .= '</div>';
		return $button;
	}

	public function avatar($args = '') {
		$defaults = array(
			'type' => 'full',
			'width' => false,
			'height' => false,
			'no_grav' => false,
		);

		$r = wp_parse_args($args, $defaults);
		extract($r, EXTR_SKIP);

		return apply_filters('bfox_reading_plan_avatar', bp_core_fetch_avatar(array( 'item_id' => $this->id, 'object' => 'plan', 'type' => $type, 'width' => $width, 'height' => $height, 'no_grav' => $no_grav)));
	}

	public function has_avatar() {
		return (bool) $this->avatar(array('no_grav' => true));
	}

	/*
	 * Static Management Functions
	 */

	const max_slug_length = 255;
	const avatar_dir = 'plan-avatars';

	const version = 9;
	private static $table_name = '';
	private static $revisions_table_name = '';
	private static $readings_table_name = '';

	public static function init_manager() {
		global $wpdb;
		self::$table_name = $wpdb->base_prefix . 'bfox_reading_plans';
		self::$revisions_table_name = $wpdb->base_prefix . 'bfox_reading_revisions';
		self::$readings_table_name = $wpdb->base_prefix . 'bfox_readings';
	}

	public static function check_install() {
		if (get_site_option(self::$table_name . '_version') < self::version) {
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta('CREATE TABLE ' . self::$table_name . ' (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				copied_from_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				owner_id BIGINT(20) UNSIGNED NOT NULL,
				owner_type TINYINT(1) NOT NULL,
				revision_id BIGINT UNSIGNED NOT NULL,
				revision_num MEDIUMINT UNSIGNED NOT NULL,
				reading_count MEDIUMINT UNSIGNED NOT NULL,
				is_published BOOLEAN NOT NULL,
				user_count BIGINT UNSIGNED NOT NULL,
				slug TINYTEXT NOT NULL,
				name TINYTEXT NOT NULL,
				description TEXT NOT NULL,
				time_created INT UNSIGNED NOT NULL,
				time_modified INT UNSIGNED NOT NULL,
				is_deleted TINYINT(1) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY owner (owner_type,owner_id)
			);

			CREATE TABLE ' . self::$revisions_table_name . ' (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				plan_id BIGINT UNSIGNED NOT NULL,
				revision_num MEDIUMINT UNSIGNED NOT NULL,
				time INT UNSIGNED NOT NULL,
				PRIMARY KEY  (id)
			);

			CREATE TABLE ' . self::$readings_table_name . ' (
				revision_id BIGINT UNSIGNED NOT NULL,
				reading_id MEDIUMINT UNSIGNED NOT NULL,
				start MEDIUMINT UNSIGNED NOT NULL,
				end MEDIUMINT UNSIGNED NOT NULL,
				KEY revision_id (revision_id)
			);');
			update_site_option(self::$table_name . '_version', self::version);
		}
	}

	public static function get_readings($args = array()) {
		global $wpdb;

		extract($args);

		$wheres = array();

		// $ref = new BfoxRef;
		if (isset($ref) && $ref->is_valid()) $wheres []= $ref->sql_where2();

		if (!empty($reading_ids)) {
			$or = array();
			foreach ($reading_ids as $_revision_id => $plan_reading_ids) {
				if (!empty($_revision_id) && !empty($plan_reading_ids))
					$or []= $wpdb->prepare('(revision_id = %d AND reading_id IN (' . implode(',', (array) $wpdb->escape($plan_reading_ids)) . '))', $_revision_id);
			}
			if (!empty($or)) $wheres []= '(' . implode(' OR ', $or) . ')';
		}

		if (!empty($revision_id)) $wheres []= '(revision_id IN (' . implode(',', (array) $wpdb->escape($revision_id)) . '))';

		$readings = array();
		if (!empty($wheres)) {
			// Get the reading info from the DB
			$_readings = $wpdb->get_results('
				SELECT *, GROUP_CONCAT(start) as start, GROUP_CONCAT(end) as end
				FROM ' . self::$readings_table_name . '
				WHERE ' . implode(' AND ', $wheres) . '
				GROUP BY revision_id, reading_id
				ORDER BY revision_id ASC, reading_id ASC');

			// Add all the readings to the reading plan
			foreach ($_readings as $_reading) {
				$reading = new BfoxRef;
				$reading->add_concat($_reading->start, $_reading->end);

				$readings[$_reading->revision_id][$_reading->reading_id] = $reading;
			}
		}

		return $readings;
	}

	public static function revision_readings($revision_id) {
		$revision_readings = self::cache_get('revision', $revision_id);
		if (is_null($revision_readings)) {
			$readings = BfoxReadingPlan::get_readings(array('revision_id' => $revision_id));
			$revision_readings = $readings[$revision_id];
			self::cache_for_id('revision', $revision_readings, $revision_id);
		}

		return (array) $revision_readings;
	}

	private static function where_for_args($args) {
		global $wpdb;

		extract($args);

		$wheres = array();

		$owner_where = self::owner_where_for_args($args);
		if (!empty($owner_where)) $wheres []= $owner_where;

		// Specific IDs
		// If we don't have specific IDs, then only get plans that aren't deleted
		if (!empty($id)) $wheres []= 'id IN (' . implode(',', (array) $wpdb->escape($id)) . ')';
		else $wheres []= 'is_deleted = 0';

		// Filters
		if ($filter) {
			$filter = '%' . like_escape($filter) . '%';
			$wheres []= $wpdb->prepare("(name LIKE %s OR description LIKE %s)", $filter, $filter);
		}

		// Is Published?
		if (isset($is_published)) $wheres []= $wpdb->prepare("is_published = %d", $is_published);

		return implode(' AND ', $wheres);
	}

	public static function get($args = array(), &$total_row_count = null) {
		global $wpdb;

		$plans = array();
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

			if (empty($order_by)) {
				$order_bys = array('alphabetical' => 'name ASC', 'updated' => 'time_modified DESC', 'newest' => 'time_created DESC', 'popular' => 'user_count DESC');
				$order_by = $order_bys[$type];
				if (empty($order_by)) $order_by = $order_bys['updated'];
			}

			$results = (array) $wpdb->get_results("SELECT $found_rows * FROM " . self::$table_name . " WHERE $where ORDER BY $order_by, id DESC $limit");
			if ($found_rows) $total_row_count = $wpdb->get_var('SELECT FOUND_ROWS()');

			foreach ($results as $_plan) $plans[$_plan->id] = new BfoxReadingPlan($_plan);
			self::cache('plan', $plans);
		}

		return array_keys($plans);
	}

	/**
	 * @param int $plan_id
	 * @return BfoxReadingPlan
	 */
	public static function plan($plan_id) {
		global $wpdb;

		if (empty($plan_id)) return new BfoxReadingPlan;

		$plan = self::cache_get('plan', $plan_id);
		if (is_null($plan)) {
			$plan = new BfoxReadingPlan($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::$table_name . ' WHERE id = %d', $plan_id)));
			self::cache('plan', array($plan));
		}

		return $plan;
	}

	public static function delete_plan(BfoxReadingPlan $plan) {
		global $wpdb;

		BfoxReadingSchedule::delete_plan($plan);
		$plan->is_deleted = true;
		$plan->save();
	}

	public static function slug_exists($slug) {
		global $wpdb;
		$plan_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::$table_name . ' WHERE slug = %s', $slug));
		return $plan_id;
	}

	public static function create_slug($name) {
		$name = sanitize_title($name);
		$slug = $name;
		$num = 1;
		while (self::slug_exists($slug)) {
			$suffix = '-' . ++$num;

			// Create a slug with a suffix and make sure that it is small enough to be a slug so that we don't have an infinite loop here
			$slug = substr($name, 0, min(strlen($name), self::max_slug_length - strlen($suffix))) . $suffix;
		}

		return $slug;
	}

	public static function count_plans($args = array()) {
		global $wpdb;
		$where = self::where_for_args($args);
		if (!empty($where)) return $wpdb->get_var("SELECT DISTINCT count(id) FROM " . self::$table_name . " WHERE $where");
		return 0;
	}

	public static function verify_permission($ids, $user_id = 0) {
		return parent::verify_permission(self::$table_name, $ids, $user_id);
	}

	/*
	 * Reading List Functions
	 */

	public static function get_revision_id($plan_id, $revision_num = 0) {
		global $wpdb;

		// If there is a revision num, get the revision_id for it
		// Otherwise, get the revision_id for the highest (latest) revision num
		if ($revision_num) return $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::$revisions_table_name . ' WHERE plan_id = %d AND revision_num = %d', $plan_id, $revision_num));
		else return $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::$revisions_table_name . ' WHERE plan_id = %d ORDER BY revision_num DESC LIMIT 1', $plan_id));
	}

	public static function register_revision_num($plan_id, $revision_num) {
		global $wpdb;

		// Check to see if there is already a revision id for this
		$revision_id = self::get_revision_id($plan_id, $revision_num);
		if ($revision_id) return $revision_id;

		$wpdb->query($wpdb->prepare('INSERT INTO ' . self::$revisions_table_name . ' (plan_id, revision_num) VALUES (%d, %d)', $plan_id, $revision_num));
		return $wpdb->insert_id;
	}

	public static function update_revision_time($revision_id) {
		global $wpdb;

		$time = time();
		$wpdb->query($wpdb->prepare('UPDATE ' . self::$revisions_table_name . ' SET time = %d WHERE id = %d', $time, $revision_id));
		return $time;
	}

	public static function save_readings($revision_id, $readings, $refresh = true) {
		global $wpdb;

		if ($refresh) $wpdb->query($wpdb->prepare('DELETE FROM ' . self::$readings_table_name . ' WHERE revision_id = %d', $revision_id));

		$values = array();
		foreach ($readings as $reading_id => $reading)
			foreach ($reading->get_seqs() as $seq)
				$values []= $wpdb->prepare('(%d, %d, %d, %d)', $revision_id, $reading_id, $seq->start, $seq->end);

		if (!empty($values)) {
			$wpdb->query($wpdb->prepare("
				INSERT INTO " . self::$readings_table_name . "
				(revision_id, reading_id, start, end)
				VALUES " . implode(',', $values)));
		}

		self::cache_for_id('revision', $readings, $revision_id);

		return self::update_revision_time($revision_id);
	}

	public static function readings_from_lines($strings) {
		if (is_string($strings)) $strings = explode("\n", $strings);

		$readings = array();
		foreach ((array) $strings as $str) {
			$ref = new BfoxRef($str);
			if ($ref->is_valid()) $readings []= $ref;
		}

		return $readings;
	}

	public static function readings_from_passages($passages, $chunk_size) {
		$chunk_size = max(1, $chunk_size);
		$ref = new BfoxRef($passages);

		$readings = array();
		if ($ref->is_valid()) {
			$chunks = $ref->get_sections($chunk_size);

			foreach ($chunks as $chunk) if ($chunk->is_valid()) $readings []= $chunk;
		}

		return $readings;
	}

	public static function compact_readings($readings) {
		$ref = new BfoxRef;
		foreach ($readings as $reading) $ref->add_ref($reading);

		return $ref;
	}

	public static function reading_strings($readings, $name = BibleMeta::name_normal) {
		$strings = array();
		foreach ($readings as $reading) $strings []= $reading->get_string($name);
		return $strings;
	}

	public static function intersecting_readings($readings, BfoxRef $ref) {
		$intersecting = array();
		foreach ($readings as $reading_id => $reading) if ($ref->intersects($reading)) $intersecting []= $reading_id;
		return $intersecting;
	}

	private static $cache;
	public static function cache($type, $items, $id_key = 'id') {
		foreach ($items as $item) self::$cache[$type][$item->$id_key] = $item;
	}

	public static function cache_for_id($type, $item, $item_id) {
		self::$cache[$type][$item_id] = $item;
	}

	public static function cache_get($type, $item_id) {
		return self::$cache[$type][$item_id];
	}

	public static function cache_clear($type = null) {
		if (is_null($type)) self::$cache = array();
		else self::$cache[$type] = array();
	}

	public static function cache_get_array($type, $item_ids) {
		$items = array();
		foreach ((array) $item_ids as $item_id) $items[$item_id] = self::$cache[$type][$item_id];
		return $items;
	}

	public static function cache_plan_ids($ids) {
		if (!empty($ids)) {
			$cached_plans = self::cache_get_array('plan', $ids);
			$ids = array_diff($ids, array_keys($cached_plans));
			if (!empty($ids)) self::get(array('id' => $ids));
		}
	}
}
BfoxReadingPlan::init_manager();
add_action('bfox_bp_check_install', 'BfoxReadingPlan::check_install');

/**
 * @return BfoxReadingPlan
 */
function bfox_bp_plan(BfoxReadingPlan $plan = null) {
	global $bfox_bp_plan;
	if (!is_null($plan)) $bfox_bp_plan = $plan;
	return $bfox_bp_plan;
}

?>