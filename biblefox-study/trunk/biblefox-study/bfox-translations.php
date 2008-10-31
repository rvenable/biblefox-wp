<?php
	define('BFOX_TRANSLATIONS_DIR', dirname(__FILE__) . "/translations");
	
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
	
?>
