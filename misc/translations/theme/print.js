function bfox_theme_print_read_cookie(name) {
	// From: http://www.quirksmode.org/js/cookies.html
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}

function bfox_theme_print_view_option_set(name, checked) {
	if ('jesus' == name) {
		jQuery('.bible_jesus').toggleClass('red_words', checked);
	}
	else if ('paragraphs' == name) {
		jQuery('p.bible_text').toggleClass('bible_display_flat', checked);
	}
	else if ('verse_nums' == name) {
		var e = jQuery('.verse_num');
		if (checked) e.hide();
		else e.show();
	}
	else if ('footnotes' == name) {
		var e = jQuery('.ref_foot_link');
		if (checked) e.hide();
		else e.show();
	}
}

jQuery(document).ready( function() {
	// View options
	jQuery('input.view_option').each(function() {
		var option = jQuery(this);
		var name = option.attr('name');
		var checked = ('true' === bfox_theme_print_read_cookie('bfox_view_option_' + name));
		option.attr('checked', checked);
		bfox_theme_print_view_option_set(name, checked);
	});
	jQuery('input.view_option').change(function() {
		var option = jQuery(this);
		var name = option.attr('name');
		var checked = option.attr('checked');

		// Set a cookie for 365 days
		var date = new Date();
		date.setTime(date.getTime()+(365*24*60*60*1000));
		document.cookie = "bfox_view_option_"+name+"="+checked+"; expires="+date.toGMTString()+"; path=/";	

		bfox_theme_print_view_option_set(name, checked);
	});
});
