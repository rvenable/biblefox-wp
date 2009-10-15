<?php
/*************************************************************************

	Plugin Name: Biblefox-Site
	Plugin URI: http://tools.biblefox.com/
	Description: Allows your blog to become a bible commentary, and adds the entire bible text to your blog, so you can read, search, and study the bible all from your blog.
	Version: 0.4.9
	Author: Biblefox
	Author URI: http://biblefox.com
Site Wide Only: true

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

define(BFOX_SITE_VERSION, '0.4.9');

define(BFOX_SITE_FILE, __FILE__);
define(BFOX_SITE_DIR, dirname(__FILE__));
define(BFOX_SITE_URL, WP_PLUGIN_URL . '/biblefox-site');

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

include_once BFOX_SITE_DIR . '/admin/admin-tools.php';
include_once BFOX_SITE_DIR . '/site/site.php';

?>