<?php

global $bfox_history, $bfox_quicknote, $bfox_trans;

if (!isset($refs)) $refs = RefManager::get_from_str($_GET[Bible::var_reference]);

// If we don't have a valid bible ref, we should just create a bible reference
if (!$refs->is_valid())
{
	// First try to create a BibleRefs from the last viewed references
	list($refs) = $bfox_history->get_refs_array();

	// If there is no history, use Genesis 1
	// TODO3: Test this
	if (!isset($refs) || !$refs->is_valid()) $refs = RefManager::get_from_str('Genesis 1');
}

$bfox_quicknote->set_biblerefs($refs);

$ref_str = $refs->get_string();

?>

<div id="bible_passage">
	<div class="page_head">
		Bible Passage
		<ul id="bible_tool_options">
			<li><a id="verse_layout_toggle" class="button" onclick="bfox_toggle_paragraphs()">Switch to Verse View</a></li>
			<li><a class="button" onclick="bfox_toggle_quick_view()">Commentaries</a></li>
		</ul>
	</div>
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
	<div class="roundbox">
		<div class="box_head">
			<?php echo $ref_str ?>
			<form id="bible_view_search" action="admin.php" method="get">
				<input type="hidden" name="page" value="<?php echo BFOX_BIBLE_SUBPAGE ?>" />
				<input type="hidden" name="<?php echo Bible::var_page ?>" value="<?php echo Bible::page_passage ?>" />
				<input type="hidden" name="<?php echo Bible::var_reference ?>" value="<?php echo $ref_str ?>" />
					<?php Translations::output_select($bfox_trans->id) ?>
				<input type="submit" value="Go" class="button">
			</form>
		</div>
		<div class="box_menu">
			<?php echo bfox_get_ref_menu($refs, TRUE) ?>
		</div>
		<div class="box_inside">
			<div class="commentary_list">
				<div class="commentary_list_head">
					Commentary Blog Posts (<a href="<?php echo $bfox_links->bible_page_url(Bible::page_commentary) ?>">edit</a>)
				</div>
				<?php Commentaries::output_posts($refs); ?>
			</div>
			<?php echo bfox_get_ref_content($refs) ?>
			<div class="clear"></div>
		</div>
		<div class="box_menu">
			<?php
				echo bfox_get_ref_menu($refs, FALSE);
				echo $refs->get_toc(TRUE);
			?>
		</div>
	</div>
</div>

<?php

// Update the read history to show that we viewed these scriptures
$bfox_history->update($refs);

?>
