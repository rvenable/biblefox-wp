<?php

global $bp_bible_search;
$verses = $bp_bible_search->search_boolean();

?>

	<div class="bible-search-results-pagination pagination">
		<div class="pag-count">
			<form action="<?php echo $bp_bible_search->get_url() ?>" method="get">
				<?php $bp_bible_search->output_page_num_description() ?> &nbsp;
				<select class="bible-search-trans-select">
					<?php echo $bp_bible_search->trans_select_options() ?>
				</select>
				&nbsp;<span class="ajax-loader"></span>
			</form>
		</div>
		<div class="pagination-links">
			<?php echo $bp_bible_search->page_links() ?>
		</div>
	</div>

	<?php $chapters = $bp_bible_search->chapter_content($verses) ?>

		<?php if ( !empty($chapters) ) : ?>

		<?php do_action( 'bp_before_bible_note_list' ) ?>

		<ul id="bible-search-results-list" class="item-list">
		<?php foreach ($chapters as $chapter_ref_str => $verses): ?>
			<li class="bible-serach-result-chapter">
				<div>
					<div class="bible-search-result-verse-text"><?php echo bp_bible_ref_link(array('ref_str' => $chapter_ref_str)) ?></div>
				</div>
				<?php foreach ($verses as $verse_ref_str => $verse): ?>
				<div class="bible-search-result-verse">
					<div class="bible-search-result-verse-ref"><a href="<?php echo bp_bible_ref_url($verse_ref_str) ?>"><?php echo $verse_ref_str ?></a></div>
					<div class="bible-search-result-verse-text"><?php echo $verse ?></div>
				</div>
				<?php endforeach ?>
			</li>
		<?php endforeach ?>
		</ul>

		<?php do_action( 'bp_after_bible_note_list' ) ?>

	<div class="bible-search-results-pagination pagination">
		<div class="pag-count">
			<form action="<?php echo $bp_bible_search->get_url() ?>" method="get">
				<?php $bp_bible_search->output_page_num_description() ?> &nbsp;
				<select class="bible-search-trans-select">
					<?php echo $bp_bible_search->trans_select_options() ?>
				</select>
				&nbsp;<span class="ajax-loader"></span>
			</form>
		</div>
		<div class="pagination-links">
			<?php echo $bp_bible_search->page_links() ?>
		</div>
	</div>

	<?php else: ?>

		<div id="message" class="info">
			<p><?php _e( "No matching notes found.", 'bp-bible' ) ?></p>
		</div>

	<?php endif;?>

