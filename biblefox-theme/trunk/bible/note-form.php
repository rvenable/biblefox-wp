<?php do_action( 'bp_before_note_edit_form_content' ) ?>

<div id="bible-note-new">

	<div id="bible-note-new-avatar">

		<?php do_action( 'bp_before_note_edit_form_avatar' ) ?>

		<?php bp_bible_note_avatar() ?>

		<?php do_action( 'bp_before_note_edit_form_avatar' ) ?>

	</div>

	<div class="bible-note-content">
		<form action="<?php bp_bible_note_edit_form_action() ?>" id="bible-note-new-edit-form" class="bible-note-edit-form" method="post">
			<?php do_action( 'bp_before_note_edit_form' ) ?>

			<?php $note_msg = bp_get_bible_note_content_help_text() ?>
			<?php $note_content = bp_get_bible_note_editable_content() ?>
			<textarea name="bible-note-textarea" class="bible-note-textarea" onfocus="if (this.value == '<?php echo $note_msg ?>') {this.value = '';}" onblur="if (this.value == '') {this.value = '<?php echo $note_msg ?>';}"><?php echo ($note_content) ? $note_content : $note_msg ?></textarea>

			<?php $note_msg = __( 'Add Bible references here...', 'bp-bible' ) ?>
			<?php $note_ref_tags = bp_get_bible_note_ref_tags() ?>
			<input type="text" name="bible-note-ref-tags" class="bible-note-ref-tags" value="<?php echo ($note_ref_tags) ? $note_ref_tags : $note_msg ?>" onfocus="if (this.value == '<?php echo $note_msg ?>') {this.value = '';}" onblur="if (this.value == '') {this.value = '<?php echo $note_msg ?>';}" /><br/>

			<?php do_action( 'bp_after_note_edit_form' ) ?>

			<input type="hidden" name="bible-note-id" id="bible-note-id" value="0" />
			<input type="submit" name="bible-note-submit" class="bible-note-submit" value="<?php _e( 'Save &raquo;', 'bp-bible' ) ?>" />

			<?php wp_nonce_field( 'bp_bible_note_edit_form' ) ?>

			<div class="bible-note-edit-form-result"></div>
		</form>
	</div>

</div>

<?php do_action( 'bp_after_note_edit_form_content' ) ?>