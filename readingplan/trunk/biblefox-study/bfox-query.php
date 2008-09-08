<?php

	// Global array for storing bible references used in a search
	$bfox_search_refs = array();

	function bfox_search_pre_get_posts($wp_query)
	{
		// See http://codex.wordpress.org/Custom_Queries
		// See http://codex.wordpress.org/Query_Overview

		global $bfox_search_refs;
		$vars = $wp_query->query_vars;
		if (is_search())
			$bfox_search_refs = bfox_parse_refs($vars['s']);
	}

	function bfox_search_refs_join($join)
	{
		global $bfox_search_refs, $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;

		if (0 < count($bfox_search_refs))
			$join .= " LEFT JOIN $table_name ON " . $wpdb->posts . ".ID = {$table_name}.post_id ";

		return $join;
	}
	
	function bfox_search_refs_where($where)
	{
		global $bfox_search_refs;
		
		if (0 < count($bfox_search_refs))
			$where .= ' OR ' . bfox_get_posts_equation_for_refs($bfox_search_refs);

		return $where;
	}

	function bfox_search_refs_groupby($groupby)
	{
		global $bfox_search_refs, $wpdb;
		
		if (0 < count($bfox_search_refs))
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

	function bfox_query_init()
	{
		add_action('pre_get_posts', 'bfox_search_pre_get_posts');
		add_filter('posts_join', 'bfox_search_refs_join');
		add_filter('posts_where', 'bfox_search_refs_where');
		add_filter('posts_groupby', 'bfox_search_refs_groupby');
	}
	
?>
