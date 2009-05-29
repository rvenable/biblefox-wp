<?php

define(BFOX_TABLE_HISTORY, BFOX_BASE_TABLE_PREFIX . 'history');

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

	public static function view_passage(BibleRefs $refs, $user_id = 0) {
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

	public static function get_history($limit = 0, $time = 0, $user_id = 0, $is_read = NULL) {
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		$history = array();

		if (!empty($user_id)) {
			global $wpdb;

			$wheres = array($wpdb->prepare("user_id = %d", $user_id));

			if (!empty($time)) {
				if (is_array($time)) {
					list($start, $end) = $time;
					$wheres []= $wpdb->prepare("(time BETWEEN %d AND %d)", $start, $end);
				}
				else $wheres []= $wpdb->prepare("time >= %d", $time);
			}
			if (!is_null($is_read)) $wheres []= $wpdb->prepare("is_read = %d", $is_read);

			$results = $wpdb->get_results("SELECT * FROM " . self::table . " WHERE " . implode(' AND ', $wheres) . " ORDER BY time DESC " . BfoxUtility::limit_str($limit));

			foreach ($results as $result) {
				if (!isset($history[$result->time])) $history[$result->time] = $result;
				if (!isset($history[$result->time]->refs)) $history[$result->time]->refs = new BibleRefs();
				$history[$result->time]->refs->add_seq($result->verse_begin, $result->verse_end);
			}
		}

		return $history;
	}
}

?>