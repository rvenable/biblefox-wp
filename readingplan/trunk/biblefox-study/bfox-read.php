<?php
	include("bibletext.php");
	
	$version = 13;
	$reflistStr = trim($_GET['ref']);
	if ($reflistStr == '')
	{
		$reflistStr = "genesis 1";
	}
?>

<div class="wrap">
<form id="posts-filter" action="admin.php" method="get">
<input type="hidden" name="page" value="<?php echo BFOX_READ_SUBPAGE; ?>" />
<p id="post-search">
<input type="text" id="post-search-input" name="ref" value="<?php echo $reflistStr; ?>" />
<input type="submit" value="<?php _e('Search Bible', BFOX_DOMAIN); ?>" class="button" />
</p>
</form>

<?php
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
