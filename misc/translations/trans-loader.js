'use strict';
var jQuery;

jQuery(document).ready(function () {
	jQuery('.esv h2').each(function () {
		var r, h;
		r = '.esv-ref-' + jQuery(this).text().toLowerCase().replace(' ', '_').replace(':', '_');
		h = jQuery(this).next('.esv-text').html();
		jQuery(r).html(h);
	});
});
