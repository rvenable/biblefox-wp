<?php

function bfox_bible_passage_ref_content(BibleRefs $refs)
{
	$visible = $refs->sql_where();
	$bcvs = BibleRefs::get_bcvs($refs->get_seqs());
	foreach ($bcvs as $book => $cvs)
	{
		$book_str = BibleRefs::create_book_string($book, $cvs);

		foreach ($cvs as $cv)
		{
			if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
			list($ch2, $vs2) = $cv->end;
		}

		// Get the previous and next chapters as well
		$ch1 = max($ch1 - 1, 1);
		$ch2 = min($ch2 + 1, bfox_get_num_chapters($book));

		$content .= "
			<div class='ref_partition'>
				<div class='partition_header box_menu'>" . $book_str . " (Context:
					<a onclick='bfox_set_context_none(this)'>none</a>
					<a onclick='bfox_set_context_verses(this)'>verses</a>
					<a onclick='bfox_set_context_chapters(this)'>chapters</a>)
				</div>
				<div class='partition_body'>
					" . Translations::get_chapters_content($book, $ch1, $ch2, $visible) . "
				</div>
			</div>
			";
	}

	return $content;
}


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
	<div id="bible_note_popup"></div>
	<div class="roundbox">
		<div class="box_head">
			<?php echo $ref_str ?>
			<form id="bible_view_search" action="admin.php" method="get">
				<a id="verse_layout_toggle" class="button" onclick="bfox_toggle_paragraphs()">Switch to Verse View</a>
				<input type="hidden" name="page" value="<?php echo BFOX_BIBLE_SUBPAGE ?>" />
				<input type="hidden" name="<?php echo Bible::var_page ?>" value="<?php echo Bible::page_passage ?>" />
				<input type="hidden" name="<?php echo Bible::var_reference ?>" value="<?php echo $ref_str ?>" />
					<?php Translations::output_select($bfox_trans->id) ?>
				<input type="submit" value="Go" class="button">
			</form>
		</div>
		<div>
			<div class="commentary_list">
				<div class="commentary_list_head">
					Commentary Blog Posts (<a href="<?php echo BfoxLinks::bible_page_url(Bible::page_commentary) ?>">edit</a>)
				</div>
				<?php Commentaries::output_posts($refs); ?>
				<?php //Bible::output_quick_press(); ?>
			</div>
			<div class="reference">
				<?php echo bfox_bible_passage_ref_content($refs); ?>
			</div>
			<div class="clear"></div>
		</div>
		<div class="box_menu">
			<?php echo $refs->get_toc(TRUE); ?>
		</div>
	</div>
</div>

<?php

// Update the read history to show that we viewed these scriptures
$bfox_history->update($refs);

?>
