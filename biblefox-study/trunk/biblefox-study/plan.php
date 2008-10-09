<?php

	/*
	 Class for common plan functionality 
	 */
	class Plan
	{
		protected $plan_table_name;
		protected $data_table_name;

		function get_data_table_name() { return $this->data_table_name; }

		function are_tables_installed()
		{
			global $wpdb;
			return ((!isset($this->plan_table_name) || ($wpdb->get_var("SHOW TABLES LIKE '$this->plan_table_name'") == $this->plan_table_name)) &&
					(!isset($this->data_table_name) || ($wpdb->get_var("SHOW TABLES LIKE '$this->data_table_name'") == $this->data_table_name)) &&
					(!isset($this->user_table_name) || ($wpdb->get_var("SHOW TABLES LIKE '$this->user_table_name'") == $this->user_table_name)));
		}
		
		function get_plan_text($plan_id)
		{
			$refs = $this->get_plan_refs($plan_id);
			$text = '';
			foreach ($refs->unread as $refs)
				$text .= $refs->get_string() . "\n";
			return $text;
		}

		function get_plan_refs($plan_id)
		{
			// Return the cache if it is set
			if (isset($this->cache['plan_refs'][$plan_id])) return $this->cache['plan_refs'][$plan_id];

			$unread = array();
			$read = array();
			
			if (isset($this->data_table_name))
			{
				global $wpdb;

				// Get an ordered array of all the plan data for this plan
				$results = $wpdb->get_results($wpdb->prepare("SELECT * from $this->data_table_name
															 WHERE plan_id = %d
															 ORDER BY period_id ASC, ref_id ASC, verse_start ASC",
															 $plan_id));

				// For each line of plan data, organize it into BibleRefs according to its period ID
				$unread_sets = array();
				$read_sets = array();
				foreach ($results as $result)
				{
					// If we have a new period ID
					// Then we should update any BibleRef information for the old period ID and begin using the new period ID
					if (!isset($period_id) || ($period_id != $result->period_id))
					{
						// If an old period ID is set, then we need to convert its set info to BibleRefs
						if (isset($period_id))
						{
							if (0 < count($unread_sets)) $unread[$period_id] = new BibleRefs($unread_sets);
							if (0 < count($read_sets)) $read[$period_id] = new BibleRefs($read_sets);
							$unread_sets = array();
							$read_sets = array();
						}
						$period_id = $result->period_id;
					}

					// This verse set is either read or unread
					if (isset($result->is_read) && $result->is_read)
					{
						$read_sets[] = array($result->verse_start, $result->verse_end);
						if (!isset($first_unread)) $last_read = $period_id;
					}
					else
					{
						$unread_sets[] = array($result->verse_start, $result->verse_end);
						if (!isset($first_unread)) $first_unread = $period_id;
					}
				}

				// Convert any remaining sets to BibleRefs
				if (0 < count($unread_sets)) $unread[$period_id] = new BibleRefs($unread_sets);
				if (0 < count($read_sets)) $read[$period_id] = new BibleRefs($read_sets);
				
			}

			$group = array();
			$group['unread'] = $unread;
			$group['read'] = $read;
			$group['first_unread'] = $first_unread;
			$group['last_read'] = $last_read;

			// Cache the group off
			$this->cache['plan_refs'][$plan_id] = (object) $group;
			return $this->cache['plan_refs'][$plan_id];
		}

		function get_plan_ids()
		{
			global $wpdb;
			$plan_ids = $wpdb->get_col("SELECT plan_id from $this->data_table_name GROUP BY plan_id");
			if (is_array($plan_ids)) return $plan_ids;
			return array();
		}

		function get_plans($plan_id = null)
		{
//			echo 'o:' . $plan_id . '!<br/>';
			if (isset($this->plan_table_name))
			{
				global $wpdb;
				if (null != $plan_id) $where = $wpdb->prepare('WHERE id = %d', $plan_id);
				$plans = $wpdb->get_results("SELECT * from $this->plan_table_name $where");
			}

			if (is_array($plans)) return $plans;
			return array();
		}

		function delete_plan_data($plan_id)
		{
			global $wpdb;
			if (isset($this->data_table_name))
				$wpdb->query($wpdb->prepare("DELETE FROM $this->data_table_name WHERE plan_id = %d", $plan_id));
			unset($this->cache['plan_refs'][$plan_id]);
		}

		function delete($plan_id)
		{
			global $wpdb;
			$this->delete_plan_data($plan_id);
			if (isset($this->plan_table_name))
				$wpdb->query($wpdb->prepare("DELETE FROM $this->plan_table_name WHERE id = %d", $plan_id));
		}
	}

	/*
	 Class for managing the plans stored on a per blog basis, which are used as the source for plans used by individuals
	 */
	class PlanBlog extends Plan
	{
		protected $user_table_name;
		protected $blog_id;

		function PlanBlog($local_blog_id = 0)
		{
			global $blog_id;
			if (0 == $local_blog_id) $local_blog_id = $blog_id;
			$this->blog_id = $local_blog_id;

			$prefix = bfox_get_blog_table_prefix($this->blog_id);
			$this->plan_table_name = $prefix . 'reading_plan';
			$this->data_table_name = $prefix . 'reading_plan_data';
			$this->user_table_name = $prefix . 'reading_plan_users';
		}

		function create_tables()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
			
			$sql = '';

			if (isset($this->plan_table_name))
			{
				$sql .= "CREATE TABLE $this->plan_table_name (
				id bigint(20) unsigned NOT NULL auto_increment,
				name varchar(128),
				summary text,
				start_date datetime,
				frequency int,
				frequency_size int,
				PRIMARY KEY  (id)
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

		function add_user_to_plan($plan_id, $user_id)
		{
			if (isset($this->user_table_name))
			{
				global $wpdb;
				if ($wpdb->get_var($wpdb->prepare("SELECT user FROM $this->user_table_name WHERE plan_id = %d AND user = %d", $plan_id, $user_id)) != $user_id)
					$wpdb->query($wpdb->prepare("INSERT INTO $this->user_table_name (plan_id, user) VALUES (%d, %d)", $plan_id, $user_id));
			}
		}

		function get_plan_users($plan_id)
		{
			if (isset($this->user_table_name))
			{
				global $wpdb;
				$users = $wpdb->get_col($wpdb->prepare("SELECT user FROM $this->user_table_name WHERE plan_id = %d GROUP BY user", $plan_id));
			}

			if (is_array($users)) return $users;
			return array();
		}
		
		function add_new_plan($plan)
		{
			if (isset($this->plan_table_name))
			{
				global $wpdb;
				
				// If the table doesn't exist, create it
				if ($wpdb->get_var("SHOW TABLES LIKE '$this->plan_table_name'") != $this->plan_table_name)
					$this->create_tables();
				
				// Update the plan table
				if (!isset($plan->name) || ('' == $plan->name)) $plan->name = 'Plan ' . $plan_id;
				$insert = $wpdb->prepare("INSERT INTO $this->plan_table_name
										 (name, summary, start_date, frequency, frequency_size)
										 VALUES (%s, %s, NOW(), %d, %d)",
										 $plan->name, $plan->summary, 0, 0);

				// Insert and get the plan ID
				$wpdb->query($insert);
				$plan_id = $wpdb->insert_id;

				// Update the data table
				$this->insert_refs_array($plan_id, $plan->refs_array);

				return $plan_id;
			}
		}

		function edit_plan($plan)
		{
			if (isset($this->plan_table_name) && isset($plan->id))
			{
				global $wpdb;

				// Update the plan table
				$set_array = array();
				if (isset($plan->name)) $set_array[] = $wpdb->prepare('name = %s', $plan->name);
				if (isset($plan->summary)) $set_array[] = $wpdb->prepare('summary = %s', $plan->summary);
				if (isset($plan->start_date)) $set_array[] = $wpdb->prepare('start_date = CAST(%s as DATETIME)', $plan->start_date);
				if (isset($plan->frequency)) $set_array[] = $wpdb->prepare('frequency = %d', $plan->frequency);
				if (isset($plan->frequency_size)) $set_array[] = $wpdb->prepare('frequency_size = %d', $plan->frequency_size);

				$wpdb->show_errors(true);
				if (0 < count($set_array))
				{
					$wpdb->query($wpdb->prepare("UPDATE $this->plan_table_name
												SET " . implode(', ', $set_array) .
												" WHERE id = %d",
												$plan->id));
				}

				// If we changed the bible refs, we need to update the data table
				if (isset($plan->refs_array))
				{
					// Delete any old ref data in the data table
					$this->delete_plan_data($plan->id);

					// Update the data table with the new refs
					$this->insert_refs_array($plan->id, $plan->refs_array);

					// Get all the users which are tracking this plan
					$users = $this->get_plan_users($plan->id);

					// For each user, correct their tracking data
					foreach ($users as $user)
					{
						$progress = new PlanProgress($user);
						$progress->track_plan($this->blog_id, $plan->id);
					}
				}
			}
		}
		
		function insert_refs_array($plan_id, $plan_refs_array)
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

		function get_plan_list($plan_id, $add_progress = TRUE)
		{
			$orig_refs_object = $this->get_plan_refs($plan_id);
			$plan_list = array();
			$plan_list['original'] = $orig_refs_object->unread;
			
			if ($add_progress)
			{
				// Get the plan progress for the current user
				global $bfox_plan_progress;
				$user_plan_id = $bfox_plan_progress->get_plan_id($this->blog_id, $plan_id);
				if (isset($user_plan_id))
				{
					$refs_object = $bfox_plan_progress->get_plan_refs($user_plan_id);
					$plan_list['unread'] = $refs_object->unread;
					$plan_list['read'] = $refs_object->read;
				}
			}

			return (object) $plan_list;
		}
	}

	/*
	 Class for managing the plans stored for individual users
	 */
	class PlanProgress extends Plan
	{
		protected $user_id;

		function PlanProgress($user_id = 0)
		{
			global $user_ID;
			if (0 == $user_id) $user_id = $user_ID;
			if (0 < $user_id)
			{
				$this->plan_table_name = BFOX_BASE_TABLE_PREFIX . "u{$user_id}_reading_plan";
				$this->data_table_name = BFOX_BASE_TABLE_PREFIX . "u{$user_id}_reading_plan_progress";
				$this->user_id = $user_id;

				// If the table doesn't exist, create it
				global $wpdb;
				if ($wpdb->get_var("SHOW TABLES LIKE '$this->plan_table_name'") != $this->plan_table_name)
					$this->create_tables();
			}
			else
			{
				unset($this->plan_table_name);
				unset($this->data_table_name);
			}
		}

		function create_tables()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
			
			$sql = '';
			
			if (isset($this->plan_table_name))
			{
				$sql .= "CREATE TABLE $this->plan_table_name (
				id bigint(20) unsigned NOT NULL auto_increment,
				blog_id int,
				original_plan_id int,
				PRIMARY KEY  (id)
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
				is_read boolean,
				PRIMARY KEY  (id)
				);";
			}
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		function track_plan($blog_id, $original_plan_id)
		{
			if (isset($this->plan_table_name))
			{
				global $wpdb;

				// Add this user to the blog's plan records
				$bfox_plan = new PlanBlog($blog_id);
				$bfox_plan->add_user_to_plan($original_plan_id, $this->user_id);

				// Check if we are already tracking this plan
				// If we aren't, then we should begin tracking it
				// If we are, then we should delete the old data (but remember what we have already read)
				$plan_id = $this->get_plan_id($blog_id, $original_plan_id);
				if (!isset($plan_id))
				{
					// Update the plan table
					$insert = $wpdb->prepare("INSERT INTO $this->plan_table_name
											 (blog_id, original_plan_id)
											 VALUES (%d, %d)",
											 $blog_id, $original_plan_id);
					
					// Insert and get the plan ID
					$wpdb->query($insert);
					$plan_id = $wpdb->insert_id;
				}
				else
				{
					// Get the references which this user has already marked as read
					$old_refs = $this->get_plan_refs($plan_id);

					// Delete all their old tracking data
					$this->delete_plan_data($plan_id);
				}

				// Update the data table
				if (isset($plan_id) && isset($this->data_table_name))
				{
					global $wpdb;
					$src_table = $bfox_plan->get_data_table_name();
					
					if ($wpdb->get_var("SHOW TABLES LIKE '$src_table'") == $src_table)
					{
						$insert = $wpdb->prepare("INSERT INTO $this->data_table_name
												 (plan_id, period_id, ref_id, verse_start, verse_end, is_read)
												 SELECT %d, $src_table.period_id, $src_table.ref_id, $src_table.verse_start, $src_table.verse_end, FALSE
												 FROM $src_table
												 WHERE plan_id = %d",
												 $plan_id,
												 $original_plan_id);
						$wpdb->query($insert);
					}
				}
				
				// For each scripture they had read previously, mark as read again
				if (isset($old_refs))
					foreach ($old_refs->read as $refs) $this->mark_as_read($refs, $plan_id);

				// Return the newly inserted plan id
				return $plan_id;
			}
		}

		function get_plan_id($blog_id, $original_plan_id)
		{
			if (isset($this->plan_table_name))
			{
				global $wpdb;
				return $wpdb->get_var($wpdb->prepare("SELECT id
													 FROM $this->plan_table_name
													 WHERE blog_id = %d
													 AND original_plan_id = %d",
													 $blog_id,
													 $original_plan_id));
			}
		}

		function mark_as_read(BibleRefs $refs, $plan_id = NULL)
		{
			global $wpdb;
			if (!is_null($plan_id)) $plan_where = $wpdb->prepare('plan_id = %d AND', $plan_id);

			foreach ($refs->get_sets() as $unique_ids)
			{
				$read_start = $unique_ids[0];
				$read_end = $unique_ids[1];
				
				// Find all plan refs, where one of the unique ids is between the start and end verse
				// or where both the start and end verse are inside of the unique ids
				$select = $wpdb->prepare("SELECT *
										 FROM $this->data_table_name
										 WHERE $plan_where is_read = FALSE
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
						$wpdb->query($update);
						if (isset($unread1))
						{
							$insert = $wpdb->prepare("INSERT INTO $this->data_table_name
													 (plan_id, period_id, ref_id, verse_start, verse_end, is_read)
													 VALUES (%d, %d, %d, %d, %d, FALSE)",
													 $plan->plan_id, $plan->period_id, $plan->ref_id, $unread1[0], $unread1[1]);
							if (isset($unread2))
								$insert .= $wpdb->prepare(", (%d, %d, %d, %d, %d, FALSE)", $plan->plan_id, $plan->period_id, $plan->ref_id, $unread2[0], $unread2[1]);
							$wpdb->query($insert);
						}
					}
					unset($read);
					unset($unread1);
					unset($unread2);
				}
			}
		}
	}
	
	class PlanSchedule
	{
		private $table_name;
		public $frequency = array('day', 'week', 'month');
		
		function PlanSchedule()
		{
			$this->frequency = array_merge($this->frequency, array_flip($this->frequency));

			$this->table_name = BFOX_BASE_TABLE_PREFIX . 'plan_schedules';

			// If the table doesn't exist, create it
			global $wpdb;
			if ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name)
				$this->create_tables();
		}
		
		function create_tables()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
			
			$sql = '';
			
			if (isset($this->table_name))
			{
				$sql .= "CREATE TABLE $this->table_name (
				id bigint(20) unsigned NOT NULL auto_increment,
				blog_id int,
				plan_id int,
				start_date varchar(16),
				readings_per_period int,
				frequency int,
				frequency_options varchar(256),
				PRIMARY KEY  (id)
				);";
			}

			if ('' != $sql)
			{
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
			}
		}

		function update_schedule($schedule)
		{
			global $wpdb;
			$columns = 'blog_id, plan_id, start_date, readings_per_period, frequency, frequency_options';
			$values = $wpdb->prepare('%d, %d, %s, %d, %d, %s',
									 $schedule['blog_id'],
									 $schedule['plan_id'],
									 $schedule['start_date'],
									 $schedule['readings_per_period'],
									 $schedule['frequency'],
									 $schedule['frequency_options']);

			// If the schedule id is set, then we should replace
			// Otherwise we should insert
			if (isset($schedule['id']))
			{
				$query = $wpdb->prepare("REPLACE INTO $this->table_name (id, $columns) VALUES (%d, $values)", $schedule['id']);
				$wpdb->query($query);
			}
			else
			{
				$query = "INSERT INTO $this->table_name ($columns) VALUES ($values)";
				$wpdb->query($query);
				$schedule['id'] = $wpdb->insert_id;
			}

			return $schedule['id'];
		}

		function get_schedules($blog_id, $plan_id = NULL)
		{
			global $wpdb;
			$select = $wpdb->prepare("SELECT * FROM $this->table_name WHERE blog_id = %d", $blog_id);
			if (isset($plan_id)) $select .= $wpdb->prepare(" AND plan_id = %d", $plan_id);

			$results = $wpdb->get_results($select . ' ORDER BY plan_id', ARRAY_A);

			if (isset($results)) return $results;
			return array();
		}

		function get_schedule($schedule_id)
		{
			global $wpdb;
			return $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $schedule_id), ARRAY_A);
		}

		function is_valid_date($date, $schedule)
		{
			$is_valid = TRUE;
			if ($this->frequency['day'] == $schedule['frequency'])
			{
				if ('' == $schedule['frequency_options']) $schedule['frequency_options'] = '0123456';
				$is_valid = !(FALSE === strstr($schedule['frequency_options'], $date->format('w')));
			}
			return $is_valid;
		}

		function get_dates($schedule, $count = 0, $start = 0)
		{
			$frequency_str = $this->frequency[$schedule['frequency']];
			$dates = array();
			$date = date_create($schedule['start_date']);
			for ($index = 0; $index < $count + $start; $index++)
			{
				if ((0 < $index) || !$this->is_valid_date($date, $schedule))
				{
					// Increment the date until
					$inc_count = 0;
					do
					{
						$date->modify('+1 ' . $frequency_str);
						$inc_count++;
					}
					while (!$this->is_valid_date($date, $schedule) && ($inc_count < 7));
				}
				
				if ($index >= $start) $dates[] = clone($date);
			}
			return $dates;
		}
	}

	global $bfox_plan;
	$bfox_plan = new PlanBlog();
	global $bfox_plan_progress;
	$bfox_plan_progress = new PlanProgress();
	global $bfox_schedule;
	$bfox_schedule = new PlanSchedule();

?>
