<?php

define(BFOX_TRANS_DIR, dirname(__FILE__));

// TODO3: Remove BOOK COUNTS TABLE
define('BFOX_TRANSLATIONS_TABLE', BFOX_BASE_TABLE_PREFIX . 'translations');
define('BFOX_BOOK_COUNTS_TABLE', BFOX_BASE_TABLE_PREFIX . 'book_counts');
define('BFOX_TRANSLATION_INDEX_TABLE', BFOX_BASE_TABLE_PREFIX . 'trans_index');
define(BFOX_TRANSLATION_DATA_DIR, BFOX_DATA_DIR . '/translations');

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
 * and thereby can be succesfully indexed (see Translation::get_index_words()).
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

	/**
	 * Construct an instance using an stdClass object (as returned by querying the Translation::translation_table DB table)
	 *
	 * @param stdClass $translation
	 */
	function __construct(stdClass $translation) {
		$this->id = (int) $translation->id;
		$this->short_name = (string) $translation->short_name;
		$this->long_name = (string) $translation->long_name;
		$this->is_default = (bool) $translation->is_default;
		$this->is_enabled = (bool) $translation->is_enabled;

		// Set the translation table if it exists
		$table = Translations::get_translation_table_name($this->id);
		if (BfoxUtility::does_table_exist($table)) $this->table = $table;
		else $this->table = '';
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
}

/**
 * Manages all translations
 *
 */
class Translations
{
	const page = 'bfox-translations';
	const min_user_level = 10;
	const translation_table = BFOX_TRANSLATIONS_TABLE;
	const book_counts_table = BFOX_BOOK_COUNTS_TABLE;
	const index_table = BFOX_TRANSLATION_INDEX_TABLE;
	const dir = BFOX_TRANSLATION_DATA_DIR;

	public static function init()
	{
		// Set the global translation (using the default translation)
		self::set_global_translations();
		add_action('admin_menu', 'Translations::add_admin_menu');
	}

	public static function add_admin_menu()
	{
		// These menu pages are only for the site admin
		if (is_site_admin())
		{
			// Add the translation page to the WPMU admin menu along with the corresponding load action
			add_submenu_page('wpmu-admin.php', 'Manage Translations', 'Translations', Translations::min_user_level, Translations::page, array('Translations', 'manage_page'));
			add_action('load-' . get_plugin_page_hookname(Translations::page, 'wpmu-admin.php'), array('Translations', 'manage_page_load'));
		}
	}

	/**
	 * Creates the DB table for the translations
	 *
	 */
	public static function create_tables()
	{
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		$sql = "
		CREATE TABLE IF NOT EXISTS " . self::translation_table . " (
			id int unsigned NOT NULL auto_increment,
			short_name varchar(8) NOT NULL default '',
			long_name varchar(128) NOT NULL default '',
			is_default boolean NOT NULL default 0,
			is_enabled boolean NOT NULL default 0,
			PRIMARY KEY  (id)
		);
		CREATE TABLE " . self::book_counts_table . " (
			trans_id int,
			book_id int,
			chapter_id int,
			value int
		);
		";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Returns all the data from the translation table
	 *
	 * @param bool $get_disabled Whether we should include disabled translations in the results
	 * @return unknown
	 */
	public static function get_translations($get_disabled = FALSE)
	{
		global $wpdb;
		if (!$get_disabled) $where = 'WHERE is_enabled = 1';
		$translations = (array) $wpdb->get_results("SELECT * FROM " . self::translation_table . " $where ORDER BY short_name, long_name");

		foreach ($translations as &$translation) $translation = new Translation($translation);

		return $translations;
	}

	/**
	 * Returns the translation data for one particular translation
	 *
	 * @param int $trans_id
	 * @return Translation
	 */
	public static function get_translation($trans_id)
	{
		global $wpdb;
		return new Translation((object) $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::translation_table . " WHERE id = %d", $trans_id)));
	}

	/**
	 * Returns an array of file names for the translations files in the translation directory
	 *
	 * @return unknown
	 */
	private static function get_translation_files()
	{
		$files = array();

		$translations_dir = opendir(Translations::dir);
		if ($translations_dir)
		{
			while (($file_name = readdir($translations_dir)) !== false)
			{
				if (substr($file_name, -4) == '.xml')
					$files[] = "$file_name";
			}
		}
		@closedir($translations_dir);

		return $files;
	}

	/**
	 * Returns the translation table name for a given translation id
	 *
	 * @param integer $trans_id
	 * @return string
	 */
	public static function get_translation_table_name($trans_id)
	{
		return BFOX_BASE_TABLE_PREFIX . "trans{$trans_id}_verses";
	}

	/**
	 * Creates the verse data table
	 *
	 */
	private static function create_translation_table($table_name)
	{
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		global $wpdb;
		$sql = "
		CREATE TABLE $table_name (
			unique_id int unsigned NOT NULL,
			book_id int unsigned NOT NULL,
			chapter_id int unsigned NOT NULL,
			verse_id int unsigned NOT NULL,
			verse text NOT NULL,
			PRIMARY KEY  (unique_id)
		);
		";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Create the translation index table
	 *
	 */
	public static function create_translation_index_table()
	{
		// TODO3: This function should not be called by admin tools and be private

		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		// Creates a FULLTEXT index on the verse data
		$sql = "
		CREATE TABLE " . self::index_table . " (
			unique_id MEDIUMINT UNSIGNED NOT NULL,
			book_id TINYINT UNSIGNED NOT NULL,
			trans_id SMALLINT UNSIGNED NOT NULL,
			index_text TEXT NOT NULL,
			FULLTEXT (index_text),
			INDEX (unique_id)
		);
		";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Refresh the index data for a given translation
	 *
	 * @param Translation $trans
	 * @param string $group
	 */
	public static function refresh_translation_index(Translation $trans, $group = 'protest')
	{
		// TODO3: This function should not be called by admin tools and be private

		global $wpdb;

		// Delete all the old index data for this translation
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::index_table . " WHERE trans_id = %d", $trans->id));

		// Add the new index data, one book at a time
		$books = range(BibleGroupPassage::get_first_book($group), BibleGroupPassage::get_last_book($group));
		foreach ($books as $book)
		{
			// Get all the verses to index for this book (we don't index chapter 0 or verse 0)
			$verses = $wpdb->get_results($wpdb->prepare("SELECT unique_id, book_id, verse FROM $trans->table WHERE book_id = %d AND chapter_id != 0 AND verse_id != 0", $book));

			// If we have verses for this book, insert their index text into the index table
			if (!empty($verses))
			{
				$values = array();
				foreach ($verses as $verse)
				{
					$values []= $wpdb->prepare('(%d, %d, %d, %s)', $verse->unique_id, $verse->book_id, $trans->id, implode(' ', self::get_index_words($verse->verse)));
				}
				$wpdb->query("INSERT INTO " . self::index_table . " (unique_id, book_id, trans_id, index_text) VALUES " . implode(', ', $values));
			}
		}
	}

	/**
	 * Repairs a full text index
	 *
	 * @param string $table_name
	 */
	private static function repair_index($table_name)
	{
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
		if (empty($bfox_ft_stopwords)) include_once BFOX_TRANS_DIR . '/stopwords.php';

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
	 * Updates the verse data for a given verse in the given translation table
	 *
	 * @param string $table_name
	 * @param BibleVerse $verse
	 * @param string $verse_text
	 */
	public static function update_verse_text($table_name, BibleVerse $verse, $verse_text)
	{
		global $wpdb;
		$sql = $wpdb->prepare("REPLACE INTO $table_name (unique_id, book_id, chapter_id, verse_id, verse)
							  VALUES (%d, %d, %d, %d, %s)",
							  $verse->unique_id,
							  $verse->book,
							  $verse->chapter,
							  $verse->verse,
							  $verse_text
							  );
		$wpdb->query($sql);
	}

	/**
	 * Modifies/Creates the info for a particular translation in the translations table
	 *
	 * @param object $trans
	 * @param int $id Optional translation ID
	 * @return unknown
	 */
	private static function edit_translation($trans, $id = NULL)
	{
		global $wpdb;

		if (!empty($id))
		{
			$wpdb->query($wpdb->prepare("UPDATE " . self::translation_table . " SET short_name = %s, long_name = %s, is_enabled = %d WHERE id = %d",
				$trans->short_name, $trans->long_name, $trans->is_enabled, $id));
		}
		else
		{
			$wpdb->query($wpdb->prepare("INSERT INTO " . self::translation_table . " SET short_name = %s, long_name = %s, is_enabled = %d",
				$trans->short_name, $trans->long_name, $trans->is_enabled));
			$id = $wpdb->insert_id;
		}

		// If a file was specified, we need to add verse data from that file
		if (!empty($trans->file_name))
		{
			$table_name = self::get_translation_table_name($id);

			// Drop the table if it already exists
			$wpdb->query("DROP TABLE IF EXISTS $table_name");

			// Create the table
			self::create_translation_table($table_name);

			// Add the new verse data
			self::load_usfx($table_name, $trans->file_name);

			// Update the book counts table
			self::update_book_counts($id, $table_name);
		}

		return $id;
	}

	/**
	 * Add verse data from a USFX file
	 *
	 * @param string $table_name
	 * @param string $file_name File name for the USFX file
	 */
	private static function load_usfx($table_name, $file_name)
	{
		require_once('usfx.php');
		$usfx = new BfoxUsfx();
		$usfx->set_table_name($table_name);
		$usfx->read_file(Translations::dir . '/' . $file_name);
	}

	/**
	 * Updates the book counts table for the given translation id, using the given translation table
	 *
	 * @param integer $trans_id
	 * @param string $table_name
	 */
	private static function update_book_counts($trans_id, $table_name)
	{
		global $wpdb;

		// Add the chapter counts
		$wpdb->query($wpdb->prepare('
			REPLACE INTO ' . self::book_counts_table . '
			(trans_id, book_id, chapter_id, value)
			SELECT %d, book_id, 0, MAX(chapter_id)
			FROM ' . $table_name . '
			GROUP BY book_id',
			$trans_id),
			ARRAY_N
		);

		// Add the verse counts
		$wpdb->query($wpdb->prepare('
			REPLACE INTO ' . self::book_counts_table . '
			(trans_id, book_id, chapter_id, value)
			SELECT %d, book_id, chapter_id, MAX(verse_id)
			FROM ' . $table_name . '
			WHERE chapter_id > 0
			GROUP BY book_id, chapter_id',
			$trans_id),
			ARRAY_N
		);
	}

	/**
	 * Deletes a translation from the translation table
	 *
	 * @param unknown_type $trans_id
	 */
	private static function delete_translation($trans_id)
	{
		global $wpdb;

		// Drop the verse data table if it exists
		$table_name = self::get_translation_table_name($trans_id);
		if (BfoxUtility::does_table_exist($table_name)) $wpdb->query("DROP TABLE $table_name");

		// Delete the translation data from the translation table
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::translation_table . " WHERE id = %d", $trans_id));

		// Delete the translation data from the book counts table
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::book_counts_table . " WHERE trans_id = %d", $trans_id));
	}

	/**
	 * Returns the default bible translation id
	 *
	 * @return unknown
	 */
	private static function get_default_id()
	{
		global $wpdb;
		return $wpdb->get_var("SELECT id FROM " . self::translation_table . " WHERE is_default = 1 LIMIT 1");
	}

	/**
	 * Returns the user's default bible translation id
	 *
	 * @return unknown
	 */
	private static function get_user_default_id()
	{
		// TODO2: User should have his own default translation
		return self::get_default_id();
	}

	/**
	 * Outputs an html select input with a list of translations
	 *
	 * @param unknown_type $select_id
	 */
	public static function output_select($select_id = NULL, $use_short = FALSE)
	{
		// Get the list of enabled translations
		$translations = self::get_translations();

		?>
		<select name="<?php echo BfoxQuery::var_translation ?>">
		<?php foreach ($translations as $translation): ?>
			<option value="<?php echo $translation->id ?>" <?php if ($translation->id == $select_id) echo 'selected' ?>><?php echo ($use_short) ? $translation->short_name : $translation->long_name; ?></option>
		<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Called before loading the manage translations admin page
	 *
	 * Performs all the user's translation edit requests before loading the page
	 *
	 */
	public function manage_page_load()
	{
		$bfox_page_url = 'admin.php?page=' . self::page;

		$action = $_POST['action'];
		if ( isset($_POST['deleteit']) && isset($_POST['delete']) )
			$action = 'bulk-delete';

		switch($action)
		{
		case 'addtrans':

			check_admin_referer('add-translation');

			if ( !current_user_can(self::min_user_level))
				wp_die(__('Cheatin&#8217; uh?'));

			$trans = array();
			$trans['short_name'] = stripslashes($_POST['short_name']);
			$trans['long_name'] = stripslashes($_POST['long_name']);
			$trans['is_enabled'] = (int) $_POST['is_enabled'];
			$trans['file_name'] = stripslashes($_POST['trans_file']);
			$trans_id = self::edit_translation((object) $trans);

			wp_redirect(add_query_arg(array('action' => 'edit', 'trans_id' => $trans_id, 'message' => 1), $bfox_page_url));

			exit;
		break;

		case 'bulk-delete':
			check_admin_referer('bulk-translations');

			if ( !current_user_can(self::min_user_level) )
				wp_die( __('You are not allowed to delete translations.') );

			foreach ((array) $_POST['delete'] as $trans_id)
				self::delete_translation($trans_id);

			wp_redirect(add_query_arg('message', 2, $bfox_page_url));

			exit;
		break;

		case 'editedtrans':
			$trans_id = (int) $_POST['trans_id'];
			check_admin_referer('update-translation-' . $trans_id);

			if ( !current_user_can(self::min_user_level) )
				wp_die(__('Cheatin&#8217; uh?'));

			$trans = array();
			$trans['short_name'] = stripslashes($_POST['short_name']);
			$trans['long_name'] = stripslashes($_POST['long_name']);
			$trans['is_enabled'] = (int) $_POST['is_enabled'];
			$trans['file_name'] = stripslashes($_POST['trans_file']);
			$trans_id = self::edit_translation((object) $trans, $trans_id);

			wp_redirect(add_query_arg(array('action' => 'edit', 'trans_id' => $trans_id, 'message' => 3), $bfox_page_url));

			exit;
		break;
		}
	}

	/**
	 * Outputs the translation management admin page
	 *
	 */
	public function manage_page()
	{
		$messages[1] = __('Translation added.');
		$messages[2] = __('Translation deleted.');
		$messages[3] = __('Translation updated.');
		$messages[4] = __('Translation not added.');
		$messages[5] = __('Translation not updated.');

		if (isset($_GET['message']) && ($msg = (int) $_GET['message'])): ?>
			<div id="message" class="updated fade"><p><?php echo $messages[$msg]; ?></p></div>
			<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
		endif;

		switch($_GET['action'])
		{
		case 'edit':
			$trans_id = (int) $_GET['trans_id'];
			include('edit-translation-form.php');
			break;

		case 'validate':
			$file = (string) $_GET['file'];
			bfox_usfx_menu($file);
			break;

		default:
			include('manage-translations.php');
			break;
		}
	}

	/**
	 * Sets the global translation to the default translation ID
	 *
	 */
	public static function set_global_translations()
	{
		global $bfox_trans;
		$bfox_trans = self::get_translation(self::get_default_id());
	}
}

add_action('init', 'Translations::init');

?>