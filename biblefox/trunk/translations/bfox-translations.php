<?php

define(BFOX_TRANS_DIR, dirname(__FILE__));

/**
 * A class for formatting verses from the translation into text for the user
 *
 */
class BfoxVerseFormatter {

	const trans_begin_poetry_1 = 'bible_poetry_indent_1';
	const trans_begin_poetry_2 = 'bible_poetry_indent_2';
	const trans_end_poetry = 'bible_end_poetry';
	const trans_end_p = 'bible_end_p';

	private $use_span, $c_text, $c_poetry;

	public $only_visible = FALSE;
	public $use_verse0 = FALSE;

	private $ps = array();
	private $p_count = 0;
	private $cur_text = '';
	private $first = '';

	private $do_footnotes = FALSE;
	private $footnote_index = 0;
	private $footnotes = array();

	public function __construct($use_span = FALSE, $c_text = 'bible_text', $c_poetry = 'bible_poetry') {
		$this->use_span = $use_span;
		$this->c_text = $c_text;
		$this->c_poetry = $c_poetry;
	}

	public function use_footnotes($footnotes) {
		$this->do_footnotes = TRUE;
		$this->footnotes = $footnotes;
		$this->footnote_index = count($this->footnotes);
	}

	public function get_footnotes() {
		return $this->footnotes;
	}

	public function format_cv($chapters) {
		$content = '';
		foreach ((array) $chapters as $chapter_id => $verses) {
			$verse_content = $this->format($verses);
			if ($this->use_span) $content .= "<div class='chapter'>\n<span class='chapter_head'>$chapter_id</span>\n$verse_content</div>\n";
			else $content .= $verse_content;
		}
		return $content;
	}

	public function format($verses) {
		$this->ps = array();
		$this->p_count = 0;
		$this->cur_text = '';
		if ($this->use_span) $this->first = 'first_p';

		$is_poetry_sub_line = FALSE;
		$cur_p = '';

		foreach ($verses as $verse) if ($this->use_verse($verse)) {
			$verse_span = "<span class='bible_verse' verse='$verse->verse_id'>";

			// HACK: Removing bible poetry breakpoints (we should remove these from the actual translation data instead)
			$verse->verse = str_ireplace('<br class="bible_poetry" />', '', $verse->verse);

			$parts = preg_split('/<span class="([^"]*)"><\/span>/i', " <b class='verse_num'>$verse->verse_id</b> $verse->verse", -1, PREG_SPLIT_DELIM_CAPTURE);
			foreach ($parts as $index => $part) {
				$trim = trim($part);
				if (!empty($trim)) {
					if (0 == ($index % 2)) $this->add_text($part, $verse_span);
					else {
						if (self::trans_begin_poetry_2 == $part) $is_poetry_sub_line = TRUE;
						elseif (self::trans_end_poetry == $part) {
							if ($is_poetry_sub_line && !empty($this->p_count)) $this->add_poetry_line();
							else $this->add_p($this->c_poetry);
							$is_poetry_sub_line = FALSE;
						}
						elseif (self::trans_end_p == $part) $this->add_p("bible_text$first");
					}
				}
			}
		}

		$total_text = '';
		foreach ($this->ps as $p) $total_text .= "<p class='{$p[0]}'>{$p[1]}</p>\n";
		$this->ps = array();

		if ($this->do_footnotes) $total_text = preg_replace_callback('/<footnote>(.*?)<\/footnote>/', array($this, 'footnote_replace'), $total_text);
		//else $total_text = preg_replace('/<footnote>(.*?)<\/footnote>/', '', $total_text);

		return $total_text;
	}

	private function footnote_replace($match) {
		$this->footnote_index++;
		$note = "<a name='footnote_$this->footnote_index' href='#footnote_ref_$this->footnote_index'>[$this->footnote_index]</a> " . BfoxRefParser::simple_html($match[1]);
		$link = " <a name='footnote_ref_$this->footnote_index' href='#footnote_$this->footnote_index' title='" . strip_tags($match[1]) . "' class='ref_foot_link'>[$this->footnote_index]</a> ";
		$this->footnotes []= $note;
		return $link;
	}

	private function use_verse($verse) {
		$use_verse = (!empty($verse->verse_id) || $this->use_verse0);
		$use_verse = $use_verse && (!$this->only_visible || $verse->visible);
		return $use_verse;
	}

	private function add_text($text, $verse_span) {
		if ($this->use_span) $this->cur_text .= "$verse_span$text</span>";
		else $this->cur_text .= $text;
	}

	private function add_poetry_line() {
		// Add the poetry line to the last p
		$this->ps[$this->p_count - 1][1] .= "<br/>\n$this->cur_text";
		$this->cur_text = '';
		$this->first = '';
	}

	private function add_p($class) {
		if (!empty($this->first)) $class .= ' ' . $this->first;
		$this->ps[$this->p_count++] = array($class, $this->cur_text);
		$this->cur_text = '';
		$this->first = '';
	}
}

/**
 * A class for individual bible translations
 *
 */
class Translation {
	public $id, $short_name, $long_name, $is_default, $is_enabled;
	public $table;

	public static $meta = array(
		'WEB' => 'World English Bible',
		'HNV' => 'Hebrew Names Version',
		'KJV' => 'King James Version',
		'ASV' => 'American Standard Version'
	);

	/**
	 * Construct an instance using an stdClass object (as returned by querying the Translation::translation_table DB table)
	 *
	 * @param stdClass $translation
	 */
	function __construct($id = '') {
		if (empty($id)) $id = 'WEB';

		// TODO3: Get rid of this num_id hack
		$hack = array(12 => 'WEB', 31 => 'HNV', 32 => 'ASV', 33 => 'KJV');
		if ((int) $id) {
			$num_id = $id;
			$id = $hack[$num_id];
		}
		else {
			$hack2 = array_flip($hack);
			$num_id = $hack2[$id];
		}

		$this->id = $num_id;
		$this->short_name = $id;
		$this->long_name = self::$meta[$id];
		$this->is_default = FALSE;
		$this->is_enabled = TRUE;

		// Set the translation table if it exists
		$table = self::get_translation_table_name($this->id);
		if (BfoxUtility::does_table_exist($table)) $this->table = $table;
		else $this->table = '';
	}

	/**
	 * Returns the translation table name for a given translation id
	 *
	 * @param integer $trans_id
	 * @return string
	 */
	public static function get_translation_table_name($trans_id) {
		// TODO3: remove this function (we save the table name as a member anyway)
		return BFOX_BASE_TABLE_PREFIX . "trans{$trans_id}_verses";
	}

	/**
	 * Get the verse content for some bible references
	 *
	 * @param string $ref_where SQL WHERE statement as returned from BibleRefs::sql_where()
	 * @return string Formatted bible verse output
	 */
	public function get_verses($ref_where, BfoxVerseFormatter $formatter = NULL) {
		$verses = array();

		// We can only grab verses if the verse data exists
		if (!empty($this->table)) {
			global $wpdb;
			$verses = $wpdb->get_results("SELECT unique_id, book_id, chapter_id, verse_id, verse FROM $this->table WHERE $ref_where");
		}

		if (!is_null($formatter)) return $formatter->format($verses);
		return $verses;
	}

	/**
	 * Get the verse content for a sequence of chapters
	 *
	 * @param integer $book
	 * @param integer $chapter1
	 * @param integer $chapter2
	 * @param string $visible SQL WHERE statement to determine which scriptures are visible (ex. as returned from BibleRefs::sql_where())
	 * @return string Formatted bible verse output
	 */
	public function get_chapter_verses($book, $chapter1, $chapter2, $visible, BfoxVerseFormatter $formatter = NULL) {
		$chapters = array();

		// We can only grab verses if the verse data exists
		if (!empty($this->table)) {
			global $wpdb;
			$verses = (array) $wpdb->get_results($wpdb->prepare("
				SELECT unique_id, chapter_id, verse_id, verse, ($visible) as visible
				FROM $this->table
				WHERE book_id = %d AND chapter_id >= %d AND chapter_id <= %d",
				$book, $chapter1, $chapter2));

			foreach ($verses as $verse) $chapters[$verse->chapter_id] []= $verse;
		}

		if (!is_null($formatter)) return $formatter->format_cv($chapters);
		return $chapters;
	}

	/**
	 * Returns all the enabled Translations in an array
	 *
	 * @return array of Translations
	 */
	public static function get_enabled() {
		$translations = array();
		foreach (Translation::$meta as $id => $meta) $translations []= new Translation($id);
		return $translations;
	}

	/**
	 * Returns all the installed Translations in an array
	 *
	 * @return array of Translations
	 */
	public static function get_installed() {
		return self::get_enabled();
	}

	/**
	 * Outputs an html select input with a list of translations
	 *
	 * @param translation_id $select_id
	 */
	public static function output_select($select_id = NULL, $use_short = FALSE) {
		// Get the list of enabled translations
		$translations = self::get_enabled();

		?>
		<select name="<?php echo BfoxQuery::var_translation ?>">
		<?php foreach ($translations as $translation): ?>
			<option value="<?php echo $translation->id ?>" <?php if ($translation->id == $select_id) echo 'selected' ?>><?php echo ($use_short) ? $translation->short_name : $translation->long_name; ?></option>
		<?php endforeach; ?>
		</select>
		<?php
	}
}

?>