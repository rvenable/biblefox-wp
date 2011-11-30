/*global jQuery, BfoxAjax */

jQuery(document).ready(function () {
	'use strict';
	
	// Create qTips using delegate method so that tooltips coming from AJAX also work
	// see http://craigsworks.com/projects/forums/thread-qtip-on-element-called-with-ajax?pid=5477#pid5477
	// For info about .delegate() see http://www.alfajango.com/blog/the-difference-between-jquerys-bind-live-and-delegate/
	jQuery(document).delegate('a.bfox-ref-tooltip', 'mouseover', function () {
		var parameters, url;
		
		parameters = jQuery.param({
			'action': 'bfox-tool-content',
			'context': 'tooltip',
			'ref': jQuery(this).attr('data-ref'),
			'nonce': BfoxAjax.tooltipNonce
		});
		
		url = BfoxAjax.appendUrlWithParamString(BfoxAjax.ajaxurl, parameters);

		jQuery(this).qtip({
			content: {
				text: 'Loading...',
				ajax: {
					url: url,
					type: 'GET',
					dataType: 'json',
					success: function (response) {
						BfoxAjax.tooltipNonce = response.nonce;

						// Set the content manually (required!)
						this.set('content.text', response.html);
					}
				}
			},
			show: {
				ready: true // Need this to make it show on first mouseover
			}
		});

		return false;
	});
});
