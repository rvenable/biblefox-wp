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

	if (bfox_blog_option('tooltips')) {
		wp_register_script('bfox-qtip', BFOX_URL . '/includes/js/jquery-qtip/jquery.qtip-1.0.0-rc3-custom.min.js', array('jquery'), BFOX_VERSION);
		wp_enqueue_script('bfox-tooltips', BFOX_URL . '/includes/js/tooltips.js', array('jquery', 'bfox-qtip'), BFOX_VERSION);
	}
}
add_action('init', 'bfox_blog_init');

function bfox_blog_add_menu() {
	add_meta_box('bible-quick-view-div', __('Biblefox Bible'), 'bfox_blog_quick_view_meta_box', 'post', 'normal', 'core');
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
	$ref_str = urlencode($ref_str);

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
	if ($refs = BfoxRefParser::no_leftovers($term->name)) $term->slug = urlencode($refs->get_string());
	return $term;
}
add_filter('get_post_tag', 'bfox_blog_get_post_tag', 10, 2);

/**
 * Returns a WP_Query object with the posts that contain the given BfoxRefs
 *
 * @param BfoxRefs $refs
 * @return WP_Query
 */
function bfox_blog_query_for_refs(BfoxRefs $refs) {
	return new WP_Query('s=' . urlencode($refs->get_string()));
}

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

/**
 * Finds any bible references in an array of tag links and adds tooltips to them
 *
 * Should be used to filter 'term_links-post_tag', called in get_the_term_list()
 *
 * @param array $tag_links
 * @return array
 */
function bfox_add_tag_ref_tooltips($tag_links) {
	if (!empty($tag_links)) foreach ($tag_links as &$tag_link) if (preg_match('/<a.*>(.*)<\/a>/', $tag_link, $matches)) {
		$tag = $matches[1];
		$refs = bfox_refs_from_tag($tag);
		if ($refs->is_valid()) {
			$tag_link = bfox_ref_bible_link(array('refs' => $refs, 'text' => $tag));
		}
	}
	return $tag_links;
}

/**
 * Filter function for adding biblefox columns to the edit posts list
 *
 * @param $columns
 * @return array
 */
function bfox_manage_posts_columns($columns) {
	// Create a new columns array with our new columns, and in the specified order
	// See wp_manage_posts_columns() for the list of default columns
	$new_columns = array();
	foreach ($columns as $key => $column) {
		$new_columns[$key] = $column;

		// Add the bible verse column right after 'author' column
		if ('author' == $key) $new_columns['bfox_col_ref'] = __('Bible Verses');
	}
	return $new_columns;
}
add_filter('manage_posts_columns', 'bfox_manage_posts_columns');
//add_filter('manage_pages_columns', 'bfox_manage_posts_columns');

/**
 * Action function for displaying bible reference information in the edit posts list
 *
 * @param string $column_name
 * @param integer $post_id
 * @return none
 */
function bfox_manage_posts_custom_column($column_name, $post_id) {
	if ('bfox_col_ref' == $column_name) {
		global $post;
		if (isset($post->bfox_bible_refs)) echo bfox_blog_ref_edit_posts_link($post->bfox_bible_refs->get_string(BibleMeta::name_short));
	}

}
add_action('manage_posts_custom_column', 'bfox_manage_posts_custom_column', 10, 2);
//add_action('manage_pages_custom_column', 'bfox_manage_posts_custom_column', 10, 2);

?>