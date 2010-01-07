<?php

class BfoxBibleEmailer {

	const event_hook = 'bfox_plan_emails_send_action';

	public static function schedule() {
		wp_schedule_event((int) BfoxUtility::format_local_date('today', 'U'), 'daily', self::event_hook);
	}

	public static function unschedule() {
		wp_clear_scheduled_hook(self::event_hook);
	}





		/**
		 * Send all of today's emails for a given reading plan
		 *
		 * @param unknown_type $plan
		 */
		function send_plan_emails($plan)
		{
			// Create the email content
			$refs = $plan->refs[$plan->todays_reading];
			$subject = "$plan->name (Reading " . ($plan->todays_reading + 1) . "): " . $refs->get_string();
			$headers = "content-type:text/html";

			// Create the email message

			$blog = 'Share your thoughts about this reading: ' . BfoxBlog::ref_write_link($refs->get_string(), 'Add a blog entry');
			$instructions = "If you would not like to receive reading plan emails, go to your " . 'profile page' . ", uncheck the 'Email Readings' option and click 'Update Profile'.";

			$message = "<p>The following email contains today's scripture reading for the '$plan->name' reading plan.<br/>$instructions</p>";
			$message .= "<h2><a href='" . BfoxBlogPlans::plan_url($plan->id, $plan->todays_reading) . "'>$subject</a></h2><p>$blog</p><hr/>";
			$message .= BfoxBlog::get_verse_content_email($refs);
			$message .= "<hr/><p>$blog</p>";

			// If this isn't the first reading, we should show any blog activity since the previous reading
			if (0 < $plan->todays_reading)
			{
				$discussions = '';
				$discussions .= bfox_get_discussions(array('min_date' => $plan->date($plan->todays_reading - 1, 'Y-m-d')));
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
				wp_clear_scheduled_hook(self::event_hook);
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
	add_action(BfoxBibleEmailer::event_hook, 'bfox_plan_emails_send');

?>