<?php

define(BFOX_TRANS_DIR, dirname(__FILE__));

// TODO3: Remove BOOK COUNTS TABLE
define('BFOX_TRANSLATIONS_TABLE', BFOX_BASE_TABLE_PREFIX . 'translations');
define('BFOX_BOOK_COUNTS_TABLE', BFOX_BASE_TABLE_PREFIX . 'book_counts');
define(BFOX_TRANSLATION_DATA_DIR, BFOX_DATA_DIR . '/translations');

/**
 * Manages all translations
 *
 */
class BfoxTransInstaller
{
	const translation_table = BFOX_TRANSLATIONS_TABLE;
	const book_counts_table = BFOX_BOOK_COUNTS_TABLE;
	const dir = BFOX_TRANSLATION_DATA_DIR;

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
		";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Returns an array of file names for the translations files in the translation directory
	 *
	 * @return unknown
	 */
	public static function get_translation_files()
	{
		$files = array();

		$translations_dir = opendir(self::dir);
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
			$table_name = Translation::get_translation_table_name($id);

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
		$usfx->read_file(self::dir . '/' . $file_name);
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
		$table_name = Translation::get_translation_table_name($trans_id);
		if (BfoxUtility::does_table_exist($table_name)) $wpdb->query("DROP TABLE $table_name");

		// Delete the translation data from the translation table
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::translation_table . " WHERE id = %d", $trans_id));

		// Delete the translation data from the book counts table
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::book_counts_table . " WHERE trans_id = %d", $trans_id));
	}
}

?>