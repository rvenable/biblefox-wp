<?php
	define("BFOX_MAX_CHAPTER", 0xFF);
	define("BFOX_MAX_VERSE", 0xFF);
	
	function bfox_get_verse_unique_id($book, $chapter, $verse)
	{
		return ($book << 16) + ($chapter << 8) + $verse;
	}

	function bfox_get_verse_ref_from_unique_id($unique_id)
	{
		$mask = 0xFF;
		return array((($unique_id >> 16) & $mask), (($unique_id >> 8) & $mask), ($unique_id & $mask));
	}

	function bfox_get_book_id($book)
	{
		global $wpdb;
		$query = $wpdb->prepare("SELECT book_id FROM " . BFOX_SYNONYMS_TABLE . " WHERE synonym LIKE %s", trim($book));
		return $wpdb->get_var($query);
	}
	
	function bfox_get_book_name($book_id)
	{
		global $wpdb;
		$query = $wpdb->prepare("SELECT name FROM " . BFOX_BOOKS_TABLE . " WHERE id = %d", $book_id);
		return $wpdb->get_var($query);
	}

	function bfox_normalize_ref($ref)
	{
		$normal_keys = array('chapter1', 'verse1', 'chapter2', 'verse2');

		// Set all the normal keys to 0 if they are not already set
		foreach ($normal_keys as $key)
			if (!isset($ref[$key]))
				$ref[$key] = 0;
		
		return $ref;
	}
	
	function bfox_get_refstr($ref)
	{
		$ref = bfox_normalize_ref($ref);

		if (isset($ref['book_name']) && ($ref['book_name'] != ''))
			$book_name = $ref['book_name'];
		else
			$book_name = bfox_get_book_name($ref['book_id']);

		// Create the reference string
		$refStr = "$book_name";
		if ($ref['chapter1'] != 0)
		{
			$refStr .= " {$ref['chapter1']}";
			if ($ref['verse1'] != 0)
				$refStr .= ":{$ref['verse1']}";
			if ($ref['chapter2'] != 0)
			{
				$refStr .= "-{$ref['chapter2']}";
				if ($ref['verse2'] != 0)
					$refStr .= ":{$ref['verse2']}";
			}
			else if ($ref['verse2'] != 0)
				$refStr .= "-{$ref['verse2']}";
		}

		return $refStr;
	}
	
	function bfox_get_reflist_str($refs)
	{
		$refStrs = array();
		foreach ($refs as $ref) $refStrs[] = bfox_get_refstr($ref);
		return implode('; ', $refStrs);
	}
	
	function bfox_get_unique_id_range($ref)
	{
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
		
		$ref = bfox_normalize_ref($ref);

		// When verse2 is not set (== 0): verse2 equals max unless chapter2 is not set and verse1 is set
		if ($ref['verse2'] == 0)
		{
			$ref['verse2'] = BFOX_MAX_VERSE;
			if (($ref['verse1'] != 0) && ($ref['chapter2'] == 0))
				$ref['verse2'] = $ref['verse1'];
		}
		
		// When chapter2 is not set (== 0): chapter2 equals chapter1 unless chapter1 is not set
		if ($ref['chapter2'] == 0)
		{
			$ref['chapter2'] = ($ref['chapter1'] == 0) ? BFOX_MAX_CHAPTER : $ref['chapter1'];
		}
		
		$range[0] = bfox_get_verse_unique_id($ref['book_id'], $ref['chapter1'], $ref['verse1']);
		$range[1] = bfox_get_verse_unique_id($ref['book_id'], $ref['chapter2'], $ref['verse2']);

		return $range;
	}
	
	function bfox_get_ref_for_range($range)
	{
		// Convert the ranges to a ref
		// Note: we currently only support ranges which have identical book ids
		list($ref['book_id'], $ref['chapter1'], $ref['verse1']) = bfox_get_verse_ref_from_unique_id($range[0]);
		list($ref['book_id'], $ref['chapter2'], $ref['verse2']) = bfox_get_verse_ref_from_unique_id($range[1]);

		if ((BFOX_MAX_CHAPTER == $ref['chapter2']) || ($ref['chapter1'] == $ref['chapter2']))
			$ref['chapter2'] = 0;
		if ((BFOX_MAX_VERSE == $ref['verse2']) || ($ref['verse1'] == $ref['verse2']))
			$ref['verse2'] = 0;
		
		return $ref;
	}
	
	function bfox_get_refs_for_ranges($ranges)
	{
		$ranges = (array) $ranges;

		$refs = array();
		foreach ($ranges as $range)
			$refs[] = bfox_get_ref_for_range($range);

		return $refs;
	}

	function bfox_get_ref_content($ref, $version_id = -1, $id_text_begin = '', $id_text_end = ' ')
	{
		global $wpdb;

		$range = bfox_get_unique_id_range($ref);

		$table_name = bfox_get_verses_table_name($version_id);

		$query = $wpdb->prepare("SELECT verse_id, verse
								FROM " . $table_name . "
								WHERE unique_id >= %d
								AND unique_id <= %d",
								$range[0],
								$range[1]);
		$verses = $wpdb->get_results($query);

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
	function bfox_echo_scripture($version_id, $ref)
	{
		$content = bfox_get_ref_content($ref, $version_id);
		echo $content;
	}

	function bfox_get_chapters($ref)
	{
		global $wpdb;

		// TODO: We need to let the user pick their own version
		// Use the default translation until we add user input for this value
		$version_id = bfox_get_default_version();

		$range = bfox_get_unique_id_range($ref);

		$table_name = bfox_get_verses_table_name($version_id);
		
		$query = $wpdb->prepare("SELECT chapter_id
								FROM $table_name
								WHERE unique_id >= %d
								AND unique_id <= %d
								AND chapter_id != 0
								GROUP BY chapter_id",
								$range[0],
								$range[1]);
		return $wpdb->get_col($query);
	}

	function bfox_parse_ref($refStr)
	{
		$chapter1 = $verse1 = $chapter2 = $verse2 = 0;
		
		$list = explode("-", trim($refStr));
		if (count($list) > 2) die("Too many dashes ('-')!");
		
		$left = explode(":", trim($list[0]));
		if (count($left) > 2) die("Too many colons (':')!");
		if (count($left) > 1) $verse1 = (int) $left[1];
		
		$bookparts = explode(" ", trim($left[0]));
		$chapter1 = (int) $bookparts[count($bookparts) - 1];
		if ($chapter1 > 0) array_pop($bookparts);
		$book_name = implode(" ", $bookparts);
		
		if (count($list) > 1)
		{
			$right = explode(":", trim($list[1]));
			if (count($right) > 2) die("Too many colons (':')!");
			if (count($right) > 1)
			{
				$chapter2 = (int) $right[0];
				$verse2 = (int) $right[1];
			}
			else
			{
				if ($verse1 > 0)
					$verse2 = (int) $right[0];
				else
					$chapter2 = (int) $right[0];
			}
		}
		
		$book_id = bfox_get_book_id($book_name);
		$ref['book_id'] = $book_id;
		$ref['book_name'] = bfox_get_book_name($book_id);
		$ref['chapter1'] = $chapter1;
		$ref['verse1'] = $verse1;
		$ref['chapter2'] = $chapter2;
		$ref['verse2'] = $verse2;

		$refStr = bfox_get_refstr($ref);
		
		$ref = bfox_normalize_ref($ref);
		
		return $ref;
	}
	
	function bfox_parse_reflist($reflistStr)
	{
		$reflist = preg_split("/[\n,;]/", trim($reflistStr));
		return $reflist;
	}
	
	function bfox_parse_refs($reflistStr)
	{
		$reflist = bfox_parse_reflist($reflistStr);
		$refs = array();
		foreach ($reflist as $refStr)
		{
			$ref = bfox_parse_ref($refStr);
			if (0 < $ref['book_id']) $refs[] = $ref;
		}
		return $refs;
	}
	
	// Takes a reference and returns the next passage after that reference of the same size
	function bfox_get_ref_next($ref, $factor = 1)
	{
		// NOTE: Currently the function only considers how many chapters are in the ref
		// It will need to consider how many verses as well
		// Also, it doesn't currently handle moving on to the next book of the bible

		// Create the new ref from the old ref
		$newRef = $ref;

		// Calculate how much we should increment our chapter numbers
		$chapDiff = $ref['chapter2'] - $ref['chapter1'];
		$chapInc = 1;
		if (0 < $chapDiff) $chapInc = $chapDiff;
		$chapInc *= $factor;

		// Increment the chapters
		$newRef['chapter1'] += $chapInc;
		if (0 != $newRef['chapter2']) $newRef['chapter2'] += $chapInc;

		return $newRef;
	}

	function bfox_get_posts_equation_for_refs($refs)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;

		$equation = '';
		foreach ($refs as $ref)
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
			
			$range = bfox_get_unique_id_range($ref);
			$begin = $table_name . '.verse_begin';
			$end = $table_name . '.verse_end';
			
			if ('' != $equation) $equation .= " OR ";
			$equation .= $wpdb->prepare("((($begin <= %d) AND ((%d <= $begin) OR (%d <= $end))) OR
										((%d <= $end) AND (($begin <= %d) OR ($end <= %d))))",
										$range[1], $range[0], $range[1],
										$range[0], $range[0], $range[1]);
		}

		return '(' . $equation . ')';
	}

	function bfox_get_posts_for_refs($refs)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;

		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			return array();

		$equation = bfox_get_posts_equation_for_refs($refs);
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
		$ranges = $wpdb->get_results($select, ARRAY_N);

		$refs = array();
		if (is_array($ranges))
		{
			foreach ($ranges as $range)
			{
				$refs[] = bfox_get_ref_for_range($range);
			}
		}
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

?>
