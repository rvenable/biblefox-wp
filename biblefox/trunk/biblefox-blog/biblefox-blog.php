<?php

define('BFOX_BLOG_DIR', dirname(__FILE__));
define('BFOX_BLOG_URL', BFOX_URL . '/biblefox-blog');

require_once BFOX_BLOG_DIR . '/posts.php';

function bfox_blog_admin_menu() {
	add_options_page(
		__('Bible Settings', 'biblefox'),
		__('Bible Settings', 'biblefox'),
		'manage_options',
		'bfox-blog-settings',
		'bfox_blog_admin_settings'
	);

	if (is_multisite()) add_submenu_page(
		'wpmu-admin.php',
		__('Bible Settings', 'biblefox'),
		__('Bible Settings', 'biblefox'),
		10,
		'bfox-blog-network-settings',
		'bfox_blog_network_admin_settings'
	);
}
add_action('admin_menu', 'bfox_blog_admin_menu', 20);

/**
 * Hook for displaying admin settings.
 *
 * In a multisite install, these will appear in the Network admin panel (via bfox_blog_admin_settings).
 * Otherwise, these will appear in the Blog settings panel (via bfox_blog_admin_settings).
 */
function bfox_admin_settings() {
	do_action('bfox_admin_settings');
}
if (is_multisite()) add_action('bfox_blog_network_admin_settings', 'bfox_admin_settings', 100);
else add_action('bfox_blog_admin_settings', 'bfox_admin_settings', 100);

function bfox_blog_admin_settings() {
	?>
	<div class="wrap">
		<h2><?php _e('Biblefox for WordPress Settings', 'biblefox') ?></h2>
	<?php if (apply_filters('bfox_blog_admin_show_settings', true)): ?>
		<p><?php _e('Biblefox for WordPress finds Bible references in all your blog posts, indexing your blog by the Bible verses you write about.', 'biblefox')?></p>
		<?php do_action('bfox_blog_admin_settings') ?>
	<?php endif ?>
	</div>
	<?php
}

function bfox_blog_network_admin_settings() {
	?>
	<div class="wrap">
		<h2><?php _e('Biblefox for WordPress - Network Admin Settings', 'biblefox') ?></h2>
	<?php if (apply_filters('bfox_blog_network_admin_show_settings', true)): ?>
		<p><?php _e('Biblefox for WordPress finds Bible references in all your blog posts, indexing your blog by the Bible verses you write about.', 'biblefox')?></p>
		<?php do_action('bfox_blog_network_admin_settings') ?>
	<?php endif ?>
	</div>
	<?php
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