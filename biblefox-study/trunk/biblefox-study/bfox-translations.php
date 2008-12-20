<?php

	/**
	 * Manages all translations
	 *
	 */
	class TranslationManager
	{
		/**
		 * Constructor for TranslationManager
		 *
		 * @return TranslationManager
		 */
		function TranslationManager()
		{
			$this->bfox_translations = BFOX_TRANSLATIONS_TABLE;
		}

		/**
		 * Creates the DB table for the translations
		 *
		 */
		function create_tables()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

			$sql = "
			CREATE TABLE IF NOT EXISTS $this->bfox_translations (
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
		function get_translations($get_disabled = FALSE)
		{
			global $wpdb;
			if (!$get_disabled) $where = 'WHERE is_enabled = 1';
			return $wpdb->get_results("SELECT * FROM $this->bfox_translations $where ORDER BY short_name, long_name");
		}

		/**
		 * Returns the translation data for one particular translation
		 *
		 * @param int $trans_id
		 * @return unknown
		 */
		function get_translation($trans_id)
		{
			global $wpdb;
			return $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->bfox_translations WHERE id = %d", $trans_id));
		}

		/**
		 * Returns whether a specific translation is enabled
		 *
		 * @param unknown_type $trans_id
		 * @return unknown
		 */
		function is_enabled($trans_id)
		{
			global $wpdb;
			return (bool) $wpdb->get_var($wpdb->prepare("SELECT is_enabled FROM $this->bfox_translations WHERE id = %d", $trans_id));
		}

		/**
		 * Returns an array of file names for the translations files in the translation directory
		 *
		 * @return unknown
		 */
		function get_translation_files()
		{
			$files = array();

			$translations_dir = opendir(BFOX_TRANSLATIONS_DIR);
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
		 * Modifies/Creates the info for a particular translation in the translations table
		 *
		 * @param object $trans
		 * @param int $id Optional translation ID
		 * @return unknown
		 */
		function edit_translation($trans, $id = NULL)
		{
			global $wpdb;

			if (!empty($id))
			{
				$wpdb->query($wpdb->prepare("UPDATE $this->bfox_translations SET short_name = %s, long_name = %s, is_enabled = %d WHERE id = %d",
					$trans->short_name, $trans->long_name, $trans->is_enabled, $id));
			}
			else
			{
				$wpdb->query($wpdb->prepare("INSERT INTO $this->bfox_translations SET short_name = %s, long_name = %s, is_enabled = %d",
					$trans->short_name, $trans->long_name, $trans->is_enabled));
				$id = $wpdb->insert_id;
			}

			// If a file was specified, we need to add verse data from that file
			if (!empty($trans->file_name))
			{
				$translation = new Translation($id);

				// If the table exists, delete its old data
				// Otherwise create the table
				if ($translation->does_data_exist()) $translation->delete_verse_data();
				else $translation->create_table();

				// Add the new verse data
				$translation->add_verse_data($trans->file_name);
			}

			return $id;
		}

		/**
		 * Deletes a translation from the translation table
		 *
		 * @param unknown_type $trans_id
		 */
		function delete_translation($trans_id)
		{
			global $wpdb;

			// Drop the verse data table
			$translation = new Translation($trans_id);
			$translation->drop_table();

			// Delete the translation data from the translation table
			$wpdb->query($wpdb->prepare("DELETE FROM $this->bfox_translations WHERE id = %d", $trans_id));
		}

		/**
		 * Returns the default bible translation id
		 *
		 * @return unknown
		 */
		function get_default_id()
		{
			global $wpdb;
			return $wpdb->get_var("SELECT id FROM $this->bfox_translations WHERE is_default = 1 LIMIT 1");
		}

		/**
		 * Returns the user's default bible translation id
		 *
		 * @return unknown
		 */
		function get_user_default_id()
		{
			// TODO2: User should have his own default translation
			return $this->get_default_id();
		}

		/* TODO2:
		function search_all();
		*/
	}

	global $bfox_translations;
	$bfox_translations = new TranslationManager();

	/**
	 * A class for individual bible translations
	 *
	 */
	class Translation
	{
		var $id;
		var $table;

		function Translation($id = NULL, $use_disabled = FALSE)
		{
			global $bfox_translations;

			// If no id was specified, or if we are not using disabled IDs and the specified ID is disabled,
			// Then use the user's default ID
			if (empty($id) || (!$use_disabled && !$bfox_translations->is_enabled($id))) $id = $bfox_translations->get_user_default_id();

			$this->id = (int) $id;
			$this->table = BFOX_BASE_TABLE_PREFIX . "trans{$this->id}_verses";
		}

		/**
		 * Creates the verse data table, with or without a full text index
		 *
		 * @param boolean $use_index Whether a full text index should be used or not
		 */
		function create_table($use_index = FALSE)
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

			// Creates a FULLTEXT index on the verse data
			if ($use_index)
			{
				$index_cols = "index_text text NOT NULL,";
				$index = ", FULLTEXT (index_text)";
			}

			global $wpdb;
			$sql = "
			CREATE TABLE $this->table (
				unique_id int unsigned NOT NULL,
				book_id int unsigned NOT NULL,
				chapter_id int unsigned NOT NULL,
				verse_id int unsigned NOT NULL,
				verse text NOT NULL,
				$index_cols
				PRIMARY KEY  (unique_id)
				$index
			);
			";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		/**
		 * Returns whether the verse data table exists
		 *
		 * @return unknown
		 */
		function does_data_exist()
		{
			global $wpdb;
			if (!isset($this->data_exists)) $this->data_exists = ($wpdb->get_var("SHOW TABLES LIKE '$this->table'") == $this->table);
			return $this->data_exists;
		}

		/**
		 * Repairs a full text index
		 *
		 */
		function repair_index()
		{
			global $wpdb;
			$wpdb->query("REPAIR TABLE $this->table QUICK");
		}

		/**
		 * Add verse data from a USFX file
		 *
		 * @param unknown_type $file_name
		 */
		function add_verse_data($file_name)
		{
			bfox_usfx_install($this->table, BFOX_TRANSLATIONS_DIR . "/$file_name");
		}

		/**
		 * Delete all the data from the verse data table
		 *
		 */
		function delete_verse_data()
		{
			global $wpdb;
			$wpdb->query("DELETE FROM $this->table");
		}

		/**
		 * Drop the verse data table
		 *
		 */
		function drop_table()
		{
			// Only try to drop the table if it actually exists
			global $wpdb;
			if ($this->does_data_exist()) $wpdb->query("DROP TABLE $this->table");
		}

		/**
		 * Get the verse content for some bible references
		 *
		 * @param BibleRefs $refs
		 * @param unknown_type $id_text_begin
		 * @param unknown_type $id_text_end
		 * @return string Formatted bible verse output
		 */
		function get_verses(BibleRefs $refs)
		{
			// We can only grab verses if the verse data exists
			if ($this->does_data_exist())
			{
				global $wpdb;

				$ref_where = $refs->sql_where();
				$verses = $wpdb->get_results("SELECT verse_id, verse FROM $this->table WHERE $ref_where");

				$content = '';
				foreach ($verses as $verse)
				{
					$content .= '<span class="bible_verse">';
					if ($verse->verse_id != 0)
						$content .= '<em class="bible-verse-id">' . $verse->verse_id . '</em> ';
					$content .= $verse->verse;
					$content .= "</span>";
				}

				$content = bfox_special_syntax($content);
			}
			else $content = 'No verse data exists for this translation.';

			return $content;
		}

		/* TODO2:
		function search();
*/
	}

	// Set the global translation (using the default translation)
	global $bfox_trans;
	$bfox_trans = new Translation();

	/**
	 * Outputs an html select input with a list of translations
	 *
	 * @param unknown_type $select_id
	 */
	function bfox_translation_select($select_id = NULL)
	{
		global $bfox_translations;

		// Get the list of enabled translations
		$translations = $bfox_translations->get_translations();

		?>
		<select name="trans_id">
		<?php foreach ($translations as $translation): ?>
			<option value="<?php echo $translation->id ?>" <?php if ($translation->id == $select_id) echo 'selected' ?>><?php echo $translation->short_name ?></option>
		<?php endforeach; ?>
		</select>
		<?php
	}





	function bfox_get_installed_translations()
	{
		global $wpdb;
		$table_name = BFOX_TRANSLATIONS_TABLE;
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
		{
			echo "No translations installed!<br/>";
		}
		else
		{
			$select = "SELECT id, long_name, is_enabled, is_default FROM $table_name ORDER BY long_name";
			$translations = $wpdb->get_results($select);
			foreach ($translations as $translation)
			{
				echo "$translation->is_enabled ";
				echo "$translation->is_default ";
				echo "$translation->long_name ";
				$page = BFOX_TRANSLATION_SUBPAGE;
				$url = "admin.php?page=$page&amp;action=delete&amp;trans_id=$translation->id";
				echo "<a href='$url'>delete</a> ";
				$url = "admin.php?page=$page&amp;action=enable&amp;trans_id=$translation->id";
				echo "<a href='$url'>enable</a>";
				echo "<br/>";
			}
		}
	}

	function bfox_install_bft_file($file_name)
	{
		$file = BFOX_TRANSLATIONS_DIR . "/$file_name";
		$lines = file($file);
		$num_lines = count($lines);

		// Read the bft header
		// Each line of the header should be of format: key=value
		// The header ends with the string 'text='
		$index = 0;
		$header = array();
		while (($index < $num_lines) && (FALSE === stripos($lines[$index], 'text=')))
		{
			$list = explode('=', $lines[$index]);
			$key = trim($list[0]);
			$value = trim($list[1]);
			if ($key != '')
			{
				$header[$key] = $value;
			}
			$index++;
		}

		// We don't need the file data anymore
		unset($lines);

		if ($index < $num_lines)
		{
			$id = bfox_add_translation($header);
			$table_name = bfox_get_verses_table_name($id);
			$delimiter = $header['delim'];

			// Create the table
			bfox_create_trans_verses_table($table_name, $header['verse_size']);

			// Load the data file into the table using a LOAD DATA statement
			global $wpdb;
			$sql = $wpdb->prepare("LOAD DATA LOCAL INFILE %s
								  INTO TABLE $table_name
								  FIELDS TERMINATED BY %s
								  IGNORE %d LINES
								  (book_id, chapter_id, verse_id, verse)
								  SET unique_id = ((book_id * %d) + (chapter_id * %d) + (verse_id * %d))",
								  $file,
								  $delimiter,
								  $index + 1,
								  256 * 256,
								  256,
								  1);

			define(DIEONDBERROR, '');

			$result = $wpdb->query($sql);
		}
	}

	function bfox_translation_update_verse($table_name, BibleRefVector $vector, $verse_text)
	{
		global $wpdb;
		$sql = $wpdb->prepare("REPLACE INTO $table_name (unique_id, book_id, chapter_id, verse_id, verse)
							  VALUES (%d, %d, %d, %d, %s)",
							  $vector->value,
							  $vector->values['book'],
							  $vector->values['chapter'],
							  $vector->values['verse'],
							  $verse_text
							  );
		$wpdb->query($sql);
	}

	function bfox_delete_translation($trans_id)
	{
		global $wpdb;
		$wpdb->query($wpdb->prepare("DELETE FROM " . BFOX_TRANSLATIONS_TABLE . " WHERE id = %d", $trans_id));
		$wpdb->query("DROP TABLE " . bfox_get_verses_table_name($trans_id));
	}

	function bfox_enable_translation($trans_id)
	{
		global $wpdb;
		$wpdb->query($wpdb->prepare("UPDATE " . BFOX_TRANSLATIONS_TABLE . " SET is_enabled = !(is_enabled) WHERE id = %d", $trans_id));
	}

	function bfox_translations_action($action)
	{
		if ('install' == $action)
		{
			$file = trim($_GET['file']);
			if (substr($file, -4) == '.bft')
				bfox_install_bft_file($file);
			else if (substr($file, -4) == '.xml')
				bfox_install_usfx_file($file);
		}
		else if ('delete' == $action)
		{
			$trans_id = (int) $_GET['trans_id'];
			bfox_delete_translation($trans_id);
		}
		else if ('enable' == $action)
		{
			$trans_id = (int) $_GET['trans_id'];
			bfox_enable_translation($trans_id);
		}
	}

	function bfox_translations_page()
	{
		if (isset($_GET['action']))
			bfox_translations_action(trim($_GET['action']));
		echo '<div class="wrap">';
		echo '<h2>Manage Translations</h2>';
		bfox_get_installed_translations();
		echo '</div>';
		bfox_menu_install_translations();
	}

	function bfox_create_book_counts_table()
	{
		$data_table_name = BFOX_BOOK_COUNTS_TABLE;

		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		$sql = "CREATE TABLE $data_table_name (
		trans_id int,
		book_id int,
		chapter_id int,
		value int
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	function bfox_create_translation_data($trans_id)
	{
		global $wpdb;

		$data_table_name = BFOX_BOOK_COUNTS_TABLE;

		// If the translations table doesn't exist, create it
		if ($wpdb->get_var("SHOW TABLES LIKE '$data_table_name'") != $data_table_name)
			bfox_create_book_counts_table();

		$table_name = bfox_get_verses_table_name($trans_id);

		$wpdb->query($wpdb->prepare('REPLACE INTO ' . $data_table_name . '
									(trans_id, book_id, chapter_id, value)
									SELECT %d, book_id, 0, MAX(chapter_id)
									FROM ' . $table_name . '
									GROUP BY book_id',
									$trans_id,
									$book_id), ARRAY_N);

		$wpdb->query($wpdb->prepare('REPLACE INTO ' . $data_table_name . '
									(trans_id, book_id, chapter_id, value)
									SELECT %d, book_id, chapter_id, MAX(verse_id)
									FROM ' . $table_name . '
									WHERE chapter_id > 0
									GROUP BY book_id, chapter_id',
									$trans_id,
									$book_id), ARRAY_N);
	}

	/**
	 * Returns the number of chapters in a book for a given translation
	 *
	 * @param int $book_id
	 * @param int $trans_id
	 * @return int The number of chapters in a book
	 */
	function bfox_get_num_chapters($book_id, $trans_id)
	{
		global $wpdb;
		$num = $wpdb->get_var($wpdb->prepare('SELECT value
												FROM ' . BFOX_BOOK_COUNTS_TABLE . '
												WHERE trans_id = %d AND book_id = %d AND chapter_id = 0',
												$trans_id, $book_id));
		return $num;
	}

	function bfox_set_book_groups()
	{
		global $bfox_bible_groups;
		$groups = array();
		$groups['all'] = range(1, 81);
		$groups['bible'] = range(1, 66);
		$groups['old'] = range(1, 39);
		$groups['new'] = range(40, 66);
		$groups['torah'] = range(1, 5);
		$groups['history'] = range(6, 17);
		$groups['wisdom'] = range(18, 22);
		$groups['prophets'] = range(28, 39);
		$groups['major_prophets'] = range(23, 27);
		$groups['minor_prophets'] = range(28, 39);
		$groups['gospels'] = range(40, 43);
		$groups['acts'] = array(44);
		$groups['gospelacts'] = range(40, 44);
		$groups['paul'] = range(45, 57);
		$groups['epistles'] = range(58, 65);
		$groups['revelation'] = array(66);
		$groups['apocrypha'] = range(67, 81);

		$bfox_bible_groups = $groups;
	}

	function bfox_books_two_cols()
	{
		global $bfox_bible_groups, $bfox_links;
		$content .= '<div id="bible_toc">';

		$content .= '<div id="old_testament"><h3>Old Testament</h3>';
		$content .= '<ul>';
		foreach ($bfox_bible_groups['old'] as $book_id)
			$content .= '<li>' . $bfox_links->ref_link(bfox_get_book_name($book_id)) . '</li>';
		$content .= '</ul></div>';

		$content .= '<div id="new_testament"><h3>New Testament</h3>';
		$content .= '<ul>';
		foreach ($bfox_bible_groups['new'] as $book_id)
		$content .= '<li>' . $bfox_links->ref_link(bfox_get_book_name($book_id)) . '</li>';
		$content .= '</ul></div>';

		$content .= '<div id="apocrypha"><h3>Apocryphal Books</h3>';
		$content .= '<ul>';
		foreach ($bfox_bible_groups['apocrypha'] as $book_id)
		$content .= '<li>' . $bfox_links->ref_link(bfox_get_book_name($book_id)) . '</li>';
		$content .= '</ul></div>';

		$content .= '</div>';
		return $content;
	}

	function bfox_show_toc_groups($groups, $books, $depth = 3)
	{
		global $bfox_bible_groups, $bfox_links;

		foreach ($groups as $key => $group)
		{
			if (is_array($group)) $output .= bfox_show_toc_groups($group, $books, $depth + 1);
			else
			{
				if ('0' != $key)
				{
					$output .= "<p><h$depth>$group</h$depth></p>";
					foreach ($bfox_bible_groups[$key] as $book_id)
					{
						if (isset($books[$book_id]))
						{
							$book_name = bfox_get_book_name($book_id);
							$output .= '<p>' . $book_name;
							$chaps = array();
							for ($chapter = 0; $chapter < $books[$book_id]; $chapter++)
								$chaps[] = $bfox_links->ref_link(array('ref_str' => $book_name . ' ' .($chapter + 1), 'text' => $chapter + 1));
//							$output .= '<br/>' . implode(', ', $chaps);
							$output .= '</p>';
						}
					}
				}
				else
				{
					$depth2 = $depth - 1;
					$output .= "<p><h$depth2>$group</h$depth2></p>";
				}
			}
		}

		return $output;
	}

	function bfox_show_toc($trans_id = 12)
	{
		bfox_set_book_groups();
		echo bfox_books_two_cols();
/*
		global $wpdb;
		$data = $wpdb->get_results($wpdb->prepare('SELECT book_id, value
												  FROM ' . BFOX_BOOK_COUNTS_TABLE . '
												  WHERE trans_id = %d AND chapter_id = 0',
												  $trans_id));

		foreach ($data as $row)
			$books[$row->book_id] = $row->value;

		$groups = array('',
						'old' => array('Old Testament',
									   'torah' => 'The Books of Moses',
									   'history' => 'The Historical Books',
									   'wisdom' => 'The Books of Wisdom',
									   'prophets' => array('The Prophets',
														   'major_prophets' => 'Major Prophets',
														   'minor_prophets' => 'Minor Prophets')),
						'new' => array('New Testament',
									   'gospels' => 'The Gospels',
									   'acts' => 'Acts',
									   'paul' => 'Pauline Epistles',
									   'epistles' => 'General Epistles',
									   'revelation' => 'Revelation'),
						'apocrypha' => 'Apocryphal Books'
		);

		echo '<center>';
		echo bfox_show_toc_groups($groups, $books);
		echo '</center>';*/
	}

	/**
	 * Creates an output string with a table row for each verse in the $results data
	 *
	 * @param array $results results from get_results() select statement with verse data
	 * @param array $words the list of words to highlight as having been used in the search
	 * @param string $header optional header string
	 * @return string
	 */
	function bfox_output_verses($results, $words, $header = '')
	{
		if (0 < count($results))
		{
			global $bfox_links;

			if (!empty($header)) $content .= "<tr><td class='verse_header' colspan=2>$header</td></tr>";

			foreach ($words as &$word) $word = '/(' . addslashes(str_replace('+', '', $word)) . ')/i';

			foreach ($results as $result)
			{
				$ref_str = bfox_get_book_name($result->book_id) . ' ' . $result->chapter_id . ':' . $result->verse_id;
				$link = $bfox_links->ref_link($ref_str);
				$result->verse = strip_tags($result->verse);
				$result->verse = preg_replace($words, '<strong>$1</strong>', $result->verse);
				$content .= "<tr><td valign='top' nowrap>$link</td><td>$result->verse</td></tr>";
			}
		}

		return $content;
	}

	/**
	 * Generates the output string for a verse search map
	 *
	 * @param unknown_type $book_counts
	 * @return unknown
	 */
	function bfox_output_verse_map($book_counts)
	{
		global $bfox_links;

		$content = '<table>';
		foreach ($book_counts as $book => $count)
		{
			// TODO2: Currently only showing the books, but we should eventually display the book groups too
			if (!empty($count) && is_int($book))
			{
				$book = bfox_get_book_name($book);
				$link = $bfox_links->ref_link($book);
				$content .= "<tr><td nowrap>$link</td><td>$count</td></tr>";
			}
		}
		$content .= '</table>';

		return $content;
	}

	/**
	 * Performs a regular full text search
	 *
	 * @param unknown_type $text
	 * @return unknown
	 */
	function bfox_search_regular($text)
	{
		global $wpdb;
		$table_name = bfox_get_verses_table_name(bfox_get_default_version());
		$match = $wpdb->prepare("MATCH(verse) AGAINST(%s)", $text);
		$results = $wpdb->get_results("SELECT *, $match AS match_val FROM $table_name WHERE $match LIMIT 5");

		return $results;
	}

	/**
	 * Performs a full text search with query expansion
	 *
	 * @param unknown_type $text
	 * @return unknown
	 */
	function bfox_search_expanded($text)
	{
		global $wpdb;
		$table_name = bfox_get_verses_table_name(bfox_get_default_version());
		$match = $wpdb->prepare("MATCH(verse) AGAINST(%s WITH QUERY EXPANSION)", $text);
		$results = $wpdb->get_results("SELECT *, $match AS match_val FROM $table_name WHERE verse_id != 0 AND $match LIMIT 5");

		return $results;
	}

	/**
	 * Performs a boolean full text search
	 *
	 * @param unknown_type $text
	 * @param unknown_type $limit
	 * @return unknown
	 */
	function bfox_search_boolean($text, $limit = 40)
	{
		global $wpdb;
		$table_name = bfox_get_verses_table_name(bfox_get_default_version());
		$match = $wpdb->prepare("MATCH(verse) AGAINST(%s IN BOOLEAN MODE)", $text);
		$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE verse_id != 0 AND $match ORDER BY unique_id ASC LIMIT %d", $limit));

		return $results;
	}

	/**
	 * Performs a boolean full text search, but returns results as a list of verse counts per book
	 *
	 * @param unknown_type $text
	 * @param unknown_type $limit
	 * @return unknown
	 */
	function bfox_search_boolean_books($text, $limit = 40)
	{
		global $wpdb;
		$table_name = bfox_get_verses_table_name(bfox_get_default_version());
		$match = $wpdb->prepare("MATCH(verse) AGAINST(%s IN BOOLEAN MODE)", $text);
		$sql = "SELECT book_id, COUNT(*) AS count FROM $table_name WHERE verse_id != 0 AND $match GROUP BY book_id";
		$results = (array) $wpdb->get_results($sql);

		$book_counts = array();
		foreach ($results as $result) $book_counts[$result->book_id] = $result->count;

		// TODO2: This function should only be called once during init
		bfox_set_book_groups();

		global $bfox_bible_groups;
		foreach ($bfox_bible_groups as $name => $book_ids)
			foreach ($book_ids as $book_id) $book_counts[$name] += $book_counts[$book_id];

		return $book_counts;
	}

	/**
	 * Performs several different text searches and formats output to display them
	 *
	 * @param string $text search string
	 * @return string output
	 */
	function bfox_bible_text_search($text)
	{
		// Parse the search text into words
		$words = str_word_count($text, 1);

		// Put the words back together for the new search text
		$text = implode(' ', $words);

		// Divide the words into long and short words
		$long_words = array();
		$short_words = array();
		foreach ($words as $word)
			if (strlen($word) > 2) $long_words[] = $word;
			else $short_words[] = $word;

		$long_word_min_len = 3;
		$match_all_text = '+' . implode(' +', $long_words) . '*';
		$book_counts = bfox_search_boolean_books($match_all_text);
		$book_count = $book_counts['all'];

		// We can only make phrase suggestions when there are long words
		if (0 < count($long_words))
		{
			$sugg_limit = 10;
			$sugg_count = 0;
			if ((1 < count($words)) || (0 == $book_count))
			{
				$exact = bfox_search_boolean('"' . $text . '"', $sugg_limit - $sugg_count);
				$sugg_count += count($exact);
			}

			if (0 < $sugg_limit - $sugg_count)
			{
				$specific = bfox_search_regular($text, $sugg_limit - $sugg_count);
				$sugg_count += count($specific);
			}

			if (0 < $sugg_limit - $sugg_count)
			{
				$other = bfox_search_expanded($text, $sugg_limit - $sugg_count);
				$sugg_count += count($other);
			}

			if (0 < $sugg_count)
			{
				$content .= "<h3>Suggestions - $text</h3>";
				$content .= '<table>';

				$content .= bfox_output_verses($exact, $words, 'Exact Matches');
				$content .= bfox_output_verses($specific, $words, 'Specific Suggestions');
				$content .= bfox_output_verses($other, $words, 'Other Suggestions');

				$content .= '</table>';
			}
		}

		// Show the exact matches at the bottom
		$content .= "<h3>Match All Words - $text</h3>";
		$content .= '<div id="bible_search_all_words">';
		$content .= '<div id="bible_search_verse_map">';
		$content .= bfox_output_verse_map($book_counts);
		$content .= '</div>';

		$content .= '<div id="bible_search_results">';
		$content .= '<table>';
		$start = microtime();
		$content .= bfox_output_verses(bfox_search_boolean($match_all_text), $words);
		$end = microtime();
		$content .= '</table>';
		$content .= '<p>Time: ' . ($end - $start) . " $end $start</p>";
		$content .= '</div>';
		$content .= '</div>';


		return $content;
	}

?>
