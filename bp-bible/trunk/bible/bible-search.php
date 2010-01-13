<?php

define(BFOX_TRANSLATION_INDEX_TABLE, BP_BIBLE_BASE_TABLE_PREFIX . 'trans_index');

/*
 * FULLTEXT Indexing Workaround:
 *
 * For bible searching, this plugin uses MySQL FULLTEXT indexes. However, the FULLTEXT
 * indexer exludes words that are too short or are considered 'stop words'. To counteract
 * this, we add a prefix to those words so that they are long enough and are not stop words,
 * and therefore will be indexed.
 */

/*
 * BFOX_FT_INDEX_PREFIX is added to the beginning of words which don't fit the MySQL
 * FULLTEXT indexing criteria of minimum length and matching a stopword.
 * Thus, by adding this prefix to the word, the word passes the indexing criteria,
 * and thereby can be succesfully indexed (see BibleSearch::get_index_words()).
 *
 * BFOX_FT_INDEX_PREFIX must be long enough to increase any word to at least the minimum
 * string length. For instance, if the min length is 4 (BFOX_FT_MIN_WORD_LEN should
 * reflect this), BFOX_FT_INDEX_PREFIX could be length 3, so that even 1 letter words
 * are prefixed with 3 additional letters to reach the minimum length of 4 letters.
 *
 * The characters in BFOX_FT_INDEX_PREFIX must be unique enough so that when prefixing
 * any word, the new word will not be a MySql stop word.
 *
 * This can be over-ridden in the wp-config file for different server setups.
 * Remember to rebuild the translation indexes if modifying this value.
 */
if (!defined('BFOX_FT_INDEX_PREFIX'))
	define('BFOX_FT_INDEX_PREFIX', 'bfx');

/*
 * Define the minimum word length for full text searches to be 4 by default.
 * Any word shorter than length BFOX_FT_MIN_WORD_LEN will be prefixed with
 * BFOX_FT_INDEX_PREFIX so that they will be long enough to be indexed by
 * MySQL's FULLTEXT search.
 *
 * This can be over-ridden in the wp-config file for different server setups.
 * Remember to rebuild the translation indexes if modifying this value.
 */
if (!defined('BFOX_FT_MIN_WORD_LEN'))
	define('BFOX_FT_MIN_WORD_LEN', 4);

class BibleSearch {

	const index_table = BFOX_TRANSLATION_INDEX_TABLE;
	const results_per_page = 40;

	public $text = '';
	public $description = '';
	public $last_search_time = 0;
	public $found_rows = 0;
	public $page = 1;
	public $ref_str = '';
	private $words = '';
	private $index_words = '';
	private $trans_where = '';
	private $ref_where = '';
	private $limit_str = '';
	private $limit = 0;

	/**
	 * The bible translation to display the verses in
	 *
	 * @var BfoxTrans
	 */
	private $display_translation;

	public function __construct($text, $ref_str = '', $page = 0, $trans = '', $group = '') {
		$this->set_text($text);

		if (!empty($trans)) bp_bible_set_trans_id($trans);
		$trans_ids = BfoxTrans::get_ids_by_short_name();
		$this->display_translation = new BfoxTrans($trans_ids[bp_bible_get_trans_id()]);

		if (empty($page)) $page = 1;
		$this->page = $page;
		$this->set_limit(self::results_per_page, self::results_per_page * ($page - 1));

		if (!empty($group)) $this->set_refs(new BibleGroupPassage($group));
		if (!empty($ref_str)) $this->set_refs(new BfoxRefs($ref_str));
	}

	public function set_text($text) {
		$text = strip_tags(trim($text));
		$this->text = $text;
		$this->description = "\"$text\"";

		// Parse the search text into words
		$this->words = str_word_count($text, 1);
		$this->index_words = self::get_index_words($text);
	}

	public function set_search_translation_id($trans_id) {
		$this->trans_where =  $wpdb->prepare('AND trans_id = %d', $trans_id);
	}

	public function set_refs(BfoxRefs $refs) {
		if ($refs->is_valid()) {
			$this->ref_str = $refs->get_string();
			$this->ref_where = 'AND ' . $refs->sql_where();
			$this->description .= ' in ' . $this->ref_str;
		}
	}

	public function set_limit($limit = self::results_per_page, $offset = 0) {
		global $wpdb;
		$this->limit = $limit;
		$this->limit_str = $wpdb->prepare('LIMIT %d, %d', $offset, $limit);
	}

	public function get_url() {
		// TODO3 (HACK): The strtolower is because bible groups need to be lowercase for some reason
		return self::search_url($this->text, strtolower($this->ref_str));
	}

	public static function search_url($search_text, $ref_str = '', $group_str = '') {
		global $bp;

		$url = $bp->root_domain . '/' . $bp->bible->slug . '/';
		if (!empty($search_text)) $url = add_query_arg('s', urlencode($search_text), $url);
		if (!empty($ref_str)) $url = add_query_arg('ref', urlencode($ref_str), $url);
		if (!empty($group_str)) $url = add_query_arg('group', urlencode($group_str), $url);

		return $url;
	}

	public function get_num_pages() {
		if (!empty($this->limit)) return ceil($this->found_rows / $this->limit);
		return 1;
	}

	/**
	 * Performs a boolean full text search
	 *
	 * @return unknown
	 */
	private function search($match)
	{
		global $wpdb;

		$start = microtime(TRUE);

		$verse_ids = $wpdb->get_results("
			SELECT SQL_CALC_FOUND_ROWS unique_id, $match as match_val
			FROM " . self::index_table . "
			WHERE $match $this->trans_where $this->ref_where
			GROUP BY unique_id
			ORDER BY unique_id ASC
			$this->limit_str");

		$end = microtime(TRUE);
		$this->last_search_time = $end - $start;
		$this->found_rows = $wpdb->get_var("SELECT FOUND_ROWS()");

		$verses = array();
		foreach ($verse_ids as $verse) $verses[$verse->unique_id] = $verse->match_val;

		$this->set_page_links();

		return $verses;
	}

	private function book_counts($match)
	{
		global $wpdb;
		$counts = $wpdb->get_results("
			SELECT book_id, COUNT(DISTINCT unique_id) AS count
			FROM " . self::index_table . "
			WHERE $match $this->trans_where
			GROUP BY book_id");

		$book_counts = array();
		foreach ($counts as $count) $book_counts[$count->book_id] = $count->count;

		return $book_counts;
	}

	public function search_boolean()
	{
		global $wpdb;
		return $this->search($wpdb->prepare('MATCH(index_text) AGAINST(%s IN BOOLEAN MODE)', '+' . implode('* +', $this->index_words) . '*'));
	}

	/**
	 * Performs a boolean full text search, but returns results as a list of verse counts per book
	 *
	 * @param string $text
	 * @return array Book counts
	 */
	function boolean_book_counts()
	{
		global $wpdb;
		return $this->book_counts($wpdb->prepare('MATCH(index_text) AGAINST(%s IN BOOLEAN MODE)', '+' . implode('* +', $this->index_words) . '*'));
	}

	private function output_group_counts($group, $counts)
	{
		$count = 0;
		$content = '';
		foreach (BibleMeta::$book_groups[$group] as $child)
		{
			$child_count = 0;
			$child_content = '';

			if (isset(BibleMeta::$book_groups[$child])) list($child_count, $child_content) = $this->output_group_counts($child, $counts);
			else if (isset($counts[$child]))
			{
				$child_count = $counts[$child];
				$ref_str = BibleMeta::get_book_name($child);

				$child_content = "<a href='" .
					self::search_url($this->text, $ref_str) .
					"' title='Search for \"$this->text\" in $ref_str'>$ref_str<span class='book_count'>$child_count</span></a>";
			}

			if (0 < $child_count)
			{
				$count += $child_count;
				$content .= "<li>$child_content</li>";
			}
		}

		$ref_str = BibleMeta::get_book_name($group);

		return array($count,
			"<a href='" .
			self::search_url($this->text, '', $group) .
			"' title='Search for \"$this->text\" in $ref_str'>$ref_str<span class='book_count'>$count</span></a>
			<ul>
				$content
			</ul>");
	}

	public function output_verse_map($book_counts, $group = 'protest')
	{
		list($count, $map) = $this->output_group_counts($group, $book_counts);

		echo $map;
	}

	private function set_page_links() {
		$this->pag_links = paginate_links( array(
			'base' => $this->get_url() . '%_%',
			'format' => '&page=%#%',
			'total' => ceil( (int) $this->found_rows / self::results_per_page ),
			'current' => $this->page,
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'mid_size' => 1
		));

		$this->from_num = intval( ( $this->page - 1 ) * self::results_per_page ) + 1;
		$this->to_num = ( $this->from_num + ( self::results_per_page - 1 ) > $this->found_rows ) ? $this->found_rows : $this->from_num + ( self::results_per_page - 1);
	}

	public function page_links() {
		return $this->pag_links;
	}

	public function output_page_num_description() {
		echo sprintf( __( 'Viewing verse %d to %d (of %d)', 'bp-bible' ), $this->from_num, $this->to_num, $this->found_rows );
	}

	public function trans_select_options() {
		$content = '';
		$curr_value = $this->display_translation->short_name;
		foreach (BfoxIframe::$translations['biblefox'] as $translation) {
			list($value, $label) = $translation;
			$selected = ($value == $curr_value) ? ' selected' : '';
			$content .= "<option value='$value'$selected>$label</option>";
		}
		return $content;
	}

	/**
	 * Creates an output string with a table row for each verse in the $verses data
	 *
	 * @param array $verses results from get_results() select statement with verse data
	 * @param array $words the list of words to highlight as having been used in the search
	 * @return string
	 */
	function chapter_content($verses)
	{
		$count = count($verses);
		if (0 < $count)
		{
			global $wpdb;

			// Get the verse data for these verses (from the global bible translation)
			$queries = array();
			foreach ($verses as $unique_id => $match) $queries []= $wpdb->prepare('unique_id = %d', $unique_id);
			$verses = $wpdb->get_results("SELECT * FROM {$this->display_translation->table} WHERE " . implode(' OR ', $queries));
			unset($queries);

			// Turn the words into keys
			$words = array_fill_keys($this->words, TRUE);

			$book = 0;
			$chapter = 0;
			$chapter_content = array();

			foreach ($verses as $verse)
			{
				if (($book != $verse->book_id) || ($chapter != $verse->chapter_id))
				{
					$book = $verse->book_id;
					$chapter = $verse->chapter_id;

					$book_name = BibleMeta::get_book_name($book);
					$chap_name = "$book_name $chapter";
					$chapter_content[$chap_name] = array();
				}

				// TODO3: Find a good way to display footnotes in search (until then, just get rid of them)
				$verse->verse = preg_replace('/<footnote>.*<\/footnote>/Ui', '', $verse->verse);

				// Get the words in the verse as an associative array (use '_' as a part of a word)
				$verse_words = str_word_count($verse->verse, 2, '_');

				// For each word in the verse that is also a search word, bold it
				foreach (array_reverse($verse_words, TRUE) as $pos => $verse_word)
					if ($words[strtolower($verse_word)])
						$verse->verse = substr_replace($verse->verse, "<strong>$verse_word</strong>", $pos, strlen($verse_word));

				$ref_str = "$chap_name:$verse->verse_id";
				$chapter_content[$chap_name][$ref_str] = $verse->verse;
			}
		}

		return $chapter_content;
	}

	/*
	 * SEARCH INDEX FUNCTIONS
	 */

	/**
	 * Refresh the index data for a given translation
	 *
	 * @param BfoxTrans $trans
	 * @param string $group
	 */
	private static function refresh_translation_index(BfoxTrans $trans, $group = 'protest') {
		global $wpdb;

		// Delete all the old index data for this translation
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::index_table . " WHERE trans_id = %d", $trans->id));

		// Add the new index data, one book at a time
		$books = range(BibleGroupPassage::get_first_book($group), BibleGroupPassage::get_last_book($group));
		foreach ($books as $book) {
			// Get all the verses to index for this book (we don't index chapter 0 or verse 0)
			$verses = $wpdb->get_results($wpdb->prepare("SELECT unique_id, book_id, verse FROM $trans->table WHERE book_id = %d AND chapter_id != 0 AND verse_id != 0", $book));

			// If we have verses for this book, insert their index text into the index table
			if (!empty($verses)) {
				$values = array();
				foreach ($verses as $verse) $values []= $wpdb->prepare('(%d, %d, %d, %s)', $verse->unique_id, $verse->book_id, $trans->id, implode(' ', self::get_index_words($verse->verse)));
				$wpdb->query("INSERT INTO " . self::index_table . " (unique_id, book_id, trans_id, index_text) VALUES " . implode(', ', $values));
			}
		}
	}

	/**
	 * Repairs a full text index
	 *
	 * @param string $table_name
	 */
	private static function repair_index($table_name) {
		global $wpdb;
		$wpdb->query("REPAIR TABLE $table_name QUICK");
	}

	/**
	 * Returns the words needed for indexing the given text string
	 *
	 * @param string $text
	 * @return array of strings
	 */
	public static function get_index_words($text) {

		/*
		 * Define the list of MySQL FULLTEXT stop words to ignore. Any of these words
		 * will be prefixed with BFOX_FT_INDEX_PREFIX so that MySQL won't detect them
		 * as stop words and they will be successfully indexed.
		 *
		 * This can be over-ridden in the wp-config file for different server setups.
		 * Remember to rebuild the translation indexes if modifying this value.
		 */
		global $bfox_ft_stopwords;
		if (empty($bfox_ft_stopwords)) include_once BFOX_BIBLE_DIR . '/stopwords.php';

		// Strip out HTML tags, lowercase it, and parse into words
		$words = str_word_count(strtolower(strip_tags($text)), 1);

		// Check each word to see if it is below the FULLTEXT min word length, or if it is a FULLTEXT stopword
		// If so, we need to prefix it with some characters so that it doesn't get ignored by MySQL
		foreach ($words as &$word) {
			if ((strlen($word) < BFOX_FT_MIN_WORD_LEN) || isset($bfox_ft_stopwords[$word]))
				$word = BFOX_FT_INDEX_PREFIX . $word;
		}

		return $words;
	}

	/**
	 * Create the translation index table
	 *
	 */
	public static function refresh_search_index() {
		global $wpdb;
		$wpdb->query('DROP TABLE IF EXISTS ' . self::index_table);

		BfoxUtility::create_table(self::index_table, "
			unique_id MEDIUMINT UNSIGNED NOT NULL,
			book_id TINYINT UNSIGNED NOT NULL,
			trans_id SMALLINT UNSIGNED NOT NULL,
			index_text TEXT NOT NULL,
			FULLTEXT (index_text),
			INDEX (unique_id)");

		$msg = "Dropped and recreated the index table.<br/>";

		// Loop through each enabled bible translation and refresh their index data
		$translations = BfoxTrans::get_enabled();
		foreach ($translations as $translation) {
			$msg .= "Refreshing $translation->long_name (ID: $translation->id)...<br/>";
			self::refresh_translation_index($translation);
		}
		$msg .= 'Finished<br/>';
		return $status;
	}
}

function bp_bible_search_list() {
	locate_template( array( '/bible/search-list.php' ), true );
}

?>