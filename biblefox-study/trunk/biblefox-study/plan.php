<?php

	/*
	 Class for common plan functionality 
	 */
	class Plan
	{
		protected $plan_table_name;
		protected $data_table_name;

		function get_data_table_name() { return $this->data_table_name; }

		function convert_plan_sets_to_refs($sets)
		{
			// Sort the $sets array by its keys (period_id) so that it is in the proper order
			ksort($sets);
			
			$plan_refs_array = array();
			foreach ($sets as $period_id => $unique_ids)
			{
				// Sort the $unique_ids array by its keys (ref_id) so that it is in the proper order
				ksort($unique_ids);
				
				$refs = new BibleRefs($unique_ids);
				if ($refs->is_valid())
					$plan_refs_array[$period_id] = $refs;
			}
			return $plan_refs_array;
		}

		function get_plan_refs($plan_id, $max_unread = -1)
		{
			global $wpdb;
			$wpdb->show_errors(true);
			$results = $wpdb->get_results($wpdb->prepare("SELECT * from $this->data_table_name WHERE plan_id = %d $this->order_by", $plan_id));

			$original_sets = array();
			$read_sets = array();
			$unread_sets = array();
			foreach ($results as $result)
			{
				$set = array($result->verse_start, $result->verse_end);
				if (!isset($result->is_original) || $result->is_original)
					$original_sets[$result->period_id][$result->ref_id] = $set;
				else
				{
					if ($result->is_read)
						$read_sets[$result->period_id][$result->ref_id] = $set;
					else
						$unread_sets[$result->period_id][$result->ref_id] = $set;
				}
				if ($max_unread == count($unread_sets)) break;
			}
			
			$group = array();
			$group['original'] = $this->convert_plan_sets_to_refs($original_sets);
			if (0 < count($read_sets)) $group['read'] = $this->convert_plan_sets_to_refs($read_sets);
			if (0 < count($unread_sets)) $group['unread'] = $this->convert_plan_sets_to_refs($unread_sets);
			return (object) $group;
		}

		function get_plan_ids()
		{
			global $wpdb;
			$plan_ids = $wpdb->get_col("SELECT plan_id from $this->data_table_name GROUP BY plan_id");
			if (is_array($plan_ids)) return $plan_ids;
			return array();
		}

		function get_plans()
		{
			if (isset($this->plan_table_name))
			{
				global $wpdb;
				$plans = $wpdb->get_results("SELECT * from $this->plan_table_name");
			}

			if (is_array($plans)) return $plans;
			return array();
		}

		function delete($plan_id)
		{
			global $wpdb;
			if (isset($this->data_table_name))
				$wpdb->query($wpdb->prepare("DELETE FROM $this->data_table_name WHERE plan_id = %d", $plan_id));
			if (isset($this->plan_table_name))
				$wpdb->query($wpdb->prepare("DELETE FROM $this->plan_table_name WHERE id = %d", $plan_id));
		}

		function get_plan_list($plan_id, $max_unread = 3, $skip_read = true)
		{
			$refs_object = $this->get_plan_refs($plan_id, $max_unread);
			foreach ($refs_object->original as $period_id => $original)
			{
				if ($skip_read && isset($refs_object->read[$period_id]) && !isset($refs_object->unread[$period_id])) continue;
				$index = $period_id + 1;
				echo "Reading $index: " . $original->get_link();
				if (isset($refs_object->unread[$period_id]))
				{
					if (isset($refs_object->read[$period_id]))
						echo " (You still need to read " . $refs_object->unread[$period_id]->get_link() . ")";
					else
						echo " (Unread)";
				}
				else
				{
					if (isset($refs_object->read[$period_id]))
						echo " (Finished!)";
				}
				echo "<br/>";
			}
		}
	}

	/*
	 Class for managing the plans stored on a per blog basis, which are used as the source for plans used by individuals
	 */
	class PlanBlog extends Plan
	{
		protected $user_table_name;

		function PlanBlog($blog_id = 0)
		{
			$prefix = bfox_get_blog_table_prefix($blog_id);
			$this->plan_table_name = $prefix . 'reading_plan';
			$this->data_table_name = $prefix . 'reading_plan_data';
			$this->user_table_name = $prefix . 'reading_plan_users';
		}

		private function create_tables()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
			
			$sql = '';

			if (isset($this->plan_table_name))
			{
				$sql .= "CREATE TABLE $this->plan_table_name (
				id int,
				name varchar(128),
				summary text,
				start_date datetime,
				frequency int,
				frequency_size int
				);";
			}
			
			if (isset($this->data_table_name))
			{
				$sql .= "CREATE TABLE $this->data_table_name (
				id bigint(20) unsigned NOT NULL auto_increment,
				plan_id int,
				period_id int,
				ref_id int,
				verse_start int,
				verse_end int,
				due_date datetime,
				PRIMARY KEY  (id)
				);";
			}

			if (isset($this->user_table_name))
			{
				$sql .= "CREATE TABLE $this->user_table_name (
				plan_id bigint(20),
				user int
				);";
			}

			if ('' != $sql)
			{
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
			}
		}

		function add_new_plan($plan)
		{
			if (isset($this->plan_table_name))
			{
				global $wpdb;
				$plan_id = 1;
				
				// If the table doesn't exist, create it
				if ($wpdb->get_var("SHOW TABLES LIKE '$this->plan_table_name'") != $this->plan_table_name)
					$this->create_tables();
				else
					$plan_id = 1 + $wpdb->get_var("SELECT MAX(id) FROM $this->plan_table_name");
				
				// Update the plan table
				$name = 'Plan ' . $plan_id;
				$summary = 'Simple Reading Plan';
				$frequency = 0;
				$frequency_size = 0;
				$insert = $wpdb->prepare("INSERT INTO $this->plan_table_name (id, name, summary, start_date, frequency, frequency_size) VALUES (%d, %s, %s, NOW(), %d, %d)", $plan_id, $name, $summary, $frequency, $frequency_size);
				$wpdb->query($insert);

				// Update the data table
				$this->insert($plan_id, $plan->refs_array);
			}
		}

		function insert($plan_id, $plan_refs_array)
		{
			if (isset($this->data_table_name))
			{
				global $wpdb;
				
				$period_id = 0;
				foreach ($plan_refs_array as $plan_refs)
				{
					$ref_id = 0;
					foreach ($plan_refs->get_sets() as $unique_ids)
					{
						$insert = $wpdb->prepare("INSERT INTO $this->data_table_name (plan_id, period_id, ref_id, verse_start, verse_end) VALUES (%d, %d, %d, %d, %d)", $plan_id, $period_id, $ref_id, $unique_ids[0], $unique_ids[1]);
						$wpdb->query($insert);
						$ref_id++;
					}
					
					$period_id++;
				}
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
			if (0 < $user_ID) $this->data_table_name = BFOX_BASE_TABLE_PREFIX . "u{$user_ID}_plan_progress";
			else unset($this->data_table_name);

			$this->order_by = 'ORDER BY period_id ASC, ref_id ASC, is_original DESC, is_read DESC';
		}

		private function create_table()
		{
			if (isset($this->data_table_name))
			{
				// Note this function creates the table with dbDelta() which apparently has some pickiness
				// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
				
				$sql = "CREATE TABLE $this->data_table_name (
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

		function copy_plan($plan_id)
		{
			if (isset($this->data_table_name))
			{
				// NOTE: This function currently uses the current blog id, which should be sufficient
				global $wpdb, $bfox_plan;
				$src_table = $bfox_plan->get_data_table_name();
				
				if ($wpdb->get_var("SHOW TABLES LIKE '$this->data_table_name'") != $this->data_table_name)
					$this->create_table();
				
				if ($wpdb->get_var("SHOW TABLES LIKE '$src_table'") == $src_table)
				{
					$prefix = "INSERT INTO $this->data_table_name (plan_id, period_id, ref_id, verse_start, verse_end, is_read, is_original)
							SELECT $src_table.plan_id, $src_table.period_id, $src_table.ref_id, $src_table.verse_start, $src_table.verse_end, FALSE, ";
					$postfix = $wpdb->prepare("FROM $src_table WHERE plan_id = %d", $plan_id);
					$insert_original = $prefix . " TRUE "  . $postfix;
					$insert_copy     = $prefix . " FALSE " . $postfix;
					$wpdb->query($insert_original);
					$wpdb->query($insert_copy);
				}
			}
		}

		function mark_as_read(BibleRefs $refs)
		{
			global $wpdb;

			foreach ($refs->get_sets() as $unique_ids)
			{
				$read_start = $unique_ids[0];
				$read_end = $unique_ids[1];
				
				// Find all plan refs, where one of the unique ids is between the start and end verse
				// or where both the start and end verse are inside of the unique ids
				$select = $wpdb->prepare("SELECT *
										 FROM $this->data_table_name
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
						$update = $wpdb->prepare("UPDATE $this->data_table_name SET is_read = TRUE, verse_start = %d, verse_end = %d WHERE id = %d", $read[0], $read[1], $plan->id);
						if (isset($unread1))
						{
							$insert = $wpdb->prepare("INSERT INTO $this->data_table_name
													 (plan_id, period_id, ref_id, verse_start, verse_end, is_read, is_original)
													 VALUES (%d, %d, %d, %d, %d, FALSE, FALSE)",
													 $plan->plan_id, $plan->period_id, $plan->ref_id, $unread1[0], $unread1[1]);
							if (isset($unread2))
								$insert .= $wpdb->prepare(", (%d, %d, %d, %d, %d, FALSE, FALSE)", $plan->plan_id, $plan->period_id, $plan->ref_id, $unread2[0], $unread2[1]);
						}
						$wpdb->query($update);
						$wpdb->query($insert);
					}
				}
			}
		}
	}

	global $bfox_plan;
	$bfox_plan = new PlanBlog();
	global $bfox_plan_progress;
	$bfox_plan_progress = new PlanProgress();

?>
