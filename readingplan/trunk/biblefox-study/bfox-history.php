<?php
	
	function bfox_create_table_read_history()
	{
		echo "yo3<br/>";
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		$sql = "CREATE TABLE " . BFOX_TABLE_READ_HISTORY . " (
				id int,
				user int,
				verse_start int,
				verse_end int,
				time datetime,
				is_read boolean
			);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	
	function bfox_update_table_read_history($refs)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_READ_HISTORY;
		$id = 1;

		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			bfox_create_table_read_history();
		else
			$id = 1 + $wpdb->get_var("SELECT MAX(id) FROM $table_name");
		
		global $user_ID;
		get_currentuserinfo();

		foreach ($refs as $ref)
		{
			$range = bfox_get_unique_id_range($ref);
			$insert = $wpdb->prepare("INSERT INTO $table_name (id, user, verse_start, verse_end, time, is_read) VALUES (%d, %d, %d, %d, NOW(), FALSE)", $id, $user_ID, $range[0], $range[1]);
			$wpdb->query($insert);
		}
	}

	function bfox_get_last_viewed_refs()
	{
		global $wpdb;
		$table_name = BFOX_TABLE_READ_HISTORY;

		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			return array();

		global $user_ID;
		get_currentuserinfo();

		$id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE user = %d ORDER BY time DESC", $user_ID));
		$ranges = $wpdb->get_results($wpdb->prepare("SELECT verse_start, verse_end FROM $table_name WHERE id = %d", $id), ARRAY_N);
		return bfox_get_refs_for_ranges($ranges);
	}

	function bfox_get_viewed_history_refs($max = 0, $read = false)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_READ_HISTORY;

		// If there is not read history, just return nothing
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			return array();

		global $user_ID;
		get_currentuserinfo();

		// Add a where clause for is_read
		if ($read) $read_where = 'AND is_read = TRUE';

		// Get all the history ids for this user
		$ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_name WHERE user = %d $where_read GROUP BY id ORDER BY time DESC", $user_ID));

		// Create an array of reference strings
		$refStrs = array();
		if (0 < count($ids))
		{
			$index = 0;
			foreach ($ids as $id)
			{
				if ($index < $max)
				{
					$ranges = $wpdb->get_results($wpdb->prepare("SELECT verse_start, verse_end FROM $table_name WHERE id = %d", $id), ARRAY_N);
					$refs = bfox_get_refs_for_ranges($ranges);
					$refStrs[] = bfox_get_reflist_str($refs);
					$index++;
				}
			}
		}

		return $refStrs;
	}

?>
