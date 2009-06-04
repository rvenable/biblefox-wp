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
			$ref_str_short = BibleRefs::create_book_string($book, $cvs, BibleMeta::name_short);

			// Create a new bible refs for just this book (so we can later pass it into BfoxBlog::get_verse_content())
			$book_refs = new BibleRefs;

			unset($ch1);
			foreach ($cvs as $cv)
			{
				if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
				list($ch2, $vs2) = $cv->end;

				// Add the cv onto our book bible refs
				$book_refs->add_bcv($book, $cv);
			}

			$bible_viewer_link = "Biblefox Bible Viewer: <a href='" . Biblefox::ref_url($ref_str, Biblefox::ref_url_bible) . "' target='blank'>$ref_str</a>";
			$tag_link = "Add tag: <a href='#tagsdiv' onclick='tag_flush_to_text(0, this)'>$ref_str_short</a>";

			// Create the navigation bar with the prev/write/next links
			$nav_bar = "<div class='bible_post_nav'>";
			if ($ch1 > BibleMeta::start_chapter)
			{
				$prev_ref_str = $book_name . ' ' . ($ch1 - 1);
				$nav_bar .= BfoxBlog::ref_link_ajax($prev_ref_str, "&lt; $prev_ref_str", "class='bible_post_prev'");
			}
			$nav_bar .= $bible_viewer_link;
			if ($ch2 < BibleMeta::end_verse_max($book))
			{
				$next_ref_str = $book_name . ' ' . ($ch2 + 1);
				$nav_bar .= BfoxBlog::ref_link_ajax($next_ref_str, "$next_ref_str &gt;", "class='bible_post_next'");
			}
			$nav_bar .= "<br/>$tag_link</div>";

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

	/*
	 AJAX function for sending the bible text
	 */
	function bfox_ajax_send_bible_text()
	{
		$ref_str = $_POST['ref_str'];
		$ref = new BibleRefs($ref_str);
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

		$script = "bfox_quick_view_loaded('$ref_str', '$content');";
		die($script);
	}
	add_action('wp_ajax_bfox_ajax_send_bible_text', 'bfox_ajax_send_bible_text');

?>
