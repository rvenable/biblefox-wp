<?php

define(BFOX_TABLE_HISTORY, BFOX_BASE_TABLE_PREFIX . 'history');

class BfoxHistoryEvent {

	public $time = 0;
	public $is_read = FALSE;
	public $refs = NULL;

	const table = BFOX_TABLE_HISTORY;

	public function __construct(stdClass $db_data) {
		$this->time = $db_data->time;
		$this->is_read = $db_data->is_read;
		$this->refs = new BfoxRefs;
		$this->refs->add_concat($db_data->verse_begin, $db_data->verse_end);
	}

	public function toggle_url() {
		return add_query_arg(BfoxQuery::var_toggle_read, urlencode($this->time), BfoxQuery::page_url(BfoxQuery::page_passage));
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

	public function ref_link() {
		return Biblefox::ref_link($this->refs->get_string(), '', '', ($this->is_read ? "class='finished'" : ''));
	}

	public function desc() {
		if ($this->is_read) return __('Read');
		else return __('Viewed');
	}
}

class BfoxHistory {

	const table = BFOX_TABLE_HISTORY;

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
			$wpdb->query($wpdb->prepare("UPDATE " . self::table . " SET is_read = NOT(is_read) WHERE user_id = %d AND time = %s", $user_id, $time));
		}
	}

	public static function get_history($limit = 0, $time = 0, BfoxRefs $refs = NULL, $is_read = NULL) {
		global $user_ID;

		$history = array();

		if (!empty($user_ID)) {
			global $wpdb;

			$wheres = array($wpdb->prepare("user_id = %d", $user_ID));

			if (!empty($time)) {
				if (is_array($time)) {
					list($start, $end) = $time;
					$wheres []= $wpdb->prepare("(time BETWEEN %d AND %d)", $start, $end);
				}
				else $wheres []= $wpdb->prepare("time >= %d", $time);
			}
			if (!is_null($is_read)) $wheres []= $wpdb->prepare("is_read = %d", $is_read);
			if (!is_null($refs)) $wheres []= $refs->sql_where2();

			$results = $wpdb->get_results("SELECT time, is_read, GROUP_CONCAT(verse_begin) as verse_begin, GROUP_CONCAT(verse_end) as verse_end FROM " . self::table . " WHERE " . implode(' AND ', $wheres) . " GROUP BY time, is_read ORDER BY time DESC " . BfoxUtility::limit_str($limit));

			foreach ($results as $result) $history[$result->time] = new BfoxHistoryEvent($result);
		}

		return $history;
	}
}

?>