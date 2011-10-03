<?php
/*************************************************************************
Plugin Name: Biblefox for WordPress
Plugin URI: http://dev.biblefox.com/biblefox-for-wordpress/
Description: Turns your WordPress site into an online Bible study tool. Creates a Bible index for your WordPress site, allowing your users to easily search your blog posts (or BuddyPress activities, when using BuddyPress) for any Bible reference. Use it for WordPress sites that involve a lot of discussion of the Bible.
Version: 0.8.3
Author: Biblefox.com, rvenable
Author URI: http://biblefox.com
License: General Public License version 2
Requires at least: WP 3.0, BuddyPress 1.2
Tested up to: WP 3.0, BuddyPress 1.2.4.1
Network: true
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

define('BFOX_VERSION', '0.8.3');
define('BFOX_DIR', dirname(__FILE__));
define('BFOX_REF_DIR', BFOX_DIR . '/external/biblefox-ref');
define('BFOX_URL', WP_PLUGIN_URL . '/biblefox-for-wordpress');
define('BFOX_PLANS_URL', BFOX_URL . '/reading-plans');

require_once BFOX_REF_DIR . '/biblefox-ref.php';

require_once BFOX_DIR . '/biblefox-blog/biblefox-blog.php';

require_once BFOX_DIR . '/bibletext.php';
require_once BFOX_DIR . '/translations.php';
require_once BFOX_DIR . '/iframe.php';

/**
 * Getter/Setter for the active instance of BfoxRef
 *
 * @param BfoxRef $ref
 * @return BfoxRef
 */
function bfox_active_ref(BfoxRef $ref = null) {
	global $_bfox_active_ref;
	if (!is_null($ref)) $_bfox_active_ref = $ref;
	if (!isset($_bfox_active_ref)) $_bfox_active_ref = new BfoxRef;
	return $_bfox_active_ref;
}

/**
 * Fixes a bible ref link options array so that it has a ref_str if it doesn't already
 *
 * @param $options
 */
function bfox_fix_ref_link_options(&$options) {
	// If there is no ref_str, try to get it from $ref->get_string($name)
	if (empty($options['ref_str']) && isset($options['ref']) && is_a($options['ref'], 'BfoxRef') && $options['ref']->is_valid())
		$options['ref_str'] = $options['ref']->get_string($options['name']);
}

/**
 * Creates a link from an array specifying bible ref link options
 *
 * Used to create links by bfox_ref_bible_link() and bfox_ref_blog_link()
 *
 * @param array $options
 * @return string
 */
function bfox_ref_link_from_options($options = array()) {
	extract($options);
	$link = '';

	// Only create a link if we actually have a ref_str
	if (!empty($ref_str)) {
		// If there is no text, use the ref_str
		if (empty($text)) $text = $ref_str;

		// If there is no href, get it from the bfox_ref_bible_url function
		if (!isset($attrs['href'])) bfox_ref_bible_url($ref_str);

		// Add the bible-ref class
		if (!isset($disable_tooltip)) {
			if (!empty($attrs['class'])) $attrs['class'] .= ' ';
			$attrs['class'] .= 'bible-tip bible-tip-' . urlencode(str_replace(' ', '_', strtolower($ref_str)));
		}

		$attr_str = '';
		foreach ($attrs as $attr => $value) $attr_str .= " $attr='$value'";

		$link = "<a$attr_str>$text</a>";
	}

	return $link;
}

function bfox_ref_url($ref_str, $post_id = 0) {
	if ($post_id) $bible_url = get_post_permalink($post_id);
	else $bible_url = get_post_type_archive_link('bfox_tool');

	return add_query_arg('ref', urlencode(strtolower($ref_str)), $bible_url);
}

/**
 * Returns a URL to the external Bible reader of choice for a given Bible Ref
 *
 * Should be used whenever we want to link to the Bible page, as opposed to the Bible archive
 *
 * @param string $ref_str
 * @return string
 */
function bfox_ref_bible_url($ref_str) {
	return bfox_ref_url($ref_str);
}

/**
 * Returns a link to the external Bible reader of choice for a given Bible Ref
 *
 * Should be used whenever we want to link to the Bible page, as opposed to the Bible archive
 *
 * @param array $options
 * @return string
 */
function bfox_ref_bible_link($options) {
	bfox_fix_ref_link_options($options);

	// If there is no href, get it from the bfox_ref_bible_url() function
	if (!isset($options['attrs']['href'])) $options['attrs']['href'] = bfox_ref_bible_url($options['ref_str']);

	return bfox_ref_link_from_options($options);
}

/**
 * Replaces bible references with bible links in a given html string
 * @param string $content
 * @return string
 */
function bfox_ref_replace_html($content) {
	return BfoxRefParser::simple_html($content, null, 'bfox_ref_replace_html_cb');
}
	function bfox_ref_replace_html_cb($text, $ref) {
		return bfox_ref_bible_link(array(
			'ref' => $ref,
			'text' => $text
		));
	}

/**
 * Returns a BfoxRef for the given tag string
 *
 * @param string $tag
 * @return BfoxRef
 */
function bfox_ref_from_tag($tag) {
	return BfoxRefParser::simple($tag);
}

/**
 * Returns a BfoxRef for the given content string
 *
 * @param string $content
 * @return BfoxRef
 */
function bfox_ref_from_content($content) {
	$ref = new BfoxRef;
	BfoxRefParser::simple_html($content, $ref);
	return $ref;
}

/**
 * Checks to see if we are requesting tooltip content (ie. by an AJAX call), returns the content, and exits
 */
function bfox_check_for_tooltip() {
	if (isset($_REQUEST['bfox-tooltip-ref'])) {
		bfox_active_ref(new BfoxRef(str_replace('_', ' ', $_REQUEST['bfox-tooltip-ref'])));

		// Make sure that the we have a query on bfox_tool
		// TODO: We can get rid of this if we make sure that the tooltip URL is already loading the right query
		// ie. if the tooltip URL loads the bfox_tool archive query (/bible-tools/?tooltip_ref=Gen+1).
		query_posts(array('post_type' => 'bfox_tool'));

		load_bfox_template('bfox-tooltip');
		exit;
	}
}
add_action('init', 'bfox_check_for_tooltip', 1000);

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
function bfox_bp_load() {
	require_once BFOX_DIR . '/biblefox-bp/biblefox-bp.php';
}
// Call bfox_bp_load() if we have already loaded BP
// Otherwise, if we haven't already loaded BP, then call bfox_bp_load() after bp_core_loaded
if (function_exists('bp_core_install')) bfox_bp_load();
else add_action('bp_core_loaded', 'bfox_bp_load');


// TODO: These need to be conditionally included based on a WP setting
require_once BFOX_DIR . '/reading-plans/reading-plans.php';
require_once BFOX_DIR . '/reading-plans/template-tags.php';
require_once BFOX_DIR . '/bfox_tool/bfox_tool.php';
require_once bfox_template_path('bp-bible-directory-functions');

?>