<?php
/*************************************************************************
Plugin Name: Biblefox for WordPress
Plugin URI: http://dev.biblefox.com/biblefox-for-wordpress/
Description: Turns your WordPress site into an online Bible study tool. Creates a Bible index for your WordPress site, allowing your users to easily search your blog posts (or BuddyPress activities, when using BuddyPress) for any Bible reference. Use it for WordPress sites that involve a lot of discussion of the Bible.
Version: 1.0 beta
Author: Biblefox.com, rvenable
Author URI: http://biblefox.com
License: General Public License version 2
Requires at least: WP 3.0, BuddyPress 1.2
Tested up to: WP 3.0, BuddyPress 1.2.4.1
Text Domain: bfox
*************************************************************************/

/*************************************************************************

	Copyright 2010 Biblefox.com

	This file is part of Biblefox for WordPress.

	Biblefox for WordPress is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Biblefox for WordPress is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with Biblefox for WordPress.  If not, see <http://www.gnu.org/licenses/>.

*************************************************************************/

define('BFOX_VERSION', '1.0');
define('BFOX_DIR', dirname(__FILE__));
define('BFOX_REF_DIR', BFOX_DIR . '/external/biblefox-ref');
define('BFOX_API_DIR', BFOX_DIR . '/api');
define('BFOX_URL', WP_PLUGIN_URL . '/biblefox-for-wordpress');
define('BFOX_PLANS_URL', BFOX_URL . '/reading-plans');

require_once BFOX_REF_DIR . '/biblefox-ref.php';
require_once BFOX_DIR . '/bfox_ref.php';
require_once BFOX_DIR . '/bfox_tool.php';
require_once BFOX_DIR . '/bfox_plan.php';


require_once BFOX_API_DIR . '/bfox_ref-functions.php';
require_once BFOX_API_DIR . '/bfox_ref-template.php';
require_once BFOX_API_DIR . '/bfox_tool-functions.php';
require_once BFOX_API_DIR . '/bfox_tool-template.php';
require_once BFOX_API_DIR . '/bfox_plan-template.php';

require_once BFOX_DIR . '/biblefox-blog/biblefox-blog.php';

// TODO: these need to be moved into API
require_once BFOX_DIR . '/translations.php';

function bfox_init() {
	wp_enqueue_style('bfox-style', BFOX_URL . '/theme/style.css', array(), BFOX_VERSION);
	wp_enqueue_style('bfox-plan-style', BFOX_URL . '/theme/style-bfox_plan.css', array(), BFOX_VERSION);
}
add_action('init', 'bfox_init');

/**
 * Checks to see if we are requesting tooltip content (ie. by an AJAX call), returns the content, and exits
 */
function bfox_check_for_tooltip() {
	if (isset($_REQUEST['bfox-tooltip-ref'])) {
		set_bfox_ref(new BfoxRef(str_replace('_', ' ', $_REQUEST['bfox-tooltip-ref'])));

		// Make sure that the we have a query on bfox_tool
		// TODO: We can get rid of this if we make sure that the tooltip URL is already loading the right query
		// ie. if the tooltip URL loads the bfox_tool archive query (/bible-tools/?tooltip_ref=Gen+1).
		query_posts(array('post_type' => 'bfox_tool'));

		load_bfox_template('bfox-tooltip');
		exit;
	}
}
add_action('init', 'bfox_check_for_tooltip', 1000);

/*
 AJAX function for sending the bible text
 */
function bfox_ajax_send_bible_text() {
	sleep(1);

	set_bfox_ref(new BfoxRef($_POST['ref_str']));

	ob_start();

	load_bfox_template('admin-bfox_tool');

	$content = ob_get_clean();
	$content = addslashes(str_replace("\n", '', $content));

	$script = "bfox_quick_view_loaded('$ref_str', '$content');";
	die($script);
}
add_action('wp_ajax_bfox_ajax_send_bible_text', 'bfox_ajax_send_bible_text');

/**
 * Returns a path for a Biblefox theme template file, first trying to load from the theme, then from the plugin
 */
function bfox_template_path($template) {
	$template .= '.php';
	$path = locate_template(array($template));
	if (empty($path)) $path = BFOX_DIR . '/theme/' . $template;
	return apply_filters('bfox_template_path', $path, $template);
}

/**
 * Loads a Biblefox theme template file, first trying to load from the theme, then from the plugin
 */
function load_bfox_template($template) {
	$path = bfox_template_path($template);
	load_template($path);
}

/**
 * Loads the BuddyPress related features
 */
function bfox_bp_init() {
	require_once BFOX_DIR . '/biblefox-bp/biblefox-bp.php';
}
add_action('bp_init', 'bfox_bp_init');

?>