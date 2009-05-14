<?php

define(BFOX_TABLE_HISTORY, BFOX_BASE_TABLE_PREFIX . 'history');

class BfoxHistory {

	const table = BFOX_TABLE_HISTORY;

	public static function create_table() {
		// Note: for user_id (aka. owner) see WP's implementation in wp-admin/includes/schema.php

		BfoxUtility::create_table(self::table, "
			user_id BIGINT(20) UNSIGNED NOT NULL,
			time DATETIME NOT NULL,
			is_read BOOLEAN NOT NULL,
			verse_begin MEDIUMINT UNSIGNED NOT NULL,
			verse_end MEDIUMINT UNSIGNED NOT NULL,
			INDEX (user_id, time)");
	}

	public static function view_passage(BibleRefs $refs, $user_id = 0) {
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		global $wpdb;

		$rows = array();
		foreach ($refs->get_seqs() as $seq) $rows []= $wpdb->prepare("(%d, NOW(), FALSE, %d, %d)", $user_id, $seq->start, $seq->end);

		$wpdb->query("INSERT INTO " . self::table . " (user_id, time, is_read, verse_begin, verse_end) VALUES " . implode(',', $rows));
	}
}

?>