<div class="wrap">
<?php
	include("bibletext.php");

	$version = 13;
//	$reflistStr = trim($_GET['ref']);
	$reflistStr = "genesis 1";
	if ($reflistStr != "")
	{
		$reflist = bfox_parse_reflist($reflistStr);
		foreach ($reflist as $refStr)
		{
			$ref = bfox_parse_ref($refStr);
			bfox_echo_scripture($version, $ref);
		}
	}
	else
	{
/*		$ref['book'] = $_GET['book'];
		$ref['chapter1'] = $_GET['chapter1'];
		$ref['verse1'] = $_GET['verse1'];*/
		bfox_echo_scripture($version, $_GET);
	}

?>
</div>
