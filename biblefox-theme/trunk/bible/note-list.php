	<?php if ( bp_has_bible_notes( /*'item_id=' . bp_get_wire_item_id() . '&can_post=' . bp_wire_can_post()*/ ) ) : ?>

			<div id="bible-note-pagination" class="pagination">

				<div class="pag-count">
					<?php bp_bible_notes_pagination_count() ?>
				</div>

				<div class="pagination-links" id="<?php bp_bible_notes_pag_id() ?>">
					<?php bp_bible_notes_pagination() ?>
				</div>

			</div>

		<?php do_action( 'bp_before_bible_note_list' ) ?>

		<ul id="bible-note-list" class="item-list">
		<?php while ( bp_bible_notes() ) : bp_the_bible_note(); ?>

			<li>
				<?php do_action( 'bp_before_bible_note_list_metadata' ) ?>

				<div class="bible-note-metadata">
					<?php bp_bible_note_avatar() ?>
					<?php _e('Last Edited: ', 'bp-bible') ?><?php bp_bible_note_modified_time() ?><br/>
					<?php if ($ref_str = bp_get_bible_note_ref_tag_links()) echo __('Scriptures: ', 'bp-bible') . $ref_str ?>
					<?php bp_bible_note_action_buttons() ?>

					<?php do_action( 'bp_bible_note_list_metadata' ) ?>
				</div>

				<?php do_action( 'bp_after_bible_note_list_metadata' ) ?>
				<?php do_action( 'bp_before_bible_note_list_item' ) ?>

				<div class="bible-note-content">
					<?php bp_bible_note_content() ?>

					<?php do_action( 'bp_bible_note_list_item' ) ?>
				</div>

				<?php do_action( 'bp_after_bible_note_list_item' ) ?>
			</li>

		<?php endwhile; ?>
		</ul>

		<?php do_action( 'bp_after_bible_note_list' ) ?>
	<?php else: ?>

		<div id="message" class="info">
			<p><?php _e( "No matching notes found.", 'bp-bible' ) ?></p>
		</div>

	<?php endif;?>
