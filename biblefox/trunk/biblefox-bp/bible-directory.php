<?php

// Define the slug for the Bible Directory component
if (!defined('BFOX_BIBLE_SLUG')) define('BFOX_BIBLE_SLUG', 'bible');

function bfox_bp_bible_directory_setup_root_component() {
	bp_core_add_root_component(BFOX_BIBLE_SLUG);
}
add_action('plugins_loaded', 'bfox_bp_bible_directory_setup_root_component', 2);

/**
 * Filter function for overriding the URLs created by Biblefox::ref_bible_url so that they point to the local Bible Directory
 * @param string $template
 * @return string
 */
function bfox_bp_bible_url_template($template) {
	global $bp;
	return $bp->root_domain . '/' . BFOX_BIBLE_SLUG . '/%s';
}
add_filter('bfox_blog_bible_url_template', 'bfox_bp_bible_url_template');

function bfox_bp_bible_directory_get_last_viewed() {
	global $user_ID;
	if ($user_ID) return get_user_option('bfox_bp_bible_directory_last_viewed');
	return $_COOKIE['bfox_bp_bible_directory_last_viewed'];
}

function bfox_bp_bible_directory_set_last_viewed($ref_str) {
	global $user_ID;
	if ($user_ID) update_user_option($user_ID, 'bfox_bp_bible_directory_last_viewed', $ref_str, true);
	else setcookie('bfox_bp_bible_directory_last_viewed', $ref_str, /* 30 days from now: */ time() + 60 * 60 * 24 * 30, '/');
}

function bfox_bp_bible_directory_setup() {
	global $bp, $biblefox;

	if ($bp->current_component == BFOX_BIBLE_SLUG && empty($bp->displayed_user->id)) {
		if (!empty($_POST['s'])) bp_core_redirect(bfox_bp_bible_directory_url($_POST['s']));

		$bp->is_directory = true;

		// Get the bible reference from the current_action
		$refs = new BfoxRefs(urldecode($bp->current_action));

		// If we don't have a valid Bible reference, we should redirect to one
		if (!$refs->is_valid()) {
			// First, try to use the last viewed reference
			$refs = new BfoxRefs(bfox_bp_bible_directory_get_last_viewed());
			// If we don't have a last viewed reference, use Gen 1
			if (!$refs->is_valid()) $refs = new BfoxRefs('Gen 1');
			bp_core_redirect(Biblefox::ref_bible_url($refs->get_string()));
		}

		bfox_bp_bible_directory_set_last_viewed($refs->get_string());
		$biblefox->set_refs($refs);

		do_action('bfox_bp_bible_directory_setup');

		bfox_bp_core_load_template(apply_filters('bfox_bp_bible_directory_template', 'activity/index'));
	}
}
add_action('wp', 'bfox_bp_bible_directory_setup', 2);

function bfox_bp_bible_directory_setup_activity_template() {
	wp_enqueue_style('biblefox-bp');

	// Add some javascript to ensure that any AJAX calls include the current bible references
	add_action('wp_head', 'bfox_bp_bible_directory_setup_ajax');
	add_action('wp_head', 'bfox_bp_bible_directory_setup_what_read_ajax');

	// Add the Bible iframe
	add_action('bp_before_directory_activity_content', 'bfox_bp_before_bible_directory_activity_content');

	add_action('bp_before_activity_post_form', 'bfox_bp_before_activity_post_form');

	// HACK: Get rid of the 'Site Activity' string which is used as the header when not logged in
	// This is a hack because we are filtering in the translate functions
	add_filter('gettext', create_function('$translated, $text, $domain', 'if (\'Site Activity\' == $text) return \'\'; return $translated;'), 10, 3);
	add_filter('gettext', create_function('$translated, $text, $domain', 'if (\'What\\\'s new %s?\' == $text) return \'Any thoughts to share?\'; return $translated;'), 10, 3);
}
add_action('bfox_bp_bible_directory_setup', 'bfox_bp_bible_directory_setup_activity_template');

function bfox_bp_bible_directory_url($page = '') {
	global $bp;
	return apply_filters('bfox_bp_bible_directory_url', $bp->root_domain . '/' . BFOX_BIBLE_SLUG . '/' . urlencode($page), $page);
}

/**
 * Adds 'bible' to the list of search options in the Buddypress header
 * @param $select
 * @return unknown_type
 */
function bfox_bp_bible_directory_search_form_type_select($select) {
	return preg_replace('/(<select.*?>)/', '$1<option value="' . BFOX_BIBLE_SLUG . '">' . __( 'Bible', 'biblefox' ) . '</option>', $select);
}
add_filter('bp_search_form_type_select', 'bfox_bp_bible_directory_search_form_type_select');

/**
 * Similar to bp_core_action_search_site() but handles the 'bible' search action
 * @param $slug
 */
function bfox_bp_bible_directory_action_search_site( $slug = false ) {
	global $bp;

	if ($bp->current_component == BP_SEARCH_SLUG) {
		$search_terms = $_POST['search-terms'];
		$search_which = $_POST['search-which'];
		if (BFOX_BIBLE_SLUG == $search_which) bp_core_redirect(bfox_bp_bible_directory_url($search_terms));
	}
}
// This has to get run before bp_core_action_search_site()
add_action('init', 'bfox_bp_bible_directory_action_search_site', 4);

function bfox_bp_bible_directory_setup_ajax() {
	global $biblefox;
	$refs = $biblefox->refs();

	// Add some javascript to ensure that any AJAX calls include the current bible references
	if ($refs->is_valid()) {
		?>
		<script type='text/javascript'>
		<!--
		jQuery.ajaxSetup({
			data: {'bfox_refs': '<?php echo urlencode($refs->get_string()) ?>'}
	 	});
		//-->
		</script>
		<?php
	}
}

function bfox_bp_bible_directory_setup_what_read_ajax() {
	// Use the jQuery.ajaxSend() function to add the "What did you read?" data (see: http://api.jquery.com/ajaxSend/ )
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

function bfox_bp_bible_directory_before_activity_loop() {
	global $biblefox;
	$refs = $biblefox->refs();
	if ($refs->is_valid()) bfox_bp_activity_set_refs($refs);
	elseif (!empty($_REQUEST['bfox_refs'])) bfox_bp_activity_set_refs(new BfoxRefs(urldecode($_REQUEST['bfox_refs'])));
}
add_action('bp_before_activity_loop', 'bfox_bp_bible_directory_before_activity_loop');
add_action('bp_after_activity_loop', 'bfox_bp_activity_unset_refs');

function bfox_bp_bible_directory_add_nav_item() {
	?>
	<li<?php if ( bp_is_page( BFOX_BIBLE_SLUG ) ) : ?> class="selected"<?php endif; ?>>
		<a href="<?php echo bfox_bp_bible_directory_url() ?>" title="<?php _e( 'Bible', 'biblefox' ) ?>"><?php _e( 'Bible Reader', 'biblefox' ) ?></a>
	</li>
	<?php
}
add_action('bp_nav_items', 'bfox_bp_bible_directory_add_nav_item');

function bfox_bp_bible_directory_iframe() {
	global $biblefox;
	$refs = $biblefox->refs();
	$prev_ref_str = $refs->prev_chapter_string();
	$next_ref_str = $refs->next_chapter_string();
	$links = '';
	if (!empty($prev_ref_str)) $links .= Biblefox::ref_bible_link(array('ref_str' => $prev_ref_str, 'attrs' => array('class' => "ref_seq_prev"), 'disable_tooltip' => TRUE));
	if (!empty($next_ref_str)) $links .= Biblefox::ref_bible_link(array('ref_str' => $next_ref_str, 'attrs' => array('class' => "ref_seq_next"), 'disable_tooltip' => TRUE));
	?>
			<h4><?php echo $refs->get_string() ?></h4>
			<div class='passage-nav'><?php echo $links ?></div>
			<?php $iframe = new BfoxIframe($refs) ?>
			<div class="bfox-iframe-wrap bfox-passage-iframe-wrap">
				<select class="bfox-iframe-select bfox-passage-iframe-select">
					<?php echo $iframe->select_options() ?>
				</select>
				<iframe class="bfox-iframe bfox-passage-iframe" src="<?php echo $iframe->url() ?>"></iframe>
			</div>
	<?php
}

function bfox_bp_bible_directory_search_form($search_value, $submit_value) {
?>
		<label><input type="text" name="s" id="bible_search" value="<?php echo attribute_escape($search_value) ?>" /></label>
		<input type="submit" id="bible_search_submit" name="bible_search_submit" value="<?php echo $submit_value ?>" />
<?php
}

function bfox_bp_before_bible_directory_activity_content() {
	global $biblefox;

	$refs = $biblefox->refs();
	$search_value = $refs->get_string(BibleMeta::name_short);

	?>
		<form action="<?php echo bfox_bp_bible_directory_url() ?>" method="post" id="bible-directory-form" class="dir-form">
			<h3><?php _e( 'Bible Reader', 'biblefox' ) ?></h3>
			<div id="bible-dir-search" class="dir-search no-ajax">
				<?php bfox_bp_bible_directory_search_form($search_value, __('Go to passage', 'biblefox')) ?>
			</div><!-- #group-dir-search -->
		</form>
	<?php
	bfox_bp_bible_directory_iframe();
}

function bfox_bp_before_activity_post_form() {
	global $biblefox;

	$refs = $biblefox->refs();
	$ref_str = $refs->get_string();

	?>
		<div class="refs-read">
		<label><?php _e('What did you read?', 'biblefox') ?>&nbsp;<input type="text" id="bfox_read_ref_str" name="bfox_read_ref_str" value="<?php echo attribute_escape($ref_str) ?>" /></label>
		</div>
	<?php
}

?>