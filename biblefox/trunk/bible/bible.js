function bfox_view_option_set(name, checked) {
	
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

	// Expand all sub sections
	jQuery('.cbox_sub .cbox_body').show();
	// Collapse all sub_sub sections
	jQuery('.cbox_sub_sub .cbox_body').hide();

	// Add toggle functionality to sub and sub_sub sections
	jQuery('.cbox_sub .cbox_head, .cbox_sub_sub .cbox_head').click(function() {
		jQuery(this).siblings('.cbox_body').slideToggle('fast');
	});

	// View options
	jQuery('input.view_option').each(function() {
		var option = jQuery(this);
		var name = option.attr('name');
		var checked = ('true' === jQuery.cookie('bfox_view_option_' + name));
		option.attr('checked', checked);
		bfox_view_option_set(name, checked);
	});
	jQuery('input.view_option').change(function() {
		var option = jQuery(this);
		var name = option.attr('name');
		var checked = option.attr('checked');
		jQuery.cookie('bfox_view_option_' + name, checked, {expires: 365});
		bfox_view_option_set(name, checked);
	});

	// User Timezone
	var d = new Date();
	jQuery.cookie('bfox_timezone', (d.getTimezoneOffset() / 60), {expires: 30});
});
