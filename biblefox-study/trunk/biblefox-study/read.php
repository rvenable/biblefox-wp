<?php

	function bfox_read_menu()
	{
		global $wpdb;
		global $bfox_history;

		// Override the global translation using the translation passed in
		global $bfox_trans;
		$bfox_trans = new Translation($_GET['trans_id']);

		// Get the search text input
		$search = trim($_GET['bible_ref']);
		if (empty($search))
		{
			// If there is no search text then get the last viewed references
			list($refs) = $bfox_history->get_refs_array();
			$search = $refs->get_string();
		}

		// If we still don't have search text, use Genesis 1
		if (empty($search))
		{
			$search = 'Genesis 1';
		}


	?>

<div class="wrap">
<form id="posts-filter" action="admin.php" method="get">
<input type="hidden" name="page" value="<?php echo BFOX_READ_SUBPAGE; ?>" />
<p id="post-search">
	<?php bfox_translation_select($bfox_trans->id) ?>
<input type="text" id="post-search-input" name="bible_ref" value="<?php echo $reflistStr; ?>" />
<input type="submit" value="<?php _e('Search Bible', BFOX_DOMAIN); ?>" class="button" />
</p>
</form>
<a id="quick_view_button">Quick View</a>
<div id="verse_select_box">
	<div id="verse_select_more_info">
	More info...
	</div>
	<div id="verse_select_menu">
		<h1 id="verse_selected"><?php echo $refStr; ?></h1>
		<ul>
			<li><a href="">Copy text without verse numbers</a></li>
			<li><a href="">View in Quick View</a></li>
			<li><a href="">Create a quick note</a></li>
		</ul>
	</div>
	<div id="edit_quick_note">
		<form action="" id="edit_quick_note_form">
			<input type="hidden" value="" id="edit_quick_note_id" />
			<textarea rows="1" style="width: 100%; height: auto;" class="edit_quick_note_input" id="edit_quick_note_text"></textarea>
			<input type="button" value="<?php _e('Save'); ?>" class="button edit_quick_note_input" onclick="bfox_save_quick_note()" />
			<input type="button" value="<?php _e('New Note'); ?>" class="button edit_quick_note_input" onclick="bfox_new_quick_note()" />
			<input type="button" value="<?php _e('Delete'); ?>" class="button edit_quick_note_input" onclick="bfox_new_quick_note()" />
			<div id="edit_quick_note_progress"></div>
		</form>
	</div>
</div>

<?php
		echo "<h2 id='bible_text_main_ref'>Bible Viewer</h2>";
		echo bfox_bible_view($search);
		$refs = new BibleRefs($search);
		echo '<div id="bible_quick_view">';
		echo '<p>This is the bible quick view. Try viewing ' . $refs->get_link(NULL, 'quick') . '</p>';
		?>
<strong><p id="bible-text-progress"></p></strong>
<input type="hidden" name="bible-request-url" id="bible-request-url" value="<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" />
<div id="bible-text"></div>

		<?php
		echo '</div>';
		global $bfox_quicknote;
		echo '<table id="quick_note_list">';
		echo $bfox_quicknote->list_quicknotes($refs);
		echo '</table>';

		// Update the read history to show that we viewed these scriptures
		$bfox_history->update($refs);

/*	TODO2: Make sure everything on this list is in a task, then remove this list
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
	echo '<h3>Greek</h3>';*/

		echo "</div>";
	}

?>
