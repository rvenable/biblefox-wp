<?php do_action( 'bp_before_note_form_content' ) ?>

<div id="bible-note-new">

	<form action="<?php bp_bible_note_form_action() ?>" id="bible-note-new-form" method="post">

		<div id="bible-note-new-avatar">

			<?php do_action( 'bp_before_note_form_avatar' ) ?>

			<?php bp_bible_note_avatar() ?>

			<?php do_action( 'bp_before_note_form_avatar' ) ?>

		</div>

		<div id="bible-note-new-metadata">

			<?php do_action( 'bp_before_note_form_metadata' ) ?>

			<span id="bible-note-by-text"><?php //printf ( __( 'On %1$s %2$s said:', "buddypress" ), bp_wire_poster_date( null, false ), bp_wire_poster_name( false ) ) ?></span>

			<?php do_action( 'bp_after_note_form_metadata' ) ?>

		</div>

		<div id="bible-note-new-input">

			<?php do_action( 'bp_before_note_form' ) ?>

			<?php $note_msg = __( 'Start writing a quick note...', 'bp-bible' ) ?>
			<?php $note_content = bp_get_bible_note_editable_content() ?>
			<textarea name="bible-note-textarea" id="bible-note-textarea" onfocus="if (this.value == '<?php echo $note_msg ?>') {this.value = '';}" onblur="if (this.value == '') {this.value = '<?php echo $note_msg ?>';}"><?php echo ($note_content) ? $note_content : $note_msg ?></textarea>

			<?php do_action( 'bp_after_note_form' ) ?>

			<?php $note_msg = __( 'Add Bible references here...', 'bp-bible' ) ?>
			<?php $note_ref_tags = bp_get_bible_note_ref_tags() ?>
			<input type="text" name="bible-note-ref-tags" id="bible-note-ref-tags" value="<?php echo ($note_ref_tags) ? $note_ref_tags : $note_msg ?>" onfocus="if (this.value == '<?php echo $note_msg ?>') {this.value = '';}" onblur="if (this.value == '') {this.value = '<?php echo $note_msg ?>';}" /><br/>

			<input type="hidden" name="bible-note-id" id="bible-note-id" value="<?php bp_get_bible_note_id() ?>" />
			<input type="submit" name="bible-note-submit" id="bible-note-submit" value="<?php _e( 'Save &raquo;', 'bp-bible' ) ?>" />

			<?php wp_nonce_field( 'bp_bible_note_form' ) ?>

		</div>
	</form>

</div>

<?php do_action( 'bp_after_note_form_content' ) ?>