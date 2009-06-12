<?php

define(BFOX_TRANS_DIR, dirname(__FILE__));

require_once BFOX_TRANS_DIR . '/formatter.php';

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