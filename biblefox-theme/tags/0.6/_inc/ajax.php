<?php
/***
 * AJAX Functions
 *
 */

function bfox_theme_bible_notes_list() {
	bp_bible_notes_list_prepare();
	bp_bible_notes_list();
}
add_action( 'wp_ajax_get_bible_notes_list', 'bfox_theme_bible_notes_list' );

function bfox_theme_bible_note_saved() {
	global $bp;
	if ( !$note = bp_bible_edit_note_with_input() ) {
		$bp->template_message = __( 'Bible note could not be saved. Please try again.', 'bp-bible' );
		$bp->template_message_type = 'error';
		bp_core_render_message();
	} else {
		$bp->template_message = __( 'Bible note successfully saved.', 'bp-bible' );
		$bp->template_message_type = 'success';
		bp_core_render_message();
		do_action( 'bp_bible_edited_note', &$note );
	}
}
add_action( 'wp_ajax_save_bible_note', 'bfox_theme_bible_note_saved' );

function bfox_theme_bible_search_list() {
	require_once BP_BIBLE_DIR . '/bp-bible/bible-search.php';

	global $bp_bible_search;
	$bp_bible_search = new BibleSearch(urldecode($_REQUEST['s']), $_REQUEST['ref'], $_REQUEST['page'], $_REQUEST['trans'], $_REQUEST['group']);

	bp_bible_search_list();
}
add_action( 'wp_ajax_get_bible_search_list', 'bfox_theme_bible_search_list' );


?>