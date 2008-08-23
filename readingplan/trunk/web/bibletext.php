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
	
	// Function for echoing scripture
	function echo_scripture($version, $book=1, $chapter1=0, $verse1=0, $chapter2=0, $verse2=0)
	{
		// Create the reference string
		$refStr = "";
		if ($chapter1 != 0)
		{
			$refStr .= " $chapter1";
			if ($verse1 != 0)
				$refStr .= ":$verse1";
			if ($chapter2 != 0)
			{
				$refStr .= "-$chapter2";
				if ($verse2 != 0)
					$refStr .= ":$verse2";
			}
			else if ($verse2 != 0)
				$refStr .= "-$verse2";
		}
		
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

		// When verse2 is not set (== 0): verse2 equals max unless chapter2 is not set and verse1 is set
		if ($verse2 == 0)
		{
			$verse2 = MAX_VERSE;
			if (($verse1 != 0) && ($chapter2 == 0))
				$verse2 = $verse1;
		}

		// When chapter2 is not set (== 0): chapter2 equals chapter1 unless chapter1 is not set
		if ($chapter2 == 0)
		{
			$chapter2 = ($chapter1 == 0) ? MAX_CHAPTER : $chapter1;
		}
		
		$book_name = get_book_name($book);
		echo "<h1>$book_name$refStr</h1>";
		
		$start_id = get_verse_unique_id($book, $chapter1, $verse1);
		$finish_id = get_verse_unique_id($book, $chapter2, $verse2);
		
		$query = sprintf("select verse_id, verse from %s_verses where unique_id >= %d and unique_id <= %d",
						 mysql_real_escape_string($version),
						 mysql_real_escape_string($start_id),
						 mysql_real_escape_string($finish_id));
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
	
?>
