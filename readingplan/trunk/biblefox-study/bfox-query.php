<?php

	/*
	 This file is for modifying the way wordpress queries work for our plugin
	 For information on how the WP query works, see:
		http://codex.wordpress.org/Custom_Queries
		http://codex.wordpress.org/Query_Overview
	 */

	// Global array for storing bible references used in a search
	$bfox_bible_refs = array();

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
	
	// Function for adding query variables for our plugin
	function bfox_queryvars($qvars)
	{
		// Add a query variable for bible references
		$qvars[] = 'bible_ref';
		$qvars[] = 'bfox_special';
		$qvars[] = 'bfox_action';
		return $qvars;
	}

	// Function to be run after parsing the query
	function bfox_parse_query($wp_query)
	{
		$wp_query->is_bfox_bible_ref = false;
		$wp_query->is_bfox_special = false;

		// Set whether this query is a bible reference
		if (isset($wp_query->query_vars['bible_ref']))
			$wp_query->is_bfox_bible_ref = true;
		else if (isset($wp_query->query_vars['bfox_special']))
			$wp_query->is_bfox_special = true;
	}

	// Function for doing any preparation before doing the post query
	function bfox_pre_get_posts($wp_query)
	{
		$vars = $wp_query->query_vars;

		if (is_search())
			$refStrs = $vars['s'];
		else if (is_bfox_bible_ref())
			$refStrs = $vars['bible_ref'];

		$refs = bfox_parse_refs($refStrs);
		
		// If we have refs, check for any needed ref modifications
		if (0 < count($refs))
		{
			// Save the refs in a global variable
			global $bfox_bible_refs;
			$bfox_bible_refs = bfox_get_next_refs($refs, $vars['bfox_action']);
		}
	}

	// Function for modifying the query JOIN statement
	function bfox_posts_join($join)
	{
		global $bfox_bible_refs, $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;

		if (0 < count($bfox_bible_refs))
			$join .= " LEFT JOIN $table_name ON " . $wpdb->posts . ".ID = {$table_name}.post_id ";

		return $join;
	}
	
	// Function for modifying the query WHERE statement
	function bfox_posts_where($where)
	{
		global $bfox_bible_refs;
		
		if (0 < count($bfox_bible_refs))
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
		global $bfox_bible_refs, $wpdb;
		
		if (0 < count($bfox_bible_refs))
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

	// Function for adjusting the posts after they have been queried
	function bfox_the_posts($posts)
	{
		global $bfox_bible_refs;
		if (0 < count($bfox_bible_refs))
		{
			// If there are bible references, then we should display them as posts
			// So we create an array of posts with scripture and add that to the current array of posts
			$new_posts = array();
			foreach ($bfox_bible_refs as $ref)
			{
				$new_post = array();
				$refStr = bfox_get_refstr($ref);
				$new_post['post_title'] = $refStr;
				$new_post['post_content'] = bfox_get_ref_menu_header($refStr) . bfox_get_ref_content($ref) . bfox_get_ref_menu_footer($refStr);
				$new_post['bible_ref_str'] = $refStr;
				$new_post['post_type'] = 'bible_ref';
				$new_posts[] = ((object) $new_post);
			}

			// Update the read history to show that we viewed these scriptures
			bfox_update_table_read_history($bfox_bible_refs);

			// Append the new posts onto the beginning of the post list
			$posts = array_merge($new_posts, $posts);
		}
		else if (is_bfox_special())
		{
			global $wp_query;
			// If this is a special page, then we need to add the content ourselves
			$posts = array();
			$page = $wp_query->query_vars['bfox_special'];
			if ('plan' == $page)
			{
				require_once("bfox-plan.php");
				$posts[] = ((object) bfox_get_special_page_plan());
			}
		}

		return $posts;
	}

	// Function for filtering the output of the_permalink()
	function bfox_the_permalink($permalink)
	{
		// the_permalink() doesn't work for our custom made bible_ref pages,
		// so we need to manually set up the permalink
		if ('' == $permalink)
		{
			// If the permalink is blank, we should try to make a permalink
			$post = &get_post($id);
			if (isset($post->bible_ref_str))
				$permalink = bfox_get_bible_permalink($post->bible_ref_str);
		}

		return $permalink;
	}

	function bfox_the_content($content)
	{
		global $post;
		$refs = bfox_get_post_bible_refs($post->ID);
		if (0 < count($refs))
		{
			$refStrs = '';
			foreach ($refs as $ref)
			{
				$refStr = bfox_get_refstr($ref);
				if ('' != $refStrs) $refStrs .= ', ';
				$refStrs .= bfox_get_bible_link($refStr);
			}
			$content = '<p>Scriptures Referenced: ' . $refStrs . '</p>' . $content;
		}
		return $content;
	}

	function bfox_the_author($author)
	{
		global $post;
		if ('bible_ref' == $post->post_type) $author = 'Biblefox.com';
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
		add_filter('posts_where', 'bfox_posts_where');
		add_filter('posts_groupby', 'bfox_posts_groupby');
		add_filter('the_posts', 'bfox_the_posts');
		add_filter('the_permalink', 'bfox_the_permalink');
		add_filter('the_content', 'bfox_the_content');
		add_filter('the_author', 'bfox_the_author');
		add_filter('get_edit_post_link', 'bfox_get_edit_post_link');
	}
	
?>
