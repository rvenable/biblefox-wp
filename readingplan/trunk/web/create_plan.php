<?php
	include("bibletext.php");
	connect_to_bible();

	function get_chapter_list($text)
	{
		$reflist = parse_reflist($text);
		
		foreach ($reflist as $refStr)
		{
			$ref = parse_ref($refStr);
			$chapters = get_chapters($ref);
			$tmpRef['book_name'] = $ref['book_name'];
			foreach ($chapters as $chapter)
			{
				if ($chapter != 0)
				{
					$tmpRef['chapter1'] = $chapter;
					
					$chapter_list[] = get_refstr($tmpRef);
				}
			}
		}
		
		return $chapter_list;
	}
	
	$text = (string) $_POST['books'];
	$period_length = (string) $_POST['frequency'];
	$section_size = (int) $_POST['num_chapters'];
	if ($section_size == 0) $section_size = 1;
	
	$chapter_list = get_chapter_list($text);
	
	$period = 0;
	$section = 0;
	foreach ($chapter_list as $chapter)
	{
		if ($period % $section_size == 0)
		{
			$section++;
			echo "<br/>$period_length $section: $chapter";
		}
		else
			echo ", $chapter";
		$period++;
	}
	
?>
