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

jQuery(document).ready( function() {
	jQuery('#verse_layout_toggle').click(bfox_toggle_verse_paragraph);
	
	// Set all collapsable boxes to their cookied values
	jQuery('.cbox').each(function() {
		var box = jQuery(this);
		var id = box.attr('id');
		var body = box.children('.cbox_body');
		var display = jQuery.cookie('passage_ui_' + id + '_display');
		if (null != display) {
			body.css('display', display);
		}
	});
	
	// Toggle all collapsable boxes using their headers
	jQuery('.cbox .cbox_head').click(function() {
		var box = jQuery(this).parent('.cbox');
		var id = box.attr('id');
		var body = box.children('.cbox_body');
		body.toggle('fast', function() {
			jQuery.cookie('passage_ui_' + id + '_display', body.css('display'));
		});
	});
	
	jQuery('.cbox_sub .cbox_body').show();
	jQuery('.cbox_sub_sub .cbox_body').hide();
	
	jQuery('.cbox_sub .cbox_head, .cbox_sub_sub .cbox_head').click(function() {
		jQuery(this).siblings('.cbox_body').toggle('fast');
	});

	// Move content from the bottom of the page to the side viewer
	jQuery('.sideview_content').hide().each(function() {
		var content = jQuery(this);
		var from = jQuery('#' + content.attr('id').substring(9));
		content.html(from.children('.cbox_body').show().clone(true));
		jQuery(from).remove();
	});
	
	// Show the cookied sideview item
	var sideshow_id = jQuery.cookie('sideshow_id');
	if (null != sideshow_id) bfox_sideshow(sideshow_id);
	
	jQuery('.sideview').show();

	// Deactivate any already active ui states (we only had them active for non-javascript users)
	jQuery('.ui-state-active').removeClass('ui-state-active');
	
	// Add the accordion
	jQuery('.passage_list').accordion({
		changestart: function(event, ui) {
			ui.oldContent.children('.prow_content').hide();
		},
		change: function(event, ui) {
			ui.newContent.children('.prow_content').show();
		},
		collapsible: true,
		autoHeight: false
	});
});
