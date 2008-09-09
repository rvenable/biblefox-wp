<?php

	function bfox_read_menu()
	{
		require_once('bfox-history.php');
		
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
		
		// Create a list of bible references to show
		// If the user passed a list through the GET parameter use that
		$reflistStr = trim($_GET['ref']);
		if ($reflistStr == '')
		{
			// If there are no GET references then get the last viewed references
			$refs = bfox_get_last_viewed_refs();
		}
		else
		{
			// Create a list of references from the passed in GET param
			$reflist = bfox_parse_reflist($reflistStr);
			$refs = array();
			foreach ($reflist as $refStr) $refs[] = bfox_parse_ref($refStr);
		}
		
		// If we don't have any refs, show Genesis 1
		if (0 == count($refs)) $refs[] = bfox_parse_ref('Genesis 1');
		$refs = bfox_get_next_refs($refs, $_GET['bfox_action']);
		
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
		// If we have at least one scripture reference
		if (0 < count($refs))
		{
			foreach ($refs as $ref) $refStrs[] = bfox_get_refstr($ref);
			$refStr = implode('; ', $refStrs);
			echo "<h2>$refStr</h2>";
			echo bfox_get_ref_menu_header($refStr);
			
			$post_ids = bfox_get_posts_for_refs($refs);
			if (0 < count($post_ids))
			{
				echo "Posts about this scripture:<br/>";
				foreach ($post_ids as $post_id)
				{
					$my_post = get_post($post_id);
					echo "<a href=\"post.php?action=edit&post=$post_id\">$my_post->post_title</a><br/>";
				}
			}
			
			// Output all the scripture references
			foreach ($refs as $ref) bfox_echo_scripture($trans_id, $ref);
			echo bfox_get_ref_menu_footer($refStr);
			
			// Update the read history to show that we viewed these scriptures
			bfox_update_table_read_history($refs);
		}
		echo "</div>";
	}

?>
