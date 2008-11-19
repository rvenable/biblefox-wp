
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
	jQuery('#bible-ref-field').focus();
	return false;
}

function bible_ref_press_key( e ) {
	if ( 13 == e.keyCode ) {
		bible_text_request_new();
		return false;
	}
}

function bible_ref_change()
{
	jQuery('#add-bible-ref').val('Tag ' + jQuery('#bible-ref-field').val());
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
	jQuery('#bible-text').fadeOut("fast");
	jQuery('#bible-text-progress').fadeOut("fast", function () {
										   jQuery('#bible-text-progress').html('Loading "' + ref_str + '"...');
										   });
	jQuery('#bible-text-progress').fadeIn("fast");
//	jQuery('#bible-text-progress').effect("pulsate", { times: 3 }, 1000);
//	jQuery('#bible-text-progress').fadeOut("normal", function () {
//										   jQuery('#bible-text-progress').fadeIn("normal");
//										   });
//	bfox_pulsate('#bible-text-progress');
	
	return false;
}

function bfox_pulsate_in(id)
{
	jQuery(id).fadeIn("normal", function () { bfox_pulsate_out(id) });
}

function bfox_pulsate(id)
{
	jQuery(id).fadeOut("normal");
	jQuery(id).fadeIn("normal", function () { bfox_pulsate(id) });
}

function bfox_quick_view_loaded(ref_str, content)
{
	jQuery('#bible-text').fadeOut("fast", function () {
										   jQuery('#bible-text').html(content);
								  jQuery('.bible-ref-link').click(bible_ref_link_click);
										   });
	jQuery('#bible-text').fadeIn("fast");
//	jQuery('#bible-text').html(content);
//	jQuery('#bible-ref-field').val(ref_str);
//	jQuery('#bible-ref-field').change();
//	jQuery('#bible-text-progress').stop( {clearQueue: true} );
	jQuery('#bible-text-progress').fadeOut("fast", function () {
										   jQuery('#bible-text-progress').html('Viewing ' + ref_str);
										   });
	jQuery('#bible-text-progress').fadeIn("fast");
//	jQuery('#bible-text-progress').fadeTo("normal", 1, function () {
//					  jQuery('#bible-text-progress').stop( {clearQueue: true} );
//					  });
}

function bfox_pulsate_stop(id)
{
	jQuery(id).stop( {clearQueue: true} );
	jQuery(id).fadeTo("normal", 1, function () {
					  jQuery(id).stop( {clearQueue: true} );
					  });
}

function bfox_pulsate_out(id)
{
	jQuery(id).fadeOut("normal", function () { bfox_pulsate_in(id) });
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
	jQuery('#bible-ref-field').change(bible_ref_change);
});
