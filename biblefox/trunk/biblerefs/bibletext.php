<?php

	/**
	 * Returns an output string with scripture text for the Scripture Quick View
	 *
	 * Includes a Table of Contents at the bottom.
	 *
	 * @param BibleRefs $refs
	 * @param unknown_type $limit The limit of how many chapters can be displayed in full
	 * @return string Scripture Text Output (with TOC)
	 */
	function bfox_get_ref_content_quick(BibleRefs $refs, $limit = 5)
	{
		$is_full = FALSE;

		// Only get the scripture text output if we haven't exceeded the chapter limit
		$num_chapters = $refs->get_num_chapters();
		if ($limit >= $num_chapters)
		{
			$content = Translations::get_verse_content($refs);
			$content .= '<hr/>';
			$is_full = TRUE;
		}

		// Add the Table of Contents to the end of the output
		$links = '';
		$bcvs = BibleRefs::get_bcvs($refs->get_seqs());
		foreach ($bcvs as $book => $cvs)
		{
			$book_name = BibleMeta::get_book_name($book);
			$end_chapter = BibleMeta::end_verse_max($book);

			for ($ch = BibleMeta::start_chapter; $ch <= $end_chapter; $ch++)
			{
				if (!empty($links)) $links .= ' | ';
				$links .= BfoxBlog::ref_link(array('ref_str' => "$book_name $ch", 'text' => $ch));
			}
		}
		$content .= "<center>$links</center>";

		return $content;
	}

	function bfox_get_posts_equation_for_refs(BibleRefs $refs, $table_name = '', $verse_begin = 'verse_begin', $verse_end = 'verse_end')
	{
		if (empty($table_name)) $table_name = $GLOBALS['wpdb']->bfox_bible_ref;
		$begin = $table_name . '.' . $verse_begin;
		$end = $table_name . '.' . $verse_end;
		return $refs->sql_where2($begin, $end);
	}

	function bfox_get_posts_for_refs(BibleRefs $refs)
	{
		global $wpdb, $blog_id;
		$table_name = $wpdb->bfox_bible_ref;
		$posts_table = $wpdb->posts;

		// TODO3: This check shouldn't be here permanently
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			return array();

		$post_ids = array();
		$equation = bfox_get_posts_equation_for_refs($refs, $table_name);
		if ('' != $equation)
		{
			$post_ids = $wpdb->get_col("
				SELECT post_id
				FROM $table_name
				WHERE $equation
				GROUP BY post_id");
		}

		$posts = array();
		if (!empty($post_ids))
		{
			$posts = (array) $wpdb->get_results("
				SELECT *
				FROM $posts_table
				WHERE post_type = 'post'
				AND (ID = " . implode(' OR ID = ', $post_ids) . ")");
		}

		return $posts;
	}

	function bfox_get_post_bible_refs($post_id = 0)
	{
		global $wpdb;
		$table_name = $wpdb->bfox_bible_ref;

		// If the table does not exist then there are obviously no bible references
		if ((0 != $post_id) && ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name))
		{
			$select = $wpdb->prepare("SELECT verse_begin, verse_end FROM $table_name WHERE post_id = %d ORDER BY ref_order ASC", $post_id);
			$sets = $wpdb->get_results($select, ARRAY_N);
		}

		return (RefManager::get_from_sets($sets));
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

	function bfox_ref_quick_view_menu(BibleRefs $ref)
	{
		$next_refs = RefManager::get_from_sets($ref->get_sets());
		$previous_refs = RefManager::get_from_sets($ref->get_sets());
		$next_refs->increment(1);
		$previous_refs->increment(-1);

		$scripture_links = array();
		$next_link = '<input type="button" class="button" onclick="bible_text_request(\'' . $next_refs->get_string() . '\')" value="' . $next_refs->get_string() . ' >">';
		$previous_link = '<input type="button" class="button" onclick="bible_text_request(\'' . $previous_refs->get_string() . '\')" value="< ' . $previous_refs->get_string() . '">';
		$tag_link = '<input type="button" class="button" id="add-bible-ref" onclick="bible_ref_flush_to_text()" bible_ref="' . $ref->get_string() . '" value="Tag ' . $ref->get_string() . '">';

		$menu = '<table width="100%"><tr>';
		$menu .= '<td align="left" width="33%">' . $previous_link . '</td>';
		$menu .= '<td align="center" width="33%">' . $tag_link . '</td>';
		$menu .= '<td align="right" width="33%">' . $next_link . '</a></td>';
		$menu .= '</tr>';
		$menu .= '</table>';
		return $menu;
	}

	/*
	 AJAX function for sending the bible text
	 */
	function bfox_ajax_send_bible_text()
	{
		global $bfox_quicknote;

		$ref_str = $_POST['ref_str'];
		$ref = RefManager::get_from_str($ref_str);
		$bfox_quicknote->set_biblerefs($ref);
		sleep(1);

		// If it is not valid, give the user an error message
		// Otherwise give the user the content they were looking for
		if (!$ref->is_valid())
		{
			$content = 'Invalid bible reference: ' . $ref_str;
		}
		else
		{
			$ref_str = $ref->get_string();
			$menu = addslashes(bfox_ref_quick_view_menu($ref));
			$content = addslashes(bfox_get_ref_content_quick($ref));
		}

		$script = "bfox_quick_view_loaded('$ref_str', '$content', '$menu');";
		die($script);
	}
	add_action('wp_ajax_bfox_ajax_send_bible_text', 'bfox_ajax_send_bible_text');

?>
