<?php
	/*
	 Plugin Name: biblefox
	 Plugin URI: http://tools.biblefox.com/
	 Description: Allows your blog to become a bible commentary, and adds the entire bible text to your blog, so you can read, search, and study the bible all from your blog.
	 Version: 0.1
	 Author: Biblefox
	 Author URI: http://biblefox.com
	 */

	define(BFOX_FILE, __FILE__);
	define(BFOX_DIR, dirname(__FILE__));
	define(BFOX_DATA_DIR, BFOX_DIR . '/data');

	define(BFOX_ADMIN_FILE, '../wp-content/mu-plugins/biblefox/biblefox.php');
	define(BFOX_DOMAIN, 'biblefox');

	define(BFOX_BASE_TABLE_PREFIX, $GLOBALS['wpdb']->base_prefix . 'bfox_');

	require_once("bfox-include.php");

	/**
	 * Hacky function for showing DB errors. Hacked into the wpdb query filter to make sure that the errors always show.
	 *
	 * @param unknown_type $query
	 * @return unknown
	 */
	function bfox_show_errors($query)
	{
		global $wpdb;
		define('DIEONDBERROR', 'die!');
		$wpdb->show_errors(true);
		return $query;
	}
	if (defined('BFOX_TESTBED')) add_filter('query', 'bfox_show_errors');

	// TODO3: come up with something else
	function pre($expr) { echo '<pre>'; print_r($expr); echo '</pre>'; }

	function bfox_study_menu()
	{
		// These menu pages are only for the site admin
		if (is_site_admin())
		{
			// Add the translation page to the WPMU admin menu along with the corresponding load action
			add_submenu_page('wpmu-admin.php', 'Manage Translations', 'Translations', Translations::min_user_level, Translations::page, array('Translations', 'manage_page'));
			add_action('load-' . get_plugin_page_hookname(Translations::page, 'wpmu-admin.php'), array('Translations', 'manage_page_load'));
		}
	}

	function bfox_study_init()
	{

		add_action('admin_menu', 'bfox_study_menu');

		bfox_query_init();
		bfox_widgets_init();
	}

	add_action('init', 'bfox_study_init');

?>
