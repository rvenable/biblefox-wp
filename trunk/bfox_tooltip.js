/*global jQuery, BfoxAjax */

jQuery(document).ready(function () {
	'use strict';

	BfoxAjax.loadTooltip = function (link, refStr) {
		var parameters, url;
		
		parameters = jQuery.param({
			'action': 'bfox-tool-content',
			'context': 'tooltip',
			'ref': refStr,
			'nonce': BfoxAjax.tooltipNonce
		});
		
		url = BfoxAjax.appendUrlWithParamString(BfoxAjax.ajaxurl, parameters);

		jQuery(link).qtip({
			content: {
				text: 'Loading...',
				ajax: {
					url: url,
					type: 'GET',
					dataType: 'json',
					success: function (response) {
						// Set the content manually (required!)
						BfoxAjax.tooltipNonce = response.nonce;

						this.set('content.text', response.html);
					}
				}
			}
		});

		return false;
	};
});
