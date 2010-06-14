<?php

define('BFOX_BP_DIR', dirname(__FILE__));
define('BFOX_BP_URL', BFOX_URL . '/biblefox-bp');

require_once BFOX_BP_DIR . '/activity.php';

if (get_site_option('bfox-enable-bible-directory')) require_once BFOX_BP_DIR . '/bible-directory.php';

function bfox_bp_admin_menu() {
	require_once BFOX_BP_DIR . '/admin.php';

	add_submenu_page(
		'bp-general-settings',
		__('Biblefox', 'bfox'),
		__('Biblefox', 'bfox'),
		'manage_options',
		'bfox-bp-settings',
		'bfox_bp_admin_page'
	);

	add_settings_section('bfox-bp-admin-settings-main', __('Settings', 'bfox'), 'bfox_bp_admin_settings_main', 'bfox-bp-admin-settings');

	register_setting('bfox-bp-admin-settings', 'bfox-enable-bible-directory');
	add_settings_field('bfox-enable-bible-directory', 'Enable BuddyPress Bible Directory', 'bfox_bp_admin_setting_enable_bible_directory', 'bfox-bp-admin-settings', 'bfox-bp-admin-settings-main', array('label_for' => 'bfox-enable-bible-directory'));
}
add_action('admin_menu', 'bfox_bp_admin_menu', 20);

// Add Ref Replace filters
add_filter('bp_get_activity_content_body', 'bfox_ref_replace_html');
add_filter('bp_get_the_topic_post_content', 'bfox_ref_replace_html');
add_action('bp_get_activity_action', 'bfox_ref_replace_html');

function bfox_bp_init() {
	wp_register_style('biblefox-bp', BFOX_BP_URL . '/biblefox-bp.css', array(), BFOX_VERSION);
	//wp_register_script('biblefox-bp', BFOX_BP_URL . '/biblefox-bp.js', array('jquery'), BFOX_VERSION);
}
add_action('init', 'bfox_bp_init');

function bfox_bp_register_widgets() {
	// Only register these widgets for the main blog
	if (is_main_blog()) do_action('bfox_bp_register_widgets');
}
add_action('widgets_init', 'bfox_bp_register_widgets');

// HACK: this function is a hack to get around a bug in bp_core_load_template() and bp_core_catch_no_access()
function bfox_bp_core_load_template($template) {
	bp_core_load_template($template);
	remove_action('wp', 'bp_core_catch_no_access');
}

function bfox_bp_check_install() {
	do_action('bfox_bp_check_install');
}
add_action('admin_menu', 'bfox_bp_check_install');

do_action('bfox_bp_loaded');

?>