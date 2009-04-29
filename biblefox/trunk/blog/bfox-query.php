<?php

class BfoxBlogQuery
{
	/*
	 Problem:
	 WP appears to use the WP_Query class as if it were a singleton, even though it is not and is even instantiated more than once.

	 Because hooks which modify a query don't pass a reference to the query, the hook functions must rely on global functions to
	 return info about the query (such as is_home() or is_page()). These functions, however, only return information about the global
	 instance of WP_Query, leading to unintended results when there are multiple instances of WP_Query (such as for the Recent Posts widget).

	 The real solution should be to pass the instance to each hook/filter function. Until that happens this hack must be in place.

	 HACK: Save information we need statically, and clear it when we've finished. This means each instance of WP_Query has to finish using
	 this information before another instance begins.
	 */

	/**
	 * Stores the post_ids statically. These are created by set_post_ids() and cleared by get_post_ids()
	 *
	 * @var array
	 */
	private static $post_ids = array();

	public static function set_post_ids(BibleRefs $refs)
	{
		self::$post_ids = BfoxPosts::get_post_ids($refs);
	}

	public static function get_post_ids()
	{
		$post_ids = self::$post_ids;
		self::$post_ids = array();

		return $post_ids;
	}
}



	/*
	 This file is for modifying the way wordpress queries work for our plugin
	 For information on how the WP query works, see:
		http://codex.wordpress.org/Custom_Queries
		http://codex.wordpress.org/Query_Overview
	 */

	// Returns whether the current query is a special page
	function is_bfox_special()
	{
		global $wp_query;
		return $wp_query->is_bfox_special;
	}

	// Function for redirecting templates
	function bfox_template_redirect()
	{
		if (is_bfox_special() && $template = get_page_template())
		{
			// Use the page template for bfox special pages
			include($template);
			exit;
		}
	}

	// Function for adding query variables for our plugin
	function bfox_queryvars($qvars)
	{
		// Add a query variable for bible references
		$qvars[] = BfoxBlog::var_bible_ref;
		$qvars[] = BfoxBlog::var_special;
		$qvars[] = BfoxBlog::var_action;
		$qvars[] = BfoxBlog::var_plan_id;
		$qvars[] = BfoxBlog::var_reading_id;
		return $qvars;
	}

	// Function to be run after parsing the query
	function bfox_parse_query($wp_query)
	{
		$wp_query->is_bfox_bible_ref = false;
		$wp_query->is_bfox_special = false;

		global $bfox_specials;
		$bfox_specials->setup_query($wp_query);

		// Set whether this query is a bible reference
		if (isset($wp_query->query_vars[BfoxBlog::var_bible_ref]))
			$wp_query->is_bfox_bible_ref = true;

		// Don't use the home page for certain queries
		if ($wp_query->is_bfox_bible_ref || $wp_query->is_bfox_special)
			$wp_query->is_home = false;
	}

	// Function for doing any preparation before doing the post query
	function bfox_pre_get_posts($wp_query)
	{
		// HACK: This special page stuff should really happen in bfox_parse_query, but WP won't call that func if is_home(), so we have to do it here
		global $bfox_specials;
		//if (($wp_query === $GLOBALS['wp_query']) && ($wp_query->is_home)) $bfox_specials->do_home($wp_query);

		$vars = $wp_query->query_vars;

		if ($wp_query->is_search)
			$ref_strs = $vars['s'];
		else if ($wp_query->is_bfox_bible_ref)
			$ref_strs = $vars[BfoxBlog::var_bible_ref];

		// Global array for storing bible references used in a search
		global $bfox_bible_refs;
		$bfox_bible_refs = RefManager::get_from_str($ref_strs);

		// TODO3: find a more appropriate place for this
		// If we are going to be displaying scripture, we make sure we load the necessary css files in wp_head()
		if ($bfox_bible_refs->is_valid()) add_action('wp_head', 'BfoxBlog::add_scripture');

		$GLOBALS['bfox_recent_wp_query'] =& $wp_query;

		BfoxBlogQuery::set_post_ids($bfox_bible_refs);
	}

	// Function for modifying the query WHERE statement
	function bfox_posts_where($where)
	{
		$post_ids = BfoxBlogQuery::get_post_ids();
		if (!empty($post_ids)) $where .= ' AND ID IN (' . implode(',', $post_ids) . ')';
		return $where;
	}

	// Function for modifying the posts array returned from the actual SQL query
	function bfox_posts_results($posts)
	{
		if (!empty($posts))
		{
			$post_ids = array();
			foreach ($posts as $post) $post_ids []= $post->ID;

			// Get all the bible references for these posts
			$refs = BfoxPosts::get_refs($post_ids);
			foreach ($posts as &$post) if (isset($refs[$post->ID]) && $refs[$post->ID]->is_valid()) $post->bible_refs = $refs[$post->ID];
		}

		return $posts;
	}

	/**
	 * Returns an array of posts with content for the given bible references
	 *
	 * @param BibleRefs $refs
	 * @param string $title
	 * @return array of posts
	 */
	function bfox_blog_get_ref_posts(BibleRefs $refs, $title = '')
	{
		$bcvs = BibleRefs::get_bcvs($refs->get_seqs());

		foreach ($bcvs as $book => $cvs)
		{
			$book_name = BibleMeta::get_book_name($book);
			$ref_str = BibleRefs::create_book_string($book, $cvs);

			// Create a new bible refs for just this book (so we can later pass it into BfoxBlog::get_verse_content())
			$book_refs = new BibleRefs();

			// Get the first and last chapters
			unset($ch1);
			foreach ($cvs as $cv)
			{
				if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
				list($ch2, $vs2) = $cv->end;

				// Add the cv onto our book bible refs
				$book_refs->add_bcv($book, $cv);
			}

			// Create the navigation bar with the prev/write/next links
			$nav_bar = "<div class='bible_post_nav'>";
			if ($ch1 > BibleMeta::start_chapter)
			{
				$prev_ref_str = $book_name . ' ' . ($ch1 - 1);
				$nav_bar .= BfoxBlog::ref_link($prev_ref_str, "&lt; $prev_ref_str", "class='bible_post_prev'");
			}
			$nav_bar .= BfoxBlog::ref_write_link($ref_str, 'Write about this passage');
			if ($ch2 < BibleMeta::end_verse_max($book))
			{
				$next_ref_str = $book_name . ' ' . ($ch2 + 1);
				$nav_bar .= BfoxBlog::ref_link($next_ref_str, "$next_ref_str &gt;", "class='bible_post_next'");
			}
			$nav_bar .= "<br/><a href='" . BfoxQuery::passage_page_url($ref_str) . "'>View in Biblefox Bible Viewer</a></div>";

			$new_post = array();
			$new_post['ID'] = -1;
			$new_post['post_title'] = $title . $ref_str;
			$new_post['post_content'] = $nav_bar . BfoxBlog::get_verse_content($book_refs) . $nav_bar;
			$new_post['bible_ref_str'] = $ref_str;
			$new_post['post_type'] = BfoxBlog::var_bible_ref;
			$new_post['post_date'] = current_time('mysql', false);
			$new_post['post_date_gmt'] = current_time('mysql', true);
			$new_post['bfox_permalink'] = BfoxBlog::ref_url($ref_str);

			// Turn off comments
			$new_post['comment_status'] = 'closed';
			$new_post['ping_status'] = 'closed';

			$new_posts[] = ((object) $new_post);
		}

		return $new_posts;
	}

	// Function for adjusting the posts after they have been queried
	function bfox_the_posts($posts)
	{
		global $bfox_bible_refs, $bfox_recent_wp_query, $wp_query, $bfox_specials;

		// If we are using the global instance of WP_Query
		if ($bfox_recent_wp_query === $wp_query)
		{
			if (isset($wp_query->bfox_plans))
			{
				$new_posts = array();

				foreach ($wp_query->bfox_plans as $plan)
				{
					foreach ($plan->query_readings as $reading_id)
					{
						$refs = $plan->refs[$reading_id];
						$ref_str = $refs->get_string();

						// Create the navigation bar with the prev/write/next links
						$nav_bar = "<div class='bible_post_nav'>";
						if (isset($plan->refs[$reading_id - 1]))
						{
							$prev_ref_str = $book_name . ' ' . ($ch1 - 1);
							$nav_bar .= '<a href="' . $bfox_specials->get_url_reading_plans($plan->id, NULL, $reading_id - 1) . '" class="bible_post_prev">&lt; ' . $plan->refs[$reading_id - 1]->get_string() . '</a>';
						}
						$nav_bar .= BfoxBlog::ref_write_link($refs->get_string(), 'Write about this passage');
						if (isset($plan->refs[$reading_id + 1]))
						{
							$next_ref_str = $book_name . ' ' . ($ch2 + 1);
							$nav_bar .= '<a href="' . $bfox_specials->get_url_reading_plans($plan->id, NULL, $reading_id + 1) . '" class="bible_post_next">' . $plan->refs[$reading_id + 1]->get_string() . ' &gt;</a>';
						}
						$nav_bar .= "<br/><a href='" . BfoxQuery::passage_page_url($ref_str) . "'>View in Biblefox Bible Viewer</a></div>";

						$new_post = array();
						$new_post['ID'] = -1;
						$new_post['post_title'] = $ref_str;
						$new_post['post_content'] = $nav_bar . BfoxBlog::get_verse_content($refs) . $nav_bar;
						$new_post['bible_ref_str'] = $ref_str;
						$new_post['post_type'] = BfoxBlog::var_bible_ref;
						$new_post['bfox_permalink'] = $bfox_specials->get_url_reading_plans($plan->id, NULL, $reading_id);
						$new_post['bfox_author'] = '<a href="' . $bfox_specials->get_url_reading_plans($plan->id) . '">' . $plan->name . ' (Reading ' . ($reading_id + 1) . ')</a>';

						// Set the date according to the reading plan if possible, otherwise set it to the current date
						if (isset($plan->dates[$reading_id]))
						{
							$new_post['post_date'] = $new_post['post_date_gmt'] = date('Y-m-d H:i:s', $plan->dates[$reading_id]);
						}
						else
						{
							$new_post['post_date'] = current_time('mysql', false);
							$new_post['post_date_gmt'] = current_time('mysql', true);
						}

						// Turn off comments
						$new_post['comment_status'] = 'closed';
						$new_post['ping_status'] = 'closed';

						$new_posts[] = ((object) $new_post);
					}
				}

				// Update the read history to show that we viewed these scriptures
				global $bfox_history;
				$bfox_history->update($bfox_bible_refs);

				// Append the new posts onto the beginning of the post list
				$posts = array_merge($new_posts, $posts);

/*				$plan_id = $wp_query->query_vars[BfoxBlog::var_plan_id];
				$reading_id = $wp_query->query_vars[BfoxBlog::var_reading_id];

				if (isset($plan_id) && isset($reading_id))
				{
					list($plan) = $bfox_plan->get_plans($plan_id);
					if (isset($plan[$reading_id])) $reading = $plan[$reading_id];
				}

				// If there are bible references, then we should display them as posts
				// So we create an array of posts with scripture and add that to the current array of posts*/

			}
			else if ($bfox_bible_refs->is_valid())
			{
				$plan_id = $wp_query->query_vars[BfoxBlog::var_plan_id];
				$reading_id = $wp_query->query_vars[BfoxBlog::var_reading_id];

				if (isset($plan_id) && isset($reading_id))
				{
					list($plan) = $bfox_plan->get_plans($plan_id);
					if (isset($plan[$reading_id])) $reading = $plan[$reading_id];
					$title = $plan->name . ' - Reading ' . $reading_id . ': ';
				}

				// If there are bible references, then we should display them as posts
				// So we create an array of posts with scripture and add that to the current array of posts
				$new_posts = bfox_blog_get_ref_posts($bfox_bible_refs, $title);

				// Update the read history to show that we viewed these scriptures
				global $bfox_history;
				$bfox_history->update($bfox_bible_refs);

				// Append the new posts onto the beginning of the post list
				$posts = array_merge($new_posts, $posts);
			}

			// If this is a special page, then we need to add the content ourselves
			if (is_bfox_special())
			{
				$bfox_specials->add_to_posts($posts, $wp_query->query_vars);
			}

			/*
			if (is_home())
			{
				// Add the blog progress page to the front of the posts
				$content = bfox_get_reading_plan_status();
				if ('' != $content)
				{
					$new_post = array();
					$new_post['post_title'] = 'Reading Plan Status';
					$new_post['post_content'] = $content;
					$new_post['post_type'] = BfoxBlog::var_special;
					$new_post['post_date'] = current_time('mysql', false);
					$new_post['post_date_gmt'] = current_time('mysql', true);

					// Append the new posts onto the beginning of the post list
					$posts = array_merge(array((object) $new_post), $posts);
				}
			}
			 */
		}

		return $posts;
	}

	// Function for filtering the output of the_permalink()
	function bfox_the_permalink($permalink, $post)
	{
		if (isset($post->bfox_permalink))
			$permalink = $post->bfox_permalink;
		return $permalink;
	}

	function bfox_the_content($content)
	{
		global $post;

		// If this post have bible references, mention them at the beginning of the post
		if (isset($post->bible_refs)) $content = '<p>Scriptures Referenced: ' . BfoxBlog::ref_link($post->bible_refs->get_string()) . '</p>' . $content;

		return $content;
	}

	// Function for adding content based on special syntax
	function bfox_special_syntax($data)
	{
		global $bfox_specials;

//		bfox_create_synonym_data();
//		$data = bfox_process_html_text($data, 'bfox_ref_replace');

		$special_chars = array('footnote' => array('open' => '((', 'close' => '))'),
							   'footnote_xml' => array('open' => '<footnote>', 'close' => '</footnote>'),
							   'content' => array('open' => '{{', 'close' => '}}'),
							   'link' => array('open' => '[[', 'close' => ']]'));

		foreach ($special_chars as $type => $type_info)
		{
			$offset = 0;
			$index = 0;
			$open = $type_info['open'];
			$close = $type_info['close'];

			// XML footnotes function exactly like regular footnotes
			if ('footnote_xml' == $type) $type = 'footnote';

			// Loop through each special char
			while (1 == preg_match("/" . preg_quote($open, '/') . "(.*?)" . preg_quote($close, '/') . "/", $data, $matches, PREG_OFFSET_CAPTURE, $offset))
			{
				// Store the match data in more readable variables
				$offset = (int) $matches[0][1];
				$pattern = (string) $matches[0][0];
				$note_text = (string) $matches[1][0];
				$index++;

				if ('footnote' == $type)
				{
					// Update the footnotes section string
					$footnotes .= "<li><a name=\"footnote_$index\" href=\"#footnote_ref_$index\">[$index]</a> $note_text</li>";

					// Replace the footnote with a link
					$replacement = "<a name=\"footnote_ref_$index\" href=\"#footnote_$index\" title=\"" . bfox_html_strip_tags($note_text) . "\">[$index]</a>";
				}
				else
				{
					list($page_name, $param_str) = explode('|', $note_text, 2);

					$params = array();
					$index = 0;
					$param_str_list = explode('|', $param_str, 2);
					foreach ($param_str_list as $param_str)
					{
						if (FALSE === ($pos = strpos($param_str, '=')))
							$params[$index++] = $param_str;
						else
							$params[substr($param_str, 0, $pos)] = substr($param_str, $pos - strlen($param_str) + 1);
					}

					if ('content' == $type)
					{
						// Replace the note with special content
						$replacement = $bfox_specials->get_content($page_name, $params);
					}
					else if ('link' == $type)
					{
						// Replace the note with a link to a special page
						$replacement = $bfox_specials->get_link($page_name, $params);
					}
				}


				// Modify the data with the replacement text
				$data = substr_replace($data, $replacement, $offset, strlen($pattern));

				// Skip the rest of the replacement string
				$offset += strlen($replacement);
			}
		}

		// Add the footnotes section to the end of the data
		if (isset($footnotes)) $data .= "<h3>Footnotes</h3><ul>" . $footnotes . "</ul>";

		return $data;
	}

	function bfox_the_author($author)
	{
		global $post, $current_site;
		if (isset($post->bfox_author))
			$author = $post->bfox_author;
		else if ((BfoxBlog::var_bible_ref == $post->post_type) || (BfoxBlog::var_special == $post->post_type)) $author = "<a href=\"http://{$current_site->domain}{$current_site->path}\">Biblefox.com</a>";
		return $author;
	}

	// Function for updating the edit post link
	function bfox_get_edit_post_link($link)
	{
		$post = &get_post($id);

		// If this post is actually scripture then we should change the
		// edit post link to be a link to write a new post about this scripture
		if (isset($post->bible_ref_str))
		{
			// Remove anything after the last '/'
			$link = substr($link, 0, strrpos($link, '/') + 1);
			$link .= "post-new.php?bible_ref=$post->bible_ref_str";
		}
		return $link;
	}

	function bfox_query_init()
	{
		add_filter('query_vars', 'bfox_queryvars' );
		add_action('parse_query', 'bfox_parse_query');
		add_action('pre_get_posts', 'bfox_pre_get_posts');
		add_filter('posts_where', 'bfox_posts_where');
		add_filter('posts_results', 'bfox_posts_results');
		add_filter('the_posts', 'bfox_the_posts');
		add_filter('post_link', 'bfox_the_permalink', 10, 2);
		add_filter('the_content', 'bfox_the_content');
		add_filter('the_content', 'bfox_special_syntax');
		add_filter('the_author', 'bfox_the_author');
		add_filter('get_edit_post_link', 'bfox_get_edit_post_link');
		add_action('template_redirect', 'bfox_template_redirect');
	}

?>