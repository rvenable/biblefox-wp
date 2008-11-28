
function bible_ref_remove_tag() {
	var id = jQuery( this ).attr( 'id' );
	var num = id.substr( 16 );
	var current_tags = jQuery( '#bible-ref-list' ).val().split(';');
	delete current_tags[num];
	var new_tags = [];
	jQuery.each( current_tags, function( key, val ) {
		if ( val && !val.match(/^\s+$/) && '' != val ) {
			new_tags = new_tags.concat( val );
		}
	});
	var new_text = new_tags.join(';');
	bible_ref_set_text(new_text);
	jQuery('#newtag').focus();
	return false;
}

function bible_ref_update_quickclicks() {
	empty_txt = '<span>No Scripture Tags.<br/>To add a new scripture tag, first view the scripture you want in the Scripture Quick View below.</span>';
	if (jQuery('#bible-ref-list').length == 0)
	{
		jQuery('#bible-ref-checklist').html(empty_txt);
		return;
	}

	var current_tags = jQuery( '#bible-ref-list' ).val().split(';');
	jQuery( '#bible-ref-checklist' ).empty();
	shown = false;

	jQuery.each( current_tags, function( key, val ) {
		val = val.replace( /^\s+/, '' ).replace( /\s+$/, '' ); // trim
		if ( !val.match(/^\s+$/) && '' != val ) {
			txt = '<span><a id="bible-ref-check-' + key + '" class="bible-tag-remove-button">X</a>&nbsp;<a id="bible-ref-link-' + key + '" class="bible-ref-quick-link" bible_ref="' + val + '">' + val + '</a></span>';
			jQuery('#bible-ref-checklist').append(txt);
			jQuery('#bible-ref-check-' + key).click(bible_ref_remove_tag);
			jQuery('#bible-ref-link-' + key).click(bible_ref_link_click);
			shown = true;
		}
	});

	if (jQuery('#bible-ref-checklist').html().length == 0)
		jQuery('#bible-ref-checklist').html(empty_txt);
}

function bible_ref_set_text(newtags)
{
	newtags = newtags.replace( /\s+;+\s*/g, ';' ).replace( /;+/g, ';' ).replace( /;+\s+;+/g, ';' ).replace( /;+\s*$/g, '' ).replace( /^\s*;+/g, '' );
	jQuery('#bible-ref-list').val( newtags );
	bible_ref_update_quickclicks();
	return false;
}

function bible_ref_flush_to_text() {
	var newtags = jQuery('#bible-ref-list').val() + ';' + jQuery('#add-bible-ref').attr('bible_ref');
	
	bible_ref_set_text(newtags);
	return false;
}

function bible_ref_press_key( e ) {
	if ( 13 == e.keyCode ) {
		bible_text_request_new();
		return false;
	}
}

function bible_text_request(ref_str)
{
	var mysack = new sack(jQuery('#bible-request-url').val());
	
	mysack.execute = 1;
	mysack.method = 'POST';
	mysack.setVar("action", "bfox_ajax_send_bible_text");
	mysack.setVar("ref_str", ref_str);
	mysack.encVar("cookie", document.cookie, false);
	mysack.onError = function() { alert('Ajax error in looking up bible reference')};
	mysack.runAJAX();

	// Fade out the the progress text, then update it to say we are loading
	jQuery('#bible-text-progress').fadeOut("fast", function () {
		jQuery('#bible-text-progress').html('Loading "' + ref_str + '"...');
	});
	
	// Fade the bible-text slightly to indicate to the user that it is about to be replaced
	jQuery('#bible-text').fadeTo("fast", 0.6);

	// Fade the load progress loading text back in
	jQuery('#bible-text-progress').fadeIn("fast");
	
	return false;
}

function bfox_quick_view_loaded(ref_str, content)
{
	// Wait until the progress text is finished
	jQuery('#bible-text-progress').queue( function () {

		// Fade out the progress text, then update date it to say we are done loading
		jQuery('#bible-text-progress').fadeOut("fast", function() {
			jQuery('#bible-text-progress').html('Viewing ' + ref_str);
		});

		// Fade out the old bible text, then replace it with the new text
		jQuery('#bible-text').fadeOut("fast", function () {
			jQuery('#bible-text').html(content);
			jQuery('.bible-ref-link').click(bible_ref_link_click);
		});

		// Fade everything back in
		jQuery('#bible-text-progress').fadeIn("fast");
		jQuery('#bible-text').fadeIn("fast");
		jQuery('#bible-text').fadeTo("fast", 1);

		// We must dequeue to continue the queue
		jQuery(this).dequeue();
	});
}

function bible_text_request_new()
{
	bible_text_request(jQuery('#new-bible-ref').val());
}

function bible_ref_link_click()
{
	bible_text_request(jQuery(this).attr('bible_ref'));
}

jQuery(document).ready( function() {
	bible_ref_update_quickclicks();
	jQuery('#add-bible-ref').click(bible_ref_flush_to_text);
	jQuery('#view-bible-ref').click(bible_text_request_new);
	jQuery('#new-bible-ref').keypress(bible_ref_press_key);
});
