<?php
	// TODO2: This define probably needs to go somewhere else
	define('BFOX_SETUP_DIR', dirname(__FILE__) . "/setup");

	class BfoxAdminTools
	{

		/**
		 * Private function for echoing the results of a DB table query
		 *
		 * @param unknown_type $results
		 * @param unknown_type $cols
		 */
		private function echo_table_results($results, $cols = NULL)
		{
			$rows = '';
			if (is_array($results))
			{
				foreach ($results as $row)
				{
					if (!is_array($cols))
						if (is_array($row)) $cols = array_keys($row);
						else $cols = array_keys(get_object_vars($row));
					$rows .= '<tr>';
					foreach ($row as $col) $rows .= '<td>' . $col . '</td>';
					$rows .= '</tr>';
				}
				echo '<table><tr>';
				foreach ($cols as $col) echo '<th>' . $col . '</th>';
				echo '</tr>' . $rows . '</table>';
			}
		}

		/**
		 * Private function for echoing DB tables
		 *
		 * @param unknown_type $table_name
		 * @param unknown_type $cols
		 */
		private function echo_table($table_name, $cols = NULL)
		{
			global $wpdb;
			$results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
			$this->echo_table_results($results, $cols);
		}

		/**
		 * Private function for echoing the description of a DB table
		 *
		 * @param unknown_type $table_name
		 * @param unknown_type $cols
		 */
		private function echo_table_describe($table_name, $cols = NULL)
		{
			global $wpdb;
			$results = $wpdb->get_results("DESCRIBE $table_name", ARRAY_A);
			$this->echo_table_results($results, $cols);
		}

		/*
		 Upgrade function for DB tables
		 */
		function upgrade_all_tables()
		{
			// Get the blogs using a WPMU function (from wpmu-functions.php)
			$blogs = get_blog_list();
			foreach ($blogs as $blog)
			{
				echo "<strong>Upgrading Blog {$blog['blog_id']}...</strong><br/>";

				$plan = new PlanBlog($blog['blog_id']);
				if (!$plan->are_tables_installed())
				{
					echo "Upgrading Plan<br/>";
					$plan->create_tables();
					//$plan->reset_end_dates();
				}
			}

			global $wpdb;
			$users = $wpdb->get_col("SELECT ID FROM $wpdb->users");
			foreach ($users as $user_id)
			{
				echo "<strong>Upgrading User $user_id...</strong><br/>";

				$history = new History($user_id);
				if ($history->are_tables_installed())
				{
					echo "Upgrading History<br/>";
					$history->create_tables();
				}

				$plan = new PlanProgress($user_id);
				if ($plan->are_tables_installed())
				{
					echo "Upgrading Plan Progress<br/>";
					$plan->create_tables();
				}
			}
		}

		/*
		 Private function to create the books table
		 */
		private function create_books_table()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

			$sql = "CREATE TABLE " . BFOX_BOOKS_TABLE . " (
			id int,
			name varchar(128),
			wiki_name varchar(128),
			short_name varchar(5),
			PRIMARY KEY  (id)
			);";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		/*
		 Creates and then fills the books table with data from setup/books.txt
		 */
		function fill_books_table()
		{
			global $wpdb;
			$file = BFOX_SETUP_DIR . "/books.txt";
			$delimiter = ",";
			$table_name = BFOX_BOOKS_TABLE;

			define(DIEONDBERROR, '');

			// Create the books table
			echo 'Creating Books Table<br/>';
			$this->create_books_table();

			// Delete everything in the books table because we want to fill it completely with fresh data
			echo 'Deleting and previous data in the Books Table<br/>';
			$sql = $wpdb->prepare("DELETE FROM $table_name");
			$wpdb->query($sql);

			// Load the data file into the table using a LOAD DATA statement
			echo 'Filling the Books Table<br/>';
			global $wpdb;
			$sql = $wpdb->prepare("LOAD DATA LOCAL INFILE %s
								  INTO TABLE $table_name
								  FIELDS TERMINATED BY %s
								  (id, name, wiki_name, short_name)",
								  $file,
								  $delimiter);

			$wpdb->query($sql);

			$this->echo_table(BFOX_BOOKS_TABLE);
		}

		/*
		 Private function to get a list of all the book ids in the books table
		 */
		private function get_book_id_list()
		{
			global $wpdb;
			$books = $wpdb->get_results("SELECT id, name FROM " . BFOX_BOOKS_TABLE);

			$book_names = array();
			foreach ($books as $book)
			{
				$book_names[strtolower($book->name)] = $book->id;
			}
			return $book_names;
		}

		/*
		 Private function to create the synonyms table
		 */
		private function create_synonyms_table()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

			$sql = "CREATE TABLE " . BFOX_SYNONYMS_TABLE . " (
			id int,
			book_id int,
			synonym varchar(128),
			PRIMARY KEY  (id)
			);";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		/*
		 Fills the synonyms table with data from setup/syns.txt
		 */
		function fill_synonyms_table()
		{
			global $wpdb;
			$file = BFOX_SETUP_DIR . "/syns.txt";
			$table_name = BFOX_SYNONYMS_TABLE;

			// Read the file into the array $lines
			$lines = file($file);
			$num_lines = count($lines);

			// Create the synonyms table
			echo 'Creating Synonyms Table<br/>';
			$this->create_synonyms_table();

			// Delete everything in the table because we want to fill it completely with fresh data
			$sql = $wpdb->prepare("DELETE FROM $table_name");
			$wpdb->query($sql);

			// Get a list of all the book ids in the books table
			$book_ids = $this->get_book_id_list();

			// Start the synonyms off by adding all the book names
			$syn_array = array();
			foreach (array_keys($book_ids) as $book_name)
			$syn_array[$book_ids[$book_name]] = $book_name;

			// Scan each line of the file to detect synonyms and which books they correspond to
			$unknowns = '';
			foreach ($lines as $line)
			{
				$line = strtolower($line);
				$syns = explode(",", $line);
				$num_syns = count($syns);

				// Find the first synonym that has a book_id
				$index = 0;
				while (($index < $num_syns) && (!array_key_exists(trim($syns[$index]), $book_ids))) $index++;

				if ($index < $num_syns)
				{
					// If we found a synonym with a book id then we should add all the synonyms on this line
					// to the synonym array element corresponding to this book id
					$book_id = $book_ids[trim($syns[$index])];
					$syn_array[$book_id] .= ",$line";
				}
				else
				{
					// If we didn't find a synonym, then this line is unknown
					$unknowns .= "$line\n";
				}
			}

			// We don't need the file lines anymore
			unset($lines);

			// Create a list of sql values to insert into the table
			$sql_values = array();
			$index = 0;
			foreach (array_keys($syn_array) as $key)
			{
				$syns = explode(',', $syn_array[$key]);
				$unique = array();
				foreach ($syns as $syn)
				{
					$syn = trim($syn);
					if (!array_key_exists($syn, $unique))
					{
						$sql_values[] = $wpdb->prepare("(%d, %d, %s)", $index, $key, $syn);
						$unique[$syn] = 1;
						$index++;
					}
				}
			}

			// Perform the insertion
			$values = implode(', ', $sql_values);
			$insert = "INSERT INTO $table_name (id, book_id, synonym) VALUES $values";
			$wpdb->query($insert);

			if ($unknowns != '')
			{
				echo "Unknowns: <br/>";
				foreach (explode("\n", $unknowns) as $unknown)
				{
					if ($unknown != '') echo "$unknown <br/>";
				}
			}

			$this->echo_table(BFOX_SYNONYMS_TABLE);
		}

		function test_html_ref_replace()
		{
			$str = "<xml>
			<p>I like Gen 1.</p>
			<p>What do you think? john 21 Do you prefer<d><d> ex 2 or 1sam 3 - 4 or 1 th 4? gen 3:4-8:2 gen 3ddd:2 fff- 1 1 3 </p>
			<p>gen lala yoyo 4:5</p>
			</xml>
			";

			echo $str;
			echo bfox_html_strip_tags($str);
			bfox_create_synonym_data();
			echo bfox_process_html_text($str, 'bfox_ref_replace');
		}

		function show_toc()
		{
//			bfox_show_toc();
			echo bfox_output_bible_group('bible');
		}

		/**
		 * Takes a bible ref string and uses it to create a BibleRefs to test BibleRefs for different inputs
		 *
		 * @param string $ref_str Bible Reference string to test
		 */
		private function test_ref($ref_str)
		{
			echo "<p><strong>$ref_str</strong><br/>";

			// Test setting a BibleRefs by a string
			$ref = RefManager::get_from_str($ref_str);
			echo '$ref->get_string(): ' . $ref->get_string() . '<br/>';

			// Test setting a BibleRefs by a set of unique ids
			$sets = $ref->get_sets();
			$ref2 = RefManager::get_from_sets($sets);
			echo '$ref2->get_string(): ' . $ref2->get_string() . '<br/>';

			// Both BibleRefs should be equal since they were created from the same reference
			if ($ref2->get_string() != $ref->get_string()) echo 'ERROR! Strings not equal!<br/>';

			echo '</p>';
		}

		/**
		 * Tests different bible reference input strings
		 *
		 */
		function test_refs()
		{
			// Test the typical references
			$this->test_ref('1 sam');
			$this->test_ref('1sam 1');
			$this->test_ref('1sam 1-2');
			$this->test_ref('1sam 1:1');
			$this->test_ref('1sam 1:1-5');
			$this->test_ref('1sam 1:1-2:5');
			$this->test_ref('1sam 1-2:5');

			// This test was failing (see bug 21)
			$this->test_ref('Judges 2:6-3:6');

			// Test ignore words
			$this->test_ref('Book of Judges 2');
			$this->test_ref('First Book of Judges 2'); // This one should not work!
			$this->test_ref('First Book of Samuel 2');

			// Test that we can match synonyms with multiple words
			$this->test_ref('Song Solomon 2');
		}

		/**
		 * Tests the bfox_get_discussions() function
		 *
		 */
		function test_discussions()
		{
			echo bfox_get_discussions(array());//'limit' => 4));
		}

		/**
		 * Tests the quicknotes system
		 *
		 */
		function test_quicknotes()
		{
			global $bfox_quicknote;
			$bfox_quicknote->create_tables();
			// $bfox_quicknote->save_quicknote(RefManager::get_from_str('Genesis 2, Gen 7-9'), 'Fun stuff!');
//			$res = $bfox_quicknote->get_quicknotes(RefManager::get_from_str('Gen'));
//			$this->echo_table_results($res);
//			$bfox_quicknote->list_quicknotes(RefManager::get_from_str('Gen'));
		}

		/**
		 * Sends all of today's reading plan emails for the current blog
		 *
		 */
		function send_reading_plan_emails()
		{
			bfox_plan_emails_send();
		}

		/**
		 * Clears the reading plan email scheduled event for this blog.
		 *
		 * To add the event again after clearing it, edit any unfinished reading plan.
		 *
		 */
		function clear_plan_email_event()
		{
			wp_clear_scheduled_hook('bfox_plan_emails_send_action');
		}

		/**
		 * For every user, updates all the default user options which haven't been set yet.
		 *
		 */
		function update_all_user_options()
		{
			global $wpdb;
			$user_ids = $wpdb->get_col("SELECT ID FROM $wpdb->users");
			foreach ($user_ids as $user_id)
				bfox_user_add_defaults($user_id);
		}

		function create_translation_table()
		{
			Translations::create_tables();
		}

		/**
		 * Create the translation index table
		 *
		 */
		function create_translation_index_table()
		{
			Translations::create_translation_index_table();
		}

		/**
		 * Loop through each enabled bible translation and refresh their index data
		 *
		 */
		function refresh_all_translation_indexes()
		{
			$translations = Translations::get_translations();
			foreach ($translations as $translation)
			{
				echo "Refreshing $translation->long_name (ID: $translation->id)...<br/>";
				Translations::refresh_translation_index($translation);
			}
			echo 'Finished<br/>';
		}

		private function output_posts(TxtToBlog $parser, $pre = FALSE, $limit = 20)
		{
			$posts = $parser->parse_file();
//			echo $parser->print_warnings();

			$url = add_query_arg('tool', $_GET['tool'], bfox_admin_page_url(BFOX_ADMIN_TOOLS_SUBPAGE));

			echo "<p>Num Posts: " . count($posts) . "</p>";

			$post = $_GET['post'];
			if (!empty($post) && isset($posts[$post]))
			{
				$post = $posts[$post];
				echo '<a href="' . add_query_arg('post', $num, $url) . '">' . $post->title . '</a><br/>';
				echo ($pre) ? '<pre>' . $post->get_string() . '</pre>' : $post->output();
			}


			foreach ($posts as $num => $post)
			{
				// if (50 >= strlen($post->title))
				// if (empty($post->ref_str))
				if (20 >= $num)
				{
					echo '<a href="' . add_query_arg('post', $num, $url) . '">' . $post->title . '</a><br/>';
//					echo ($pre) ? '<pre>' . $post->get_string() . '</pre>' : $post->output();
				}
			}
		}

		function parse_mhcc()
		{
			require_once('txt_to_blog.php');
			$this->output_posts(new MhccTxtToBlog(), TRUE);
		}

		function parse_calcom()
		{
			require_once('txt_to_blog.php');
			$this->output_posts(new CalcomTxtToBlog());
		}

		/**
		 * Run this to check if there are any synonyms that need to have their ignore words removed
		 *
		 */
		function clean_ignore_words()
		{
			$prefixes = array();
			foreach (BibleMeta::$synonyms[0] as $synonym => $book_id)
			{
				$raw_words = str_word_count($synonym, 1, '0123456789');

				$words = array();
				foreach ($raw_words as $word) if (!isset(BibleMeta::$ignore_words[$word])) $words []= $word;
				unset($raw_words);

				$new_syn = implode(' ', $words);
				if ($synonym != $new_syn)
				{
					echo "Replace '$synonym' with '$new_syn'<br/>";
				}
			}
		}

		private static function print_array_decl($array, $level = 0)
		{
			$elements = array();
			foreach ($array as $key => $value)
			{
				if (is_array($value)) $value = print_array_decl($value, $level + 1);
				else $value = "'$value'";
				$elements []= "'$key' => $value";
			}

			if (0 >= $level) $glue = ",\n";
			else $glue = ', ';

			return 'array(' . implode($glue, $elements) . ')';
		}

		function create_synonym_prefixes_array()
		{
			$prefixes = array();
			foreach (BibleMeta::$synonyms[0] as $synonym => $book_id)
			{
				$words = str_word_count($synonym, 1, BibleMeta::digits);
				array_pop($words);
				while (!empty($words))
				{
					$prefixes[implode(' ', $words)] = TRUE;
					array_pop($words);
				}
			}
			pre(self::print_array_decl($prefixes));
		}

		function test_string_parsing()
		{
			$str = 'hello dude genesis 1,;,2 gen 5 1 sam 4, song ;of song 3';
			echo "$str<br/>";
			$books = BibleMeta::get_books_in_string($str);
			foreach ($books as $book)
			{
				$book_name = BibleMeta::get_book_name($book[0]);
				echo "Book: $book_name ($book[1])<br/>";
			}
		}

		/**
		 * A function for dumping temporary functionality to do temporary tasks
		 *
		 */
		function temp()
		{
//			$refs = RefManager::get_from_str("[1] Horne's Introduction, vol. 5, Part I, chap. 1, sect. 4. London,");
//			echo $refs->get_string();
			RefManager::parse_nums('14,4:5,1:4-10;11,3:10-4:3,1:5-9');
		}

	}

	/**
	 * Displays the admin tools menu
	 *
	 */
	function bfox_admin_tools_menu()
	{
		echo '<div class="wrap"><h2>Admin Tools</h2>';
		bfox_list_admin_tools();

		$tool = $_GET['tool'];
		if (isset($tool))
		{
			global $wpdb;
			$wpdb->show_errors(TRUE);

			echo '<h2>' . $tool . '</h2>';
			$admin_tools = new BfoxAdminTools();
			$func = array($admin_tools, $tool);
			if (is_callable($func)) call_user_func($func);

			$wpdb->show_errors(FALSE);
		}
		echo '</div>';
	}

	function bfox_list_admin_tools()
	{
		$tools = get_class_methods('BfoxAdminTools');
		foreach ($tools as $tool) echo '<a href="' . bfox_admin_page_url(BFOX_ADMIN_TOOLS_SUBPAGE) . '&amp;tool=' . $tool . '">' . $tool . '</a><br/>';
	}

?>
