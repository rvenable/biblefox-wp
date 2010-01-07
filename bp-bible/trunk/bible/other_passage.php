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



class BfoxBible {

	/**
	 * @var BfoxRefs
	 */
	private $refs = NULL;

	/**
	 * @var BfoxTrans
	 */
	private $translation = NULL;

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
		$this->visible = $refs->sql_where();

		$this->passages = array();
		$this->passage_count = 0;
		$this->passage_index = -1;

		foreach ($bcvs as $book => $cvs) {
			$this->passages []= new BfoxPassage($book, $cvs);
			$this->passage_count++;
		}

		return !empty($this->passages);
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

		if (!empty($ref_str)) return '';
		else return '';
	}

	public function prev_link($title = '', $attrs = '') {
		return $this->ref_nav_link('prev', $title, $attrs);
	}

	public function next_link($title = '', $attrs = '') {
		return $this->ref_nav_link('next', $title, $attrs);
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
			if (BibleMeta::end_verse_max($book) >= $ch) return BibleMeta::get_book_name($this->book) . ' ' . $ch;
		}
	}
}

$passage = BfoxBible::get_passage();

$refs = $bible->get_refs();
$translation = $bible->get_translation();

		// From:
		// BfoxRefContent::ref_content_new($refs, $translation)

		$footnotes = array();

		list($ref_history_content, $ref_history) = (array) self::ref_history($refs);
		if (!empty($ref_history)) {
			$event = array_shift($ref_history);
			$mark_link = $event->desc() . ' at ' . date('g:i a', $event->time) . ': ' . $event->toggle_link('mark as read', 'mark as unread');
		}
		else $mark_link = '';


				global $user_ID;
		if (!empty($user_ID)) {
			$history = BfoxHistory::get_history(10, 0, $refs);
			return array(self::ref_seq(__('Your History for ') . $refs->get_string(), self::history_table($history)), $history);
		}


$bible = new BfoxBible();

get_header(); ?>
<div id="bible" class="">
	<div id="bible_page">
	<?php if ($bible->has_passages()) : ?>
		<?php echo bfox_reader_tools_tab($refs) ?>
		<div class='ref_content'>

			<!-- Bible Header -->
			<div class='bible_page_head'>
				<?php _e('Bible Reader') ?> - <?php echo $bible->ref_string() ?><br/>
				<small><?php echo $bible->mark_read_link() ?></small>
			</div>

			<!-- Passages -->
			<?php while ( $bible->passages() ) : $bible->the_passage(); ?>
			<div class='ref_seq'>
				<div class='ref_seq_head'>
					<span class='ref_seq_title'><?php echo $bible->prev_link() . $bible->next_link() . $bible->book_name() ?></span>
				</div>
				<div class='ref_seq_body'>
					<?php $bible->passage_content() ?>
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

			<!-- Tables of Contents -->
			<?php foreach ($bible->books() as $book): ?>
				<div class='ref_seq'>
					<?php $book_name = BibleMeta::get_book_name($book) ?>
					<div class='ref_seq_head'><?php echo $book_name ?> - <?php _e('Table of Contents') ?></div>
					<div class='ref_seq_body'>
					<?php $end_chapter = BibleMeta::end_verse_max($book) ?>
						<ul class='flat_toc'>
						<?php for ($ch = BibleMeta::start_chapter; $ch <= $end_chapter; $ch++): ?>
							<li><a href='<?php echo BfoxQuery::ref_url("$book_name $ch") ?>'><?php echo $ch ?></a></li>
						<?php endfor ?>
						</ul>
					</div>
				</div>
			<?php endforeach ?>

			<!-- History for this Passage -->
			<?php $history = BfoxHistory::get_history(10, 0, $refs) ?>
			<?php if (!empty($history)): ?>
			<div class='ref_seq'>
				<div class='ref_seq_head'><?php echo __('Your History for ') . $bible->ref_string() ?></div>
				<div class='ref_seq_body'>
					<?php $bible->history_table($history) ?>
				</div>
			</div>
			<?php endif ?>

		</div>
		<div class="clear"></div>
	<?php endif ?>
	</div>
</div>

<?php BfoxBible::get_sidebar(); ?>

<?php get_footer() ?>