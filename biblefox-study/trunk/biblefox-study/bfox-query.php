<?php

	/*
	 This file is for modifying the way wordpress queries work for our plugin
	 For information on how the WP query works, see:
		http://codex.wordpress.org/Custom_Queries
		http://codex.wordpress.org/Query_Overview
	 */

	// Returns whether the current query is a bible reference query
	function is_bfox_bible_ref()
	{
		global $wp_query;
		return $wp_query->is_bfox_bible_ref;
	}

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
		$qvars[] = BFOX_QUERY_VAR_BIBLE_REF;
		$qvars[] = BFOX_QUERY_VAR_SPECIAL;
		$qvars[] = BFOX_QUERY_VAR_ACTION;
		$qvars[] = BFOX_QUERY_VAR_PLAN_ID;
		$qvars[] = BFOX_QUERY_VAR_READING_ID;
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
		if (isset($wp_query->query_vars[BFOX_QUERY_VAR_BIBLE_REF]))
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
		if (($wp_query === $GLOBALS['wp_query']) && ($wp_query->is_home)) $bfox_specials->do_home($wp_query);

		$vars = $wp_query->query_vars;

		if ($wp_query->is_search)
			$refStrs = $vars['s'];
		else if ($wp_query->is_bfox_bible_ref)
			$refStrs = $vars[BFOX_QUERY_VAR_BIBLE_REF];

		// Global array for storing bible references used in a search
		global $bfox_bible_refs;
		$bfox_bible_refs = RefManager::get_from_str($refStrs);

		/*
		 Problem:
		 WP appears to use the WP_Query class as if it were a singleton, even though it is not and is even instantiated more than once.

		 Because hooks which modify a query don't pass a reference to the query, the hook functions must rely on global functions to
		 return info about the query (such as is_home() or is_page()). These functions, however, only return information about the global
		 instance of WP_Query, leading to unintended results when there are multiple instances of WP_Query (such as for the Recent Posts widget).

		 The real solution should be to pass the instance to each hook/filter function. Until that happens this hack must be in place.

		 HACK:
		 Keep a global bfox var to remember the most recent instance of WP_Query.
		 This can be compared against global $wp_query to see if the current instance is the main query (ie. ($bfox_recent_wp_query === $wp_query))

		 Also note that we are using the $GLOBALS array here to save the reference to the query
		  (see the warning on http://nz.php.net/manual/en/language.references.whatdo.php )
		 */
		$GLOBALS['bfox_recent_wp_query'] =& $wp_query;

		// If we have refs, check for any needed ref modifications
		if ($bfox_bible_refs->is_valid())
		{
			// Save the refs in a global variable
			$bfox_bible_refs = bfox_get_next_refs($bfox_bible_refs, $vars[BFOX_QUERY_VAR_ACTION]);
		}
	}

	// Function for modifying the query JOIN statement
	function bfox_posts_join($join)
	{
		global $bfox_bible_refs, $wpdb, $bfox_recent_wp_query;
		$table_name = $wpdb->bfox_bible_ref;

		if ($bfox_bible_refs->is_valid() || $bfox_recent_wp_query->query_vars[BFOX_QUERY_VAR_JOIN_BIBLE_REFS])
			$join .= " LEFT JOIN $table_name ON " . $wpdb->posts . ".ID = {$table_name}.post_id ";

		return $join;
	}

	function bfox_posts_fields($fields)
	{
		global $bfox_recent_wp_query, $wpdb;

		// When we join on bible refs, we want to merge all the bible refs for a single post into that one post
		// To do this, we use the GROUP_CONCAT() SQL function, which concatenates all the values (separated by commas by default)
		if ($bfox_recent_wp_query->query_vars[BFOX_QUERY_VAR_JOIN_BIBLE_REFS])
			$fields .= ', GROUP_CONCAT(' . $wpdb->bfox_bible_ref . '.verse_begin) AS verse_begin, GROUP_CONCAT(' . $wpdb->bfox_bible_ref . '.verse_end) AS verse_end';

		return $fields;
	}

	// Function for modifying the query WHERE statement
	function bfox_posts_where($where)
	{
		global $bfox_bible_refs;

		if ($bfox_bible_refs->is_valid())
		{
			// NOTE: Searches can currently return unpublished results too!!! (because of this OR)
			if (is_search())
				$where .= ' OR ';
			else
				$where .= ' AND ';

			$where .= bfox_get_posts_equation_for_refs($bfox_bible_refs);
		}

		return $where;
	}

	// Function for modifying the query GROUP BY statement
	function bfox_posts_groupby($groupby)
	{
		global $bfox_bible_refs, $wpdb, $bfox_recent_wp_query;

		if ($bfox_bible_refs->is_valid() || $bfox_recent_wp_query->query_vars[BFOX_QUERY_VAR_JOIN_BIBLE_REFS])
		{
			// Group on post ID
			$mygroupby = "{$wpdb->posts}.ID";

			// If the grouping we need isn't already there
			if (!preg_match("/$mygroupby/", $groupby))
			{
				if (strlen(trim($groupby)))
					$groupby .= ', ';

				$groupby .= $mygroupby;
			}
		}

		return $groupby;
	}

	// Function for modifying the posts array returned from the actual SQL query
	function bfox_posts_results($posts)
	{
		global $bfox_recent_wp_query;

		// When we joined on bible refs, we had to  merge all the bible refs for a single post into that one post
		// To do this, we used the GROUP_CONCAT() SQL function, which concatenates all the values (separated by commas by default)
		// Now we need to separate the values and create a BibleRef object to store the references
		if ($bfox_recent_wp_query->query_vars[BFOX_QUERY_VAR_JOIN_BIBLE_REFS] && (0 < count($posts)))
		{
			foreach ($posts as &$post)
			{
				$post->bible_refs = RefManager::get_from_concat_values($post->verse_begin, $post->verse_end);
			}
		}
		return $posts;
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
						$ref = $plan->refs[$reading_id];
						$new_post = array();
						$url_prefix = BFOX_QUERY_VAR_PLAN_ID . '=' . $plan->id . '&' . BFOX_QUERY_VAR_READING_ID . '=';
						$scripture_links = array();
						$scripture_links['current_url'] = $bfox_specials->get_url_reading_plans($plan->id, NULL, $reading_id);
						if (isset($plan->refs[$reading_id - 1]))
							$scripture_links['previous'] = '<a href="' . $bfox_specials->get_url_reading_plans($plan->id, NULL, $reading_id - 1) . '">< ' . $plan->refs[$reading_id - 1]->get_string() . '</a>';
						if (isset($plan->refs[$reading_id + 1]))
							$scripture_links['next'] = '<a href="' . $bfox_specials->get_url_reading_plans($plan->id, NULL, $reading_id + 1) . '">' . $plan->refs[$reading_id + 1]->get_string() . ' ></a>';

						$refStr = $ref->get_string();
						$new_post['ID'] = -1;
						$new_post['post_title'] = $refStr;
						$new_post['post_content'] = bfox_get_ref_menu($ref, true, $scripture_links) . Translations::get_verse_content($ref) . bfox_get_ref_menu($ref, false, $scripture_links);
						$new_post['bible_ref_str'] = $refStr;
						$new_post['post_type'] = BFOX_QUERY_VAR_BIBLE_REF;
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

/*				$plan_id = $wp_query->query_vars[BFOX_QUERY_VAR_PLAN_ID];
				$reading_id = $wp_query->query_vars[BFOX_QUERY_VAR_READING_ID];

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
				$plan_id = $wp_query->query_vars[BFOX_QUERY_VAR_PLAN_ID];
				$reading_id = $wp_query->query_vars[BFOX_QUERY_VAR_READING_ID];

				if (isset($plan_id) && isset($reading_id))
				{
					list($plan) = $bfox_plan->get_plans($plan_id);
					if (isset($plan[$reading_id])) $reading = $plan[$reading_id];
					$title = $plan->name . ' - Reading ' . $reading_id . ': ';
				}

				// If there are bible references, then we should display them as posts
				// So we create an array of posts with scripture and add that to the current array of posts
				$new_posts = array();
				foreach ($bfox_bible_refs->get_refs_array() as $ref)
				{
					$new_post = array();
					$refStr = $ref->get_string();
					$new_post['ID'] = -1;
					$new_post['post_title'] = $title . $refStr;
					$new_post['post_content'] = bfox_get_ref_menu($ref, true) . Translations::get_verse_content($ref) . bfox_get_ref_menu($ref, false);
					$new_post['bible_ref_str'] = $refStr;
					$new_post['post_type'] = BFOX_QUERY_VAR_BIBLE_REF;
					$new_post['post_date'] = current_time('mysql', false);
					$new_post['post_date_gmt'] = current_time('mysql', true);
					$new_post['bfox_permalink'] = BfoxLinks::get_ref_link($ref, NULL, 'blog');

					// Turn off comments
					$new_post['comment_status'] = 'closed';
					$new_post['ping_status'] = 'closed';

					$new_posts[] = ((object) $new_post);
				}

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
					$new_post['post_type'] = BFOX_QUERY_VAR_SPECIAL;
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
		$refs = bfox_get_post_bible_refs($post->ID);
		if ($refs->is_valid()) $content = '<p>Scriptures Referenced: ' . BfoxLinks::get_ref_link($refs) . '</p>' . $content;

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
		else if ((BFOX_QUERY_VAR_BIBLE_REF == $post->post_type) || (BFOX_QUERY_VAR_SPECIAL == $post->post_type)) $author = "<a href=\"http://{$current_site->domain}{$current_site->path}\">Biblefox.com</a>";
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
		add_filter('posts_join', 'bfox_posts_join');
		add_filter('posts_fields', 'bfox_posts_fields');
		add_filter('posts_where', 'bfox_posts_where');
		add_filter('posts_groupby', 'bfox_posts_groupby');
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
