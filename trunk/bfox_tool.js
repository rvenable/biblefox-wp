/*global jQuery, BfoxAjax, bfox_blog_iframe_select_change: true */
'use strict';

var bfox_blog_select_change;

// For when bfox translation iframe selects change to update their iframe
bfox_blog_select_change = function () {
	var option, date, tool;
	option = jQuery(this).find('option:selected');
	tool = option.attr('name');

	// Save the translation in a cookie for 30 days
	date = new Date();
	date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
	document.cookie = "selected_bfox_tool=" + tool + "; expires=" + date.toGMTString() + "; path=/";

	jQuery.post(BfoxAjax.ajaxurl,
		{
			'action': 'bfox-tool-content',
			'bfox-ajax-nonce': BfoxAjax.nonce,
			'tool': tool,
			'ref': BfoxAjax.ref
		},
		function (response) {
			BfoxAjax.nonce = response.nonce;
			jQuery('#bfox-bible-container').html(response.html);
		}
	);

	//jQuery(this).next('iframe.bfox-iframe').attr('src', option.val());
};

jQuery(document).ready(function () {
	// Iframes
	jQuery('select.bfox-tool-select').change(bfox_blog_select_change);
});
