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
			echo_scripture($version, $ref);
		}
	}
	else
	{
/*		$ref['book'] = $_GET['book'];
		$ref['chapter1'] = $_GET['chapter1'];
		$ref['verse1'] = $_GET['verse1'];*/
		echo_scripture($version, $_GET);
	}

?>
