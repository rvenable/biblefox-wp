<?php

define('BFOX_BP_DIR', dirname(__FILE__));
define('BFOX_BP_URL', BFOX_URL . '/biblefox-bp');

/**
 * Set up the admin menu
 */

function bfox_bp_admin_init() {
	if (is_super_admin()) {
		require_once BFOX_BP_DIR . '/admin.php';

		do_action('bfox_bp_admin_init');
	}
}
add_action('admin_init', 'bfox_bp_admin_init');

function bfox_bp_admin_menu() {
	if (is_super_admin()) {
		do_action('bfox_bp_check_install');

		add_submenu_page(
			'bp-general-settings', // Parent slug
			__('Biblefox', 'bfox'), // Page title
			__('Biblefox', 'bfox'), // Menu title
			'manage_options', // Capability
			'bfox-bp-settings', // Menu slug
			'bfox_bp_admin_page' // Function
		);

		add_settings_section('bfox-bp-admin-settings-main', __('Settings', 'bfox'), 'bfox_bp_admin_settings_main', 'bfox-bp-admin-settings');

		register_setting('bfox-bp-admin-settings', 'bfox-enable-bible-directory', 'bfox_bp_option_sanitize');
		add_settings_field('bfox-enable-bible-directory', 'Enable BuddyPress Bible Directory', 'bfox_bp_admin_setting_enable_bible_directory', 'bfox-bp-admin-settings', 'bfox-bp-admin-settings-main', array('label_for' => 'bfox-enable-bible-directory'));

		do_action('bfox_bp_admin_menu');
	}
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
	if (is_main_site()) do_action('bfox_bp_register_widgets');
}
add_action('widgets_init', 'bfox_bp_register_widgets');

// HACK: this function is a hack to get around a bug in bp_core_load_template() and bp_core_catch_no_access()
function bfox_bp_core_load_template($template) {
	bp_core_load_template($template);
	remove_action('wp', 'bp_core_catch_no_access');
}

/*
 * Options Functions
 */
function bfox_bp_get_option($key) {
	return get_blog_option(BP_ROOT_BLOG, $key);
}

function bfox_bp_update_option($key, $value) {
	return update_blog_option(BP_ROOT_BLOG, $key, $value);
}

/**
 * Sanitize the BP options
 *
 * We don't want to save BP options to any blog other than the BP_ROOT_BLOG
 *
 * @param string $new_value
 * @param mixed $option
 * @return string $new_value
 */
function bfox_bp_option_sanitize($new_value, $option = '') {
	global $blog_id;
	// We don't want to save BP options to any blog other than the BP_ROOT_BLOG
	if (BP_ROOT_BLOG != $blog_id) {
		if (!$option) $option = substr(current_filter(), strlen('sanitize_option_'));

		// Save the option to the BP_ROOT_BLOG
		update_blog_option(BP_ROOT_BLOG, $option, $new_value);

		// Since we really want to save to the BP_ROOT_BLOG, let's not change the value in this blog
		$new_value = get_option($option);
	}
	return $new_value;
}

// Load other files
require_once BFOX_BP_DIR . '/activity.php';
if (bfox_bp_get_option('bfox-enable-bible-directory')) require_once BFOX_BP_DIR . '/bible-directory.php';

do_action('bfox_bp_loaded');

?>