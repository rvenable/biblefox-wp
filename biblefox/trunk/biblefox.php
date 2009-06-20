<?php
/*************************************************************************

	Plugin Name: Biblefox
	Plugin URI: http://tools.biblefox.com/
	Description: Allows your blog to become a bible commentary, and adds the entire bible text to your blog, so you can read, search, and study the bible all from your blog.
	Version: 0.4.0
	Author: Biblefox
	Author URI: http://biblefox.com

*************************************************************************/

// TODO2: Add the license file, and add copyright notice to all source files (see http://www.fsf.org/licensing/licenses/gpl-howto.html )
/*************************************************************************

	Copyright 2009 biblefox.com

	This file is part of Biblefox.

	Biblefox is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Biblefox is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with Biblefox.  If not, see <http://www.gnu.org/licenses/>.

*************************************************************************/

define(BFOX_VERSION, '0.4.0');

define(BFOX_FILE, __FILE__);
define(BFOX_DIR, dirname(__FILE__));
define(BFOX_URL, WPMU_PLUGIN_URL . '/biblefox');

define(BFOX_DATA_DIR, BFOX_DIR . '/data');
define(BFOX_REFS_DIR, BFOX_DIR . '/biblerefs');
define(BFOX_PLANS_DIR, BFOX_DIR . '/plans');

define(BFOX_ADMIN_FILE, '../wp-content/mu-plugins/biblefox/biblefox.php');
define(BFOX_DOMAIN, 'biblefox');

define(BFOX_BASE_TABLE_PREFIX, $GLOBALS['wpdb']->base_prefix . 'bfox_');

require_once BFOX_REFS_DIR . '/refs.php';
require_once BFOX_DIR . '/utility.php';
require_once BFOX_DIR . '/query.php';

include_once BFOX_DIR . '/translations/translations.php';
include_once BFOX_DIR . '/admin/admin-tools.php';
include_once BFOX_DIR . '/blog/blog.php';
include_once BFOX_DIR . '/site/site.php';

/**
 * Hacky function for showing DB errors. Hacked into the wpdb query filter to make sure that the errors always show.
 *
 * @param unknown_type $query
 * @return unknown
 */
function bfox_show_errors($query) {
	global $wpdb;
	define('DIEONDBERROR', 'die!');
	$wpdb->show_errors(true);
	return $query;
}
if (defined('BFOX_TESTBED')) add_filter('query', 'bfox_show_errors');

// TODO3: come up with something else
function pre($expr) { echo '<pre>'; print_r($expr); echo '</pre>'; }
function deb($expr) { global $bfox_debug_text; $bfox_debug_text .= print_r($expr, TRUE) . "\n"; }
function bfox_pre_deb() { global $bfox_debug_text; if (!empty($bfox_debug_text)) echo "<pre style='clear:both;'>$bfox_debug_text</pre>"; }
add_action('get_footer', 'bfox_pre_deb');
function bfox_posts_requests($request) { deb("REQUEST: $request"); return $request; }
//add_filter('posts_request', 'bfox_posts_requests'); // Uncomment this to show the WP_Query SQL request query

class Biblefox {

	const ref_url_blog = 'blog';
	const ref_url_bible = 'bible';

	const option_version = 'bfox_version';

	private static $default_ref_url = '';

	public static function init() {
		global $current_site;

		$old_ver = get_site_option(self::option_version);
		if (BFOX_VERSION != $old_ver) {
			@include_once BFOX_DIR . '/bible/upgrade.php';
			@include_once BFOX_DIR . '/blog/upgrade.php';
			update_site_option(self::option_version, BFOX_VERSION);
		}

		BfoxQuery::set_url((is_ssl() ? 'https://' : 'http://') . $current_site->domain . $current_site->path, !(TRUE === BFOX_NO_PRETTY_URLS));

		// Register all the global scripts and styles
		BfoxUtility::register_style('bfox_scripture', 'scripture.css');
	}

	public static function set_default_ref_url($ref_url) {
		self::$default_ref_url = $ref_url;
	}

	public static function ref_url($ref_str, $ref_url = '') {
		if (empty($ref_url)) $ref_url = self::$default_ref_url;

		if (self::ref_url_bible == $ref_url) return BfoxQuery::passage_page_url($ref_str);
		else return BfoxBlog::ref_url($ref_str);
	}

	public static function ref_link($ref_str, $text = '', $ref_url = '', $attrs = '') {
		if (empty($text)) $text = $ref_str;

		if (!empty($attrs)) $attrs = ' ' . $attrs;
		return "<a href='" . self::ref_url($ref_str, $ref_url) . "'$attrs>$text</a>";
	}

}

add_action('init', 'Biblefox::init');

?>