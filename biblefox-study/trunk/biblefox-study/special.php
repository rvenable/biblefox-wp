<?php

	require_once('bfox-plan.php');

	class BfoxSpecialPages
	{
		function BfoxSpecialPages()
		{
			$this->pages =
			array(
				  'current_reading' => array('title' => __('Current Readings'), 'type' => 'post', 'desc' => __('View the current readings for this bible study')),
				  'reading_plans' => array('title' => __('Reading Plans'), 'type' => 'page', 'desc' => __('View the reading plans for this bible study')),
//				  'my_reading' => array('title' => __('My Reading'), 'type' => 'post', 'desc' => __('View your current reading for this bible study')),
				  'my_history' => array('title' => __('My Passage History'), 'type' => 'page', 'desc' => __('View the history of scriptures you have viewed and read')),
				  'join' => array('title' => __('Join this Bible Study'), 'type' => 'page', 'desc' => __('Make a request to join this Bible Study'))
				  );
			global $current_blog;
			foreach ($this->pages as $base => &$page)
			{
				$page['url'] = $current_blog->path . '?' . BFOX_QUERY_VAR_SPECIAL . '=' . $base;
				$page['content_cb'] = array($this, 'get_' . $base);
				$page['setup_query_cb'] = array($this, 'setup_query_' . $base);
			}
		}

		function get_url_reading_plans($plan_id = NULL, $action = NULL, $reading_id = NULL)
		{
			// HACK for when there is a reading id
			global $current_blog;
			if (is_null($reading_id))
				$url = $this->pages['reading_plans']['url'];
			else
				$url = $this->pages['current_reading']['url'];

			if (!is_null($plan_id)) $url .= '&' . BFOX_QUERY_VAR_PLAN_ID . '=' . $plan_id;
			if (!is_null($action)) $url .= '&' . BFOX_QUERY_VAR_ACTION . '=' . $action;
			if (!is_null($reading_id)) $url .= '&' . BFOX_QUERY_VAR_READING_ID . '=' . $reading_id;
			return $url;
		}

		function do_home(&$wp_query)
		{
			$wp_query->query_vars[BFOX_QUERY_VAR_SPECIAL] = 'current_reading';
			$this->setup_query($wp_query);
			// Set whether this query is a bible reference
			if (isset($wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF]))
				$wp_query->is_bfox_bible_ref = true;
		}

		function setup_query_current_reading($wp_query)
		{
			global $bfox_plan, $blog_id;
			$wp_query->bfox_plans = $bfox_plan->get_plans($wp_query->query_vars[BFOX_QUERY_VAR_PLAN_ID]);
			if (0 < count($wp_query->bfox_plans))
			{
				foreach ($wp_query->bfox_plans as &$plan)
				{
					$plan->query_readings = array();
					if (isset($wp_query->query_vars[BFOX_QUERY_VAR_READING_ID]))
						$plan->query_readings[] = $wp_query->query_vars[BFOX_QUERY_VAR_READING_ID];
					else if (isset($plan->current_reading))
						$plan->query_readings[] = $plan->current_reading;

					foreach ($plan->query_readings as $reading_id)
					{
						if (isset($wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF]) && ('' != $wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF]))
							$wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF] .= '; ';
						$wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF] .= $plan->refs[$reading_id]->get_string();
					}
				}
			}
		}

		/*
		function setup_query_reading_plans($wp_query)
		{
			global $blog_id, $bfox_plan_progress;
			if ('track' == $wp_query->query_vars[BFOX_QUERY_VAR_ACTION])
				$bfox_plan_progress->track_plan($blog_id, $wp_query->query_vars[BFOX_QUERY_VAR_PLAN_ID]);
		}
		 */

		function setup_query(&$wp_query)
		{
			$page_name = $wp_query->query_vars[BFOX_QUERY_VAR_SPECIAL];
			if (isset($this->pages[$page_name]))
			{
				$wp_query->is_bfox_special = true;

				$func = $this->pages[$page_name]['setup_query_cb'];
				if (is_callable($func)) call_user_func_array($func, array(&$wp_query));

				return true;
			}

			return false;
		}

		function get_reading_plans($args = array())
		{
			$content = '';
			
			// Get the plans for this bible blog
			global $bfox_plan;
			$plans = $bfox_plan->get_plans($args[BFOX_QUERY_VAR_PLAN_ID]);
			if (isset($args[BFOX_QUERY_VAR_PLAN_ID]))
			{
				$content .= bfox_blog_reading_plans($plans, bfox_can_user_edit_plans());
			}
			else
			{
				$content = bfox_plan_summaries($blog_id);
			}
			
			$page = array();
			$page['post_content'] = $content;
			return $page;
		}
		
		function get_current_reading($wp_query)
		{
			global $bfox_plan, $blog_id;
			$content = '';
			$blog_plans = $bfox_plan->get_plans();
			if (0 < count($blog_plans))
			{
				$content .= '<table width="100%">';
				foreach ($blog_plans as $plan)
				{
					if (isset($plan->current_reading))
					{
						$url = $this->get_url_reading_plans($plan->id);
						$scripture_link = '<a href="' . $this->get_url_reading_plans($plan->id, NULL, $plan->current_reading) . '">' . $plan->refs[$plan->current_reading]->get_string() . '</a>';
						$content .= '<tr><td><a href="' . $url . '">' . $plan->name . '</a></td><td>' . $scripture_link . '</td></tr>';
					}
				}
				$content .= '</table>';
			}
			else
			{
				$content .= __('This blog has no Bible reading plans.');
			}

			$page = array();
			$page['post_content'] = $content;
			return $page;
		}

		function get_my_reading()
		{
			$page = array();
			$page['post_content'] = bfox_plan_summaries($blog_id);
			return $page;
		}

		function get_join()
		{
			global $bfox_message, $blog_id, $user_ID;
			$requests = $bfox_message->get_join_requests($user_ID, $blog_id);
			
			if ((0 == count($requests)) && (isset($_POST['send_request'])))
			{
				$bfox_message->send_join_request($blog_id);
				$requests = $bfox_message->get_join_requests($user_ID, $blog_id);
			}

			if (0 == count($requests))
			{
				$content = <<<CONTENT
					<p>Would you like to send a message to this blog requesting to join the bible study?</p>
					<form action="" method="post">
					<input type="submit" value="Send Request" name="send_request"/>
					</form>
CONTENT;
			}
			else
			{
				$request = array_pop($requests);
				$status = $bfox_message->join_request_status[$request->status];
				$content = <<<CONTENT
					<p>Your request has been sent.</p>
					<p>Status: $status</p>
CONTENT;
			}
			
			$page = array();
			$page['post_content'] = $content;
			return $page;
		}
		
		function get_my_history()
		{
			$content = '';

			// Get the recently read scriptures
			$content .= bfox_get_recent_scriptures_output(10, true);
			
			// Get the recently viewed scriptures
			$content .= bfox_get_recent_scriptures_output(10, false);
			
			$page = array();
			$page['post_content'] = $content;
			return $page;
		}
		
		function add_to_posts(&$posts, $args = array())
		{
			$page_name = $args[BFOX_QUERY_VAR_SPECIAL];
			if (isset($this->pages[$page_name]))
			{
				$func = $this->pages[$page_name]['content_cb'];
				if (is_callable($func)) $page = call_user_func($func, $args);
				else $page = array();
				
				$page['post_title'] = $this->pages[$page_name]['title'];
				$page['post_type'] = $this->pages[$page_name]['type'];

				// If this is a page it should be the only page in the posts array
				// Otherwise it should just go at the beginning of the posts array
				if ('page' == $page['post_type']) $posts = array((object)$page);
				else $posts = array_merge(array((object)$page), $posts);
			}
		}
	}

	global $bfox_specials;
	$bfox_specials = new BfoxSpecialPages;
	
?>
