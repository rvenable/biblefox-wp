<?php

define(BFOX_BLOG_DIR, dirname(__FILE__));
define(BFOX_BLOG_URL, BFOX_URL . '/biblefox-blog');

require_once BFOX_BLOG_DIR . '/posts.php';

function bfox_blog_admin_menu() {
	add_options_page(
		__('Bible Settings', 'biblefox'),
		__('Bible Settings', 'biblefox'),
		'manage_options',
		'bfox-blog-settings',
		'bfox_blog_admin_settings'
	);

	add_submenu_page(
		'wpmu-admin.php',
		__('Bible Settings', 'biblefox'),
		__('Bible Settings', 'biblefox'),
		10,
		'bfox-blog-network-settings',
		'bfox_blog_network_admin_settings'
	);

}
add_action('admin_menu', 'bfox_blog_admin_menu', 20);


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

?>