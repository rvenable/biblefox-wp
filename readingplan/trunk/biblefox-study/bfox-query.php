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

	// Function for adding query variables for our plugin
	function bfox_queryvars($qvars)
	{
		// Add a query variable for bible references
		$qvars[] = 'bible_ref';
		return $qvars;
	}
	
	// Function to be run after parsing the query
	function bfox_parse_query($wp_query)
	{
		// Set whether this query is a bible reference
		$wp_query->is_bfox_bible_ref = false;
		if (isset($wp_query->query_vars['bible_ref']))
			$wp_query->is_bfox_bible_ref = true;
	}

	// Function for doing any preparation before doing the post query
	function bfox_pre_get_posts($wp_query)
	{
		global $bfox_bible_refs;
		$vars = $wp_query->query_vars;

		if (is_search())
			$refStrs = $vars['s'];
		else if (is_bfox_bible_ref())
			$refStrs = $vars['bible_ref'];

		$bfox_bible_refs = bfox_parse_refs($refStrs);
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
				$new_post['post_title'] = bfox_get_refstr($ref);
				$new_post['post_content'] = bfox_get_ref_content($ref);
				$new_posts[] = ((object) $new_post);
			}

			// Append the new posts onto the beginning of the post list
			$posts = array_merge($new_posts, $posts);
		}

		return $posts;
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
	}
	
?>
