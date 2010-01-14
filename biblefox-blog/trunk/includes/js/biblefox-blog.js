
// For when bfox translation iframe selects change to update their iframe
function bfox_blog_iframe_select_change() {
	var option = jQuery(this).find('option:selected');

	// Save the translation in a cookie for 30 days
	var date = new Date();
	date.setTime(date.getTime()+(30*24*60*60*1000));
	document.cookie = "bfox-blog-iframe-select="+option.attr('name')+"; expires="+date.toGMTString()+"; path=/";	

	jQuery(this).next('iframe.bfox-iframe').attr('src', option.val()).get(0).reload();
}

function bfox_blog_add_tooltips(elements) {
	// Prevent bible ref links from going anywhere because we are showing a tooltip on a click
	elements.click(function(event) {
		event.preventDefault();
	});
	
	elements.each(function() {
		jQuery(this).qtip({
			content: {
				text: 'Loading...',
				url: jQuery(this).next('a.bible-tooltip-url').attr('href'),
				title: {
					text: '<a href="' + jQuery(this).attr('href') + '">' + jQuery(this).text() + '</a>', // Give the tooltip a title using each elements text
					button: 'Close' // Show a close link in the title
				}
			},
			position: {
				corner: {
					target: 'bottomMiddle', // Position the tooltip above the link
					tooltip: 'topMiddle'
				},
				adjust: {
					screen: true // Keep the tooltip on-screen at all times
				}
			},
			show: {
				when: 'click',
				solo: true
			},
			hide: 'unfocus',
			style: {
				tip: true, // Apply a speech bubble tip to the tooltip at the designated tooltip corner
				border: {
					width: 0,
					radius: 4
				},
				name: 'light', // Use the default light style
				width: 640 // Set the tooltip width
			},
			api: {
				onContentUpdate: function() {
					// When the content is updated, make sure that any iframe selects can update properly
					this.elements.content.find('select.bfox-iframe-select').change(bfox_blog_iframe_select_change);
				},
				onHide: function() {
					// HACK: Firefox has a bug that causes flickering when the iframe scroll position is not 0
					// See: http://craigsworks.com/projects/qtip/forum/topic/314/qtip-flicker-in-firefox/
					// Fix it by disabling scrolling on the iframes when we hide them
					this.elements.content.find('iframe').attr('scrolling', 'no');
				},
				onShow: function() {
					// Re-enable scrolling on the iframes
					this.elements.content.find('iframe').attr('scrolling', 'yes');
				}
			}
		});
	});
}

jQuery(document).ready(function() {
	// Iframes
	jQuery('select.bfox-iframe-select').change(bfox_blog_iframe_select_change);

	// Add tooltips to bible ref links
	bfox_blog_add_tooltips(jQuery('span.bible-tooltip a'));
});
