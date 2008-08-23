<?php
	include("bibletext.php");
	connect_to_bible();

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
		
		$ref['book'] = get_book_id($book_name);
		$ref['chapter1'] = $chapter1;
		$ref['verse1'] = $verse1;
		$ref['chapter2'] = $chapter2;
		$ref['verse2'] = $verse2;

		return $ref;
	}
	
	$version = "asv";
	$ref = trim($_GET['ref']);
	if ($ref != "")
	{
		$reflist = explode(";", trim($ref));
		foreach ($reflist as $refStr)
		{
			$ref = parse_ref($refStr);
			echo_scripture($version, $ref['book'], $ref['chapter1'], $ref['verse1'], $ref['$chapter2'], $ref['$verse2']);
		}
	}
	else
	{
		$book_name = $_GET['book'];
		$book = get_book_id($book_name);
		$chapter1 = (int) $_GET['chapter1'];
		$verse1 = (int) $_GET['verse1'];
		$chapter2 = (int) $_GET['chapter2'];
		$verse2 = (int) $_GET['verse2'];

		echo_scripture($version, $book, $chapter1, $verse1, $chapter2, $verse2);
	}

?>
