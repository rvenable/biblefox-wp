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
		
		function get_plan_refs($plan_id)
		{
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
						$last_read = $period_id;
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
			return (object) $group;
		}

		function get_plan_ids()
		{
			global $wpdb;
			$plan_ids = $wpdb->get_col("SELECT plan_id from $this->data_table_name GROUP BY plan_id");
			if (is_array($plan_ids)) return $plan_ids;
			return array();
		}

		function get_plans($plan_id = -1)
		{
			if (isset($this->plan_table_name))
			{
				global $wpdb;
				if (-1 != $plan_id) $where = $wpdb->prepare('WHERE id = %d', $plan_id);
				$plans = $wpdb->get_results("SELECT * from $this->plan_table_name $where");
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
				frequency_size int
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
				$wpdb->query($wpdb->prepare("INSERT INTO $this->user_table_name (plan_id, user) VALUES (%d, %d)", $plan_id, $user_id));
			}
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

		function get_plan_list($plan_id)
		{
			$orig_refs_object = $this->get_plan_refs($plan_id);
			$plan_list = array();
			$plan_list['original'] = $orig_refs_object->unread;
			
			// Get the plan progress for the current user
			global $bfox_plan_progress;
			$user_plan_id = $bfox_plan_progress->get_plan_id($this->blog_id, $plan_id);
			if (isset($user_plan_id))
			{
				$refs_object = $bfox_plan_progress->get_plan_refs($user_plan_id);
				$plan_list['unread'] = $refs_object->unread;
				$plan_list['read'] = $refs_object->read;
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

				// Update the plan table
				$insert = $wpdb->prepare("INSERT INTO $this->plan_table_name
										 (blog_id, original_plan_id)
										 VALUES (%d, %d)",
										 $blog_id, $original_plan_id);

				// Insert and get the plan ID
				$wpdb->query($insert);
				$plan_id = $wpdb->insert_id;

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
										 WHERE is_read = FALSE
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
													 (plan_id, period_id, ref_id, verse_start, verse_end, is_read)
													 VALUES (%d, %d, %d, %d, %d, FALSE)",
													 $plan->plan_id, $plan->period_id, $plan->ref_id, $unread1[0], $unread1[1]);
							if (isset($unread2))
								$insert .= $wpdb->prepare(", (%d, %d, %d, %d, %d, FALSE)", $plan->plan_id, $plan->period_id, $plan->ref_id, $unread2[0], $unread2[1]);
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
