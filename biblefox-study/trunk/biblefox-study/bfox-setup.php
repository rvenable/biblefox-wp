<?php
	define('BFOX_SETUP_DIR', dirname(__FILE__) . "/setup");
	
	class BfoxAdminTools
	{
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
				if ($plan->are_tables_installed())
				{
					echo "Upgrading Plan<br/>";
					$plan->create_tables();
					$plan->reset_end_dates();
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
								  (id, name, wiki_name)",
								  $file,
								  $delimiter);
			
			$wpdb->query($sql);
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
		}
	}
	
	function bfox_initial_setup()
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
