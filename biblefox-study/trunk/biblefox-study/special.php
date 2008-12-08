<?php

	require_once('bfox-plan.php');

	class BfoxSpecialPages
	{
		function BfoxSpecialPages()
		{
			$this->pages =
			array(
				  'current_readings' => array('title' => __('Current Readings'), 'type' => 'post', 'desc' => __('View the current readings for this bible study')),
				  'recent_readings' => array('title' => __('Recent Readings'), 'type' => 'page', 'desc' => __('View the recent readings for this bible study')),
				  'reading_plans' => array('title' => __('Reading Plans'), 'type' => 'page', 'desc' => __('View the reading plans for this bible study')),
//				  'my_reading' => array('title' => __('My Reading'), 'type' => 'post', 'desc' => __('View your current reading for this bible study')),
				  'my_history' => array('title' => __('My Passage History'), 'type' => 'page', 'desc' => __('View the history of scriptures you have viewed and read')),
				  'join' => array('title' => __('Join this Bible Study'), 'type' => 'page', 'desc' => __('Make a request to join this bible study')),
				  'updates' => array('title' => __('Latest Updates'), 'type' => 'page', 'desc' => __('View all the latest updates to this bible study'))
				  );
			global $current_blog;
			foreach ($this->pages as $base => &$page)
			{
				$page['url'] = $current_blog->path . '?' . BFOX_QUERY_VAR_SPECIAL . '=' . $base;
				$page['content_cb'] = array($this, 'get_' . $base);
				$page['setup_query_cb'] = array($this, 'setup_query_' . $base);
			}
		}

		function get_url_reading_plans($plan_id = NULL, $action = NULL, $reading_id = NULL, $url = NULL)
		{
			// HACK $url shouldn't be a parameter, i did this because it was easier since
			// the $this->pages['reading_plans']['url'] has the current blog built in
			// and thus can't be used for other blogs
			if (is_null($url))
			{
				// HACK for when there is a reading id
				if (is_null($reading_id))
					$url = $this->pages['reading_plans']['url'];
				else
					$url = $this->pages['current_readings']['url'];
			}

			if (!is_null($plan_id)) $url .= '&' . BFOX_QUERY_VAR_PLAN_ID . '=' . $plan_id;
			if (!is_null($action)) $url .= '&' . BFOX_QUERY_VAR_ACTION . '=' . $action;
			if (!is_null($reading_id)) $url .= '&' . BFOX_QUERY_VAR_READING_ID . '=' . ($reading_id + 1);
			return $url;
		}

		function do_home(&$wp_query)
		{
			$wp_query->query_vars[BFOX_QUERY_VAR_SPECIAL] = 'current_readings';
			$this->setup_query($wp_query);
			// Set whether this query is a bible reference
			if (isset($wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF]))
				$wp_query->is_bfox_bible_ref = true;
		}

		function setup_query_current_readings($wp_query)
		{
			global $bfox_plan, $blog_id;

			// We don't need to show any special content for current readings, just the bible ref content
			$wp_query->is_bfox_special = false;

			$wp_query->bfox_plans = $bfox_plan->get_plans($wp_query->query_vars[BFOX_QUERY_VAR_PLAN_ID]);
			if (0 < count($wp_query->bfox_plans))
			{
				foreach ($wp_query->bfox_plans as &$plan)
				{
					$plan->query_readings = array();
					if (isset($wp_query->query_vars[BFOX_QUERY_VAR_READING_ID]))
						$plan->query_readings[] = $wp_query->query_vars[BFOX_QUERY_VAR_READING_ID] - 1;
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
				$content .= bfox_blog_reading_plans($plans, bfox_can_user_edit_plans(), 2);
			}
			else
			{
				$content = bfox_plan_summaries($blog_id);
			}

			return $content;
		}

		function get_current_readings($args)
		{
			if (!isset($args['limit'])) $args['limit'] = 1;
			return $this->get_recent_readings($args);
		}

		function get_recent_readings($args)
		{
			global $bfox_plan, $blog_id;

			if (isset($args['limit'])) $limit = $args['limit'];
			else $limit = 4;

			$content = '';
			$blog_plans = $bfox_plan->get_plans();
			if (0 < count($blog_plans))
			{
				$content .= '<table width="100%">';
				$content .= '<tr><th>Plan</th><th>Date</th><th>Scripture</th></tr>';
				foreach ($blog_plans as $plan)
				{
					if (isset($plan->current_reading))
					{
						$url = $this->get_url_reading_plans($plan->id);
						$plan_link = '<a href="' . $url . '">' . $plan->name . '</a>';

						$oldest = $plan->current_reading - $limit + 1;
						if ($oldest < 0) $oldest = 0;
						for ($index = $plan->current_reading; $index >= $oldest; $index--)
						{
							$scripture_link = '<a href="' . $this->get_url_reading_plans($plan->id, NULL, $index) . '">' . $plan->refs[$index]->get_string() . '</a>';
							$content .= '<tr><td>' . $plan_link . '</td><td>' . date('M d', $plan->dates[$index]) . '</td><td>' . $scripture_link . '</td></tr>';
							$plan_link = '';
						}
					}
				}
				$content .= '</table>';
			}
			else
			{
				$content .= __('This blog has no Bible reading plans.');
			}

			return $content;
		}

		function get_my_reading()
		{
			global $blog_id;
			return bfox_plan_summaries($blog_id);
		}

		function get_join()
		{
			global $bfox_message, $blog_id, $user_ID;

			if (0 < $user_ID)
			{
				if (is_user_member_of_blog($user_ID, $blog_id))
				{
					$content = <<<CONTENT
						<p>You are already a member of this bible study.</p>
CONTENT;
				}
				else
				{
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
				}
			}
			else
			{
				$login = strtolower(bfox_loginout());
				$signup = site_url('wp-signup.php');
				$content = <<<CONTENT
					<p>In order to join this bible study, you must first $login with your Biblefox account.</p>
					<p>If you don't have a Biblefox account. You can <a href="$signup">sign up</a> for one for free.</p>
CONTENT;
			}
			//'} stupid comment for xcode to color correctly


			return $content;
		}

		function get_my_history()
		{
			$content = '';

			// Get the recently read scriptures
			$content .= bfox_get_recent_scriptures_output(10, true);

			// Get the recently viewed scriptures
			$content .= bfox_get_recent_scriptures_output(10, false);

			return $content;
		}

		function get_updates($args)
		{
			// This function uses an instance of WP_Query to get the latest posts
			//  (similar to the recent posts widget - see wp_widget_recent_entries())

			if (isset($args['limit'])) $limit = $args['limit'];
			else $limit = 4;

			$content = '';
			$r = new WP_Query(array('showposts' => $limit, 'what_to_show' => 'posts', 'nopaging' => 0, 'post_status' => 'publish', BFOX_QUERY_VAR_JOIN_BIBLE_REFS => TRUE));
			if ($r->have_posts())
			{
				$content .= '<table width="100%">';
				$content .= '<tr><th>Post</th><th>Author</th><th>Scriptures</th></tr>';
				global $post;
				while ($r->have_posts())
				{
					$r->the_post();
					$ref = $post->bible_refs;
					$ref_str = '';
					if ($ref->is_valid()) $ref_str = $ref->get_link($ref->get_string(BFOX_REF_FORMAT_SHORT));
					$author = '<a href="' . get_author_posts_url($post->post_author) . '">' . get_author_name($post->post_author) . '</a>';

					if (0 < $post->comment_count) $comments = ' (' . $post->comment_count . ')';
					else $comments = '';

					$content .= '<tr><td><a href="' . get_permalink($post->ID) . '">' . $post->post_title . $comments . '</a></td>';
					$content .= '<td>' . $author . '</td>';
					$content .= '<td>' . $ref_str . '</td></tr>';
				}
				$content .= '</table>';
			}
			wp_reset_query();  // Restore global post data stomped by the_post().

			return $content;
		}

		function add_to_posts(&$posts, $args = array())
		{
			$page_name = $args[BFOX_QUERY_VAR_SPECIAL];
			if (isset($this->pages[$page_name]))
			{
				$page = array();

				$func = $this->pages[$page_name]['content_cb'];
				if (is_callable($func)) $page['post_content'] = call_user_func($func, $args);
				else $page['post_content'] = '';

				$page['post_title'] = $this->pages[$page_name]['title'];
				$page['post_type'] = $this->pages[$page_name]['type'];

				// If this is a page it should be the only page in the posts array
				// Otherwise it should just go at the beginning of the posts array
				if ('page' == $page['post_type']) $posts = array((object)$page);
				else $posts = array_merge(array((object)$page), $posts);
			}
		}

		function get_content($page_name, $args = array())
		{
			$content = '';
			if (isset($this->pages[$page_name]))
			{
				$func = $this->pages[$page_name]['content_cb'];
				if (is_callable($func)) $content = call_user_func($func, $args);
			}
			return $content;
		}

		function get_link($page_name, $args = array())
		{
			$link = '';
			if (isset($this->pages[$page_name]))
			{
				$url = $this->pages[$page_name]['url'];

				$display = $page_name;
				if (isset($args[0])) $display = $args[0];

				$link = '<a href="' . $url . '">' . $display . '</a>';
			}
			return $link;
		}
	}

	global $bfox_specials;
	$bfox_specials = new BfoxSpecialPages;

	/**
	 * Shortcode function for listing all the recent comments and posts together in chronological order
	 *
	 * @param unknown_type $atts
	 * @return string shortcode text
	 */
	function bfox_get_discussions($atts)
	{
		global $wpdb, $comment, $authordata;

		extract(shortcode_atts(array(
			'limit' => 0),
			$atts));

		// Create the limit portion of the sql
		if (0 < $limit) $limit = $wpdb->prepare('LIMIT %d', $limit);
		else $limit = '';

		// The post select statement (will be unioned with the comment select)
		// Only select posts that are published
		$pselect = "SELECT
			1 as is_post,
			post_author as author,
			NULL as comment_author_url,
			NULL as comment_author_email,
			ID as id,
			NULL as comment_ID,
			post_title,
			post_date as date
			FROM $wpdb->posts
			WHERE post_status = 'publish'
			AND post_type = 'post'";

		// The comment select statement (will be unioned with the post select)
		// Only select comments that are approved and are on posts that are published
		$cselect = "SELECT
			0 as is_post,
			$wpdb->comments.comment_author as author,
			$wpdb->comments.comment_author_url as comment_author_url,
			$wpdb->comments.comment_author_email as comment_author_email,
			$wpdb->comments.comment_post_ID as id,
			$wpdb->comments.comment_ID as comment_ID,
			$wpdb->posts.post_title as post_title,
			$wpdb->comments.comment_date as date
			FROM $wpdb->comments
			LEFT JOIN $wpdb->posts ON ($wpdb->comments.comment_post_ID = $wpdb->posts.ID)
			WHERE $wpdb->comments.comment_approved = '1'
			AND $wpdb->posts.post_status = 'publish'";

		// Get the posts and comments
		// We are using a UNION so that we can get the posts and comments at the same time, thus being
		// able to one limit that applies to both at the same time
		$results = (array) $wpdb->get_results("($pselect) UNION ALL ($cselect) ORDER BY date DESC $limit");

		// Each post/comment will be a row in a table
		$content = '<div><table>';
		foreach ($results as $result)
		{
			// Format the date and time strings
			// TODO2: These times should be formated for the timezone specified for the blog
			$timestamp = strtotime($result->date);
			$date = date('D, M j, Y', $timestamp);
			$time = date('g:i a', $timestamp);

			// Only show the date once per each day
			if ($prev_date == $date) $date = '';
			else $prev_date = $date;

			if ($result->is_post)
			{
				// Format posts as "[post_author_name_link] posted [post_name_link] at [time]"

				$authordata = get_userdata($result->author);
				$post_link = '<a href="'. get_permalink($result->id) . '">' . get_the_title($result->id) . '</a>';
				$author = '<a href="' . get_author_posts_url($result->author) . '">' . get_author_name($result->author) . '</a>';
				$action = 'posted';
			}
			else
			{
				// Format comments as "[comment_author_name_link] commented on [post_name_link] at [time]"

				$result->comment_author = $result->author;
				$comment = $result;

				// If the author's url isn't valid, check if he is a user (by using his email) so that we can create a url
				if ((empty($result->comment_author_url) || ('http://' == $result->comment_author_url)) && !empty($result->comment_author_email))
				{
					$user = get_user_by_email($result->comment_author_email);
					$result->comment_author_url = get_author_posts_url($user->ID);
				}

				$post_link = '<a href="'. get_permalink($result->id) . '#comment-' . $result->comment_ID . '">' . get_the_title($result->id) . '</a>';
				$author = get_comment_author_link();
				$action = 'commented on';
			}

			// Add the new row to the content
			$content .= "<tr><td>$date</td><td>$author $action $post_link at $time</td></tr>";
		}
		$content .= '</table></div>';

		return $content;
	}
	add_shortcode('discussions', 'bfox_get_discussions');

?>
