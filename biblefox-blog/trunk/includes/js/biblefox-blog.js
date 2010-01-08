jQuery(document).ready( function() {

	// Prevent bible ref links from going anywhere because we are showing a tooltip on a click
	jQuery('span.bible-tooltip a').click(function(event) {
		event.preventDefault();
	});
	
	// Add tooltips to bible ref links
	jQuery('span.bible-tooltip a').each(function() {
		jQuery(this).qtip({
			content: {
				url: jQuery(this).next('span.bible-tooltip-url').text(),
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
			}
		});
	});
});
