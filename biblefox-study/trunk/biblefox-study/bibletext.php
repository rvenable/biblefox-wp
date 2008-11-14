<?php

	function bfox_get_ref_content(BibleRefs $refs, $version_id = -1, $id_text_begin = '<em class="bible-verse-id">', $id_text_end = '</em> ')
	{
		global $wpdb;
		
		$ref_where = $refs->sql_where();
		$table_name = bfox_get_verses_table_name($version_id);
		$verses = $wpdb->get_results("SELECT verse_id, verse FROM " . $table_name . " WHERE $ref_where");
		
		$content = '';
		foreach ($verses as $verse)
		{
			if ($verse->verse_id != 0)
				$content .= "$id_text_begin$verse->verse_id$id_text_end";
			$content .= $verse->verse;
		}

		$content = bfox_special_syntax($content);
		return $content;
	}
	
	// Function for echoing scripture
	function bfox_echo_scripture($version_id, BibleRefs $ref)
	{
		$content = bfox_get_ref_content($ref, $version_id);
		echo $content;
	}
	
	function bfox_get_posts_equation_for_refs(BibleRefs $refs, $table_name = BFOX_TABLE_BIBLE_REF, $verse_begin = 'verse_begin', $verse_end = 'verse_end')
	{
		$begin = $table_name . '.' . $verse_begin;
		$end = $table_name . '.' . $verse_end;
		return $refs->sql_where2($begin, $end);
	}
	
	function bfox_get_posts_for_refs(BibleRefs $refs)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;
		
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			return array();
		
		$equation = bfox_get_posts_equation_for_refs($refs);
		if ('' != $equation)
			return $wpdb->get_col("SELECT post_id
								  FROM $table_name
								  INNER JOIN $wpdb->posts
								  ON $table_name.post_id = $wpdb->posts.ID
								  WHERE $wpdb->posts.post_type = 'post'
								  AND $equation");
		
		return array();
	}
	
	function bfox_get_post_bible_refs($post_id = 0)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;

		// If the table does not exist then there are obviously no bible references
		if ((0 != $post_id) && ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name))
		{
			$select = $wpdb->prepare("SELECT verse_begin, verse_end FROM $table_name WHERE post_id = %d ORDER BY ref_order ASC", $post_id);
			$sets = $wpdb->get_results($select, ARRAY_N);
		}

		return (new BibleRefs($sets));
	}
	
	function bfox_get_bible_permalink($refStr)
	{
		return get_option('home') . '/?bible_ref=' . $refStr;
	}

	function bfox_get_bible_link($refStr)
	{
		$permalink = bfox_get_bible_permalink($refStr);
		return "<a href=\"$permalink\" title=\"$refStr\">$refStr</a>";
	}

	function bfox_get_ref_menu(BibleRefs $refs, $header = true, $scripture_links = NULL)
	{
		$home_dir = get_option('home');
		$admin_dir = $home_dir . '/wp-admin';
		$refStr = $refs->get_string();

		// Use the current_url param if it is passed
		if (isset($scripture_links['current_url'])) $page_url = $scripture_links['current_url'];
		else $page_url = $refs->get_url();

		$menu = '';

		// Add bible tracking data
		global $user_ID;
		get_currentuserinfo();
		if (0 < $user_ID)
		{
			global $bfox_history;
			$menu .= '<small>';
			if ($header) $menu .= $bfox_history->get_dates_str($refs, false) . '<br/>';
			$menu .= $bfox_history->get_dates_str($refs, true);
			$menu .= ' (<a href="' . $page_url . '&bfox_action=mark_read">Mark as read</a>)';
			if ($header) $menu .= '<br/><a href="http://www.biblegateway.com/passage/?search=' . $refStr . '&version=31" target="_blank">Read on BibleGateway</a>';
			$menu .= '</small>';
			
			$write_link = '<a href="' . $admin_dir . '/post-new.php?bible_ref=' . $refStr . '">Write about this passage</a>';
		}
		else $menu .= '<small><a href="' . $home_dir . '/wp-login.php">Login</a> to track your bible reading</small>';

		// Scripture navigation links
		if (is_null($scripture_links))
		{
			$next_refs = new BibleRefs($refs->get_sets());
			$previous_refs = new BibleRefs($refs->get_sets());
			$next_refs->increment(1);
			$previous_refs->increment(-1);
			
			$scripture_links = array();
			$scripture_links['next'] = '<a href="' . $next_refs->get_url() . '">' . $next_refs->get_string() . ' >';
			$scripture_links['previous'] = '<a href="' . $previous_refs->get_url() . '">< ' . $previous_refs->get_string() . '</a>';
		}
		
		$menu .= '<table width="100%"><tr>';
		$menu .= '<td align="left" width="33%">' . $scripture_links['previous'] . '</td>';
		$menu .= '<td align="center" width="33%">' . $write_link . '</td>';
		$menu .= '<td align="right" width="33%">' . $scripture_links['next'] . '</a></td>';
		$menu .= '</tr>';
		$menu .= '</table>';
		
		return $menu;
	}

	function bfox_get_next_refs(BibleRefs $refs, $action)
	{
		// Determine if we need to modify the refs using a next/previous action
		$next_factor = 0;
		if ('next' == $action) $next_factor = 1;
		else if ('previous' == $action) $next_factor = -1;
		else if ('mark_read' == $action)
		{
			$next_factor = 0;
			global $bfox_history;
			$bfox_history->update($refs, true);
		}

		// Modify the refs for the next factor
		if (0 != $next_factor) $refs->increment($next_factor);

		return $refs;
	}

	/*
	 AJAX function for sending the bible text
	 */
	function bfox_ajax_send_bible_text()
	{
		$ref_str = $_POST['ref_str'];
		$text_field = 'bible-text-1';
		$ref_field = 'bible-ref-field';
		$ref = new BibleRefs($ref_str);
		$content = bfox_get_ref_content($ref);
		$ref_str = $ref->get_string();
		die("jQuery('#$text_field').html('$content'); jQuery('#$ref_field').val('$ref_str'); jQuery('#$ref_field').change();");
	}
	add_action('wp_ajax_bfox_ajax_send_bible_text', 'bfox_ajax_send_bible_text');

?>
