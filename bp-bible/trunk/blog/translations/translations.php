<?php

define(BFOX_TRANS_DIR, dirname(__FILE__));

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
		$this->table = BFOX_BASE_TABLE_PREFIX . "trans_{$this->short_name}_verses";
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