function bfox_get_context_parent(toggle)
{
	return jQuery(toggle).parents('.ref_seq').children('.ref_seq_body');
}

function bfox_get_context_chapters(parent)
{
	return parent.children('.visible_chapter');
}

function bfox_set_context_none(toggle)
{
	var parent = bfox_get_context_parent(toggle);
	var chapters = bfox_get_context_chapters(parent);
	chapters.children('.hidden_verses').hide();
	chapters.children('.hidden_verses_rule').show();
	parent.children('.hidden_chapter').hide();
}

function bfox_set_context_verses(toggle)
{
	var parent = bfox_get_context_parent(toggle);
	var chapters = bfox_get_context_chapters(parent);
	chapters.children('.hidden_verses').show();
	chapters.children('.hidden_verses_rule').hide();
	parent.children('.hidden_chapter').hide();
}

function bfox_set_context_chapters(toggle)
{
	var parent = bfox_get_context_parent(toggle);
	var chapters = bfox_get_context_chapters(parent);
	chapters.children('.hidden_verses').show();
	chapters.children('.hidden_verses_rule').hide();
	parent.children('.hidden_chapter').show();
}

// Select a specific tab to show in the sideviewer
function bfox_sideshow(id) {
	jQuery('.sideview_content').hide();
	jQuery('#sideview_' + id).show();
	jQuery.cookie('sideshow_id', id);
}

function bfox_ref_load(content) {
	var loader = content.children('.ref_loader:first');
	if (1 == loader.size()) {
		content.load(loader.attr('href'), '', function() {
			bfox_refresh_ref_js(content);
		});
		loader.remove();
	}
	else {
		bfox_refresh_ref_js(content);
	}
}

function bfox_move(from_str, to) {
	var from = jQuery(from_str);
	to.html(from.clone(true));
	from.remove();
}

function bfox_refresh_ref_js(prow_content) {
	var new_ref_js_hold = prow_content.children('.ref_js_hold:first');
	if (1 == new_ref_js_hold.size()) {
		// Hide all the ref_content, because we just show the ref_js 
		prow_content.children('.ref_content').hide();
	
		// Move content from the bottom of the page to the side viewer
		bfox_move('#ref_js', new_ref_js_hold);
		var ref_js = jQuery('#ref_js');
	
		// Move all the cbox content to the sideview
		ref_js.find('.sideview_content').each(function() {
			var to = jQuery(this);
			var item = to.attr('id').substring(9);
			var from = prow_content.find('.' + item);
			to.html(from.children('.cbox_body').clone());
		});
		
		// Move the passage
		jQuery('#ref_js_passage').html(prow_content.find('.ref_content > .reference').clone(true));
		
		// Expand all sub sections
		ref_js.find('.cbox_sub .cbox_body').show();
		// Collapse all sub_sub sections
		ref_js.find('.cbox_sub_sub .cbox_body').hide();
		
		// Add toggle functionality to sub and sub_sub sections
		ref_js.find('.cbox_sub .cbox_head, .cbox_sub_sub .cbox_head').click(function() {
			jQuery(this).siblings('.cbox_body').toggle('fast');
		});
	}
}

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

	// Show the cookied sideview item
	var sideshow_id = jQuery.cookie('sideshow_id');
	if (null != sideshow_id) bfox_sideshow(sideshow_id);
	
	jQuery('#sideview').show();
	
	// Deactivate any already active ui states (we only had them active for non-javascript users)
	jQuery('.ui-state-active').removeClass('ui-state-active');
	
	// Add the accordion
	jQuery('.passage_list').accordion({
		changestart: function(event, ui) {
			ui.oldContent.children('.prow_content').hide();
			bfox_ref_load(ui.newContent.children('.prow_content'));
		},
		change: function(event, ui) {
			ui.newContent.children('.prow_content').show();
		},
		collapsible: true,
		autoHeight: false
	});
	
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

	//bfox_refresh_ref_js(jQuery('.ref_content:first').parent('.prow_content'));
});
