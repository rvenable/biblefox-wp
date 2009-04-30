<?php

		function bfox_invite_page()
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
				$login = strtolower(BiblefoxSite::loginout());
				$signup = site_url('wp-signup.php');
				$content = <<<CONTENT
					<p>In order to join this bible study, you must first $login with your Biblefox account.</p>
					<p>If you don't have a Biblefox account. You can <a href="$signup">sign up</a> for one for free.</p>
CONTENT;
			}
			//'} stupid comment for xcode to color correctly


			return $content;
		}

function bfox_shortcode_blog_updates($args)
{
	// This function uses an instance of WP_Query to get the latest posts
	//  (similar to the recent posts widget - see wp_widget_recent_entries())

	if (isset($args['limit'])) $limit = $args['limit'];
	else $limit = 4;

	$content = '';
	$r = new WP_Query(array('showposts' => $limit, 'what_to_show' => 'posts', 'nopaging' => 0, 'post_status' => 'publish', BfoxBlog::var_join_bible_refs => TRUE));
	if ($r->have_posts())
	{
		$content .= '<table width="100%">';
		$content .= '<tr><th>Post</th><th>Author</th><th>Scriptures</th></tr>';
		global $post;
		while ($r->have_posts())
		{
			$r->the_post();
			$ref_link = '';
			if (isset($post->bible_refs) && $post->bible_refs->is_valid()) $ref_link = BfoxBlog::ref_link($post->bible_refs->get_string(BibleMeta::name_short));
			$author = '<a href="' . get_author_posts_url($post->post_author) . '">' . get_author_name($post->post_author) . '</a>';

			if (0 < $post->comment_count) $comments = ' (' . $post->comment_count . ')';
			else $comments = '';

			$content .= '<tr><td><a href="' . get_permalink($post->ID) . '">' . $post->post_title . $comments . '</a></td>';
			$content .= '<td>' . $author . '</td>';
			$content .= '<td>' . $ref_link . '</td></tr>';
		}
		$content .= '</table>';
	}
	wp_reset_query();  // Restore global post data stomped by the_post().

	return $content;
}
//add_shortcode('blog_updates', 'bfox_shortcode_blog_updates');

	/**
	 * Shortcode function for listing all the recent comments and posts together in chronological order
	 *
	 * @param array $atts
	 * @return string shortcode text
	 */
	function bfox_get_discussions($atts)
	{
		global $wpdb, $comment, $authordata;

		extract(shortcode_atts(array(
			'limit' => 0, 'min_date' => '', 'max_date' => ''),
			$atts));

		// Create the limit portion of the sql
		if (0 < $limit) $limit = $wpdb->prepare('LIMIT %d', $limit);
		else $limit = '';

		// Create the WHERE for the Post Select
		$where = '';
		if (!empty($min_date)) $where .= $wpdb->prepare('AND (DATE(%s) <= DATE(post_date))', $min_date);
		if (!empty($max_date)) $where .= $wpdb->prepare('AND (DATE(%s) >= DATE(post_date))', $max_date);

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
			AND post_type = 'post'
			$where";

		// Create the WHERE for the Comment Select
		$where = '';
		if (!empty($min_date)) $where .= $wpdb->prepare("AND (DATE(%s) <= DATE($wpdb->comments.comment_date))", $min_date);
		if (!empty($max_date)) $where .= $wpdb->prepare("AND (DATE(%s) >= DATE($wpdb->comments.comment_date))", $max_date);

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
			AND $wpdb->posts.post_status = 'publish'
			$where";

		// Get the posts and comments
		// We are using a UNION so that we can get the posts and comments at the same time, thus being
		// able to one limit that applies to both at the same time
		$results = (array) $wpdb->get_results("($pselect) UNION ALL ($cselect) ORDER BY date DESC $limit");

		$content = '';
		if (!empty($results))
		{
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
		}

		return $content;
	}
	//add_shortcode('discussions', 'bfox_get_discussions');

?>