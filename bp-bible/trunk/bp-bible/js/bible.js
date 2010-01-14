jQuery(document).ready( function() {

	// Expand all sub sections
	jQuery('.cbox_sub .cbox_body').show();
	// Collapse all sub_sub sections
	jQuery('.cbox_sub_sub .cbox_body').hide();
	// Make sure the passage cboxes start expanded 
	jQuery('#bible-passages .cbox_sub_sub .cbox_body').show();

	// Add toggle functionality to sub and sub_sub sections
	jQuery('.cbox_sub .cbox_head, .cbox_sub_sub .cbox_head').click(function() {
		jQuery(this).siblings('.cbox_body').slideToggle('fast');
	});

	// User Timezone
	var d = new Date();
	jQuery.cookie('bfox_timezone', (d.getTimezoneOffset() / 60), {expires: 30});
});
