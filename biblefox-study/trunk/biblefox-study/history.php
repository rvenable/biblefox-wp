<?php

	class History
	{
		private $table_name;

		function History()
		{
			global $user_ID;
			if (0 < $user_ID) $this->table_name = BFOX_BASE_TABLE_PREFIX . "u{$user_ID}_read_history";
			else unset($this->table_name);
		}

		private function create_table()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
			
			$sql = "CREATE TABLE $this->table_name (
			id int,
			verse_start int,
			verse_end int,
			time datetime,
			is_read boolean
			);";
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
		
		// Returns BibleRefs for a given history id
		function get_refs_for_id($id)
		{
			global $wpdb;
			$refs = new BibleRefs;
			$refs->push_sets($wpdb->get_results($wpdb->prepare("SELECT verse_start, verse_end FROM $this->table_name WHERE id = %d", $id), ARRAY_N));
			return $refs;
		}
		
		function update(BibleRefs $refs, $is_read = false)
		{
			if (isset($this->table_name))
			{
				global $wpdb;
				$id = 1;
				
				if ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name)
					$this->create_table();
				else
					$id = 1 + $wpdb->get_var("SELECT MAX(id) FROM $this->table_name");

				foreach ($refs->get_sets() as $unique_ids)
				{
					$insert = $wpdb->prepare("INSERT INTO $this->table_name (id, verse_start, verse_end, time, is_read) VALUES (%d, %d, %d, NOW(), %d)", $id, $unique_ids[0], $unique_ids[1], $is_read);
					$wpdb->query($insert);
				}
			}
		}

		// Returns an array of BibleRefs with max size $max
		function get_refs_array($max = 1, $read = false)
		{
			global $wpdb;
			
			$refs_array = array();
			if ((isset($this->table_name)) && ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") == $this->table_name))
			{
				// Add a where clause for is_read
				if ($read) $where_read = 'WHERE is_read = TRUE';
				
				// Get all the history ids for this user
				$ids = $wpdb->get_col("SELECT id FROM $this->table_name $where_read GROUP BY id ORDER BY time DESC");
				
				// Create an array of reference strings
				if (0 < count($ids))
				{
					$index = 0;
					foreach ($ids as $id)
					{
						if ($index < $max)
						{
							$refs_array[] = $this->get_refs_for_id($id);
							$index++;
						}
					}
				}
			}
			
			return $refs_array;
		}
		
		function get_ref_history(BibleRefs $refs, $max = 1, $read = false)
		{
			global $wpdb;
			
			$ids = array();
			if ((isset($this->table_name)) && ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") == $this->table_name))
			{
				// Add a where clause for is_read
				if ($read) $where_read = '(is_read = TRUE) AND ';
				
				$where_ref = $refs->sql_where2("$this->table_name.verse_start", "$this->table_name.verse_end");
				
				// Get all the history ids for this user
				$ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $this->table_name WHERE $where_read $where_ref GROUP BY id ORDER BY time DESC LIMIT %d", $max));
			}
			
			return $ids;
		}
		
		function get_date_for_id($id)
		{
			global $wpdb;
			return $wpdb->get_var($wpdb->prepare("SELECT DATE(time) FROM $this->table_name WHERE id = %d LIMIT 1", $id));
		}
		
		function get_special_url($read)
		{
			return get_option('home') . '/?bfox_special=plan';
		}
		
		function get_dates_str(BibleRefs $refs, $read = false)
		{
			list($id) = $this->get_ref_history($refs, 1, $read);
			
			if ($read) $read_str = 'read';
			else $read_str = 'viewed';
			$read_link = "<a href=\"" . $this->get_special_url($read) . "\">$read_str</a>";
			
			if (isset($id))
			{
				$date = $this->get_date_for_id($id);
				$str = "You last $read_link this scripture on $date";
			}
			else $str = "You have not previously $read_link this scripture";
			
			return $str;
		}
	}

	global $bfox_history;
	$bfox_history = new History();

?>
