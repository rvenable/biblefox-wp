jQuery(document).ready(function($) {

	jQuery('input.bfox-reading-status').live('change', function () {
		var data = {
				action: 'bfox_plan_post_reading_status',
				status_id: this.id,
				checked: jQuery(this).attr('checked'),
				nonce: jQuery('input#bfox_plan_edit_status_nonce').attr('value')
		};
		
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function(response) {
			//alert(response);
		});
	});
});