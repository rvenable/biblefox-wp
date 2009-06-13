<?php

define(BFOX_TRANS_DIR, dirname(__FILE__));
define(BFOX_TRANSLATION_DATA_DIR, BFOX_DATA_DIR . '/translations');

class BfoxTransInstaller {

	const dir = BFOX_TRANSLATION_DATA_DIR;

	public static function run($refresh = FALSE) {
		global $wpdb;
		$msgs = array();

		// If this is a full refresh, then start by dropping all the possible translation tables
		if ($refresh) {
			$possible = BfoxTrans::get_possible();
			$sql = array();
			foreach ($possible as $tran) $sql []= "DROP TABLE IF EXISTS $trans->table";
			$wpdb->query(implode('; ', $sql));
			$msgs []= 'Refresh: Dropped all translation tables';
		}

		$files = self::get_translation_files();
		$installed = BfoxTrans::get_installed();

		// Loop through all the translation files we have, and if not installed, install them
		foreach ($files as $id => $file) if (!isset($installed[$id])) {
			$trans = new BfoxTrans($id);
			self::create_translation_table($trans->table);
			self::load_usfx($trans, $file);
			$msgs []= "Installed Translation: $trans->id ($trans->long_name) from $file";
			$installed[$id] = $trans;
		}

		// Enable all the installed translations
		BfoxTrans::set_enabled($installed);

		return $msgs;
	}

	/**
	 * Returns an array of file names for the translations files in the translation directory
	 *
	 * @return array
	 */
	public static function get_translation_files() {
		$files = array();

		$translations_dir = opendir(self::dir);
		if ($translations_dir) {
			while (($file_name = readdir($translations_dir)) !== false) {
				if (substr($file_name, -9) == '-usfx.xml') {
					$trans_id = strtoupper(substr($file_name, 0, -9));
					if (BfoxTrans::is_valid_id($trans_id)) $files [$trans_id] = $file_name;
				}
			}
		}
		@closedir($translations_dir);

		return $files;
	}

	/**
	 * Creates the verse data table
	 *
	 */
	private static function create_translation_table(BfoxTrans $trans) {
		BfoxUtility::create_table($trans->table, "
			unique_id int unsigned NOT NULL,
			book_id int unsigned NOT NULL,
			chapter_id int unsigned NOT NULL,
			verse_id int unsigned NOT NULL,
			verse text NOT NULL,
			PRIMARY KEY  (unique_id)");
	}

	/**
	 * Updates the verse data for a given verse in the given translation table
	 *
	 * @param string $table_name
	 * @param BibleVerse $verse
	 * @param string $verse_text
	 */
	public static function update_verse_text($table_name, BibleVerse $verse, $verse_text) {
		global $wpdb;

		$sql = $wpdb->prepare("
			REPLACE INTO $table_name (unique_id, book_id, chapter_id, verse_id, verse)
			VALUES (%d, %d, %d, %d, %s)",
			$verse->unique_id,
			$verse->book,
			$verse->chapter,
			$verse->verse,
			$verse_text);

		$wpdb->query($sql);
	}

	/**
	 * Add verse data from a USFX file
	 *
	 * @param BfoxTrans $trans
	 * @param string $file_name File name for the USFX file
	 */
	private static function load_usfx(BfoxTrans $trans, $file_name) {
		require_once('usfx.php');
		$usfx = new BfoxUsfx();
		$usfx->set_table_name($trans->table);
		$usfx->read_file(self::dir . '/' . $file_name);
	}
}

?>