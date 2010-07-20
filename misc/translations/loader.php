<?php
/*************************************************************************
Plugin Name: Biblefox Bible Translations
Plugin URI: http://dev.biblefox.com/biblefox-for-wordpress/
Description: Requires Biblefox for WordPress to be activated. Adds local Bible Translations stored in WordPress' database.
Version: 0.1
Author: Biblefox.com, rvenable
Author URI: http://biblefox.com
License: General Public License version 2
Requires at least: WP 3.0, BuddyPress 1.2
Tested up to: WP 3.0, BuddyPress 1.2.4.1
*************************************************************************/

function bfox_trans_load() {
	if (defined('BFOX_VERSION')) {
		require_once dirname(__FILE__) . '/translations.php';
	}
}
add_action('plugins_loaded', 'bfox_trans_load');

?>