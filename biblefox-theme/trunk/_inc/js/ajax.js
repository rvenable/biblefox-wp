// AJAX Functions

jQuery(document).ready( function() {
	var j = jQuery;

	// Bible Note pages
	j("div#bible-note-pagination a").livequery('click',
			function() { 
				j('.ajax-loader').toggle();

				var fpage = j(this).attr('href');
				fpage = fpage.split('=');

				j.post( ajaxurl, {
					action: 'get_bible_notes_list',
					'cookie': encodeURIComponent(document.cookie),
					'bp_page': fpage[1]
				},
				function(response)
				{	
					j('.ajax-loader').toggle();
				
					response = response.substr(0, response.length-1);

					j("#bible-note-list-content").fadeOut(200, 
						function() {
							j("#bible-note-list-content").html(response);
							j("#bible-note-list-content").fadeIn(200);
						}
					);

					return false;
				});
			
				return false;
			}
		);

});
