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
			if (isset($this->plan_table_name))
			{
				global $wpdb;
				if (null != $plan_id) $where = $wpdb->prepare('WHERE id = %d', $plan_id);
				$plans = $wpdb->get_results("SELECT * from $this->plan_table_name $where");

				if (isset($this->blog_id))
				{
					foreach ($plans as &$plan)
					{
						$refs = $this->get_plan_refs($plan->id);
						$plan->refs = $refs->unread;
						$plan->dates = $this->get_dates($plan, count($plan->refs));
					}
				}
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
		public $frequency = array('day', 'week', 'month');

		function PlanBlog($local_blog_id = 0)
		{
			global $blog_id;
			if (0 == $local_blog_id) $local_blog_id = $blog_id;
			$this->blog_id = $local_blog_id;

			$prefix = bfox_get_blog_table_prefix($this->blog_id);
			$this->plan_table_name = $prefix . 'reading_plan';
			$this->data_table_name = $prefix . 'reading_plan_data';
			$this->user_table_name = $prefix . 'reading_plan_users';

			$this->frequency = array_merge($this->frequency, array_flip($this->frequency));
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
				start_date varchar(16),
				end_date varchar(16),
				frequency int,
				frequency_options varchar(256),
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

		// This function is only for upgrading to the new db schema
		function reset_end_dates()
		{
			global $wpdb;
			define('DIEONDBERROR', 'die!');
			$wpdb->show_errors(true);
			$query = $wpdb->prepare("UPDATE $this->plan_table_name SET start_date = %s, end_date = %s, frequency_options = '' WHERE end_date IS NULL", date('m/d/Y'), date('m/d/Y'));
			echo $query . '<br/>';
			$wpdb->query($query);
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

				// Calculate the end date
				$dates = $this->get_dates($plan, count($plan->refs_array));
				if (0 < count($dates))
					$plan->end_date = date('m/d/Y', $dates[count($dates) - 1]);
				else
					$plan->end_date = date('m/d/Y', current_time('timestamp'));

				// Update the plan table
				if (!isset($plan->name) || ('' == $plan->name)) $plan->name = 'Plan ' . $plan_id;
				$insert = $wpdb->prepare("INSERT INTO $this->plan_table_name
										 (name, summary, start_date, end_date, frequency, frequency_options)
										 VALUES (%s, %s, %s, %s, %d, %s)",
										 $plan->name, $plan->summary, $plan->start_date, $plan->end_date, $plan->frequency, $plan->frequency_options);

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

				// Calculate the end date
				$dates = $this->get_dates($plan, count($plan->refs_array));
				if (0 < count($dates))
					$plan->end_date = date('m/d/Y', $dates[count($dates) - 1]);
				else
					$plan->end_date = date('m/d/Y', current_time('timestamp'));

				// Update the plan table
				$set_array = array();
				if (isset($plan->name)) $set_array[] = $wpdb->prepare('name = %s', $plan->name);
				if (isset($plan->summary)) $set_array[] = $wpdb->prepare('summary = %s', $plan->summary);
				if (isset($plan->start_date)) $set_array[] = $wpdb->prepare('start_date = %s', $plan->start_date);
				if (isset($plan->end_date)) $set_array[] = $wpdb->prepare('end_date = %s', $plan->end_date);
				if (isset($plan->frequency)) $set_array[] = $wpdb->prepare('frequency = %d', $plan->frequency);
				if (isset($plan->frequency_options)) $set_array[] = $wpdb->prepare('frequency_options = %s', $plan->frequency_options);

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

				// If this plan is not finished, and we haven't scheduled the emails action, then we should schedule it
				if (!$plan->is_finished && !wp_next_scheduled('bfox_plan_emails_send_action'))
					wp_schedule_event(strtotime('today'), 'daily', 'bfox_plan_emails_send_action');
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

		/*
		function get_plan_list($plan_id, $add_progress = FALSE)
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
		 */

		function is_valid_date($date, $plan)
		{
			$is_valid = TRUE;
			if ($this->frequency['day'] == $plan->frequency)
			{
				if ('' == $plan->frequency_options) $plan->frequency_options = '0123456';
				$is_valid = !(FALSE === strstr($plan->frequency_options, date('w', $date)));
			}
			return $is_valid;
		}

		function get_dates(&$plan, $count = 0)
		{
			// Get today according to the local blog settings, formatted as an integer number of seconds
			$now = (int) date('U', strtotime(bfox_format_local_date('today')));

			$frequency_str = $this->frequency[$plan->frequency];
			$dates = array();
			$date = strtotime($plan->start_date);
			for ($index = 0; $index < $count + 1; $index++)
			{
				if ((0 < $index) || !$this->is_valid_date($date, $plan))
				{
					// Increment the date until
					$inc_count = 0;
					do
					{
						$date = strtotime('+1 ' . $frequency_str, $date);
						$inc_count++;
					}
					while (!$this->is_valid_date($date, $plan) && ($inc_count < 7));
				}

				// If the date is later than today, we can try to set the current and next readings
				// Otherwise, if the date is today, we can set today's reading
				$unix_date = (int) date('U', $date);
				if ($now < $unix_date)
				{
					if (!isset($plan->next_reading)) $plan->next_reading = $index;
					if (!isset($plan->current_reading) && (0 <= $index - 1)) $plan->current_reading = $index - 1;
				}
				else if ($now == $unix_date)
				{
					$plan->todays_reading = $index;
				}

				if ($index < $count)
					$dates[] = $date;
			}

			// Calculate whether this is finished
			// Note: this is based off of the $dates array, so will vary for any given plan depending on the $count parameter passed in
			$plan->is_finished = TRUE;
			if (!empty($dates) && (((int) date('U', $dates[count($dates) - 1])) >= $now)) $plan->is_finished = FALSE;

			return $dates;
		}

		/**
		 * Send all of today's emails for a given reading plan
		 *
		 * @param unknown_type $plan
		 */
		function send_plan_emails($plan)
		{
			global $bfox_links;

			// Create the email content
			$refs = $plan->refs[$plan->todays_reading];
			$subject = "$plan->name (Reading " . ($plan->todays_reading + 1) . "): " . $refs->get_string();
			$headers = "content-type:text/html";

			// Create the email message

			$blog = 'Share your thoughts about this reading: ' . $refs->get_link('Add a blog entry', 'write');
			$instructions = "If you would not like to receive reading plan emails, go to your " . $bfox_links->admin_link('profile.php#bfox_email_readings', 'profile page') . ", uncheck the 'Email Readings' option and click 'Update Profile'.";

			$message = "<p>The following email contains today's scripture reading for the '$plan->name' reading plan.<br/>$instructions</p>";
			$message .= "<h2><a href='" . $bfox_links->reading_plan_url($plan->id, NULL, $plan->todays_reading) . "'>$subject</a></h2><p>$blog</p><hr/>";
			$message .= $refs->get_scripture(TRUE);
			$message .= "<hr/><p>$blog</p>";

			// If this isn't the first reading, we should show any blog activity since the previous reading
			if (0 < $plan->todays_reading)
			{
				$discussions = '';
				$discussions .= bfox_get_discussions(array('min_date' => date('Y-m-d', $plan->dates[$plan->todays_reading - 1])));
				if (!empty($discussions)) $message .= "<div><h3>Recent Blog Activity</h3>$discussions</div>";
			}

			// Add the removal instructions again
			$message .= "<hr/><p>$instructions</p>";

			// Check each user in this blog to see if they want to receive emails
			// If they want to, send them an email
			$success = array();
			$failed = array();
			$blog_users = get_users_of_blog();
			foreach ($blog_users as $user)
			{
				if ('true' == get_user_option('bfox_email_readings', $user->user_id))
				{
					$result = wp_mail($user->user_email, $subject, "<html>$message</html>", $headers);
					if ($result) $success[] = $user->user_email;
					else $failed[] = $user->user_email;
				}
			}

			// Send a log message to the admin email with info about the emails that were just sent
			$message = "<p>The following message was sent to these users:<br/>Successful: " . implode(', ', $success) . '<br/>Failed: ' . implode(', ', $failed) . "</p><hr/>$message";
			// TODO3: get_site_option() is a WPMU-only function
			wp_mail(get_site_option('admin_email'), $subject, "<html>$message</html>", $headers);
		}

		/**
		 * Send all the reading plan emails for this blog
		 *
		 */
		function send_emails()
		{
			$plans = $this->get_plans();
			$not_finished_count = 0;
			foreach ($plans as $plan)
			{
				// We can only send out emails if there is an actual reading for today
				// So, first check it the plan is finished, if it is stop the email scheduled event
				// Otherwise, see if there is a reading for today, and if so, send it
				if (!$plan->is_finished)
				{
					$not_finished_count++;
					if (isset($plan->todays_reading))
					{
						$this->send_plan_emails($plan);
					}
				}
			}

			// If there aren't any unfinished plans, then we might as well get rid of the email action
			if (0 == $not_finished_count)
			{
				wp_clear_scheduled_hook('bfox_plan_emails_send_action');
			}
		}
	}

	/**
	 * Send the reading emails for this blog
	 *
	 */
	function bfox_plan_emails_send()
	{
		global $bfox_plan;
		$bfox_plan->send_emails();
	}
	add_action('bfox_plan_emails_send_action', 'bfox_plan_emails_send');

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

		/*
		function get_read_status($plan_refs, $read_refs)
		{
			/*
			 $plan_refs ordered by start, end
			 $read_refs ordered by start and there can't have overlapping references

			 a1,a2
			 skip all that end before a1
			 everything else that starts before or at a2 overlaps

			 everything else that ends before or at a2 overlaps
			 everything else that ends after a2 overlaps if it starts before or at a2
			 while
			$read_ref = array_pop($read_refs);
			$start_index = 0;
			$end_index = 0;
			$count = count($read_refs);
			foreach ($plan_refs as $plan_ref)
			{
				// Skip every passage that ends before the reading starts
				while (($start_index < $count) && ($read_refs[$start_index]->end < $plan_ref->end)) $start_index++;
				while (($end_index < $count) && ($read_refs[$end_index]->start <= $plan_ref->end)) $end_index++;
				$unread_start = $plan_ref->start;
				$unread_end = $plan_ref->end;
				for ($index = $start_index; $index < $end_index; $index++)
				{
					$new_read_ref = new BibleRefs;
					if ($unread_start < $read_refs[$index]->start)
					{
						$new_unread_ref = new BibleRefs;
						$new_unread_ref->start = $unread_start;
						$new_unread_ref->end = $read_refs[$index]->start - 1;
						$divs[] = $new_unread_ref;

						$new_read_ref->start = $read_refs[$index]->start;
					}
					else
					{
						$new_read_ref->start = $unread_start;
					}

					$new_read_ref->end = $read_refs[$index]->end;
					$new_read_ref->date = $read_refs[$index]->date;
					$unread_start = $new_read_ref->end + 1;
					$divs[] = $new_read_ref;
				}
				foreach (
				if ($plan_start < $read_start)
				{
					if ($plan_end < $read_start)
				}
			}
		}
		 */
	}

	global $bfox_plan;
	$bfox_plan = new PlanBlog();
	global $bfox_plan_progress;
	$bfox_plan_progress = new PlanProgress();

?>
