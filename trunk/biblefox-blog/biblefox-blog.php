<?php

define('BFOX_BLOG_DIR', dirname(__FILE__));
define('BFOX_BLOG_URL', BFOX_URL . '/biblefox-blog');

require_once BFOX_BLOG_DIR . '/posts.php';

function bfox_blog_init() {
	// Styles
	wp_enqueue_style('bfox-scripture', BFOX_URL . '/includes/css/scripture.css', array(), BFOX_VERSION);
	wp_enqueue_style('bfox-blog', BFOX_URL . '/includes/css/biblefox-blog.css', array(), BFOX_VERSION);

	// Scripts
	wp_enqueue_script('bfox-blog', BFOX_URL . '/includes/js/biblefox-blog.js', array('jquery'), BFOX_VERSION);

	if (!bfox_blog_option('disable-tooltips')) {
		wp_register_script('bfox-qtip', BFOX_URL . '/includes/js/jquery-qtip/jquery.qtip-1.0.0-rc3-custom.min.js', array('jquery'), BFOX_VERSION);
		wp_enqueue_script('bfox-tooltips', BFOX_URL . '/includes/js/tooltips.js', array('jquery', 'bfox-qtip'), BFOX_VERSION);

		// Load the ajaxurl var if BuddyPress isn't planning on loading
		if (!has_action('wp_head', 'bp_core_add_ajax_url_js')) {
			function bfox_blog_add_ajax_url_js() {
				echo '<script type="text/javascript">var ajaxurl = "' . site_url( 'wp-load.php' ) . '";</script>';
			}
			add_action('wp_head', 'bfox_blog_add_ajax_url_js');
		}
	}
}
add_action('init', 'bfox_blog_init');

function bfox_blog_add_menu() {
	add_meta_box('bible-quick-view-div', __('Biblefox Bible', 'bfox'), 'bfox_blog_quick_view_meta_box', 'post', 'normal', 'core');
}
add_action('admin_menu', 'bfox_blog_add_menu');

function bfox_blog_admin_init() {
	wp_enqueue_script('bfox-admin', BFOX_URL . '/includes/js/admin.js', array('sack'), BFOX_VERSION);
}
add_action('admin_init', 'bfox_blog_admin_init');

/**
 * Returns a url for the search page using a Bible Reference as the search filter
 *
 * Should be used whenever we want to link to the Bible search archive, as opposed to the Bible reader
 *
 * @param string $ref_str
 * @return string
 */
function bfox_ref_blog_url($ref_str) {
	$ref_str = urlencode(strtolower($ref_str));

	// NOTE: This function imitates the WP get_tag_link() function, but instead of getting a tag slug, we use $ref_str
	global $wp_rewrite;
	$taglink = $wp_rewrite->get_search_permastruct();

	if (empty($taglink)) $taglink = get_option('home') . '/?s=' . $ref_str;
	else {
		$taglink = str_replace('%search%', $ref_str, $taglink);
		$taglink = get_option('home') . '/' . user_trailingslashit($taglink, 'category');
	}

	return $taglink;
}

/**
 * Returns a link for the blog search page using a Bible Reference as the tag filter
 *
 * Should be used whenever we want to link to the Bible search archive, as opposed to the Bible reader
 *
 * @param array $options
 * @return string
 */
function bfox_ref_blog_link($options) {
	bfox_fix_ref_link_options($options);

	// If there is no href, get it from the bfox_ref_blog_url() function
	if (!isset($options['attrs']['href'])) $options['attrs']['href'] = bfox_ref_blog_url($options['ref_str']);

	return bfox_ref_link_from_options($options);
}

// TODO: remove
function bfox_blog_ref_link_ajax($ref_str, $text = '', $attrs = '') {
	if (empty($text)) $text = $ref_str;

	return "<a href='#bible_ref' onclick='bible_text_request(\"$ref_str\")' $attrs>$text</a>";
}

function bfox_blog_ref_write_url($ref_str, $home_url = '') {
	if (empty($home_url)) $home_url = get_option('home');

	return rtrim($home_url, '/') . '/wp-admin/post-new.php?bfox_ref=' . urlencode($ref_str);
}

function bfox_blog_ref_write_link($ref_str, $text = '', $home_url = '') {
	if (empty($text)) $text = $ref_str;

	return "<a href='" . bfox_blog_ref_write_url($ref_str, $home_url) . "'>$text</a>";
}

function bfox_blog_ref_edit_posts_link($ref_str, $text = '') {
	if (empty($text)) $text = $ref_str;
	$href = get_option('home') . '/wp-admin/edit.php?tag=' . urlencode($ref_str);

	return "<a href='$href'>$text</a>";
}

/**
 * Filters tags for bible references and changes their slugs to be bible reference friendly
 *
 * @param $term
 * @return object $term
 */
function bfox_blog_get_post_tag($term) {
	if ($ref = BfoxRefParser::no_leftovers($term->name)) $term->slug = urlencode($ref->get_string());
	return $term;
}
add_filter('get_post_tag', 'bfox_blog_get_post_tag', 10, 2);

/**
 * Returns a WP_Query object with the posts that contain the given BfoxRef
 *
 * @param BfoxRef $ref
 * @return WP_Query
 */
function bfox_blog_query_for_ref(BfoxRef $ref) {
	return new WP_Query('s=' . urlencode($ref->get_string()));
}

function bfox_blog_admin_menu() {
	require_once BFOX_BLOG_DIR . '/admin.php';

	add_options_page(
		__('Bible Settings', 'bfox'), // Page title
		__('Bible Settings', 'bfox'), // Menu title
		'manage_options', // Capability
		'bfox-blog-settings', // Menu slug
		'bfox_blog_admin_page' // Function
	);

	add_settings_section('bfox-admin-settings-main', 'Settings', 'bfox_bp_admin_settings_bible_directory', 'bfox-admin-settings');

	add_settings_section('bfox-blog-admin-settings-main', __('Settings', 'bfox'), 'bfox_blog_admin_settings_main', 'bfox-blog-admin-settings');

	add_settings_field('bfox-tooltips', __('Disable Bible Reference Tooltips', 'bfox'), 'bfox_blog_admin_setting_tooltips', 'bfox-blog-admin-settings', 'bfox-blog-admin-settings-main', array('label_for' => 'bfox-toolips'));
	register_setting('bfox-blog-admin-settings', 'bfox-blog-options');

	do_action('bfox_blog_admin_menu');
}
if (!is_multisite() || get_site_option('bfox-ms-allow-blog-options')) add_action('admin_menu', 'bfox_blog_admin_menu', 20);

function bfox_ms_admin_menu() {
	require_once BFOX_BLOG_DIR . '/ms-admin.php';
	require_once BFOX_BLOG_DIR . '/admin.php'; // We need to load this for the blog options functions (for instance, bfox_blog_admin_setting_tooltips())

	add_submenu_page(
		'ms-admin.php', // Parent slug
		__('Biblefox', 'bfox'), // Page title
		__('Biblefox', 'bfox'), // Menu title
		10, // Capability
		'bfox-ms', // Menu slug
		'bfox_ms_admin_page' // Function
	);

	add_settings_section('bfox-ms-admin-settings-main', __('Settings', 'bfox'), 'bfox_ms_admin_settings_main', 'bfox-ms-admin-settings');
	add_settings_field('bfox-ms-allow-blog-options', __('Allow Biblefox Blog Options', 'bfox'), 'bfox_ms_admin_setting_allow_blog_options', 'bfox-ms-admin-settings', 'bfox-ms-admin-settings-main', array('label_for' => 'bfox-ms-allow-blog-options'));

	// Blog settings (found in admin.php, not ms-admin.php)
	add_settings_field('bfox-tooltips', __('Disable Bible Reference Tooltips', 'bfox'), 'bfox_blog_admin_setting_tooltips', 'bfox-ms-admin-settings', 'bfox-ms-admin-settings-main', array('label_for' => 'bfox-tooltips'));

	do_action('bfox_ms_admin_menu');
}
if (is_multisite()) add_action('admin_menu', 'bfox_ms_admin_menu', 20);

// Add "Settings" link on plugins menu
function bfox_blog_admin_add_action_link($links, $file) {
	if ('biblefox-for-wordpress/biblefox.php' != $file) return $links;

	if (!is_multisite() || get_site_option('bfox-ms-allow-blog-options')) array_unshift($links, '<a href="' . menu_page_url('bfox-blog-settings', false) . '">' . __('Settings', 'bfox') . '</a>');
	if (is_multisite()) array_unshift($links, '<a href="' . menu_page_url('bfox-ms', false) . '">' . __('Network Settings', 'bfox') . '</a>');

	return $links;
}
add_filter('plugin_action_links', 'bfox_blog_admin_add_action_link', 10, 2);

function bfox_blog_options() {
	global $_bfox_blog_options;

	if (!isset($_bfox_blog_options)) {
		// Get the options using get_site_option() first (which will be get_option() if not multisite anyway)
		$_bfox_blog_options = (array) get_site_option('bfox-blog-options');

		// If we are allowing the blog to set options, use them to overwrite the defaults
		if (is_multisite() && get_site_option('bfox-ms-allow-blog-options'))
			$_bfox_blog_options = array_merge($_bfox_blog_options, (array) get_option('bfox-blog-options'));
	}

	return $_bfox_blog_options;
}

function bfox_blog_option($key) {
	$options = bfox_blog_options();
	return $options[$key];
}

?>