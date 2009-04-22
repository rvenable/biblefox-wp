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
		$bcvs = BibleRefs::get_bcvs($refs->get_seqs());

		$book_content = array();
		foreach ($bcvs as $book => $cvs)
		{
			$content = '';
			$book_name = BibleMeta::get_book_name($book);
			$ref_str = BibleRefs::create_book_string($book, $cvs);

			// Create a new bible refs for just this book (so we can later pass it into BfoxBlog::get_verse_content())
			$book_refs = new BibleRefs();

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
				$nav_bar .= BfoxBlog::ref_link_ajax($prev_ref_str, "&lt; $prev_ref_str", "class='bible_post_prev button'");
			}
			$nav_bar .= '<input type="button" class="button" id="add-bible-ref" onclick="bible_ref_flush_to_text()" bible_ref="' . $ref_str . '" value="Tag ' . $ref_str . '">';
			if ($ch2 < BibleMeta::end_verse_max($book))
			{
				$next_ref_str = $book_name . ' ' . ($ch2 + 1);
				$nav_bar .= BfoxBlog::ref_link_ajax($next_ref_str, "$next_ref_str &gt;", "class='bible_post_next button'");
			}
			$nav_bar .= "<br/><a href='" . BfoxQuery::passage_page_url($ref_str) . "'>View in Biblefox Bible Viewer</a></div>";

			$content = $nav_bar . BfoxBlog::get_verse_content($book_refs) . $nav_bar;
			$content .= '<hr/>';

			// Add the Table of Contents to the end of the output
			$links = '';
			$end_chapter = BibleMeta::end_verse_max($book);
			for ($ch = BibleMeta::start_chapter; $ch <= $end_chapter; $ch++)
			{
				if (!empty($links)) $links .= ' | ';
				$links .= BfoxBlog::ref_link_ajax("$book_name $ch", $ch);
			}
			$content .= "<center>$links</center>";

			$book_content[$book] = $content;
		}

		return implode('<hr/>', $book_content);
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

	/*
	 AJAX function for sending the bible text
	 */
	function bfox_ajax_send_bible_text()
	{
		$ref_str = $_POST['ref_str'];
		$ref = RefManager::get_from_str($ref_str);
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
			$content = addslashes(bfox_get_ref_content_quick($ref));
		}

		$script = "bfox_quick_view_loaded('$ref_str', '$content', '');";
		die($script);
	}
	add_action('wp_ajax_bfox_ajax_send_bible_text', 'bfox_ajax_send_bible_text');

?>
