<?php

define(BFOX_TABLE_HISTORY, BP_BIBLE_BASE_TABLE_PREFIX . 'history');

class BfoxHistoryEvent {

	public $time = 0;
	public $db_time = 0;
	public $is_read = FALSE;
	public $refs = NULL;

	const table = BFOX_TABLE_HISTORY;

	public function __construct(stdClass $db_data) {

		// History time is adjusted for the user's timezone
		$this->db_time = strtotime($db_data->time);
		$this->time = BfoxUtility::adjust_time($this->db_time);

		$this->is_read = $db_data->is_read;
		$this->refs = new BfoxRefs;
		$this->refs->add_concat($db_data->verse_begin, $db_data->verse_end);
	}

	public function toggle_url() {
		return add_query_arg(BfoxQuery::var_toggle_read, urlencode($this->db_time), BfoxQuery::ref_url(''));
	}

	public function toggle_link($unread_text = '', $read_text = '') {
		if ($this->is_read) {
			if (empty($read_text)) $text = __('Mark as unread');
			else $text = $read_text;
		}
		else {
			if (empty($unread_text)) $text = __('Mark as read');
			else $text = $unread_text;
		}
		return "<a href='" . $this->toggle_url() . "'>$text</a>";
	}

	public function ref_link($name = '') {
		return Biblefox::ref_link($this->refs->get_string($name), '', '', ($this->is_read ? "class='finished'" : ''));
	}

	public function desc($date_str = '') {
		if (!empty($date_str)) $date_str = date($date_str, $this->time);

		if ($this->is_read) return __('Read') . $date_str;
		else return __('Viewed') . $date_str;
	}
}

class BfoxHistory {

	const table = BFOX_TABLE_HISTORY;
	private static $total_history = 0;

	public static function create_table() {
		// Note: for user_id (aka. owner) see WP's implementation in wp-admin/includes/schema.php

		BfoxUtility::create_table(self::table, "
			user_id BIGINT(20) UNSIGNED NOT NULL,
			time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			is_read BOOLEAN NOT NULL,
			verse_begin MEDIUMINT UNSIGNED NOT NULL,
			verse_end MEDIUMINT UNSIGNED NOT NULL,
			INDEX (user_id, time)");
	}

	public static function view_passage(BfoxRefs $refs, $user_id = 0) {
		if ($refs->is_valid()) {
			if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

			// We don't want to save history if there isn't a valid user
			if (!empty($user_id)) {
				global $wpdb;

				$rows = array();
				foreach ($refs->get_seqs() as $seq) $rows []= $wpdb->prepare("(%d, FALSE, %d, %d)", $user_id, $seq->start, $seq->end);

				$wpdb->query("INSERT INTO " . self::table . " (user_id, is_read, verse_begin, verse_end) VALUES " . implode(',', $rows));
			}
		}
	}

	public static function toggle_is_read($time, $user_id = 0) {
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		if (!empty($time) && !empty($user_id)) {
			global $wpdb;
			$wpdb->query($wpdb->prepare("UPDATE " . self::table . " SET is_read = NOT(is_read) WHERE user_id = %d AND time = FROM_UNIXTIME(%d)", $user_id, $time));
		}
	}

	// TODO: delete this old get_history()
	public static function get_history($limit = 0, $time = 0, BfoxRefs $refs = NULL, $is_read = NULL) {
		$args = array(
			'limit' => $limit,
			'time' => $time
		);

		if (!is_null($is_read)) $args['is_read'] = $is_read;
		if (!is_null($refs)) $args['refs'] = $refs;

		return self::get_history_using_args($args);
	}

	public static function get_history_using_args($args = array()) {
		global $user_ID;

		$history = array();

		if (!empty($user_ID)) {
			global $wpdb;

			extract($args);

			$wheres = array($wpdb->prepare("user_id = %d", $user_ID));

			// Time selector
			if (!empty($days_ago)) $wheres []= $wpdb->prepare("time >= (NOW() - INTERVAL %d DAY)", $days_ago);
			elseif (!empty($time)) {
				if (is_array($time)) {
					list($start, $end) = $time;
					$wheres []= $wpdb->prepare("(time BETWEEN %d AND %d)", $start, $end);
				}
				else $wheres []= $wpdb->prepare("time >= %d", $time);
			}

			if (isset($is_read)) $wheres []= $wpdb->prepare("is_read = %d", $is_read);
			if (isset($refs)) $wheres []= $refs->sql_where2();

			$limit_str = '';
			$found_rows = '';
			if (!empty($limit)) {
				if (!empty($page)) $page -= 1;
				$limit_str = $wpdb->prepare("LIMIT %d, %d", $limit * $page, $limit);
				$found_rows = 'SQL_CALC_FOUND_ROWS';
			}

			$results = $wpdb->get_results("
				SELECT $found_rows time, is_read, GROUP_CONCAT(verse_begin) as verse_begin, GROUP_CONCAT(verse_end) as verse_end
				FROM " . self::table . "
				WHERE " . implode(' AND ', $wheres) . "
				GROUP BY time, is_read
				ORDER BY time DESC
				$limit_str"
			);

			if (!empty($limit)) self::$total_history = $wpdb->get_var('SELECT FOUND_ROWS()');
			else self::$total_history = count($results);

			foreach ($results as $result) $history []= new BfoxHistoryEvent($result);
		}

		return $history;
	}

	public static function get_total_history() {
		return self::$total_history;
	}
}

?>