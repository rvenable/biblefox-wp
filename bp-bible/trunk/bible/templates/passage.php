<?php

	function bfox_reader_check_option($name, $label) {
		$id = "option_$name";

		return "<input type='checkbox' name='$name' id='$id' class='view_option'/><label for='$id'>$label</label>";
	}

	function bfox_reader_options() {
		$table = new BfoxHtmlList();
		$table->add(bfox_reader_check_option('jesus', __('Show Jesus\' words in red')));
		$table->add(bfox_reader_check_option('paragraphs', __('Display verses as paragraphs')));
		$table->add(bfox_reader_check_option('verse_nums', __('Hide verse numbers')));
		$table->add(bfox_reader_check_option('footnotes', __('Hide footnote links')));

		return $table->content();
	}

	function bfox_reader_tools_tab(BfoxRefs $refs) {
		global $user_ID;

		$tool_tabs = new BfoxHtmlTabs("id='tool_tabs' class='tabs'");

		if (!empty($user_ID)) {
			$url = BfoxQuery::page_url(BfoxQuery::page_passage);
			$cboxes = array();
			$cboxes['blogs'] = new BfoxCboxBlogs($refs, $url, 'commentaries', 'Blog Posts');
			$cboxes['notes'] = new BfoxCboxNotes($refs, $url, 'notes', 'My Bible Notes');

			ob_start();
			$cboxes['blogs']->content();
			$post_count = ' (' . $cboxes['blogs']->post_count . ')';
			$blog_content = ob_get_clean();

			ob_start();
			$cboxes['notes']->content();
			$note_content = ob_get_clean();

			$tool_tabs->add('blogs', __('Blog Posts') /*. $post_count*/, $blog_content /*. "<a href='" . BfoxQuery::page_url(BfoxQuery::page_commentary) . "'>Manage Blog Commentaries</a>"*/);
			$tool_tabs->add('notes', __('Notes'), $note_content);
		}
		$tool_tabs->add('options', __('Options'), bfox_reader_options());

		return $tool_tabs->content();
	}

class BfoxPassage {

	private $book;
	private $chapter1;
	private $chapter2;

	public function __construct($book, $cvs) {
		foreach ($cvs as $cv) {
			if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
			list($ch2, $vs2) = $cv->end;
		}

		$ch1 = max($ch1, BibleMeta::start_chapter);
		if ($ch2 >= BibleMeta::end_verse_min($book)) $ch2 = BibleMeta::end_verse_max($book);

		$this->book = $book;
		$this->chapter1 = $ch1;
		$this->chapter2 = $ch2;
	}

	public function content(BfoxTrans $translation, $visible, &$footnotes = NULL) {

		// Get the verse data from the bible translation
		$formatter = new BfoxVerseFormatter(TRUE);
		if (!is_null($footnotes)) $formatter->use_footnotes($footnotes);

		$content = $translation->get_chapter_verses($this->book, $this->chapter1, $this->chapter2, $visible, $formatter);

		if (!is_null($footnotes)) $footnotes = $formatter->get_footnotes();

		return $content;
	}

	public function nav_ref($type) {
		if ('prev' == $type) {
			$ch = $this->chapter1 - 1;
			if (BibleMeta::start_chapter <= $ch) return BibleMeta::get_book_name($this->book) . ' ' . $ch;
		}
		elseif ('next' == $type) {
			$ch = $this->chapter1 + 1;
			if (BibleMeta::end_verse_max($this->book) >= $ch) return BibleMeta::get_book_name($this->book) . ' ' . $ch;
		}
	}

	public function book_name() {
		return BibleMeta::get_book_name($this->book);
	}
}

class BfoxBible2 {

	/**
	 * @var BfoxRefs
	 */
	private $refs = NULL;

	private $bcvs = NULL;

	/**
	 * @var BfoxTrans
	 */
	private $translation = NULL;

	public function __construct(BfoxRefs $refs, BfoxTrans $translation) {
		$this->refs = $refs;
		$this->translation = $translation;
	}

	public function get_refs() {
		return $this->refs;
	}

	public function get_bcvs() {
		if (is_null($this->bcvs)) $this->bcvs = BfoxRefs::get_bcvs($this->refs->get_seqs());
		return $this->bcvs;
	}

	public function get_books() {
		return array_keys($this->get_bcvs());
	}

	public function ref_string($name) {
		return $this->refs->get_string($name);
	}

	public function mark_read_link() {
		return '';
	}

	private $visible = '';
	private $footnotes = array();

	/**
	 * @var BfoxPassage
	 */
	private $passage = NULL;

	private $passages = array();
	private $passage_count = 0;
	private $passage_index = -1;

	public function has_passages($args = '') {
		$this->visible = $this->refs->sql_where();

		$this->passages = array();
		$this->passage_count = 0;
		$this->passage_index = -1;

		$bcvs = BfoxRefs::get_bcvs($this->refs->get_seqs());

		foreach ($bcvs as $book => $cvs) {
			$this->passages []= new BfoxPassage($book, $cvs);
			$this->passage_count++;
		}

		$has_passages = !empty($this->passages);

/*		if ($has_passages) {
			// From:
			// BfoxRefContent::ref_content_new($refs, $translation)

			$footnotes = array();

			list($ref_history_content, $ref_history) = (array) self::ref_history($refs);
			if (!empty($ref_history)) {
				$event = array_shift($ref_history);
				$mark_link = $event->desc() . ' at ' . date('g:i a', $event->time) . ': ' . $event->toggle_link('mark as read', 'mark as unread');
			}
			else $mark_link = '';
		}
*/
		return $has_passages;
	}

	public function passages() {
		if (($this->passage_index + 1) < $this->passage_count) {
			return true;
		}
		return false;
	}

	public function the_passage() {
		$this->passage = $this->passages[++$this->passage_index];
	}

	public function passage_content() {
		if (!is_null($this->passage)) return $this->passage->content($this->translation, $this->visible, $this->footnotes);
	}

	public function ref_nav_link($type = '', $title = '', $attrs = '') {
		if (!is_null($this->passage)) $ref_str = $this->passage->nav_ref($type);

		if (!empty($ref_str)) return Biblefox::ref_link($ref_str, $title, '', $attrs);
		else return '';
	}

	public function prev_link($title = '', $attrs = '') {
		return $this->ref_nav_link('prev', $title, $attrs);
	}

	public function next_link($title = '', $attrs = '') {
		return $this->ref_nav_link('next', $title, $attrs);
	}

	public function book_name() {
		if (!is_null($this->passage)) return $this->passage->book_name();
	}

	public function get_footnotes() {
		if (!empty($this->footnotes)) {
			$footnotes = $this->footnotes;
			$this->footnotes = array();
			return $footnotes;
		}
		return false;
	}

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

}


global $bible, $page_passage_refs, $page_passage_trans;
$bible = new BfoxBible2($page_passage_refs, $page_passage_trans);

//get_header();
?>
<div id="content" class="narrowcolumn">

	<div id="bible" class="">
		<div id="bible_page">
		<?php if ($bible->has_passages()) : ?>
			<?php echo bfox_reader_tools_tab(bfox_bible_get_refs()) ?>
			<div class='ref_content'>

				<!-- Bible Header -->
				<div class='bible_page_head'>
					<?php _e('Bible Reader') ?> - <?php echo $bible->ref_string() ?><br/>
					<small><?php echo $bible->mark_read_link() ?></small>
				</div>

				<!-- Passages -->
				<?php while ($bible->passages()) : $bible->the_passage(); ?>
				<div class='ref_seq'>
					<div class='ref_seq_head'>
						<span class='ref_seq_title'><?php echo $bible->prev_link() . $bible->next_link() . $bible->book_name() ?></span>
					</div>
					<div class='ref_seq_body'>
						<?php echo $bible->passage_content() ?>
					</div>
				</div>
				<?php endwhile; ?>

				<!-- Footnotes -->
				<?php if ($footnotes = $bible->get_footnotes()): ?>
				<div class='ref_seq'>
					<div class='ref_seq_head'><?php _e('Footnotes') ?></div>
					<div class='ref_seq_body'>
						<ul>
						<?php foreach ($footnotes as $footnote): ?>
							<li><?php echo $footnote ?></li>
						<?php endforeach ?>
						</ul>
					</div>
				</div>
				<?php endif ?>

				<!-- Passage Widgets -->
				<?php if (!function_exists('dynamic_sidebar') || !dynamic_sidebar('bible-passage')): ?>
				<div class="widget-error">
					<?php _e('Please log in and add widgets to this column.') ?> <a href="<?php echo get_option('siteurl') ?>/wp-admin/widgets.php?s=&amp;show=&amp;sidebar=bible-passage"><?php _e('Add Widgets') ?></a>
				</div>
				<?php endif; ?>

			</div>
			<div class="clear"></div>
		<?php endif ?>
		</div>
	</div>
</div>

	<!-- Passage Sidebar Widgets -->
	<div id="sidebar">
		<?php if (!function_exists('dynamic_sidebar') || !dynamic_sidebar('bible-passage-sidebar')): ?>
		<div class="widget-error">
			<?php _e('Please log in and add widgets to this column.') ?> <a href="<?php echo get_option('siteurl') ?>/wp-admin/widgets.php?s=&amp;show=&amp;sidebar=bible-passage-sidebar"><?php _e('Add Widgets') ?></a>
		</div>
		<?php endif; ?>
	</div>


<?php //get_footer(); ?>
