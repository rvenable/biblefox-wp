<?php

	/**
	 * Returns an output string with scripture text for the Scripture Quick View
	 *
	 * Includes a Table of Contents at the bottom.
	 *
	 * @param BfoxRef $input_ref
	 * @param unknown_type $limit The limit of how many chapters can be displayed in full
	 * @return string Scripture Text Output (with TOC)
	 */
	function bfox_get_ref_content_quick(BfoxRef $input_ref, $limit = 5) {
		// Limit the ref to $limit chapters
		list($ref) = $input_ref->get_sections($limit, 1);

		$bcvs = BfoxRef::get_bcvs($ref->get_seqs());

		$book_content = array();
		foreach ($bcvs as $book => $cvs) {
			$content = '';
			$book_name = BibleMeta::get_book_name($book);
			$ref_str = BfoxRef::create_book_string($book, $cvs);

			// Create a new bible ref for just this book (so we can later pass it into the BfoxIframe)
			$book_ref = new BfoxRef;

			unset($ch1);
			foreach ($cvs as $cv) {
				if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
				list($ch2, $vs2) = $cv->end;

				// Add the cv onto our book bible ref
				$book_ref->add_bcv($book, $cv);
			}

			$tag_link = "Add tag: <a href='#tagsdiv' class='add-bible-ref-tag'>$ref_str</a>";

			// Create the navigation bar with the prev/write/next links
			$nav_bar = "<div class='bible_post_nav'>";
			if ($ch1 > BibleMeta::start_chapter) {
				$prev_ref_str = $book_name . ' ' . ($ch1 - 1);
				$nav_bar .= bfox_blog_ref_link_ajax($prev_ref_str, "&lt; $prev_ref_str", "class='bible_post_prev'");
			}
			if ($ch2 < BibleMeta::passage_end($book)) {
				$next_ref_str = $book_name . ' ' . ($ch2 + 1);
				$nav_bar .= bfox_blog_ref_link_ajax($next_ref_str, "$next_ref_str &gt;", "class='bible_post_next'");
			}
			$nav_bar .= "<br/>$tag_link</div>";

			$iframe = new BfoxIframe($book_ref);
			$verse_content = '<select class="bfox-iframe-select">' . $iframe->select_options() . '</select>';
			$verse_content .= '<iframe class="bfox-iframe bfox-tooltip-iframe" src="' . $iframe->url() . '"></iframe>';

			$content = $nav_bar . $verse_content;
			$content .= '<hr/>';

			// Add the Table of Contents to the end of the output
			$links = '';
			$end_chapter = BibleMeta::passage_end($book);
			for ($ch = BibleMeta::start_chapter; $ch <= $end_chapter; $ch++) {
				if (!empty($links)) $links .= ' | ';
				$links .= bfox_blog_ref_link_ajax("$book_name $ch", $ch);
			}
			$content .= "<center>$links</center>";

			$book_content[$book] = $content;
		}

		return implode('<hr/>', $book_content);
	}

	/*
	 AJAX function for sending the bible text
	 */
	function bfox_ajax_send_bible_text() {
		$ref_str = $_POST['ref_str'];
		$ref = new BfoxRef($ref_str);
		sleep(1);

		// If it is not valid, give the user an error message
		// Otherwise give the user the content they were looking for
		if (!$ref->is_valid()) {
			$content = 'Invalid bible reference: ' . $ref_str;
		}
		else {
			$ref_str = $ref->get_string();
			$content = addslashes(str_replace("\n", '', bfox_get_ref_content_quick($ref)));
		}

		$script = "bfox_quick_view_loaded('$ref_str', '$content');";
		die($script);
	}
	add_action('wp_ajax_bfox_ajax_send_bible_text', 'bfox_ajax_send_bible_text');

?>
