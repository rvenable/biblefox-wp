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
		$bp->is_directory = true;

		$biblefox->set_refs(new BfoxRefs(urldecode($bp->current_action)));

		wp_enqueue_style('biblefox-bp');

		// Add some javascript to ensure that any AJAX calls include the current bible references
		add_action('wp_head', 'bfox_bp_bible_directory_setup_ajax');

		// Add the Bible iframe
		add_action('bp_before_directory_activity_content', 'bfox_bp_bible_directory_iframe');

		do_action('bfox_bp_bible_directory_setup');

		bfox_bp_core_load_template(apply_filters('bfox_bp_bible_directory_template', 'activity/index'));
	}
}
add_action('wp', 'bfox_bp_bible_directory_setup', 2);

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
		<a href="<?php echo get_option('home') ?>/<?php echo BFOX_BIBLE_SLUG ?>" title="<?php _e( 'Bible Reader', 'biblefox' ) ?>"><?php _e( 'Bible', 'biblefox' ) ?></a>
	</li>
	<?php
}
add_action('bp_nav_items', 'bfox_bp_bible_directory_add_nav_item');

function bfox_bp_bible_directory_iframe() {
	global $biblefox;
	$refs = $biblefox->refs();

	?>
			<h4><?php echo $refs->get_string() ?></h4>
			<?php $iframe = new BfoxIframe($refs) ?>
			<div class="bfox-iframe-wrap bfox-passage-iframe-wrap">
				<select class="bfox-iframe-select bfox-passage-iframe-select">
					<?php echo $iframe->select_options() ?>
				</select>
				<iframe class="bfox-iframe bfox-passage-iframe" src="<?php echo $iframe->url() ?>"></iframe>
			</div>
	<?php
}

function bfox_bp_bible_directory_search_form() {
	global $biblefox;

	$refs = $biblefox->refs();
	$search_value = $refs->get_string(BibleMeta::name_short);

?>
	<form action="" method="get" id="search-bible-form">
		<label><input type="text" name="s" id="bible_search" value="<?php echo attribute_escape($search_value) ?>" /></label>
		<input type="submit" id="groups_search_submit" name="groups_search_submit" value="<?php _e( 'Go to passage', 'buddypress' ) ?>" />
	</form>
<?php
}

?>