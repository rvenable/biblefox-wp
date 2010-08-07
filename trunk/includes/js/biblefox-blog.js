/*global jQuery, bfox_blog_iframe_select_change: true */
'use strict';

// For when bfox translation iframe selects change to update their iframe
bfox_blog_iframe_select_change = function () {
	var option, date;
	option = jQuery(this).find('option:selected');

	// Save the translation in a cookie for 30 days
	date = new Date();
	date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
	document.cookie = "bfox-blog-iframe-select=" + option.attr('name') + "; expires=" + date.toGMTString() + "; path=/";	

	jQuery(this).next('iframe.bfox-iframe').attr('src', option.val());
};

jQuery(document).ready(function () {
	// Iframes
	jQuery('select.bfox-iframe-select').change(bfox_blog_iframe_select_change);
});
