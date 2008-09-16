<?php
	define('BFOX_UNIQUE_ID_PART_SIZE', 8);
	define('BFOX_UNIQUE_ID_MASK', 0xFF);
	define('BFOX_MAX_CHAPTER', BFOX_UNIQUE_ID_MASK);
	define('BFOX_MAX_VERSE', BFOX_UNIQUE_ID_MASK);

	function bfox_get_book_name($book_id)
	{
		global $wpdb;
		$query = $wpdb->prepare("SELECT name FROM " . BFOX_BOOKS_TABLE . " WHERE id = %d", $book_id);
		return $wpdb->get_var($query);
	}

	function bfox_find_book_id($synonym)
	{
		global $wpdb;
		$query = $wpdb->prepare("SELECT book_id FROM " . BFOX_SYNONYMS_TABLE . " WHERE synonym LIKE %s", trim($synonym));
		if (isset($query))
			return $wpdb->get_var($query);
		return false;
	}
	
	class BibleRefSingle
	{
		private $unique_ids = array(0, 0);

		function get_unique_ids()
		{
			return $this->unique_ids;
		}

		function get_string()
		{
			if (isset($this->str))
				return $this->str;

			$parsed = get_parsed();

			if (!isset($this->book_name))
				$this->book_name = bfox_get_book_name($parsed->book_id);

			// Create the reference string
			$str = "$this->book_name";
			if (isset($parsed->chapter1))
			{
				$str .= " {$parsed->chapter1}";
				if (isset($parsed->verse1))
					$str .= ":{$parsed->verse1}";
				if (isset($parsed->chapter2))
				{
					$str .= "-{$parsed->chapter2}";
					if (isset($parsed->verse2))
						$str .= ":{$parsed->verse2}";
				}
				else if (isset($parsed->verse2))
					$str .= "-{$parsed->verse2}";
			}

			$this->str = $str;
			return $this->str;
		}

		function get_parsed()
		{
			// Returns the parsed array form of the bible reference
			// Parsed arrays are as follows:
			
			if (isset($this->parsed))
				return $this->parsed;

			$parsed->book_id  = (int) (($unique_id[0] >> (BFOX_UNIQUE_ID_PART_SIZE * 2)) & BFOX_UNIQUE_ID_MASK);
			$parsed->chapter1 = (int) (($unique_id[0] >> BFOX_UNIQUE_ID_PART_SIZE) & BFOX_UNIQUE_ID_MASK);
			$parsed->verse1   = (int) ($unique_id[0] & BFOX_UNIQUE_ID_MASK);
			$parsed->chapter2 = (int) (($unique_id[1] >> BFOX_UNIQUE_ID_PART_SIZE) & BFOX_UNIQUE_ID_MASK);
			$parsed->verse2   = (int) ($unique_id[1] & BFOX_UNIQUE_ID_MASK);

			// If chapter two is set to max, we should not use it
			if ((BFOX_UNIQUE_ID_MASK == $parsed->chapter2) || ($parsed->chapter1 == $parsed->chapter2))
				unset($parsed->chapter2);
			if ((BFOX_UNIQUE_ID_MASK == $parsed->verse2) || ($parsed->verse1 == $parsed->verse2))
				unset($parsed->verse2);

			// Unset the first chapter and verse if they are 0
			if (0 == $parsed->chapter1) unset($parsed->chapter1);
			if (0 == $parsed->verse1) unset($parsed->verse1);
			
			$this->parsed = $parsed;
			return $this->parsed;
		}

		function set_by_unique_ids($unique_ids)
		{
			// The unique ids are valid only if the first is greater than 0 and the second is greater than (or equal to) the first
			if ((0 < $unique_ids[0]) && ($unique_ids[0] <= $unique_ids[1]))
			{
				$this->unique_ids = $unique_ids;
				return true;
			}
			return false;
		}

		function set_by_parsed($parsed)
		{
			// The parsed data is only valid if it has a valid book id
			$parsed->book_id &= BFOX_UNIQUE_ID_MASK;
			if (0 < $parsed->book_id)
			{
				// Clean up parsed so that it stores information in a standard way
				$parsed_clean->book_id = $parsed->book_id;

				// Create the unique ids
				$unique_ids = array();
				$unique_ids[0] = $parsed->book_id << (BFOX_UNIQUE_ID_PART_SIZE * 2);
				$unique_ids[1] = $parsed->book_id << (BFOX_UNIQUE_ID_PART_SIZE * 2);

				// We only need to look at the chapter1 is set 
				$parsed->chapter1 &= BFOX_UNIQUE_ID_MASK;
				if (0 < $parsed->chapter1)
				{
					$parsed->verse1   &= BFOX_UNIQUE_ID_MASK;
					$parsed->chapter2 &= BFOX_UNIQUE_ID_MASK;
					$parsed->verse2   &= BFOX_UNIQUE_ID_MASK;

					// Clean up parsed so that it stores information in a standard way
					$parsed_clean->chapter1 = $parsed->chapter1;
					if (0 < $parsed->verse1) $parsed_clean->verse1 = $parsed->verse1;
					if (0 < $parsed->chapter2) $parsed_clean->chapter2 = $parsed->chapter2;
					if (0 < $parsed->verse2) $parsed_clean->verse2 = $parsed->verse2;

					/*
					 Conversion methods:
					 john			0:0-max:max		max:max
					 john 1			1:0-1:max		first:max
					 john 1-2		1:0-2:max		second:max
					 john 1:1		1:1-1:1			first:first
					 john 1:1-5		1:1-5:max		second:max
					 john 1:1-0:2	1:1-1:2			first:second
					 john 1:1-5:2	1:1-5:2			second:second
					 john 1-5:2		1:0-5:2			second:second
					 
					 When chapter2 is not set (== 0): chapter2 equals chapter1 unless chapter1 is not set
					 When verse2 is not set (== 0): verse2 equals max unless chapter2 is not set and verse1 is set
					 */

					$chapter1 = $parsed_clean->chapter1;

					if (isset($parsed_clean->verse1)) $verse1 = $parsed_clean->verse1;
					else $verse1 = 0;

					// When verse2 is not set: verse2 equals max unless chapter2 is not set and verse1 is set
					if (isset($parsed_clean->verse2)) $verse2 = $parsed_clean->verse2;
					else $verse2 = ((isset($parsed_clean->verse1)) && (!isset($parsed_clean->chapter2))) ? $verse1 : BFOX_UNIQUE_ID_MASK;

					// When chapter2 is not set: chapter2 equals chapter1 unless chapter1 is not set
					if (isset($parsed_clean->chapter2)) $chapter2 = $parsed_clean->chapter2;
					else $chapter2 = (isset($parsed_clean->chapter1)) ? BFOX_UNIQUE_ID_MASK : $parsed_clean->chapter1;

					// Add the verse and chapter information to the unique ids
					$unique_ids[0] += ($chapter1 << BFOX_UNIQUE_ID_PART_SIZE) + verse1;
					$unique_ids[1] += ($chapter2 << BFOX_UNIQUE_ID_PART_SIZE) + verse2;
				}

				// Save off the cleaned parsed data
				$this->parsed = $parsed_clean;

				return set_by_unique_ids($unique_ids);
			}
			return false;
		}

		function set_by_string($str)
		{
			// Convert all whitespace to single spaces
			$str = preg_replace('/\s+/', ' ', $str);

			// Find the last dash in the string and use it to divide the ref
			$dash_left = trim($str);
			if ($pos = strrpos($dash_left, '-'))
			{
				$dash_right = trim(substr($dash_left, $pos + 1));
				$dash_left = trim(substr($dash_left, 0, $pos));
			}

			// Parse the left side of the dash
			$left_colon_list = explode(':', $dash_left);
			$left_colon_count = count($left_colon_list);

			// We can only have one dash
			if (2 >= $left_colon_count)
			{
				// If there was a dash, then save the right side as an integer verse number
				if (1 < $left_colon_count)
					$verse_num = (int) trim($left_colon_list[1]);

				// If we didn't have any problems with the right side of the colon (verse_num)
				if ((!isset($verse_num)) || (0 < $verse_num))
				{
					if (isset($verse_num)) $verse1 = $verse_num;

					// Parse the left side of the colon to get the book name and the chapter num
					$colon_left = trim($left_colon_list[0]);
					if ($pos = strrpos($colon_left, ' '))
					{
						// Get the chapter number which must be greater than 0
						$chapter_num = (int) trim(substr($colon_left, $pos + 1));
						if (0 < $chapter_num)
						{
							$chapter1 = $verse_num;
							$book_str = trim(substr($colon_left, 0, $pos));
						}
					}
				}
			}

			// Parse the right side of the dash if the left side worked (yielding at least a chapter id)
			if ((isset($dash_right)) && (isset($chapter1)))
			{
				$right_colon_list = explode(':', $dash_right);
				$right_colon_count = count($right_colon_list);

				// We can only have one dash
				if (2 >= $right_colon_count)
				{
					// If there was a dash, then save the right side as an integer
					if (1 < $right_colon_count)
						$num2 = (int) trim($right_colon_list[1]);

					// If we didn't have any problems with the right side of the colon (num2)
					// Then save the left side as an integer
					if ((!isset($num2)) || (0 < $num2))
						$num1 = (int) trim($right_colon_list[0]);
				}

				// If we got at least one integer and it is greater than zero,
				// then everything went fine on this side of the dash
				if ((isset($num1)) && (0 < $num1))
				{
					if (isset($num2))
					{
						// If we have 2 numbers then the first is a chapter and the second is a verse
						$chapter2 = $num1;
						$verse2 = $num2;
					}
					else
					{
						// If there is only one number on the right side of the dash,
						// then it can be either a chapter or a verse

						// If the left side of the dash yielded a verse2,
						// then we must have a verse on the right side of the dash also
						if (isset($verse1)) $verse2 = $num1;
						else $chapter2 = $num1;
					}
				}
				else
				{
					// If we didn't get any numbers on this side of the dash
					// then the string is misformatted
					unset($chapter1);
				}
			}

			// If we haven't set a book string yet, set it to the original str
			if (!isset($book_str)) $book_str = $str;

			// Try to find a book id for the book string
			if ($book_id = bfox_find_book_id($book_str))
			{
				// If we found a book id, this must be a valid bible reference string
				$parsed->$book_id = $book_id;
				if (isset($chapter1))
				{
					$parsed->chapter1 = $chapter1;
					if (isset($verse1)) $parsed->verse1 = $verse1;
					if (isset($chapter2)) $parsed->chapter2 = $chapter2;
					if (isset($verse2)) $parsed->verse2 = $verse2;
				}

				// Save this string
				$this->orig_str = $str;

				return $this->set_by_parsed($parsed);
			}

			// This string did not result in a valid bible reference
			return false;
		}

		// Returns an SQL expression for comparing this bible reference against one unique id column
		function sql_where($col1 = 'unique_id')
		{
			global $wpdb;
			return $wpdb->prepare("($col1 >= %d AND $col1 <= %d)", $this->unique_ids[0], $this->unique_ids[1]);
		}

		// Returns an SQL expression for comparing this bible reference against two unique id columns
		function sql_where2($col1, $col2)
		{
			/*
			 Equation for determining whether one bible reference overlaps another
			 
			 a1 <= b1 and b1 <= a2 or
			 a1 <= b2 and b2 <= a2
			 or
			 b1 <= a1 and a1 <= b2 or
			 b1 <= a2 and a2 <= b2
			 
			 a1b1 * b1a2 + a1b2 * b2a2 + b1a1 * a1b2 + b1a2 * a2b2
			 b1a2 * (a1b1 + a2b2) + a1b2 * (b1a1 + b2a2)
			 
			 */
			
			global $wpdb;
			return $wpdb->prepare("((($col1 <= %d) AND ((%d <= $col1) OR (%d <= $col2))) OR
								    ((%d <= $col2) AND (($col1 <= %d) OR ($col2 <= %d))))",
								  $this->unique_ids[1], $this->unique_ids[0], $this->unique_ids[1],
								  $this->unique_ids[0], $this->unique_ids[0], $this->unique_ids[1]);
		}

		// Takes a reference and returns the next passage after that reference of the same size
		function get_next($factor = 1)
		{
			// NOTE: Currently the function only considers how many chapters are in the ref
			// It will need to consider how many verses as well
			// Also, it doesn't currently handle moving on to the next book of the bible
			
			$parsed = get_parsed();
			
			// Calculate how much we should increment our chapter numbers
			$chapDiff = $parsed->chapter2 - get_parsed->chapter1;
			$chapInc = 1;
			if (0 < $chapDiff) $chapInc = $chapDiff;
			$chapInc *= $factor;
			
			// Increment the chapters
			$parsed->chapter1 += $chapInc;
			if (isset($parsed->chapter2)) $parsed->chapter2 += $chapInc;

			// Try to create a new bible reference from the new parsed information
			$ref = new BibleRefSingle;
			if ($ref->set_by_parsed($parsed))
				return $ref;

			// If we couldn't create a new ref, return the old one
			return $this;
		}
		
	}

	class BibleRefs
	{
		private $refs = array();

		function get_sets()
		{
			$unique_id_sets = array();
			foreach ($this->refs as $ref) $unique_id_sets[] = $ref->get_unique_ids();
			return $unique_id_sets;
		}

		function get_string()
		{
			$strs = array();
			foreach ($this->refs as $ref) $strs[] = $ref->get_string();
			return implode('; ', $strs);
		}
		
		function push_sets($unique_id_sets)
		{
			$count = 0;
			foreach ($unique_id_sets as $unique_ids)
			{
				$ref = new BibleRefSingle;
				if ($ref->set_by_unique_ids($unique_ids))
				{
					$this->$refs[] = $ref;
					$count++;
				}
			}
			return $count;
		}

		function push_string($str)
		{
			$count = 0;
			$refstrs = preg_split("/[\n,;]/", trim($str));
			foreach ($refstrs as $refstr)
			{
				$ref = new BibleRefSingle;
				if ($ref->set_by_string($refstr))
				{
					$this->$refs[] = $ref;
					$count++;
				}
			}
			return $count;
		}

		function sql_where($col1 = 'unique_id')
		{
			$strs = array();
			foreach ($this->refs as $ref) $strs[] = $ref->sql_where($col1);
			return '(' . implode(' OR ', $strs) . ')';
		}

		function sql_where2($col1, $col2)
		{
			$strs = array();
			foreach ($this->refs as $ref) $strs[] = $ref->sql_where2($col1, $col2);
			return '(' . implode(' OR ', $strs) . ')';
		}
		
		function increment($factor = 1)
		{
			foreach ($this->refs as &$ref) $ref = $ref->get_next($factor);
		}
	}

	function bfox_get_ref_content(BibleRefs $refs, $version_id = -1, $id_text_begin = '', $id_text_end = ' ')
	{
		global $wpdb;

		$ref_where = $refs->sql_where();
		$table_name = bfox_get_verses_table_name($version_id);
		$verses = $wpdb->get_results("SELECT verse_id, verse FROM " . $table_name . " WHERE $ref_where");

		$content = '';
		foreach ($verses as $verse)
		{
			if ($verse->verse_id != 0)
				$content .= "$id_text_begin$verse->verse_id$id_text_end";
			$content .= $verse->verse;
		}

		return $content;
	}

	// Function for echoing scripture
	function bfox_echo_scripture($version_id, BibleRefs $ref)
	{
		$content = bfox_get_ref_content($ref, $version_id);
		echo $content;
	}

	function bfox_get_posts_equation_for_refs(BibleRefs $refs, $table_name = BFOX_TABLE_BIBLE_REF, $verse_begin = 'verse_begin', $verse_end = 'verse_end')
	{
		$begin = $table_name . '.' . $verse_begin;
		$end = $table_name . '.' . $verse_end;
		return refs->sql_where2($begin, $end);
	}

	function bfox_get_posts_for_refs(BibleRefs $refs)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;

		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			return array();

		$equation = bfox_get_posts_equation_for_refs(BibleRefs $refs);
		if ('' != $equation)
			return $wpdb->get_col("SELECT post_id FROM $table_name WHERE $equation GROUP BY post_id");
		
		return array();
	}
	
	function bfox_get_post_bible_refs($post_id = 0)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;
		
		// If the table does not exist then there are obviously no bible references
		if ((0 == $post_id) || ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name))
			return array();

		$select = $wpdb->prepare("SELECT verse_begin, verse_end FROM $table_name WHERE post_id = %d ORDER BY ref_order ASC", $post_id);
		$sets = $wpdb->get_results($select, ARRAY_N);
		$refs = new BibleRefs;
		$refs->push_sets($sets);
		return $refs;
	}
	
	function bfox_get_bible_permalink($refStr)
	{
		return get_option('home') . '/?bible_ref=' . $refStr;
	}

	function bfox_get_bible_link($refStr)
	{
		$permalink = bfox_get_bible_permalink($refStr);
		return "<a href=\"$permalink\" title=\"$refStr\">$refStr</a>";
	}

	function bfox_get_ref_menu($refStr, $header = true)
	{
		$home_dir = get_option('home');
		$admin_dir = $home_dir . '/wp-admin';

		if (defined('WP_ADMIN'))
			$page_url = "{$admin_dir}/admin.php?page=" . BFOX_READ_SUBPAGE . "&";
		else
			$page_url = "{$home_dir}/?";

		$menu = '';
		$refs = array(bfox_parse_ref($refStr));

		// Add bible tracking data
		global $user_ID;
		get_currentuserinfo();
		if (0 < $user_ID)
		{
			if ($header) $menu .= bfox_get_dates_last_viewed_str($refs, false) . '<br/>';
			$menu .= bfox_get_dates_last_viewed_str($refs, true);
			$menu .= " (<a href=\"{$page_url}bible_ref=$refStr&bfox_action=mark_read\">Mark as read</a>)<br/>";
		}
		else $menu .= "<a href=\"$home_dir/wp-login.php\">Login</a> to track your bible reading<br/>";

		// Scripture navigation links
		if ($header)
		{
			$menu .= "<a href=\"http://www.biblegateway.com/passage/?search=$refStr&version=31\" target=\"_blank\">Read on BibleGateway</a><br/>";
			$menu .= "<a href=\"{$page_url}bible_ref=$refStr&bfox_action=previous\">Previous</a> | ";
			$menu .= "<a href=\"{$page_url}bible_ref=$refStr&bfox_action=next\">Next</a><br/>";
		}

		// Write about this passage
		$menu .= "<a href=\"{$admin_dir}/post-new.php?bible_ref=$refStr\">Write about this passage</a>";

		return '<center>' . $menu . '</center>';
	}

	function bfox_get_next_refs($refs, $action)
	{
		// Determine if we need to modify the refs using a next/previous action
		$next_factor = 0;
		if ('next' == $action) $next_factor = 1;
		else if ('previous' == $action) $next_factor = -1;
		else if ('mark_read' == $action)
		{
			$next_factor = 0;
			bfox_update_table_read_history($refs, true);
		}

		// Modify the refs for the next factor
		if (0 != $next_factor)
		{
			$newRefs = array();
			foreach ($refs as $ref) $newRefs[] = bfox_get_ref_next($ref, $next_factor);
			$refs = $newRefs;
			unset($newRefs);
		}

		return $refs;
	}

?>
