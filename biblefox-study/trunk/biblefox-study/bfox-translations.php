<?php
	
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
	
	function bfox_create_translations_table()
	{
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		$sql = "CREATE TABLE " . BFOX_TRANSLATIONS_TABLE . " (
				id int,
				short_name varchar(8),
				long_name varchar(128),
				is_default boolean,
				is_enabled boolean,
				PRIMARY KEY  (id)
			);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	function bfox_create_trans_verses_table($table_name, $verse_size = 1024)
	{
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		global $wpdb;
		$sql = $wpdb->prepare("CREATE TABLE $table_name (
							  unique_id int,
							  book_id int,
							  chapter_id int,
							  verse_id int,
							  verse varchar(%d),
							  PRIMARY KEY  (unique_id)
							  );",
							  $verse_size);
							  
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	
	// Adds a translation to the translation table
	// Returns the id of the new translation
	function bfox_add_translation($header)
	{
		global $wpdb;
		$table_name = BFOX_TRANSLATIONS_TABLE;

		// If the translations table doesn't exist, create it
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
		{
			bfox_create_translations_table();
		}
		else
		{
			// If the table already exists we need to know the last id
			$last_id = $wpdb->get_var("SELECT id FROM $table_name ORDER BY id DESC");
		}

		$id = 0;
		$is_default = 1;
		if (isset($last_id))
		{
			$id = $last_id + 1;
			$is_default = 0;
		}
		
		// Insert the header data for this translation
		$insert = $wpdb->prepare("INSERT INTO $table_name
								 (id, short_name, long_name, is_default, is_enabled)
								 VALUES (%d, %s, %s, %d, %d)",
								 $id,
								 $header['short_name'],
								 $header['long_name'],
								 $is_default,
								 0);

		$wpdb->query($insert);
		
		return $id;
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

	function bfox_install_usfx_file($file_name)
	{
		$file = BFOX_TRANSLATIONS_DIR . "/$file_name";
		$header = array('short_name' => 'WEB', 'long_name' => 'World English Bible');
		$id = bfox_add_translation($header);
		$table_name = bfox_get_verses_table_name($id);
		bfox_create_trans_verses_table($table_name);
		
		bfox_usfx_install($table_name, $file);
	}
	
	function bfox_get_bft_files()
	{
		$bft_files = array();

		$translations_dir = opendir(BFOX_TRANSLATIONS_DIR);
		if ($translations_dir)
		{
			while (($file_name = readdir($translations_dir)) !== false)
			{
				if (substr($file_name, -4) == '.bft')
					$bft_files[] = "$file_name";
				else if (substr($file_name, -4) == '.xml')
					$bft_files[] = "$file_name";
			}
		}
		@closedir($translations_dir);
		
		return $bft_files;
	}

	function bfox_menu_install_translations()
	{
		echo '<div class="wrap">';
		echo '<h2>Install Translations</h2>';
		echo 'The following translation files have been found in your translations directory.<br/>';
		echo 'Please select a translation to install:<br/>';

		$bft_files = bfox_get_bft_files();
		foreach ($bft_files as $bft_file)
		{
			$page = BFOX_TRANSLATION_SUBPAGE;
			$url = "admin.php?page=$page&amp;action=install&amp;file=$bft_file";
			echo "<a href='$url'>$bft_file</a><br/>";
		}
		echo '</div>';
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
							$output .= $book_name;
							$chaps = array();
							for ($chapter = 0; $chapter < $books[$book_id]; $chapter++)
								$chaps[] = $bfox_links->ref_link(array('ref_str' => $book_name . ' ' .($chapter + 1), 'text' => $chapter + 1));
//							$output .=  ': ' . implode(', ', $chaps);
							$output .= '<br/>';
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
		echo '</center>';
	}
	
?>
