<?php
	include("bibletext.php");
	connect_to_bible();

	function get_num_chapters($books)
	{
	}
	
	function create_plan($books)
	{
		$query = "select book_id from ${version}_verses group by book_id, chapter_id";
		$result = mysql_query($query) or die(mysql_error());
		$chapters = mysql_num_rows($result);

		$query = "select book_id from ${version}_verses where verse_id != 0";
		$result = mysql_query($query) or die(mysql_error());
		$verses = mysql_num_rows($result);

		$partitions = $chapters / $num;
		echo "$chapters Chapters<br/>";
		echo "$verses Verses<br/>";
		echo "$partitions Partitions<br/>";
	}
	
	function parse_books($text)
	{
		$lines = explode("\n", $text);
		foreach ($lines as $reflistStr)
		{
			$reflist = parse_reflist($reflistStr);
			$line = "";

			foreach ($reflist as $refStr)
			{
				if ($line != "")
					$line .= ", ";
				$ref = parse_ref($refStr);
				$line .= get_refstr($ref);
			}

			if ($line != "")
			{
				$books[] = "$line\n";
			}
		}
		return $books;
	}
	
	$text = $_POST['books'];
	$books = parse_books($text);
	foreach ($books as $book)
	{
		echo "$book<br/>";
	}
	
	$unique_id = get_verse_unique_id(2, 1, 5);
	//list
	list($book, $chapter, $verse) = get_verse_ref_from_unique_id($unique_id);
	echo "$unique_id,$book,$chapter,$verse<br/>";
?>
