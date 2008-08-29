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
		return array((($book >> 16) & $mask), (($chapter >> 8) & $mask), ($verse & $mask));
	}

	function bfox_get_book_id($book)
	{
		// HACK: BFOX_SYNONYMS_TABLE is in bfox-translations.php but should be in some common file
		require_once("bfox-setup.php");

		global $wpdb;
		$query = $wpdb->prepare("SELECT book_id FROM " . BFOX_SYNONYMS_TABLE . " WHERE synonym LIKE %s", trim($book));
		return $wpdb->get_var($query);
	}
	
	function bfox_get_book_name($book_id)
	{
		// HACK: BFOX_BOOKS_TABLE is in bfox-translations.php but should be in some common file
		require_once("bfox-setup.php");
		
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

		// Create the reference string
		$refStr = "{$ref['book_name']}";
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
	
	// Function for echoing scripture
	function bfox_echo_scripture($version_id, $ref)
	{
		global $wpdb;

		$refStr = bfox_get_refstr($ref);
//		echo "<title>Biblefox: $refStr</title>";
		echo "<h2>$refStr</h2>";
		
		$range = bfox_get_unique_id_range($ref);

		// HACK: The bfox_get_verses_table_name function is in bfox-translations.php but should be in some common file
		require_once("bfox-translations.php");
		$table_name = bfox_get_verses_table_name($version_id);

		$query = $wpdb->prepare("SELECT verse_id, verse
								FROM " . $table_name . "
								WHERE unique_id >= %d
								AND unique_id <= %d",
								$range[0],
								$range[1]);
		$verses = $wpdb->get_results($query);
		
		foreach ($verses as $verse)
		{
			if ($verse->verse_id != 0)
				echo "<sup>$verse->verse_id</sup>";
			echo $verse->verse;
		}
	}
	
	function bfox_get_chapters($ref)
	{
		global $wpdb;

		// HACK: We need to let the user pick their own version
		$version_id = 13;

		$range = bfox_get_unique_id_range($ref);

		// HACK: The bfox_get_verses_table_name function is in bfox-translations.php but should be in some common file
		require_once("bfox-translations.php");
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
	
?>
