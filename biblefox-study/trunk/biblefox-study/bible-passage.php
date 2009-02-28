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
		</ul>
	</div>
	<div id="bible_note_popup"></div>
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
				<?php Bible::output_quick_press(); ?>
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
