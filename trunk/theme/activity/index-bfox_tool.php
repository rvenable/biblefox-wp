<?php

/*
This template is for the Bible Tools archive in a BuddyPress enabled theme.
It duplicates the BP activities/index template, but adds bfox_tools to the top of the page,
and filters activities by Bible reference.
*/

// Add some javascript to ensure that any AJAX calls include the current bible references
function bfox_bp_bible_directory_setup_ajax() {
	$ref = bfox_ref();

	if ($ref->is_valid()) {
		?>
		<script type='text/javascript'>
		<!--
		jQuery.ajaxSetup({
			data: {'bfox_ref': '<?php echo urlencode($ref->get_string()) ?>'}
	 	});
		//-->
		</script>
		<?php
	}
}
add_action('wp_head', 'bfox_bp_bible_directory_setup_ajax');

// Use the jQuery.ajaxSend() function to add the "What did you read?" data (see: http://api.jquery.com/ajaxSend/ )
function bfox_bp_bible_directory_setup_what_read_ajax() {
	?>
	<script type='text/javascript'>
	<!--
	jQuery(document).ajaxSend( function(e, xhr, settings) {
		var ref_str = jQuery('input#bfox_read_ref_str').val();
		if (ref_str.length) {
			settings.data += '&bfox_read_ref_str=' + escape(ref_str);
		}
	});
	//-->
	</script>
	<?php
}
add_action('wp_head', 'bfox_bp_bible_directory_setup_what_read_ajax');

// Add the active bible references to the search terms
function bfox_bp_bible_directory_querystring($query_string, $object) {
	$ref = bfox_ref();
	if ($ref->is_valid()) {
		$args = wp_parse_args($query_string);
		if (!empty($args['search_terms'])) $args['search_terms'] .= ' ';
		$args['search_terms'] .= $ref->get_string();
		$query_string = build_query($args);
	}

	return $query_string;
}

function bfox_bp_bible_directory_before_activity_loop() {
	$ref = bfox_ref();

	// Try to get a ref from the REQUEST params if there isn't already an active ref
	if (!empty($_REQUEST['bfox_ref']) && !$ref->is_valid()) {
		$ref = new BfoxRef(urldecode($_REQUEST['bfox_ref']));
		if ($ref->is_valid()) set_bfox_ref($ref);
	}

	if ($ref->is_valid()) {
		// Make sure our bible references get added to the search terms
		add_filter('bp_ajax_querystring', 'bfox_bp_bible_directory_querystring', 20, 2);

		// Enable search term refs
		bfox_bp_activity_enable_search_term_refs();

		// Disable the search term refs after the activity loop
		add_action('bp_after_activity_loop', 'bfox_bp_activity_disable_search_term_refs');
	}
}
add_action('bp_before_activity_loop', 'bfox_bp_bible_directory_before_activity_loop');

function bfox_bp_bible_directory_search_form($search_value, $submit_value) {
?>
		<label><input type="text" name="ref" id="bible_search" value="<?php echo attribute_escape($search_value) ?>" /></label>
		<input type="submit" id="bible_search_submit" value="<?php echo $submit_value ?>" />
<?php
}

// Add the Bible Tools before the activities
function bfox_bp_before_bible_directory_activity_content() {
	$ref = bfox_ref();
	$search_value = $ref->get_string(BibleMeta::name_short);

	?>
		<form action="<?php echo get_post_type_archive_link('bfox_tool') ?>" method="get" id="bible-directory-form" class="dir-form">
			<h3><?php _e( 'Bible Reader', 'bfox' ) ?></h3>
			<div id="bible-dir-search" class="dir-search no-ajax">
				<?php bfox_bp_bible_directory_search_form($search_value, __('Go to passage', 'bfox')) ?>
			</div><!-- #group-dir-search -->
		</form>
		<h4><?php echo bfox_ref_str() ?></h4>
		<div class="bfox-tool-bp-bible">
			<div id="bfox-bible-container">
				<?php load_bfox_template('content-bfox_tool'); ?>
			</div>
		</div>
	<?php
}
add_action('bp_before_directory_activity_content', 'bfox_bp_before_bible_directory_activity_content');

function bfox_bp_before_activity_post_form() {
	$ref = bfox_ref();
	$ref_str = $ref->get_string();

	?>
		<div class="ref-read">
		<label><?php _e('Bible tags', 'bfox') ?>&nbsp;<input type="text" id="bfox_read_ref_str" name="bfox_read_ref_str" value="<?php echo attribute_escape($ref_str) ?>" /></label>
		</div>
	<?php
}
add_action('bp_before_activity_post_form', 'bfox_bp_before_activity_post_form');

// HACK: Get rid of the 'Site Activity' string which is used as the header when not logged in
// This is a hack because we are filtering in the translate functions
add_filter('gettext', create_function('$translated, $text, $domain', 'if (\'Site Activity\' == $text) return \'\'; return $translated;'), 10, 3);
add_filter('gettext', create_function('$translated, $text, $domain', 'if (\'What\\\'s new %s?\' == $text) return \'Any thoughts to share?\'; return $translated;'), 10, 3);

// Load the normal activity/index template
bfox_bp_core_load_template('activity/index');

?>