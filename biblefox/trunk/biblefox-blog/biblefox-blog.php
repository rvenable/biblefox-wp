<?php

define('BFOX_BLOG_DIR', dirname(__FILE__));
define('BFOX_BLOG_URL', BFOX_URL . '/biblefox-blog');

require_once BFOX_BLOG_DIR . '/posts.php';

function bfox_blog_admin_menu() {
	require_once BFOX_BLOG_DIR . '/admin.php';

	add_options_page(
		__('Bible Settings', 'biblefox'),
		__('Bible Settings', 'biblefox'),
		'manage_options',
		'bfox-blog-settings',
		'bfox_blog_admin_page'
	);

	add_settings_section('bfox-admin-settings-main', 'Settings', 'bfox_bp_admin_settings_bible_directory', 'bfox-admin-settings');

	add_settings_section('bfox-blog-admin-settings-main', __('Settings', 'bfox'), 'bfox_blog_admin_settings_main', 'bfox-blog-admin-settings');

	add_settings_field('bfox-tooltips', __('Bible Reference Tooltips', 'bfox'), 'bfox_blog_admin_setting_tooltips', 'bfox-blog-admin-settings', 'bfox-blog-admin-settings-main', array('label_for' => 'bfox-toolips'));
	register_setting('bfox-blog-admin-settings', 'bfox-blog-options');

	do_action('bfox_blog_admin_menu');
}
if (!is_multisite() || get_site_option('bfox-ms-allow-blog-options')) add_action('admin_menu', 'bfox_blog_admin_menu', 20);

function bfox_ms_admin_menu() {
	require_once BFOX_BLOG_DIR . '/ms-admin.php';
	require_once BFOX_BLOG_DIR . '/admin.php'; // We need to load this for the blog options functions (for instance, bfox_blog_admin_setting_tooltips())

	add_submenu_page(
		'wpmu-admin.php',
		__('Biblefox', 'biblefox'),
		__('Biblefox', 'biblefox'),
		10,
		'bfox-ms',
		'bfox_ms_admin_page'
	);

	add_settings_section('bfox-ms-admin-settings-main', __('Settings', 'bfox'), 'bfox_ms_admin_settings_main', 'bfox-ms-admin-settings');
	add_settings_field('bfox-ms-allow-blog-options', __('Allow Biblefox Blog Options', 'bfox'), 'bfox_ms_admin_setting_allow_blog_options', 'bfox-ms-admin-settings', 'bfox-ms-admin-settings-main', array('label_for' => 'bfox-ms-allow-blog-options'));

	// Blog settings (found in admin.php, not ms-admin.php)
	add_settings_field('bfox-tooltips', __('Bible Reference Tooltips', 'bfox'), 'bfox_blog_admin_setting_tooltips', 'bfox-ms-admin-settings', 'bfox-ms-admin-settings-main', array('label_for' => 'bfox-tooltips'));

	do_action('bfox_ms_admin_menu');
}
if (is_multisite()) add_action('admin_menu', 'bfox_ms_admin_menu', 20);

function bfox_blog_option_defaults($new_options = array()) {
	$defaults = array(
		'tooltips' => true,
	);

	if (!empty($new_options)) {
		foreach ($defaults as $key => $value) {
			if (isset($new_options[$key])) $defaults[$key] = $new_options[$value];
		}
	}

	return $defaults;
}

function bfox_blog_options() {
	global $_bfox_blog_options;

	if (!isset($_bfox_blog_options)) {
		// Get the options using get_site_option() first (which will be get_option() if not multisite anyway)
		$_bfox_blog_options = get_site_option('bfox-blog-options');

		// If we are allowing the blog to set options, use them to overwrite the defaults
		if (is_multisite() && get_site_option('bfox-ms-allow-blog-options'))
			$_bfox_blog_options = array_merge($_bfox_blog_options, (array) get_option('bfox-blog-options'));

		// Add the default values
		$_bfox_blog_options = bfox_blog_option_defaults($_bfox_blog_options);
	}

	return $_bfox_blog_options;
}

function bfox_blog_option($key) {
	$options = bfox_blog_options();
	return $options[$key];
}

/*
 * Bible post write link handling
 *
 * Pretty hacky, but better than previous javascript hack
 * HACK necessary until WP ticket 10544 is fixed: http://core.trac.wordpress.org/ticket/10544
 */

function bfox_bible_post_link_setup($page, $context, $post) {
	if (!$post->ID && 'post' == $page && 'side' == $context && !empty($_REQUEST['bfox_ref'])) {
		$hidden_refs = new BfoxRefs($_REQUEST['bfox_ref']);
		if ($hidden_refs->is_valid()) {
			global $wp_meta_boxes;
			// Change the callback function
			$wp_meta_boxes[$page][$context]['core']['tagsdiv-post_tag']['callback'] = 'bfox_post_tags_meta_box';
		}
	}
}
add_action('do_meta_boxes', 'bfox_bible_post_link_setup', 10, 3);

function bfox_post_tags_meta_box($post, $box) {
	// We need our filter on wp_get_object_terms to get called, but it won't be if post->ID is 0, so we set it to -1
	$fake_post = new stdClass;
	$fake_post->ID = -1;
	add_action('wp_get_object_terms', 'bfox_wp_get_object_terms');
	post_tags_meta_box($fake_post, $box);
	remove_action('wp_get_object_terms', 'bfox_wp_get_object_terms');
}

function bfox_wp_get_object_terms($terms) {
	$hidden_refs = new BfoxRefs($_REQUEST['bfox_ref']);
	if ($hidden_refs->is_valid()) {
		$term = new stdClass;
		$term->name = $hidden_refs->get_string();
		$terms = array($term);
	}
	return $terms;
}

?>