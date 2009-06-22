<?php

require_once BFOX_BIBLE_DIR . '/cbox_notes.php';
require_once BFOX_BIBLE_DIR . '/cbox_blogs.php';

class BfoxRefContent {

	public static function history_table($history) {
		$table = new BfoxHtmlTable("class='widefat'");

		foreach ($history as $event) $table->add_row('', 5,
			$event->desc(),
			$event->ref_link(),
			BfoxUtility::nice_date($event->time),
			date('g:i a', $event->time),
			$event->toggle_link());

		return $table->content();
	}

	public static function ref_loader($ref_str) {
		return "<a href='" . BfoxQuery::add_display_type(BfoxQuery::display_ajax, BfoxQuery::passage_page_url($ref_str)) . "' class='ref_loader'></a>";
	}

	public static function passage_row($head, $menu, $content) {
		return "<div class='prow_head ui-accordion-header ui-state-active ui-corner-top'>$head</div>
			<div class='ui-accordion-content ui-widget-content ui-corner-bottom ui-accordion-content-active'><div>
				<div class='prow_menu'>$menu</div>
				<div class='prow_content'>$content</div>
			</div></div>";
	}

	public static function ref_js() {
		?>
		<div id='ref_js'>
			<?php self::toolbox() ?>
			<div id='ref_js_passage'></div>
		</div>
		<?php
	}

	private static function add_cboxes(BfoxRefs $refs) {
		$url = BfoxQuery::ref_url($refs->get_string());
		$cboxes = array();
		$cboxes['blogs'] = new BfoxCboxBlogs($refs, $url, 'commentaries', 'Blog Posts');
		$cboxes['notes'] = new BfoxCboxNotes($refs, $url, 'notes', 'My Bible Notes');

		foreach ($cboxes as $cbox) echo $cbox->cbox();
	}

	private static function toolbox() {
		$top_boxes = array('commentaries' => __('Blogs'), 'notes' => __('Notes'), 'none' => __('Hide'));

		?>
		<div id="sideview">
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

	private static function ref_seq($head = '', $body = '', $foot = '', $extra_classes = '') {
		$content = '';
		if (!empty($head)) $content .= "<div class='ref_seq_head'>$head</div>";
		if (!empty($body)) $content .= "<div class='ref_seq_body'>$body</div>";
		if (!empty($foot)) $content .= "<div class='ref_seq_foot'>$foot</div>";

		if (!empty($extra_classes)) $extra_classes = ' ' . $extra_classes;
		return "<div class='ref_seq$extra_classes'>$content</div>";
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

	private static function ref_history(BfoxRefs $refs) {
		global $user_ID;
		if (!empty($user_ID)) return self::ref_seq(__('Your History for ') . $refs->get_string(), self::history_table(BfoxHistory::get_history(10, 0, $refs)));
	}

	public static function ref_content(BfoxRefs $refs, BfoxTrans $translation) {
		$bcvs = BfoxRefs::get_bcvs($refs->get_seqs());
		if (!empty($bcvs)) {
			if ((1 < count($bcvs)) || (1 < count(current($bcvs)))) {
				$footnotes = array();
				?>
				<div>
					<?php self::toolbox() ?>
					<div class="reference">
						<?php echo self::ref_content_complex($refs, $translation, $footnotes, $bcvs) ?>
						<?php echo self::ref_footnotes($footnotes) ?>
						<?php self::add_cboxes($refs) ?>
					</div>
					<div class="clear"></div>
				</div>
				<div class="box_menu">
					<center>
						<?php echo self::ref_toc($refs); ?>
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

	private static function ref_content_simple(BfoxRefs $refs, BfoxTrans $translation, $bcvs) {
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
				<?php self::add_cboxes($refs) ?>
			</div>
			<div class="clear"></div>
		</div>
		<?php self::next_page_bar($bcvs) ?>
		<?php echo self::ref_toc($refs); ?>
		<?php
	}

	public static function ref_content_new(BfoxRefs $refs, BfoxTrans $translation) {
		$footnotes = array();

		?>
		<div class='ref_content'>
			<div class='bible_page_head'><?php echo __('Bible Reader - ') . $refs->get_string() ?></div>
			<?php echo self::ref_content_complex($refs, $translation, $footnotes, BfoxRefs::get_bcvs($refs->get_seqs())) ?>
			<?php echo self::ref_footnotes($footnotes) ?>
			<?php echo self::ref_toc($refs); ?>
			<?php echo self::ref_history($refs) ?>
		</div>
		<?php
	}

	public static function ref_content_paged(BfoxRefs $refs, BfoxTrans $translation, $base_url, $page_var, $page_num = 0, $chs_per_page = 2) {

		$pages = $refs->get_sections($chs_per_page);

		if (!isset($pages[$page_num])) $page_num = 0;
		$page_refs = $pages[$page_num];

		if (isset($pages[$page_num - 1])) $prev_link = "<a href='" . add_query_arg($page_var, $page_num - 1, $base_url) . "' class='ref_bar'>Previous page: " . $pages[$page_num - 1]->get_string() . "</a>";
		if (isset($pages[$page_num + 1])) $next_link = "<a href='" . add_query_arg($page_var, $page_num + 1, $base_url) . "' class='ref_bar'>Next page: " . $pages[$page_num + 1]->get_string() . "</a>";

		$footnotes = array();

		?>
		<div class='ref_js_hold'></div>
		<div class='ref_content'>
			<?php echo $prev_link ?>
			<?php //self::toolbox() ?>
			<div class="reference">
				<?php echo self::ref_content_complex($page_refs, $translation, $footnotes, BfoxRefs::get_bcvs($refs->get_seqs())) ?>
				<?php echo self::ref_footnotes($footnotes) ?>
			</div>
			<?php self::add_cboxes($refs) ?>
			<div class="clear"></div>
			<?php echo $next_link ?>
		</div>
		<?php
	}

	private static function ref_content_complex(BfoxRefs $refs, BfoxTrans $translation, &$footnotes, $bcvs) {
		$visible = $refs->sql_where();

		foreach ($bcvs as $book => $cvs) {
			$book_name = BibleMeta::get_book_name($book);
			$book_short = BibleMeta::get_book_name($book, BibleMeta::name_short);
			$book_str = BfoxRefs::create_book_string($book, $cvs);

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

	private static function ref_toc(BfoxRefs $refs) {
		$content = '';

		$bcvs = BfoxRefs::get_bcvs($refs->get_seqs());
		foreach ($bcvs as $book => $cvs) {
			$book_name = BibleMeta::get_book_name($book);
			$end_chapter = BibleMeta::end_verse_max($book);

			$book_content = "<ul class='flat_toc'>";
			for ($ch = BibleMeta::start_chapter; $ch <= $end_chapter; $ch++) $book_content .= "<li><a href='" . BfoxQuery::passage_page_url("$book_name $ch") . "'>$ch</a></li>\n";
			$book_content .= "</ul>";
			$content .= self::ref_seq("$book_name - Table of Contents", $book_content, '');
		}

		return $content;
	}

	/**
	 * Return verse content for display in chapter groups
	 *
	 * @param integer $book
	 * @param integer $chapter1
	 * @param integer $chapter2
	 * @param string $visible
	 * @param array $footnotes
	 * @param BfoxTrans $trans
	 * @return string
	 */
	private static function get_chapters_content($book, $chapter1, $chapter2, $visible, &$footnotes, BfoxTrans $translation) {

		// Get the verse data from the bible translation
		$formatter = new BfoxVerseFormatter(TRUE);
		$formatter->use_footnotes($footnotes);
		$content = $translation->get_chapter_verses($book, $chapter1, $chapter2, $visible, $formatter);
		$footnotes = $formatter->get_footnotes();

		return $content;
	}
}

?>