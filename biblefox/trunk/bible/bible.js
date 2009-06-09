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

function bfox_toggle_verse_paragraph()
{
	verse = 'Switch to Verse View';
	paragraph = 'Switch to Paragraph View';
	if (verse == jQuery('#verse_layout_toggle').html())
	{
		jQuery('.bible_verse').css('display', 'block').css('margin', '8px 0px 8px 0px');
		jQuery('.bible_end_p').css('display', 'none');
		jQuery('#verse_layout_toggle').html(paragraph);
	}
	else
	{
		jQuery('.bible_verse').css('display', 'inline').css('margin', '0px');
		jQuery('.bible_end_p').css('display', 'block');
		jQuery('#verse_layout_toggle').html(verse);
	}
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

jQuery(document).ready( function() {
	jQuery('#verse_layout_toggle').click(bfox_toggle_verse_paragraph);
	
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
	
	jQuery('.tabs').tabs();

	bfox_refresh_ref_js(jQuery('.ref_content:first').parent('.prow_content'));
});
