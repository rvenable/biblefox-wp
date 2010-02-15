<?php

/* Define the slug for the component */
if (!defined('BFOX_BIBLE_SLUG')) define(BFOX_BIBLE_SLUG, 'bible');

function bfox_bp_bible_directory_setup_root_component() {
	bp_core_add_root_component(BFOX_BIBLE_SLUG);
}
add_action('plugins_loaded', 'bfox_bp_bible_directory_setup_root_component', 2);

function bfox_bp_bible_directory_setup() {
	global $bp, $biblefox;

	if ($bp->current_component == BFOX_BIBLE_SLUG && empty($bp->displayed_user->id)) {
		if (!empty($_POST['s'])) bp_core_redirect(bfox_bp_bible_directory_url($_POST['s']));

		$bp->is_directory = true;

		$biblefox->set_refs(new BfoxRefs(urldecode($bp->current_action)));

		wp_enqueue_style('biblefox-bp');

		// Add some javascript to ensure that any AJAX calls include the current bible references
		add_action('wp_head', 'bfox_bp_bible_directory_setup_ajax');

		// Add the Bible iframe
		add_action('bp_before_directory_activity_content', 'bfox_bp_before_bible_directory_activity_content');

		// HACK: Get rid of the 'Site Activity' string which is used as the header when not logged in
		// This is a hack because we are filtering in the translate functions
		add_filter('gettext', create_function('$translated, $text, $domain', 'if (\'Site Activity\' == $text) return \'\'; return $translated;'), 10, 3);

		do_action('bfox_bp_bible_directory_setup');

		bfox_bp_core_load_template(apply_filters('bfox_bp_bible_directory_template', 'activity/index'));
	}
}
add_action('wp', 'bfox_bp_bible_directory_setup', 2);

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
	if (!empty($prev_ref_str)) $links .= bp_bible_ref_link(array('ref_str' => $prev_ref_str, 'attrs' => array('class' => "ref_seq_prev"), 'disable_tooltip' => TRUE));
	if (!empty($next_ref_str)) $links .= bp_bible_ref_link(array('ref_str' => $next_ref_str, 'attrs' => array('class' => "ref_seq_next"), 'disable_tooltip' => TRUE));
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
		<input type="submit" id="groups_search_submit" name="groups_search_submit" value="<?php echo $submit_value ?>" />
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

?>