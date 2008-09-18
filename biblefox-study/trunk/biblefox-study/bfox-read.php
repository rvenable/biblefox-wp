<?php

	function bfox_read_menu()
	{
		global $wpdb;
		global $bfox_history;
		
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
		$refStr = trim($_GET['bible_ref']);
		if ($refStr == '')
		{
			// If there are no GET references then get the last viewed references
			list($refs) = $bfox_history->get_refs_array();
		}
		else
		{
			// Create a list of references from the passed in GET param
			$refs = new BibleRefs($refStr);
		}

		// If we don't have any refs, show Genesis 1
		if (0 == $refs->get_count()) $refs->push_string('Genesis 1');
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
		if (0 < $refs->get_count())
		{
			$refStr = $refs->get_string();
			echo "<h2>$refStr</h2>";
			echo bfox_get_ref_menu($refs, true);
			
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
			bfox_echo_scripture($trans_id, $refs);
			echo bfox_get_ref_menu($refs, false);
			
			// Update the read history to show that we viewed these scriptures
			$bfox_history->update($refs);
		}
		echo "</div>";
	}

?>
