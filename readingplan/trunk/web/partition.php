<?php
	include("bibletext.php");
	connect_to_bible();
	
	function partition($version, $num)
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
	
	partition("asv", 52);
?>
