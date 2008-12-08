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
		if (!isset($refs)) $refs = new BibleRefs('Genesis 1');
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
<input type="text" id="post-search-input" name="bible_ref" value="<?php echo $reflistStr; ?>" />
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
	echo '<h2>Blog Post Commentaries</h2>';
	echo '<p><a href="">Write A Post</a></p>';
	echo '<h3>My Bible Study Blogs</h3><p>View posts from any Biblefox Bible Studies that you have joined or subscribed to.<br/>Check out the list of Commentary Blogs to find some you can subscribe to.<br/><a href="">Add Commentaries</a></p>';
	echo '<h3>My Friend Commentaries</h3><p>You can see what other users have written about this passage.<br/><a href="">Add Friends</a></p>';
	echo '<h2>Tools</h2>';
	echo '<h3>Bible Search</h3><ul><li>Reference</li><li>Text</li><li>Topic</li></ul>';
	echo '<h3>Random Passage</h3>';
	echo '<h3>My Reading Plans</h3>';
	echo '<h3>Create A Reading Plan</h3>';
	echo '<h3>Side by Side Passages</h3>';
	echo '<h3>Table of Contents</h3>';
	echo '<h3>Quick Table of Contents</h3>';
	echo '<h3>Bible Dictionary</h3>';
	echo '<h3>Bible Forum</h3>';
	echo '<h3>Bible Wiki</h3>';
	echo '<h3>Bible By Email</h3>';
	echo '<h3>Topical Cross References</h3><p>From the Topical Search</p>';
	echo '<h3>Hebrew</h3>';
	echo '<h3>Greek</h3>';

		echo "</div>";
	}

?>
