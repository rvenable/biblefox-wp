<?php
/*************************************************************************

	Plugin Name: Biblefox for WordPress
	Plugin URI: http://tools.biblefox.com/
	Description: Turns your blog or BuddyPress site into an online Bible study tool. Allows your blog to become a bible commentary, and adds the entire bible text to your blog, so you can read, search, and study the bible all from your blog.
	Version: 0.7
	Author: Biblefox.com
	Author URI: http://biblefox.com

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

define('BFOX_VERSION', '0.7');
define('BFOX_DIR', dirname(__FILE__));
define('BFOX_REFS_DIR', BFOX_DIR . '/biblerefs');
define('BFOX_URL', WP_PLUGIN_URL . '/biblefox');

require_once BFOX_REFS_DIR . '/refs.php';

require_once BFOX_DIR . '/biblefox-blog/biblefox-blog.php';

require_once BFOX_DIR . '/bibletext.php';
require_once BFOX_DIR . '/translations.php';
require_once BFOX_DIR . '/iframe.php';

class Biblefox {
	/**
	 * @var BfoxPostRefsDbTable
	 */
	public $post_refs;

	/**
	 * @var BfoxActivityRefsDbTable
	 */
	public $activity_refs;

	/**
	 * @var BfoxRefs
	 */
	private $refs;

	/**
	 * The current WP_Query being run - used for the blog post query filter functions
	 * @var WP_Query
	 */
	public $blog_query;

	public function __construct() {
		$this->set_refs(new BfoxRefs());
	}

	public function set_refs(BfoxRefs $refs) {
		$this->refs = $refs;
	}

	/**
	 * @return BfoxRefs
	 */
	public function refs() {
		return $this->refs;
	}

	/**
	 * Fixes a bible ref link options array so that it has a ref_str if it doesn't already
	 *
	 * @param $options
	 */
	public static function fix_ref_link_options(&$options) {
		// If there is no ref_str, try to get it from $refs->get_string($name)
		if (empty($options['ref_str']) && isset($options['refs']) && is_a($options['refs'], BfoxRefs) && $options['refs']->is_valid())
			$options['ref_str'] = $options['refs']->get_string($options['name']);
	}

	/**
	 * Creates a link from an array specifying bible ref link options
	 *
	 * Used to create links by Biblefox::ref_bible_link() and Biblefox::ref_blog_link()
	 *
	 * @param array $options
	 * @return string
	 */
	public static function ref_link_from_options($options = array()) {
		extract($options);
		$link = '';

		// Only create a link if we actually have a ref_str
		if (!empty($ref_str)) {
			// If there is no text, use the ref_str
			if (empty($text)) $text = $ref_str;

			// If there is no href, get it from the ref_bible_url function
			if (!isset($attrs['href'])) self::ref_bible_url($ref_str);

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

	/**
	 * Returns a URL to the external Bible reader of choice for a given Bible Ref
	 *
	 * Should be used whenever we want to link to the Bible page, as opposed to the Bible archive
	 *
	 * @param string $ref_str
	 * @return string
	 */
	public static function ref_bible_url($ref_str) {
		return sprintf(apply_filters('bfox_blog_bible_url_template', 'http://biblefox.com/bible/%s'), urlencode($ref_str));
	}

	/**
	 * Returns a link to the external Bible reader of choice for a given Bible Ref
	 *
	 * Should be used whenever we want to link to the Bible page, as opposed to the Bible archive
	 *
	 * @param array $options
	 * @return string
	 */
	public static function ref_bible_link($options) {
		self::fix_ref_link_options($options);

		// If there is no href, get it from the Biblefox::ref_bible_url() function
		if (!isset($options['attrs']['href'])) $options['attrs']['href'] = self::ref_bible_url($options['ref_str']);

		return self::ref_link_from_options($options);
	}

	/**
	 * Returns a BfoxRefs for the given tag string
	 *
	 * @param string $tag
	 * @return BfoxRefs
	 */
	public static function tag_to_refs($tag) {
		return new BfoxRefs($tag);
	}

	public static function content_to_refs($content) {
		$refs = new BfoxRefs;
		BfoxRefParser::simple_html($content, $refs);
		return $refs;
	}
}

global $biblefox;
$biblefox = new Biblefox();

/**
 * Checks to see if we are requesting tooltip content (ie. by an AJAX call), returns the content, and exits
 */
function bfox_check_for_tooltip() {
	if (isset($_REQUEST['bfox-tooltip-ref'])) {
		global $tooltip_refs;
		$tooltip_refs = new BfoxRefs(str_replace('_', ' ', $_REQUEST['bfox-tooltip-ref']));
		require BFOX_DIR . '/tooltip.php';
		exit;
	}
}
add_action('init', 'bfox_check_for_tooltip', 1000);

/**
 * Replaces bible references with bible links in a given html string
 * @param string $content
 * @return string
 */
function bfox_ref_replace_html($content) {
	return BfoxRefParser::simple_html($content);
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

?>