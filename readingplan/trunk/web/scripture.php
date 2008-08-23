<?php
	include("bibletext.php");
	connect_to_bible();

	$version = "asv";
	$reflistStr = trim($_GET['ref']);
	if ($reflistStr != "")
	{
		$reflist = parse_reflist($reflistStr);
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
