<?php
/*************************************************************************
Plugin Name: Biblefox Bible Reading Plans
Plugin URI: http://dev.biblefox.com/biblefox-for-wordpress/
Description: Requires Biblefox for WordPress to be activated. Adds reading plans.
Version: 0.1
Author: Biblefox.com, rvenable
Author URI: http://biblefox.com
License: General Public License version 2
Requires at least: WP 3.0, BuddyPress 1.2
Tested up to: WP 3.0, BuddyPress 1.2.4.1
*************************************************************************/

define('BFOX_READING_PLANS_DIR', dirname(__FILE__));

function bfox_bp_plans_get_users_with_option_value($option, $value, $user_ids) {
	global $wpdb;
	return (array) $wpdb->get_col("SELECT user_id FROM $wpdb->usermeta WHERE user_id IN (" . implode(',', (array) $wpdb->escape($user_ids)) . ") AND meta_key = '$option' AND meta_value = '$value' LIMIT " . count($user_ids));
}

function bfox_reading_plans_load() {
	if (defined('BFOX_BIBLE_SLUG')) {
		require_once BFOX_READING_PLANS_DIR . '/plan.php';
		require_once BFOX_READING_PLANS_DIR . '/schedule.php';
		require_once BFOX_READING_PLANS_DIR . '/plans-directory.php';
	}
}

add_action('bfox_bp_loaded', 'bfox_reading_plans_load');

?>