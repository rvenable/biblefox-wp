<?php

if (!defined(BFOX_TRANS_DIR)) define(BFOX_TRANS_DIR, dirname(__FILE__));

require_once BFOX_TRANS_DIR . '/formatter.php';

/**
 * A class for individual bible translations
 *
 */
class BfoxTrans {
	public $id, $short_name, $long_name, $table, $installed;

	const default_id = 1;
	const option_enabled = 'bfox_enabled_trans';

	private static $meta = array(
		1 => array('WEB', 'World English Bible'),
		2 => array('HNV', 'Hebrew Names Version'),
		3 => array('KJV', 'King James Version'),
		4 => array('ASV', 'American Standard Version')
	);

	/**
	 * Constructor
	 *
	 * @param $id
	 * @param $quick When true, the SQL check for the table is not performed
	 * @return unknown_type
	 */
	public function __construct($id = 0, $quick = FALSE) {
		if (empty($id) || !isset(self::$meta[$id])) $id = self::default_id;

		$this->id = $id;
		list($this->short_name, $this->long_name) = self::$meta[$id];

		// Set the translation table if it exists
		$this->table = BFOX_BLOG_TABLE_PREFIX . "trans_{$this->short_name}_verses";
		if ($quick) $this->installed = FALSE;
		else $this->installed = BfoxUtility::does_table_exist($this->table);
	}

	public static function get_ids_by_short_name() {
		$arr = array();
		foreach (self::$meta as $id => $meta) $arr[$meta[0]] = $id;
		return $arr;
	}

	/**
	 * Get the verse content for some bible references
	 *
	 * @param string $ref_where SQL WHERE statement as returned from BfoxRefs::sql_where()
	 * @return string Formatted bible verse output
	 */
	public function get_verses($ref_where, BfoxVerseFormatter $formatter = NULL) {
		$verses = array();

		// We can only grab verses if the verse data exists
		if ($this->installed) {
			global $wpdb;
			$verses = $wpdb->get_results("SELECT unique_id, book_id, chapter_id, verse_id, verse FROM $this->table WHERE $ref_where");
		}

		if (!is_null($formatter)) return $formatter->format($verses);
		return $verses;
	}

	public function get_verses_in_books($ref_where, BfoxVerseFormatter $formatter = NULL) {
		$books = array();

		// We can only grab verses if the verse data exists
		if ($this->installed) {
			global $wpdb;
			$verses = $wpdb->get_results("SELECT unique_id, book_id, chapter_id, verse_id, verse FROM $this->table WHERE $ref_where");

			foreach ($verses as $verse) if ($verse->chapter_id) $books[$verse->book_id][$verse->chapter_id] []= $verse;
		}

		return $books;
	}

	/**
	 * Get the verse content for a sequence of chapters
	 *
	 * @param integer $book
	 * @param integer $chapter1
	 * @param integer $chapter2
	 * @param string $visible SQL WHERE statement to determine which scriptures are visible (ex. as returned from BfoxRefs::sql_where())
	 * @return string Formatted bible verse output
	 */
	public function get_chapter_verses($book, $chapter1, $chapter2, $visible, BfoxVerseFormatter $formatter = NULL) {
		$chapters = array();

		// We can only grab verses if the verse data exists
		if ($this->installed) {
			global $wpdb;
			$verses = (array) $wpdb->get_results($wpdb->prepare("
				SELECT unique_id, chapter_id, verse_id, verse, ($visible) as visible
				FROM $this->table
				WHERE book_id = %d AND chapter_id >= %d AND chapter_id <= %d",
				$book, $chapter1, $chapter2));

			foreach ($verses as $verse) $chapters[$verse->chapter_id] []= $verse;
		}

		if (!is_null($formatter)) {
			$formatter->only_visible = TRUE;
			return $formatter->format_cv($chapters);
		}
		return $chapters;
	}

	/**
	 * Returns all the enabled BfoxTrans in an array
	 *
	 * @return array of BfoxTrans
	 */
	public static function get_enabled($quick = TRUE) {
		$translations = array();
		$update = FALSE;

		foreach ((array) get_site_option(self::option_enabled) as $id) {
			$trans = new BfoxTrans($id, $quick);
			if ($quick || $trans->installed) $translations[$id] = $trans;
			else $update = TRUE;
		}
		if ($update) self::set_enabled($translations);

		return $translations;
	}

	public static function set_enabled($translations) {
		$ids = array_keys($translations);
		sort($ids);
		update_site_option(self::option_enabled, $ids);
	}

	/**
	 * Returns all the installed BfoxTrans in an array
	 *
	 * @return array of BfoxTrans
	 */
	public static function get_installed() {
		$translations = array();
		foreach (self::$meta as $id => $meta) {
			$trans = new BfoxTrans($id);
			if ($trans->installed) $translations[$id] = $trans;
		}
		return $translations;
	}

	/**
	 * Return verse content for the given bible refs with minimum formatting
	 *
	 * @param BfoxRefs $refs
	 * @return string
	 */
	public function get_verse_content(BfoxRefs $refs) {
		// Get the verse data from the bible translation
		$formatter = new BfoxVerseFormatter();
		return $this->get_verses($refs->sql_where(), $formatter);
	}

	public function get_verse_content_foot(BfoxRefs $refs, $delete_footnotes = FALSE) {
		// TODO3: This is pretty hacky, if the shortcode regex ever changes, this regex has to change as well!

		// Get the verse content, and filter it using the <footnote> tags as if they were [footnote] shortcodes
		// The regex being used here should mirror the regex returned by get_shortcode_regex() and is being used similarly to do_shortcode(),
		//  the only difference being that we only need to look for <footnote> shortcodes (and using chevrons instead of brackets)
		if ($delete_footnotes) return preg_replace('/<(footnote)\b(.*?)(?:(\/))?>(?:(.+?)<\/footnote>)?/s', '', $this->get_verse_content($refs));
		else $content = preg_replace_callback('/(.?)<(footnote)\b(.*?)(?:(\/))?>(?:(.+?)<\/\2>)?(.?)/s', 'do_shortcode_tag', $this->get_verse_content($refs));
		return array($content, shortfoot_get_list());
	}

	/**
	 * Return verse content for the given bible refs formatted for email output
	 *
	 * @param BfoxRefs $refs
	 * @param BfoxTrans $trans
	 * @return string
	 */
	public function get_verse_content_email(BfoxRefs $refs) {
		// Pre formatting is for when we can't use CSS (ie. in an email)
		// We just replace the tags which would have been formatted by css with tags that don't need formatting
		// We also need to run the shortcode function to correctly output footnotes

		$mods = array(
			'<span class="bible_poetry_indent_2"></span>' => '<span style="margin-left: 20px"></span>',
			'<span class="bible_poetry_indent_1"></span>' => '',
			'<span class="bible_end_poetry"></span>' => "<br/>\n",
			'<span class="bible_end_p"></span>' => "<br/><br/>\n",
			'</footnote>' => '[/foot]',
			'<footnote>' => '[foot]'
		);

		return do_shortcode(str_replace(array_keys($mods), array_values($mods), $this->get_verse_content($refs)));
	}
}

?>