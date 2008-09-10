<?php

	function bfox_get_history_user_id()
	{
		global $user_ID;
		get_currentuserinfo();
		if (0 < $user_ID)
			return $user_ID;
	}

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

	function bfox_get_refs_for_history_id($id)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_READ_HISTORY;
		$ranges = $wpdb->get_results($wpdb->prepare("SELECT verse_start, verse_end FROM $table_name WHERE id = %d", $id), ARRAY_N);
		return bfox_get_refs_for_ranges($ranges);
	}

	function bfox_update_table_read_history($refs, $is_read = false)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_READ_HISTORY;
		$id = 1;

		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			bfox_create_table_read_history();
		else
			$id = 1 + $wpdb->get_var("SELECT MAX(id) FROM $table_name");

		$user_id = bfox_get_history_user_id();

		if (0 < $user_id)
		{
			foreach ($refs as $ref)
			{
				$range = bfox_get_unique_id_range($ref);
				$insert = $wpdb->prepare("INSERT INTO $table_name (id, user, verse_start, verse_end, time, is_read) VALUES (%d, %d, %d, %d, NOW(), %d)", $id, $user_id, $range[0], $range[1], $is_read);
				$wpdb->query($insert);
			}
		}
	}

	function bfox_get_last_viewed_refs()
	{
		global $wpdb;
		$table_name = BFOX_TABLE_READ_HISTORY;

		$refs = array();
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name)
		{
			$user_id = bfox_get_history_user_id();
			if (0 < $user_id)
			{
				$id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE user = %d ORDER BY time DESC", $user_id));
				$refs = bfox_get_refs_for_history_id($id);
			}
		}

		return $refs;
	}

	function bfox_get_viewed_history_refs($max = 0, $read = false)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_READ_HISTORY;

		$refStrs = array();
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name)
		{
			$user_id = bfox_get_history_user_id();
			if (0 < $user_id)
			{
				// Add a where clause for is_read
				if ($read) $where_read = 'AND is_read = TRUE';
				
				// Get all the history ids for this user
				$ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_name WHERE user = %d $where_read GROUP BY id ORDER BY time DESC", $user_id));
				
				// Create an array of reference strings
				if (0 < count($ids))
				{
					$index = 0;
					foreach ($ids as $id)
					{
						if ($index < $max)
						{
							$refs = bfox_get_refs_for_history_id($id);
							$refStrs[] = bfox_get_reflist_str($refs);
							$index++;
						}
					}
				}
			}
		}

		return $refStrs;
	}

	function bfox_get_history_for_ref($refs, $max = 1, $read = false)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_READ_HISTORY;

		$ids = array();
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name)
		{
			$user_id = bfox_get_history_user_id();
			if (0 < $user_id)
			{
				// Add a where clause for is_read
				if ($read) $where_read = 'AND is_read = TRUE';
				
				$where_ref = 'AND ' . bfox_get_posts_equation_for_refs($refs, $table_name, 'verse_start', 'verse_end');
				
				// Get all the history ids for this user
				$ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_name WHERE user = %d $where_read $where_ref GROUP BY id ORDER BY time DESC LIMIT %d", $user_id, $max));
			}
		}

		return $ids;
	}

	function bfox_get_date_for_history_id($id)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_READ_HISTORY;
		return $wpdb->get_var($wpdb->prepare("SELECT DATE(time) FROM $table_name WHERE id = %d LIMIT 1", $id));
	}

	function bfox_get_special_history_url($read)
	{
		return get_option('home') . '/?bfox_special=plan';
	}

	function bfox_get_dates_last_viewed_str($refs, $read = false)
	{
		list($id) = bfox_get_history_for_ref($refs, 1, $read);

		if ($read) $read_str = 'read';
		else $read_str = 'viewed';
		$read_link = "<a href=\"" . bfox_get_special_history_url($read) . "\">$read_str</a>";

		if (isset($id))
		{
			$date = bfox_get_date_for_history_id($id);
			$str = "You last $read_link this scripture on $date";
		}
		else $str = "You have not yet $read_link this scripture";
		
		return $str;
	}

?>
