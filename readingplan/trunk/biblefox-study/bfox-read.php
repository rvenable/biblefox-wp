<?php

	global $wpdb;

	// Get all enabled translations
	$translations = $wpdb->get_results("SELECT id, short_name FROM " . BFOX_TRANSLATIONS_TABLE . " WHERE is_enabled = TRUE ORDER BY is_default DESC");
	if (isset($_GET['trans_id']))
	{
		$trans_id = $_GET['trans_id'];
	}
	else
	{
		$trans_id = $translations[0]->id;
	}

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
<?php
	$header = 'name="trans"';
	echo '<select name="trans_id">';
	foreach ($translations as $translation)
	{
		echo "<option value = \"$translation->id\"";
		if ($translation->id == $trans_id) echo " selected";
		echo ">$translation->short_name</option>";
	}
	echo '</select>';
	?>
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
			bfox_echo_scripture($trans_id, $ref);
		}
	}
	else
	{
/*		$ref['book'] = $_GET['book'];
		$ref['chapter1'] = $_GET['chapter1'];
		$ref['verse1'] = $_GET['verse1'];*/
		bfox_echo_scripture($trans_id, $_GET);
	}

?>
</div>
