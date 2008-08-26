<?php
	function connect_to_bible()
	{
		mysql_connect("localhost", "biblefox", "bfoxword") or die(mysql_error());
		mysql_select_db("bibletext") or die(mysql_error());
	}

	define("MAX_CHAPTER", 0xFF);
	define("MAX_VERSE", 0xFF);
	
	function get_verse_unique_id($book, $chapter, $verse)
	{
		return ($book << 16) + ($chapter << 8) + $verse;
	}

	function get_verse_ref_from_unique_id($unique_id)
	{
		$mask = 0xFF;
		return array((($book >> 16) & $mask), (($chapter >> 8) & $mask), ($verse & $mask));
	}

	function get_book_id($book)
	{
		// Strip beginning and ending whitespace
		$book = trim($book);
		
		$query = sprintf("select book_id from synonyms where synonym like '%s'",
						 mysql_real_escape_string($book));
		$result = mysql_query($query) or die(mysql_error());
		$row = mysql_fetch_array($result);
		
		return $row['book_id'];
	}
	
	function get_book_name($id)
	{
		$query = sprintf("select name from books where id = '%s'",
						 mysql_real_escape_string($id));
		$result = mysql_query($query) or die(mysql_error());
		$row = mysql_fetch_array($result);
		
		return $row['name'];
	}

	function normalize_ref($ref)
	{
		$normal_keys = array('chapter1', 'verse1', 'chapter2', 'verse2');

		// Set all the normal keys to 0 if they are not already set
		foreach ($normal_keys as $key)
			if (!isset($ref[$key]))
				$ref[$key] = 0;
		
		return $ref;
	}
	
	function get_refstr($ref)
	{
		$ref = normalize_ref($ref);

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

	function get_unique_id_range($ref)
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
		
		$ref = normalize_ref($ref);

		// When verse2 is not set (== 0): verse2 equals max unless chapter2 is not set and verse1 is set
		if ($ref['verse2'] == 0)
		{
			$ref['verse2'] = MAX_VERSE;
			if (($ref['verse1'] != 0) && ($ref['chapter2'] == 0))
				$ref['verse2'] = $ref['verse1'];
		}
		
		// When chapter2 is not set (== 0): chapter2 equals chapter1 unless chapter1 is not set
		if ($ref['chapter2'] == 0)
		{
			$ref['chapter2'] = ($ref['chapter1'] == 0) ? MAX_CHAPTER : $ref['chapter1'];
		}
		
		$range[0] = get_verse_unique_id($ref['book_id'], $ref['chapter1'], $ref['verse1']);
		$range[1] = get_verse_unique_id($ref['book_id'], $ref['chapter2'], $ref['verse2']);

		return $range;
	}
	
	// Function for echoing scripture
	function echo_scripture($version, $ref)
	{
		$refStr = get_refstr($ref);
		echo "<title>Biblefox: $refStr</title>";
		echo "<h1>$refStr</h1>";
		
		$range = get_unique_id_range($ref);
		
		$query = sprintf("select verse_id, verse from %s_verses where unique_id >= %d and unique_id <= %d",
						 mysql_real_escape_string($version),
						 mysql_real_escape_string($range[0]),
						 mysql_real_escape_string($range[1]));
		$result = mysql_query($query) or die(mysql_error());
		
		while($row = mysql_fetch_array($result))
		{
			$verse_id = $row['verse_id'];
			if ($verse_id != 0)
			{
				echo "<sup>$verse_id</sup>";
			}
			echo $row['verse'];
		}
	}
	
	function get_chapters($ref)
	{
		// HACK: We need to let the user pick their own version
		$version = "asv";

		$range = get_unique_id_range($ref);
		
		$query = sprintf("select chapter_id, verse from %s_verses where unique_id >= %d and unique_id <= %d group by chapter_id",
						 mysql_real_escape_string($version),
						 mysql_real_escape_string($range[0]),
						 mysql_real_escape_string($range[1]));
		$result = mysql_query($query) or die(mysql_error());
		
		while($row = mysql_fetch_array($result))
		{
			$chapter = (int) $row['chapter_id'];
			if ($chapter > 0)
				$chapters[] = $chapter;
		}
		
		return $chapters;
	}

	function parse_ref($refStr)
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
		
		$book_id = get_book_id($book_name);
		$ref['book_id'] = $book_id;
		$ref['book_name'] = get_book_name($book_id);
		$ref['chapter1'] = $chapter1;
		$ref['verse1'] = $verse1;
		$ref['chapter2'] = $chapter2;
		$ref['verse2'] = $verse2;

		$refStr = get_refstr($ref);
		
		$ref = normalize_ref($ref);
		
		return $ref;
	}
	
	function parse_reflist($reflistStr)
	{
		$reflist = preg_split("/[\n,;]/", trim($reflistStr));
		return $reflist;
	}
	
?>
