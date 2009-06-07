<?php

class BfoxRefContent {

	private static function toolbox() {
		$top_boxes = array('commentaries' => __('Blogs'), 'notes' => __('Notes'), 'none' => __('Hide'));

		?>
		<div class="sideview">
			<ul id='sideview_list'>
			<?php foreach ($top_boxes as $id => $title): ?>
				<li><a onclick='bfox_sideshow("<?php echo $id ?>")'><?php echo $title ?></a></li>
			<?php endforeach ?>
			</ul>
			<?php foreach ($top_boxes as $id => $title): ?>
				<div id='sideview_<?php echo $id ?>' class='sideview_content'></div>
			<?php endforeach ?>
		</div>
		<?php
	}

	private static function ref_seq($head = '', $body = '', $foot = '') {
		$content = '';
		if (!empty($head)) $content .= "<div class='ref_seq_head'>$head</div>";
		if (!empty($body)) $content .= "<div class='ref_seq_body'>$body</div>";
		if (!empty($foot)) $content .= "<div class='ref_seq_foot'>$foot</div>";

		return "<div class='ref_seq'>$content</div>";
	}

	private static function ref_footnotes($footnotes) {
		$content = '';

		if (!empty($footnotes)) {
			$body = '<ul>';
			foreach ($footnotes as $index => $footnote) $body .= "<li>$footnote</li>\n";
			$body .= '</ul>';
			$content = self::ref_seq(__('Footnotes'), $body);
		}

		return $content;
	}

	private static function ref_footer(BibleRefs $refs, Translation $translation) {
		?>
		<div class="box_menu">
			<center>
				<?php echo self::ref_toc($refs, $translation); ?>
			</center>
		</div>
		<?php
	}

	public static function ref_content(BibleRefs $refs, Translation $translation) {
		$bcvs = BibleRefs::get_bcvs($refs->get_seqs());
		if (!empty($bcvs)) {
			if ((1 < count($bcvs)) || (1 < count(current($bcvs)))) {
				$footnotes = array();
				?>
				<div>
					<?php self::toolbox() ?>
					<div class="reference">
						<?php echo self::ref_content_complex($refs, $translation, $footnotes, $bcvs) ?>
						<?php echo self::ref_footnotes($footnotes) ?>
					</div>
					<div class="clear"></div>
				</div>
				<div class="box_menu">
					<center>
						<?php echo self::ref_toc($refs, $translation); ?>
					</center>
				</div>
				<?php
			}
			else self::ref_content_simple($refs, $translation, $bcvs);
		}
	}

	private static function prev_link($book, $ch, $book_name = '', $title = '', $attrs = '') {
		if (empty($book_name)) $book_name = BibleMeta::get_book_name($book);
		if (BibleMeta::start_chapter <= --$ch) return Biblefox::ref_link("$book_name $ch", $title, '', $attrs);
	}

	private static function next_link($book, $ch, $book_name = '', $title = '', $attrs = '') {
		if (empty($book_name)) $book_name = BibleMeta::get_book_name($book);
		if (BibleMeta::end_verse_max($book) >= ++$ch) return Biblefox::ref_link("$book_name $ch", $title, '', $attrs);
	}

	private static function prev_page_bar($bcvs) {
		if (!empty($bcvs)) {
			$ch = reset(reset($bcvs))->start[0];
			$book = key($bcvs);
			$book_name = BibleMeta::get_book_name($book);
			echo self::prev_link($book, $ch, $book_name, __('Previous: ') . "$book_name $ch", "class='ref_bar'");
		}
	}

	private static function next_page_bar($bcvs) {
		if (!empty($bcvs)) {
			$ch = end(end($bcvs))->end[0];
			$book = key($bcvs);
			$book_name = BibleMeta::get_book_name($book);
			echo self::next_link($book, $ch, $book_name, __('Next: ') . "$book_name $ch", "class='ref_bar'");
		}
	}

	private static function ref_content_simple(BibleRefs $refs, Translation $translation, $bcvs) {
		$cv = reset(reset($bcvs));
		$book = key($bcvs);
		$ch1 = $cv->start[0];
		$ch2 = $cv->end[0];

		$footnotes = array();

		?>
		<?php self::prev_page_bar($bcvs) ?>
		<div class='ref_content'>
			<?php self::toolbox() ?>
			<div class="reference">
				<?php echo self::get_chapters_content($book, $ch1, $ch2, $refs->sql_where(), $footnotes, $translation) ?>
				<?php echo self::ref_footnotes($footnotes) ?>
			</div>
			<div class="clear"></div>
		</div>
		<?php self::next_page_bar($bcvs) ?>
		<?php self::ref_footer($refs, $translation) ?>
		<?php
	}

	public static function ref_content_paged(BibleRefs $refs, Translation $translation, $base_url, $page_var, $page_num = 0, $chs_per_page = 2) {

		$pages = $refs->get_sections($chs_per_page);

		if (!isset($pages[$page_num])) $page_num = 0;
		$page_refs = $pages[$page_num];

		if (isset($pages[$page_num - 1])) $prev_link = "<a href='" . add_query_arg($page_var, $page_num - 1, $base_url) . "' class='ref_bar'>Previous page: " . $pages[$page_num - 1]->get_string() . "</a>";
		if (isset($pages[$page_num + 1])) $next_link = "<a href='" . add_query_arg($page_var, $page_num + 1, $base_url) . "' class='ref_bar'>Next page: " . $pages[$page_num + 1]->get_string() . "</a>";

		$footnotes = array();

		?>
		<?php echo $prev_link ?>
		<div class='ref_content'>
			<?php self::toolbox() ?>
			<div class="reference">
				<?php echo self::ref_content_complex($page_refs, $translation, $footnotes, BibleRefs::get_bcvs($refs->get_seqs())) ?>
				<?php echo self::ref_footnotes($footnotes) ?>
			</div>
			<div class="clear"></div>
		</div>
		<?php echo $next_link ?>
		<?php
	}

	private static function ref_content_complex(BibleRefs $refs, Translation $translation, &$footnotes, $bcvs) {
		$visible = $refs->sql_where();

		foreach ($bcvs as $book => $cvs) {
			$book_name = BibleMeta::get_book_name($book);
			$book_short = BibleMeta::get_book_name($book, BibleMeta::name_short);
			$book_str = BibleRefs::create_book_string($book, $cvs);

			unset($ch1);
			foreach ($cvs as $cv) {
				if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
				list($ch2, $vs2) = $cv->end;
			}

			$ch1 = max($ch1, BibleMeta::start_chapter);
			if ($ch2 >= BibleMeta::end_verse_min($book)) $ch2 = BibleMeta::end_verse_max($book);

			$prev = self::prev_link($book, $ch1, $book_short, '', "class='ref_seq_prev'");
			$next = self::next_link($book, $ch2, $book_short, '', "class='ref_seq_next'");

			$content .= self::ref_seq("<span class='ref_seq_title'>$prev$next$book_str</span>", self::get_chapters_content($book, $ch1, $ch2, $visible, $footnotes, $translation));
		}

		return $content;
	}

	private static function ref_toc(BibleRefs $refs, Translation $translation) {
		$bcvs = BibleRefs::get_bcvs($refs->get_seqs());

		foreach ($bcvs as $book => $cvs) {
			$book_name = BibleMeta::get_book_name($book);
			$end_chapter = BibleMeta::end_verse_max($book);

			?>
			<?php echo $book_name ?>
			<ul class='flat_toc'>
			<?php for ($ch = BibleMeta::start_chapter; $ch <= $end_chapter; $ch++): ?>
				<li><a href='<?php echo BfoxQuery::passage_page_url("$book_name $ch", $translation) ?>'><?php echo $ch ?></a></li>
			<?php endfor; ?>
			</ul>
			<?php
		}
	}

	/**
	 * Return verse content for display in chapter groups
	 *
	 * @param integer $book
	 * @param integer $chapter1
	 * @param integer $chapter2
	 * @param string $visible
	 * @param array $footnotes
	 * @param Translation $trans
	 * @return string
	 */
	private static function get_chapters_content($book, $chapter1, $chapter2, $visible, &$footnotes, Translation $trans = NULL) {
		if (is_null($trans)) $trans = $GLOBALS['bfox_trans'];

		$content = '';
		$footnote_index = count($footnotes);

		$book_name = BibleMeta::get_book_name($book);

		// Get the verse data from the bible translation
		$chapters = $trans->get_chapter_verses($book, $chapter1, $chapter2, $visible);

		if (!empty($chapters)) {
			// We don't want to start with a hidden rule
			$add_rule = FALSE;

			foreach ($chapters as $chapter_id => $verses) {
				$is_hidden_chapter = TRUE;
				$prev_visible = TRUE;
				$index = 0;

				$sections = array();

				foreach ($verses as $verse) {
					if (0 == $verse->verse_id) continue;

					if ($verse->visible) $is_hidden_chapter = FALSE;

					if ($prev_visible != $verse->visible) $index++;
					$prev_visible = $verse->visible;

					// TODO3: Remove 'verse' attribute
					$sections[$index] .= "<span class='bible_verse' verse='$verse->verse_id'><b>$verse->verse_id</b> $verse->verse</span>\n";
				}
				$last_index = $index;

				if ($is_hidden_chapter) {
					$chapter_class = 'hidden_chapter';
					$chapter_content = $sections[1];

					// TODO3: Instead of removing footnotes, find a way to show them when showing hidden chapters
					// Remove any footnotes
					$ch_footnotes = BfoxUtility::find_footnotes($chapter_content);
					foreach (array_reverse($ch_footnotes) as $footnote) $chapter_content = substr_replace($chapter_content, '', $footnote[0], $footnote[1]);

					// Don't show a hidden rule immediately following a hidden chapter
					$add_rule = FALSE;
				}
				else {
					$chapter_class = 'visible_chapter';
					$chapter_content = '';
					foreach ($sections as $index => $section) {
						// Every odd numbered section is hidden
						if ($index % 2) {
							$chapter_content .= "<span class='hidden_verses'>\n$section\n</span>\n";

							// If we can add a rule, do it now
							// We don't want to add a rule for the last section, though
							if ($add_rule) { // && ($last_index != $index))
								$chapter_content .= "<hr class='hidden_verses_rule' />\n";

								// Don't add a rule immediately after this one
								$add_rule = FALSE;
							}
						}
						else {
							$chapter_content .= $section;

							// We only want to add a rule if the previous section was not hidden
							$add_rule = TRUE;
						}
					}

					$ch_footnotes = BfoxUtility::find_footnotes($chapter_content);
					$foot_count = count($ch_footnotes);
					if (0 < $foot_count) {
						foreach ($ch_footnotes as $index => $footnote) {
							$index += $footnote_index + 1;
							$footnotes[$index] = "<a name=\"footnote_$index\" href=\"#footnote_ref_$index\">[$index]</a> " . $footnote[2];
						}

						foreach (array_reverse($ch_footnotes, TRUE) as $index => $footnote) {
							$index += $footnote_index + 1;
							$chapter_content = substr_replace($chapter_content, "<a name='footnote_ref_$index' href='#footnote_$index' title='" . strip_tags($footnote[2]) . "'>[$index]</a>", $footnote[0], $footnote[1]);
						}

						$footnote_index += $foot_count;
					}
				}

				$content .= "<span class='chapter $chapter_class'>\n<span class='chapter_head'>$chapter_id</span>\n$chapter_content</span>\n";
			}

		}

		return $content;
	}
}

?>