<?php

	class History
	{
		private $table_name;

		function History($user_id = 0)
		{
			global $user_ID;
			if (0 == $user_id) $user_id = $user_ID;
			if (0 < $user_id) $this->table_name = BFOX_BASE_TABLE_PREFIX . "u{$user_id}_read_history";
			else unset($this->table_name);
		}

		function are_tables_installed()
		{
			global $wpdb;
			return (!isset($this->table_name) || ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") == $this->table_name));
		}

		function create_tables()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

			if (isset($this->table_name))
			{
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
		}

		// Returns BibleRefs for a given history id
		function get_refs_for_id($id)
		{
			global $wpdb;
			$refs = RefManager::get_from_sets($wpdb->get_results($wpdb->prepare("SELECT verse_start, verse_end FROM $this->table_name WHERE id = %d", $id), ARRAY_N));
			return $refs;
		}

		function get_refs_for_time($time, $read)
		{
			global $wpdb;
			$refs = RefManager::get_from_sets($wpdb->get_results($wpdb->prepare("SELECT verse_start, verse_end FROM $this->table_name WHERE time = CAST(%s as DATETIME) AND is_read = %d", $time, $read), ARRAY_N));
			return $refs;
		}

		// TODO: This function should go in a general SQL utility area
		function sql_array_expression($column, $vals)
		{
			global $wpdb;
			// If $vals is not an array, make it one
			if (!is_array($vals)) $vals = array($vals);

			$exprs = array();
			foreach ($vals as $val)
			{
				if (is_string($val)) $type = '%s';
				else $type = '%d';

				$exprs[] = $wpdb->prepare("$column = $type", $val);
			}
			return '(' . implode(' OR ', $exprs) . ')';
		}

		function update(BibleRefs $refs, $is_read = false)
		{
			if (isset($this->table_name))
			{
				global $wpdb;

				if ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name)
					$this->create_tables();
				else
/*				{
					// Get all the history ids which are inside this ref (viewed only)
					$times = $this->get_ref_history_times($refs, 0, false, true);

					// If we found history ids inside this ref, then we should remove them (and reuse one of their ids)
					// Otherwise, we should just get a new id
					if (0 < count($times))
						$wpdb->query("DELETE FROM $this->table_name WHERE " . $this->sql_array_expression('time', $times));
				}*/

				$values = array();
				foreach ($refs->get_sets() as $unique_ids)
					$values[] = $wpdb->prepare("(%d, %d, NOW(), %d)", $unique_ids[0], $unique_ids[1], $is_read);

				if (0 < count($values))
					$wpdb->query("INSERT INTO $this->table_name (verse_start, verse_end, time, is_read) VALUES " . implode(', ', $values));

				if ($is_read)
				{
					global $bfox_plan_progress;
					$bfox_plan_progress->mark_as_read($refs);
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
				$times = $wpdb->get_col("SELECT time FROM $this->table_name $where_read GROUP BY time DESC");

				// Create an array of reference strings
				if (0 < count($times))
				{
					$index = 0;
					foreach ($times as $time)
					{
						if ($index < $max)
						{
							$refs_array[] = $this->get_refs_for_time($time, $read);
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
				if ($read) $where_read = 'AND (is_read = TRUE)';

				// If $inside is set, then we only want verses that are inside of $refs
				// Otherwise, we want any verses which overlap this one
				if ($inside) $having_ref = 'HAVING ' . $refs->sql_where("MIN($this->table_name.verse_start)") . ' AND ' . $refs->sql_where("MAX($this->table_name.verse_end)");
				else $where_ref = 'AND ' . $refs->sql_where2("$this->table_name.verse_start", "$this->table_name.verse_end");

				// Only use the limit if we set a max value
				if ($max) $limit = $wpdb->prepare("LIMIT %d", $max);

				// Get all the history ids for this user
				$select = "SELECT time FROM $this->table_name WHERE 1=1 $where_read $where_ref GROUP BY time DESC $having_ref $limit";
				$times = $wpdb->get_col($select);
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
			return get_option('home') . '/?bfox_special=my_history';
		}

		function get_dates_str(BibleRefs $refs, $read = false)
		{
			list($time) = $this->get_ref_history_times($refs, 1, $read);

			if ($read) $read_str = 'read';
			else $read_str = 'viewed';
			$read_link = "<a href=\"" . $this->get_special_url($read) . "#recent_{$read_str}\">$read_str</a>";

			if (isset($time))
			{
				$date = $this->get_date_for_time($time);
				$str = "You last $read_link " . $refs->get_string() . " on $date";
			}
			else $str = "You have not previously $read_link " . $refs->get_string();

			return $str;
		}

		/*
		function get_flat_history_sets(DateTime $start_time)
		{
			global $wpdb;

			// The sets of read verses should be ordered by priority, with the big guys (higher priority) coming before smaller guys
			$sets = $wpdb->get_results($wpdb->prepare("SELECT verse_start, verse_end, time
													  FROM $this->table_name
													  WHERE time >= CAST(%s as DATETIME)
													  AND is_read = %d
													  ORDER BY time DESC",
													  $start_time->format('c'),
													  $read));

			$count = count($sets);

			for ($big_index = 0; $big_index < $count; $big_index++)
			{
				// Big guys eat small guys, so
				// For each guy smaller than this big guy, see if we can eat anything
				for ($small_index = $big_index + 1; $small_index < $count; $small_index++)
				{
					$big_start = $sets[$big_index]->verse_start;
					$big_end = $sets[$big_index]->verse_end;
					$small_start = $sets[$small_index]->verse_start;
					$small_end = $sets[$small_index]->verse_end;

					$uneaten = array($small_start, $small_end);

					// If the big guy starts before or where the small guy starts
					// Then the big guy might eat the first portion of the small guy
					// Otherwise, we should see if the big guy eats later portions
					if ($big_start <= $small_start)
					{
						// If the big guy ends after the small guy starts
						// Then some of the small guy will be eaten
						// Otherwise, the big guy won't eat any of the small guy
						if ($big_end >= $small_start)
							$uneaten = array($big_end + 1, $small_end);
					}
					else
					{
						// Because the big guy doesn't start before or when the small guy starts
						// If the big guy starts before or when the small guy ends
						// Then some of the small guy will be eaten
						if ($big_start <= $small_end)
						{
							$uneaten = array($small_start, $big_start - 1);

							// If the big guy ends before the small guy ends
							// Then only a middle portion of the small guy will be eaten,
							//   so there will be two uneaten portions (the small guy will be split in two)
							if ($big_end < $small_end)
								$uneaten2 = array($big_end + 1, $small_end);
						}
					}

					// Set the small guy to the uneaten values
					$sets[$small_index]->verse_start = $uneaten[0];
					$sets[$small_index]->verse_end = $uneaten[1];

					// Add uneaten2 to the end of the sets
					if (isset($uneaten2))
					{
						$new_set = $sets[$small_index];
						$new_set->verse_start = $uneaten2[0];
						$new_set->verse_end = $uneaten2[1];
						unset($uneaten2);

						$sets[] = $new_set;
					}
				}

				// Recalculate the count because we might have added new small guys
				$count = count($sets);
			}

			// Sort the sets by verse_start
			function verse_start_cmp($a, $b)
			{
				if ($a->verse_start == $b->verse_start) return 0;
				return ($a->verse_start < $b->verse_start) ? -1 : 1;
			}
			usort($sets, array($this, 'verse_start_cmp'));

			return $sets;
		}
		 */
	}

	global $bfox_history;
	$bfox_history = new History();

?>
