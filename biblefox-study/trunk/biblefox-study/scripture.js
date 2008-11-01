
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
	if ( jQuery( '#bible-ref-list' ).length == 0 )
		return;
	var current_tags = jQuery( '#bible-ref-list' ).val().split(';');
	jQuery( '#bible-ref-checklist' ).empty();
	shown = false;

	jQuery.each( current_tags, function( key, val ) {
		val = val.replace( /^\s+/, '' ).replace( /\s+$/, '' ); // trim
		if ( !val.match(/^\s+$/) && '' != val ) {
			txt = '<span><a id="bible-ref-check-' + key + '" class="ntdelbutton">X</a>&nbsp;' + val + '</span> ';
			jQuery( '#bible-ref-checklist' ).append( txt );
			jQuery( '#bible-ref-check-' + key ).click( bible_ref_remove_tag );
			shown = true;
		}
	});
	if ( shown )
		jQuery( '#bible-ref-checklist' ).prepend( '<strong>Scripture Tags Used:</strong><br />' );
}

function bible_ref_set_text(newtags)
{
	newtags = newtags.replace( /\s+;+\s*/g, ';' ).replace( /;+/g, ';' ).replace( /;+\s+;+/g, ';' ).replace( /;+\s*$/g, '' ).replace( /^\s*;+/g, '' );
	jQuery('#bible-ref-list').val( newtags );
	bible_ref_update_quickclicks();
	return false;
}

function bible_ref_flush_to_text() {
	var newtags = jQuery('#bible-ref-list').val() + ';' + jQuery('#new-bible-ref').val();
	
	bible_ref_set_text(newtags);
	jQuery('#new-bible-ref').val('');
	jQuery('#new-bible-ref').focus();
	return false;
}

function bible_ref_press_key( e ) {
	if ( 13 == e.keyCode ) {
		bible_ref_flush_to_text();
		return false;
	}
}

jQuery(document).ready( function() {
	bible_ref_update_quickclicks();
	jQuery('#add-bible-ref').click( bible_ref_flush_to_text );
	jQuery('#new-bible-ref').keypress( bible_ref_press_key );

	// Hide the bible-ref-viewer unless we have some bible refs to view
	if ('' == jQuery('#bible-ref-list').val()) jQuery('#bible-ref-viewer').hide();

	jQuery('#bible-text-toggle').click( function() {
		jQuery(this).parents('div:first').toggleClass( 'wp-hidden-children' );
		return false;
	} );

});
