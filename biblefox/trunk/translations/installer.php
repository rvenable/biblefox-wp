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
	const page = 'bfox-translations';
	const min_user_level = 10;
	const translation_table = BFOX_TRANSLATIONS_TABLE;
	const book_counts_table = BFOX_BOOK_COUNTS_TABLE;
	const dir = BFOX_TRANSLATION_DATA_DIR;

	public static function init()
	{
		// Set the global translation (using the default translation)
		self::set_global_translations();
		add_action('admin_menu', 'BfoxTransInstaller::add_admin_menu');
	}

	public static function add_admin_menu()
	{
		// These menu pages are only for the site admin
		if (is_site_admin())
		{
			// Add the translation page to the WPMU admin menu along with the corresponding load action
			add_submenu_page('wpmu-admin.php', 'Manage Translations', 'Translations', BfoxTransInstaller::min_user_level, BfoxTransInstaller::page, array('BfoxTransInstaller', 'manage_page'));
			add_action('load-' . get_plugin_page_hookname(BfoxTransInstaller::page, 'wpmu-admin.php'), array('BfoxTransInstaller', 'manage_page_load'));
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
	public static function get_translations($get_disabled = FALSE) {
		$translations = array();
		foreach (Translation::$meta as $id => $meta) $translations []= new Translation($id);
		return $translations;
	}

	/**
	 * Returns an array of file names for the translations files in the translation directory
	 *
	 * @return unknown
	 */
	private static function get_translation_files()
	{
		$files = array();

		$translations_dir = opendir(BfoxTransInstaller::dir);
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
		$usfx->read_file(BfoxTransInstaller::dir . '/' . $file_name);
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
		$bfox_trans = new Translation();
	}
}

add_action('init', 'BfoxTransInstaller::init');

?>