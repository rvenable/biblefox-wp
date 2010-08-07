/*global jQuery, tagBox, sack */
'use strict';

function bible_text_request(ref_str) {
	var mysack = new sack(jQuery('#bible-request-url').val());
	
	mysack.execute = 1;
	mysack.method = 'POST';
	mysack.setVar("action", "bfox_ajax_send_bible_text");
	mysack.setVar("ref_str", ref_str);
	mysack.encVar("cookie", document.cookie, false);
	mysack.onError = function() { alert('Ajax error in looking up bible reference'); };
	mysack.runAJAX();

	// Fade out the the progress text, then update it to say we are loading
	jQuery('#bible-text-progress').fadeOut("fast", function () {
		jQuery(this).html('Loading "' + ref_str + '"...');
	});
	
	// Fade the bible-text slightly to indicate to the user that it is about to be replaced
	jQuery('#bible-text').fadeTo("fast", 0.6);

	// Fade the load progress loading text back in
	jQuery('#bible-text-progress').fadeIn("fast");
	
	return false;
}

function bfox_quick_view_loaded(ref_str, content) {
	// Wait until the progress text is finished
	jQuery('#bible-text-progress').queue( function () {

		// Fade out the progress text, then update it to say we are done loading
		jQuery(this).fadeOut("fast", function() {
			jQuery(this).html('Viewing ' + ref_str);
		});

		// Fade out the old bible text, then replace it with the new text
		jQuery('#bible-text').fadeOut("fast", function () {
			jQuery(this).html(content);
		});

		// Fade everything back in
		jQuery('#bible-text-progress').fadeIn("fast");
		jQuery('#bible-text').fadeIn("fast").fadeTo("fast", 1);

		// We must dequeue to continue the queue
		jQuery(this).dequeue();
	});
}

function bible_text_request_new() {
	bible_text_request(jQuery('#new-bible-ref').val());
}

function bible_ref_link_click() {
	bible_text_request(jQuery(this).attr('bible_ref'));
}

function bible_ref_press_key(e) {
	if (13 == e.keyCode) {
		bible_text_request_new();
		return false;
	}
}

jQuery(document).ready( function() {
	jQuery('#view-bible-ref').click(bible_text_request_new);
	jQuery('#new-bible-ref').keypress(bible_ref_press_key);
	jQuery('a.add-bible-ref-tag').live('click', function () {
		tagBox.flushTags(jQuery('.tagsdiv'), this);
	});
});
