	<?php $has_notes = bp_has_bible_notes() ?>

	<form id="bible-note-list-filter-form" action="" method="get">
		<label for="bible-note-list-filter-privacy"><?php _e( 'Include friends', 'bp-bible' ); ?></label>
		<input type="checkbox" id="bible-note-list-filter-privacy" name="nt-privacy"<?php bp_bible_notes_filter_privacy_setting(1) ?> />
		<input type="text" name="nt-filter" id="bible-note-list-filter" value="<?php bp_bible_notes_filter_str() ?>" />
		<input type="submit" id="bible-note-list-filter-submit" value="<?php _e( 'Filter', 'bp-bible' ) ?>" />
	</form>

	<div id="bible-note-pagination" class="pagination">

		<div class="pag-count">
			<?php bp_bible_notes_pagination_count() ?> &nbsp;
			<span class="ajax-loader"></span>
		</div>

		<div class="pagination-links" id="<?php bp_bible_notes_pag_id() ?>">
			<?php bp_bible_notes_pagination() ?>
		</div>

	</div>

	<?php if ( $has_notes ) : ?>

		<?php do_action( 'bp_before_bible_note_list' ) ?>

		<ul id="bible-note-list" class="item-list">
		<?php while ( bp_bible_notes() ) : bp_the_bible_note(); ?>

			<li>
				<?php do_action( 'bp_before_bible_note_list_metadata' ) ?>

				<div class="bible-note-metadata">
					<?php bp_bible_note_avatar() ?>
					<?php _e('Last saved: ', 'bp-bible') ?><?php bp_bible_note_modified_time() ?>
					<?php _e(' by ', 'bp-bible') ?><?php bp_bible_note_author_link() ?>
					<?php if (!bp_get_bible_note_privacy()) echo '(' . bp_get_bible_note_privacy_str() . ')' ?><br/>
					<?php if ($ref_str = bp_get_bible_note_ref_tag_links()) echo __('Scriptures: ', 'bp-bible') . $ref_str ?>
					<?php bp_bible_note_action_buttons() ?>

					<?php do_action( 'bp_bible_note_list_metadata' ) ?>
				</div>

				<?php do_action( 'bp_after_bible_note_list_metadata' ) ?>
				<?php do_action( 'bp_before_bible_note_list_item' ) ?>

				<div class="bible-note-content">
					<?php bp_bible_note_content() ?>

					<?php do_action( 'bp_bible_note_list_item' ) ?>

					<?php if (bp_is_bible_note_editable()): ?>
					<div class="generic-button bible-note-open-edit-form"><a href=""><?php _e('Edit', 'bp-bible') ?></a></div>

					<form action="<?php bp_bible_note_edit_form_action() ?>" class="bible-note-edit-form" method="post">
						<div class="bible-note-edit-form-header"><?php _e('Edit Note', 'bp-bible') ?></div>
						<?php do_action( 'bp_before_note_edit_form' ) ?>

						<textarea name="bible-note-textarea" class="bible-note-textarea"><?php bp_bible_note_editable_content() ?></textarea>
						<input type="text" name="bible-note-ref-tags" class="bible-note-ref-tags" value="<?php bp_bible_note_ref_tags() ?>"/><br/>

						<?php do_action( 'bp_after_note_edit_form' ) ?>

						<input type="hidden" name="bible-note-id" class="bible-note-id" value="<?php bp_bible_note_id() ?>" />
						<input type="submit" name="bible-note-submit" class="bible-note-submit" value="<?php _e( 'Save &raquo;', 'bp-bible' ) ?>" />
						<div class="bible-note-privacy-setting">
							<?php _e( 'Privacy: ', 'bp-bible' ); ?>
							<input type="radio" id="bible-note-privacy-private" name="bible-note-privacy" value="0"<?php bp_bible_note_privacy_setting(0) ?> />&nbsp;<label for="bible-note-privacy-private"><?php _e( 'Private', 'bp-bible' ); ?></label>
							<input type="radio" id="bible-note-privacy-friends" name="bible-note-privacy" value="1"<?php bp_bible_note_privacy_setting(1) ?> />&nbsp;<label for="bible-note-privacy-friends"><?php _e( 'Friends only', 'bp-bible' ); ?></label>
							<span class="ajax-loader"></span>
						</div>

						<?php wp_nonce_field( 'bp_bible_note_edit_form' ) ?>

						<div class="bible-note-edit-form-result"></div>
					</form>
					<?php endif ?>

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
