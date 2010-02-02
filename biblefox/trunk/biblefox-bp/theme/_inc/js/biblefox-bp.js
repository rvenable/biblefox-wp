jQuery(document).ready( function() {
	var j = jQuery;

	/**** Page Load Actions *******************************************************/
	
	/* Bible */
	if ( j('div.bible').length ) {
		
		var filter = j.cookie('bp-bible-filter');
		var scope = j.cookie('bp-bible-scope');

		bp_filter_request( 'bible', filter, scope, 'div.bible' );
	}

});
