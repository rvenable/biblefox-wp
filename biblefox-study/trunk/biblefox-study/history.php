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
			id bigint(20) unsigned NOT NULL auto_increment,
			verse_start int,
			verse_end int,
			time datetime,
			is_read boolean,
			PRIMARY KEY  (id)
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

		function get_refs_for_time($time)
		{
			global $wpdb;
			$refs = new BibleRefs;
			$refs->push_sets($wpdb->get_results($wpdb->prepare("SELECT verse_start, verse_end FROM $this->table_name WHERE time = %s", $time), ARRAY_N));
			return $refs;
		}
		
		// TODO: This function should go in a general SQL utility area
		function sql_array_expression($column, $vals)
		{
			global $wpdb;
			// If $vals is not an array, make it one
			if (!is_array($vals)) $vals = array($vals);

			$exprs = array();
			foreach ($vals as $val) $exprs[] = $wpdb->prepare("$column = %d", $val);
			return '(' . implode(' OR ', $vals) . ')';
		}

		function update(BibleRefs $refs, $is_read = false)
		{
			if (isset($this->table_name))
			{
				global $wpdb;
				
				if ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name)
					$this->create_table();
/*				else
				{
					// Get all the history ids which are inside this ref (viewed only)
					$ids = $this->get_ref_history_ids($refs, 0, false, true);

					// If we found history ids inside this ref, then we should remove them (and reuse one of their ids)
					// Otherwise, we should just get a new id
					if (0 < count($ids))
						$wpdb->query("DELETE FROM $this->table_name WHERE " . $this->sql_array_expression('id', $ids));
				}*/

				$values = array();
				foreach ($refs->get_sets() as $unique_ids)
					$values[] = $wpdb->prepare("(%d, %d, NOW(), %d)", $unique_ids[0], $unique_ids[1], $is_read);

				if (0 < count($values))
					$wpdb->query("INSERT INTO $this->table_name (verse_start, verse_end, time, is_read) VALUES " . implode(', ', $values));
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
				$times = $wpdb->get_col("SELECT time FROM $this->table_name $where_read GROUP BY time DESC");
				
				// Create an array of reference strings
				if (0 < count($times))
				{
					$index = 0;
					foreach ($times as $time)
					{
						if ($index < $max)
						{
							$refs_array[] = $this->get_refs_for_time($time);
							$index++;
						}
					}
				}
			}
			
			return $refs_array;
		}
		
		function get_ref_history_times(BibleRefs $refs, $max = 0, $read = false, $inside = false)
		{
			global $wpdb;
			
			$times = array();
			if ((isset($this->table_name)) && ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") == $this->table_name))
			{
				// Add a where clause for is_read
				if ($read) $where_read = '(is_read = TRUE) AND ';

				// If $inside is set, then we only want verses that are inside of $refs
				// Otherwise, we want any verses which overlap this one
				if ($inside) $where_ref = $refs->sql_where("$this->table_name.verse_start") . ' AND ' . $refs->sql_where("$this->table_name.verse_end");
				else $where_ref = $refs->sql_where2("$this->table_name.verse_start", "$this->table_name.verse_end");

				// Only use the limit if we set a max value
				if ($max) $limit = $wpdb->prepare("LIMIT %d", $max);
				
				// Get all the history ids for this user
				$times = $wpdb->get_col("SELECT time FROM $this->table_name WHERE $where_read $where_ref GROUP BY time DESC $limit");
			}
			
			return $times;
		}
		
		function get_date_for_time($time)
		{
			global $wpdb;
			return $wpdb->get_var($wpdb->prepare("SELECT DATE(%s)", $time));
		}
		
		function get_special_url($read)
		{
			return get_option('home') . '/?bfox_special=plan';
		}
		
		function get_dates_str(BibleRefs $refs, $read = false)
		{
			list($time) = $this->get_ref_history_times($refs, 1, $read);
			
			if ($read) $read_str = 'read';
			else $read_str = 'viewed';
			$read_link = "<a href=\"" . $this->get_special_url($read) . "\">$read_str</a>";
			
			if (isset($time))
			{
				$date = $this->get_date_for_time($time);
				$str = "You last $read_link this scripture on $date";
			}
			else $str = "You have not previously $read_link this scripture";
			
			return $str;
		}
	}

	global $bfox_history;
	$bfox_history = new History();

?>
