<?php
	include("bibletext.php");
	connect_to_bible();

	function get_sections($text, $size)
	{
		$reflist = parse_reflist($text);

		$period = 0;
		$section = 0;
		$remainder = 0;
		$remainderStr = "";
		foreach ($reflist as $refStr)
		{
			$ref = parse_ref($refStr);
			$chapters = get_chapters($ref);
			$num_chapters = count($chapters);
			$num_sections = (int) floor(($num_chapters + $remainder) / $size);

			$tmpRef['book_name'] = $ref['book_name'];
			$chapter1_index = 0;
			$chapter2_index = $size - $remainder - 1;
			for ($index = 0; $index < $num_sections; $index++)
			{
				$tmpRefStr = "";
				if (($index == 0) && ($remainder > 0))
				{
					$tmpRefStr .= "$remainderStr, ";
					$remainderStr = "";
					$remainder = 0;
				}

				$tmpRef['chapter1'] = $chapters[$chapter1_index];
				if ($chapter2_index > $chapter1_index)
					$tmpRef['chapter2'] = $chapters[$chapter2_index];
				else $tmpRef['chapter2'] = 0;

				$tmpRefStr .= get_refstr($tmpRef);
				$sections[] = $tmpRefStr;

				$chapter1_index = $chapter2_index + 1;
				$chapter2_index = $chapter1_index + $size - 1;
			}

			if ($chapter1_index < $num_chapters)
			{
				$remainder += $num_chapters - $chapter1_index;
				$chapter2_index = $num_chapters - 1;

				$tmpRef['chapter1'] = $chapters[$chapter1_index];
				if ($chapter2_index > $chapter1_index)
					$tmpRef['chapter2'] = $chapters[$chapter2_index];
				else $tmpRef['chapter2'] = 0;

				if ($remainderStr != "")
					$remainderStr .= ", ";
				$remainderStr .= get_refstr($tmpRef);
			}
		}
		if ($remainderStr != "")
			$sections[] = $remainderStr;
		
		return $sections;
	}
	
	$text = (string) $_POST['books'];
	$period_length = (string) $_POST['frequency'];
	$section_size = (int) $_POST['num_chapters'];
	if ($section_size == 0) $section_size = 1;
	
	$sections = get_sections($text, $section_size);
	
	$index = 1;
	foreach ($sections as $section)
	{
		echo "<br/>$period_length $index: $section";
		$index++;
	}
	
?>
