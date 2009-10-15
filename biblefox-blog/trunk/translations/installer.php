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
			$installed = BfoxTrans::get_installed();
			$msgs []= 'Refresh: Dropping all installed translation tables';
			foreach ($installed as $trans) {
				$wpdb->query("DROP TABLE IF EXISTS $trans->table");
				$msgs []= "Dropped $trans->table";
			}
		}

		// Get the translation files
		$files = array();
		$trans_ids = BfoxTrans::get_ids_by_short_name();
		$translations_dir = opendir(self::dir);
		if ($translations_dir) {
			while (($file_name = readdir($translations_dir)) !== false) {
				if (substr($file_name, -9) == '-usfx.xml') {
					$trans_name = strtoupper(substr($file_name, 0, -9));
					if (isset($trans_ids[$trans_name])) {
						$files[$trans_ids[$trans_name]] = $file_name;
						$msgs []= "Found translations file: $file_name";
					}
				}
			}
		}
		@closedir($translations_dir);

		$installed = BfoxTrans::get_installed();
		$done_installing = FALSE;

		// Loop through all the translation files we have, and if not installed, install them
		foreach ($files as $id => $file) if (!isset($installed[$id])) {
			if (!$done_installing) {
				$trans = new BfoxTrans($id);
				self::create_translation_table($trans);
				self::load_usfx($trans, $file);
				$msgs []= "Installed: $file ($trans->short_name (ID: $trans->id) - $trans->long_name)";
				$installed[$id] = $trans;
				$done_installing = TRUE;
			}
			else $msgs []= "NOTE: Still need to install: $file";
		}
		else $msgs []= "Skipped: $file (already installed)";

		// Enable all the installed translations
		BfoxTrans::set_enabled($installed);

		return $msgs;
	}

	/**
	 * Creates the verse data table
	 *
	 */
	private static function create_translation_table(BfoxTrans $trans) {
		BfoxUtility::create_table($trans->table, "
			unique_id MEDIUMINT UNSIGNED NOT NULL,
			book_id TINYINT UNSIGNED NOT NULL,
			chapter_id TINYINT UNSIGNED NOT NULL,
			verse_id TINYINT UNSIGNED NOT NULL,
			verse TEXT NOT NULL,
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