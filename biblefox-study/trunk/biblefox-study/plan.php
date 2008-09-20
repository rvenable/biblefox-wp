<?php

	/*
	 Class for common plan functionality 
	 */
	class Plan
	{
		protected $table_name;

		function get($plan_id, $where_additional = '')
		{
			global $wpdb;
			$results = $wpdb->get_results($wpdb->prepare("SELECT * from $this->table_name WHERE plan_id = %d $where_additional", $plan_id));

			$sets = array();
			foreach ($results as $result)
			{
				$sets[$result->period_id][$result->ref_id] = array($result->verse_start, $result->verse_end);
			}

			// Sort the $sets array by its keys (period_id) so that it is in the proper order
			ksort($sets);

			$plan_refs_array = array();
			foreach ($sets as $unique_ids)
			{
				// Sort the $unique_ids array by its keys (ref_id) so that it is in the proper order
				ksort($unique_ids);

				$refs = new BibleRefs($unique_ids);
				if ($refs->is_valid())
					$plan_refs_array[] = $refs;
			}

			return $plan_refs_array;
		}

		function get_plan_ids()
		{
			global $wpdb;
			$plan_ids = $wpdb->get_col("SELECT plan_id from $this->table_name GROUP BY plan_id");
			if (is_array($plan_ids)) return $plan_ids;
			return array();
		}

		function delete($plan_id)
		{
			global $wpdb;
			$wpdb->query($wpdb->prepare("DELETE FROM $this->table_name WHERE plan_id = %d", $plan_id));
		}
	}

	/*
	 Class for managing the plans stored on a per blog basis, which are used as the source for plans used by individuals
	 */
	class PlanSource extends Plan
	{
		function PlanSource()
		{
			$this->table_name = BFOX_TABLE_READ_PLAN;
		}

		private function create_table()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
			
			$sql = "CREATE TABLE $this->table_name (
			plan_id int,
			period_id int,
			ref_id int,
			verse_start int,
			verse_end int
			);";
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		function insert($plan_refs_array)
		{
			global $wpdb;
			$plan_id = 1;
			
			// If the table doesn't exist, create it
			if ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name)
				$this->create_table();
			else
				$plan_id = 1 + $wpdb->get_var("SELECT MAX(plan_id) FROM $this->table_name");
			
			$period_id = 0;
			foreach ($plan_refs_array as $plan_refs)
			{
				$ref_id = 0;
				foreach ($plan_refs->get_sets() as $unique_ids)
				{
					$insert = $wpdb->prepare("INSERT INTO $this->table_name (plan_id, period_id, ref_id, verse_start, verse_end) VALUES (%d, %d, %d, %d, %d)", $plan_id, $period_id, $ref_id, $unique_ids[0], $unique_ids[1]);
					$wpdb->query($insert);
					$ref_id++;
				}
				
				$period_id++;
			}
		}
		
	}

	/*
	 Class for managing the plans stored for individual users
	 */
	class PlanProgress extends Plan
	{
		function PlanProgress()
		{
			global $user_ID;
			if (0 < $user_ID) $this->table_name = BFOX_BASE_TABLE_PREFIX . "u{$user_ID}_plan_progress";
			else unset($this->table_name);
		}

		private function create_table()
		{
			if (isset($this->table_name))
			{
				// Note this function creates the table with dbDelta() which apparently has some pickiness
				// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
				
				$sql = "CREATE TABLE $this->table_name (
				id bigint(20) unsigned NOT NULL auto_increment,
				plan_id int,
				period_id int,
				ref_id int,
				verse_start int,
				verse_end int,
				is_read boolean,
				is_original boolean,
				PRIMARY KEY  (id)
				);";
				
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
			}
		}

		function get_plan($plan_id, $is_original = true)
		{
			return get($plan_id, ' AND is_original = ' . $is_original ? 'TRUE' : 'FALSE');
		}

		function copy_plan($blog_id, $plan_id)
		{
		}

		function mark_as_read(BibleRefs $refs)
		{
			foreach ($refs->get_sets() as $unique_ids)
			{
				$read_start = $unique_ids[0];
				$read_end = $unique_ids[1];
				
				// Find all plan refs, where one of the unique ids is between the start and end verse
				// or where both the start and end verse are inside of the unique ids
				$select = $wpdb->prepare("SELECT *
										 FROM $this->table_name
										 WHERE is_original = FALSE
										 AND is_read = FALSE
										 AND ((%d BETWEEN verse_start AND verse_end)
										   OR (%d BETWEEN verse_start AND verse_end)
										   OR (%d < verse_start AND %d > verse_end))",
										 $read_start,
										 $read_end,
										 $read_start, $read_end);
				$plans = $wpdb->get_results($select);

				foreach ($plans as $plan)
				{
					$plan_start = $plan->verse_start;
					$plan_end = $plan->verse_end;

					// If we started reading before or when the plan started
					// Otherwise we must have started reading after the plan started
					if ($read_start <= $plan_start)
					{
						// We started reading before or when the plan started, so...
						// If we finished reading after or when the plan ended
						// Then we read the whole plan
						// Otherwise, we read the first portion of the plan
						if ($read_end >= $plan_end)
						{
							// We read all of the plan
							$read = array($plan_start, $plan_end);
						}
						else
						{
							// We read the first portion
							// The last portion is still unread
							$read = array($plan_start, $read_end);
							$unread1 = array($read_end + 1, $plan_end);
						}
					}
					else
					{
						// We started reading after the plan started, so...
						// If we finished reading after or when the plan ended
						// Then we read the last portion of the plan
						// Otherwise, we read in the middle of the plan
						if ($read_end >= $plan_end)
						{
							// We read the last portion
							// The first portion is still unread
							$read = array($read_start, $plan_end);
							$unread1 = array($plan_start, $read_start - 1);
						}
						else
						{
							// We read a middle portion
							// The first and last portions are still unread
							$read = array($read_start, $read_end);
							$unread1 = array($plan_start, $read_start - 1);
							$unread2 = array($read_end + 1, $plan_end);
						}
					}

					// We should definitely have found some section which was read
					if (isset($read))
					{
						$update = $wpdb->prepare("UPDATE $this->table_name SET is_read = TRUE, verse_start = %d, verse_end = %d WHERE id = %d", $read[0], $read[1], $plan->id);
						if (isset($unread1))
						{
							$insert = $wpdb->prepare("INSERT INTO $this->table_name
													 (plan_id, period_id, ref_id, verse_start, verse_end, is_read, is_original)
													 VALUES (%d, %d, %d, %d, %d, TRUE, FALSE)",
													 $plan->plan_id, $plan->period_id, $plan->ref_id, $unread1[0], $unread1[1]);
							if (isset($unread2))
								$insert .= $wpdb->prepare(", (%d, %d, %d, %d, %d, TRUE, FALSE)", $plan->plan_id, $plan->period_id, $plan->ref_id, $unread2[0], $unread2[1]);
						}
						echo "U:" . $update . "<br/>";
						echo "I:" . $insert . "<br/>";
					}
				}
			}
		}
	}

	global $bfox_plan;
	$bfox_plan = new PlanSource();
	global $bfox_plan_progress;
	$bfox_plan_progress = new PlanProgress();

?>
