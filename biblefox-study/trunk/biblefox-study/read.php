<?php

	function bfox_read_menu()
	{
		global $wpdb, $bfox_history, $bfox_quicknote, $bfox_trans, $bfox_translations;

		// Override the global translation using the translation passed in
		$bfox_trans = new Translation($_GET['trans_id']);
		$translation = $bfox_translations->get_translation($bfox_trans->id);

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

		$refs = new BibleRefs($search);
		$bfox_quicknote->set_biblerefs($refs);

	?>

<div class="" id="bible_tool">
<h2 id='bible_text_main_ref'>Bible Viewer</h2>
	<div id="bible_note_popup"></div>
	<div id="verse_select_box">
		<a href="#close" id="verse_select_box_close" onclick="bfox_close_select_box()">X Close</a>
		<div id="verse_select_menu">
			<h1 id="verse_selected"><?php echo $refStr; ?></h1>
			<ul>
				<li><a href="">Commentaries</a></li>
				<li><a href="">View text without verse numbers</a></li>
				<li><a href="">View in Quick View</a></li>
				<li><a href="">Create a quick note</a></li>
			</ul>
		</div>
		<div id="edit_quick_note">
			<form action="" id="edit_quick_note_form">
				Enter note text:
				<input type="hidden" value="" id="edit_quick_note_id" />
				<textarea rows="1" style="width: 100%; height: auto;" class="edit_quick_note_input" id="edit_quick_note_text"></textarea>
				<input type="text" id="quick_note_bible_ref" name="quick_note_bible_ref" value="" disabled />
				<input type="button" value="<?php _e('Save'); ?>" class="button edit_quick_note_input" onclick="bfox_save_quick_note()" />
				<input type="button" value="<?php _e('New Note'); ?>" class="button edit_quick_note_input" onclick="bfox_new_quick_note()" />
				<input type="button" value="<?php _e('Delete'); ?>" class="button edit_quick_note_input" onclick="bfox_delete_quick_note()" />
				<div id="edit_quick_note_progress"></div>
			</form>
		</div>
	</div>
	<div id="bible_tool_header">
		<form id="bible_view_search" action="admin.php" method="get">
			<input type="hidden" name="page" value="<?php echo BFOX_READ_SUBPAGE; ?>" />
				<?php bfox_translation_select($bfox_trans->id) ?>
			<input type="text" name="bible_ref" value="<?php echo $reflistStr; ?>" />
			<input type="submit" value="<?php _e('Search Bible', BFOX_DOMAIN); ?>" class="button" />
		</form>
		<ul id="bible_tool_options">
			<li><a id="verse_layout_toggle" class="button" onclick="bfox_toggle_paragraphs()">Switch to Verse View</a></li>
			<li><a class="button" onclick="bfox_toggle_quick_view()">Quick View</a></li>
		</ul>
	</div>
	<div id="bible_tool_body">
		<div id="bible_view">
			<?php if ($refs->is_valid()): ?>
			<div id="bible_view_header">
				<h3><?php echo $refs->get_string() . " ($translation->short_name)" ?></h3>
				<?php echo bfox_get_ref_menu($refs, TRUE) ?>
			</div>
			<div id="bible_view_content">
				<?php echo bfox_get_ref_content($refs) ?>
			</div>
			<div id="bible_view_footer">
				<?php
					echo bfox_get_ref_menu($refs, FALSE);
					echo $refs->get_toc(TRUE);
				?>
			</div>
			<?php elseif (!empty($text)):
				// TODO2: use leftovers from ref detection to create search results
				// if (isset($refs->leftovers)) bfox_bible_text_search($refs->leftovers);

				// Ref not valid, so perform search results

				echo bfox_bible_text_search($text);
	/*
				$content .= bfox_bible_text_search('"' . $text . '"');

				$text = '+' . implode(' +', explode(' ', $text));
				$content .= bfox_bible_text_search($text);
	*/
			endif; ?>
		</div>
		<div id="bible_quick_view">
			<div id="bible_quick_view_header">
				<ul class="bible_quick_view_menu">
					<li><a class="button" onclick="bfox_select_quick_view('bible_quick_view_scripture')">Scripture</a></li>
					<li><a class="button" onclick="bfox_select_quick_view('bible_quick_view_blogs')">Blogs</a></li>
					<li><a class="button" onclick="bfox_select_quick_view('bible_quick_view_dict')">Dictionary</a></li>
					<li><a class="button" onclick="bfox_select_quick_view('bible_quick_view_forum')">Forum</a></li>
					<li><a class="button" onclick="bfox_select_quick_view('bible_quick_view_audio')">Audio</a></li>
				</ul>
				<div id="bible_quick_view_scripture_header" class="bible_quick_view_menu_option">
					<h4 id="bible-text-progress">No Scripture</h4>
					<?php bfox_translation_select($bfox_trans->id, TRUE) ?>
					<input type="text" name="new-bible-ref" id="new-bible-ref" size="16" value="" />
					<input type="button" class="button" id="view-bible-ref" value="Search" tabindex="3" />
					<input type="hidden" name="bible-request-url" id="bible-request-url" value="<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" />
					<div id="bible_quick_view_scripture_menu"></div>
				</div>
				<div id="bible_quick_view_blogs_header" class="bible_quick_view_menu_option">
					This will display blog entries for this scripture.
				</div>
				<div id="bible_quick_view_dict_header" class="bible_quick_view_menu_option">
					This will display dictionary entries for this scripture.
				</div>
				<div id="bible_quick_view_forum_header" class="bible_quick_view_menu_option">
					This will display forum discussions for this scripture.
				</div>
				<div id="bible_quick_view_audio_header" class="bible_quick_view_menu_option">
					This will display audio bibles for this scripture.
				</div>
			</div>
			<div id="bible_quick_view_body">
				<div id="bible_quick_view_scripture_body" class="bible_quick_view_menu_option">
					<div id="bible-text">
						<p>This is the bible quick view. Try viewing <?php echo $refs->get_link(NULL, 'quick') ?></p>
					</div>
				</div>
				<div id="bible_quick_view_blogs_body" class="bible_quick_view_menu_option">
					<?php Commentaries::output_posts($refs); ?>
				</div>
				<div id="bible_quick_view_dict_body" class="bible_quick_view_menu_option">
					This will display dictionary entries for this scripture.
				</div>
				<div id="bible_quick_view_forum_body" class="bible_quick_view_menu_option">
					This will display forum discussions for this scripture.
				</div>
				<div id="bible_quick_view_audio_body" class="bible_quick_view_menu_option">
					This will display audio bibles for this scripture.
				</div>
			</div>
		</div>
	</div>
	<div id="bible_tool_footer">
	</div>
</div>

<?php
/*		echo '<table id="quick_note_list">';
		echo $bfox_quicknote->list_quicknotes();
		echo '</table>';*/

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

	}

?>
