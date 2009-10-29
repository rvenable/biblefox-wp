<?php do_action( 'bp_before_note_form_content' ) ?>

<div id="bible-note-new">

	<form action="<?php bp_bible_note_form_action() ?>" id="bible-note-new-form" method="post">

		<div id="bible-note-new-avatar">

			<?php do_action( 'bp_before_note_form_avatar' ) ?>

			<?php bp_wire_poster_avatar() ?>

			<?php do_action( 'bp_before_note_form_avatar' ) ?>

		</div>

		<div id="bible-note-new-metadata">

			<?php do_action( 'bp_before_note_form_metadata' ) ?>

			<span id="bible-note-by-text"><?php //printf ( __( 'On %1$s %2$s said:', "buddypress" ), bp_wire_poster_date( null, false ), bp_wire_poster_name( false ) ) ?></span>

			<?php do_action( 'bp_after_note_form_metadata' ) ?>

		</div>

		<div id="bible-note-new-input">

			<?php do_action( 'bp_before_note_form' ) ?>

			<textarea name="bible-note-textarea" id="bible-note-textarea" onfocus="if (this.value == '<?php _e( 'Start writing a short message...', 'buddypress' ) ?>') {this.value = '';}" onblur="if (this.value == '') {this.value = '<?php _e( 'Start writing a short message...', 'buddypress' ) ?>';}"><?php _e( 'Start writing a short message...', 'buddypress' ) ?></textarea>

			<?php do_action( 'bp_after_note_form' ) ?>

			<input type="hidden" name="bible-note-id" id="bible-note-id" value="0" />
			<input type="submit" name="bible-note-submit" id="bible-note-submit" value="<?php _e( 'Save &raquo;', 'bp-bible' ) ?>" />

			<?php wp_nonce_field( 'bp_bible_note_form' ) ?>

		</div>
	</form>

</div>

<?php do_action( 'bp_after_note_form_content' ) ?>