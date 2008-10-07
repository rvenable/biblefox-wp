<?php

	require_once('bfox-plan.php');

	class BfoxSpecialPages
	{
		function BfoxSpecialPages()
		{
			$this->pages =
			array(
				  'reading_plans' => array('title' => 'Reading Plans', 'type' => 'post', 'desc' => 'View the reading plans for this bible study'),
//				  'current_reading' => array('title' => 'Current Reading', 'type' => 'post', 'desc' => 'View the current reading for this bible study'),
				  'my_reading' => array('title' => 'My Reading', 'type' => 'post', 'desc' => 'View your current reading for this bible study')
				  );
			global $current_blog;
			foreach ($this->pages as $base => &$page)
			{
				$page['url'] = $current_blog->path . '?' . BFOX_QUERY_VAR_SPECIAL . '=' . $base;
				$page['content_cb'] = array($this, 'get_' . $base);
				$page['setup_query_cb'] = array($this, 'setup_query_' . $base);
			}
		}

		function get_url_reading_plans($plan_id = null, $action = null)
		{
			$url = $this->pages['reading_plans']['url'];
			if (null != $plan_id) $url .= '&' . BFOX_QUERY_VAR_PLAN_ID . '=' . $plan_id;
			if (null != $action) $url .= '&' . BFOX_QUERY_VAR_ACTION . '=' . $action;
			return $url;
		}

		function setup_query_my_reading($wp_query)
		{
			global $bfox_plan, $bfox_plan_progress, $blog_id;
			$blog_plans = $bfox_plan->get_plans();
			if (0 < count($blog_plans))
			{
				foreach ($blog_plans as $plan)
				{
					$progress_plan_id = $bfox_plan_progress->get_plan_id($blog_id, $plan->id);
					if (isset($progress_plan_id))
					{
						$refs_object = $bfox_plan_progress->get_plan_refs($progress_plan_id);
						if (isset($refs_object->first_unread))
						{
							if (isset($wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF]) && ('' != $wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF]))
								$wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF] .= '; ';
							$wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF] .= $refs_object->unread[$refs_object->first_unread]->get_string();
						}
					}
				}
			}
		}

		function setup_query_reading_plans($wp_query)
		{
			global $blog_id, $bfox_plan_progress;
			if ('track' == $wp_query->query_vars[BFOX_QUERY_VAR_ACTION])
				$bfox_plan_progress->track_plan($blog_id, $wp_query->query_vars[BFOX_QUERY_VAR_PLAN_ID]);
		}

		function setup_query($wp_query)
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
				$content = '';
				foreach ($plans as $plan)
				{
					$view_url = $this->get_url_reading_plans($plan->id);
					$content .= '<a href="' . $view_url . '">' . $plan->name . '</a> - ';
					if (isset($plan->summary) && ('' != $plan->summary)) $content .= $plan->summary;
					$content .= '<br/>';
				}
			}
			
			// Get the recently read scriptures
			$content .= bfox_get_recent_scriptures_output(10, true);
			
			// Get the recently viewed scriptures
			$content .= bfox_get_recent_scriptures_output(10, false);
			
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
		
		function add_to_posts($posts, $args = array())
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
