<?php
	/*
	 Plugin Name: biblefox-study
	 Plugin URI: http://tools.biblefox.com/study/
	 Description: Allows your blog to become a bible commentary, and adds the entire bible text to your blog, so you can read, search, and study the bible all from your blog.
	 Version: 0.1
	 Author: Biblefox
	 Author URI: http://biblefox.com
	 */

	define(BFOX_FILE, __FILE__);
	define(BFOX_DIR, dirname(__FILE__));
	define(BFOX_TRANSLATIONS_DIR, BFOX_DIR . '/translations');
	define(BFOX_TEXTS_DIR, BFOX_DIR . '/texts');

	define(BFOX_ADMIN_FILE, '../wp-content/mu-plugins/biblefox-study/biblefox-study.php');
	define(BFOX_READ_SUBPAGE, 'bfox-read');
	define(BFOX_BIBLE_SUBPAGE, 'bfox-bible');
	define(BFOX_DOMAIN, 'biblefox-study');

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
		// These pages are temporarily only for site admin as they are being tested
		if (is_site_admin())
		{
			add_menu_page('Study the Bible', 'The Bible', 0, BFOX_BIBLE_SUBPAGE, 'bfox_bible_page');
			add_submenu_page(BFOX_BIBLE_SUBPAGE, 'Bible', 'Bible', 0, BFOX_BIBLE_SUBPAGE, 'bfox_bible_page');
			add_action('load-' . get_plugin_page_hookname(BFOX_BIBLE_SUBPAGE, BFOX_BIBLE_SUBPAGE), 'bfox_bible_page_load');
			add_submenu_page(BFOX_BIBLE_SUBPAGE, 'Advanced Reading Pane', 'Read', 0, BFOX_READ_SUBPAGE, 'bfox_read');
		}

		// These menu pages are only for the site admin
		if (is_site_admin())
		{
			// Add the translation page to the WPMU admin menu along with the corresponding load action
			add_submenu_page('wpmu-admin.php', 'Manage Translations', 'Translations', Translations::min_user_level, Translations::page, array('Translations', 'manage_page'));
			add_action('load-' . get_plugin_page_hookname(Translations::page, 'wpmu-admin.php'), array('Translations', 'manage_page_load'));
		}
	}

	function bfox_bible_page_load()
	{
		global $bfox_bible_viewer;
		$bfox_bible_viewer = new Bible();
		$bfox_bible_viewer->page_load($_GET);
	}

	function bfox_bible_page()
	{
		global $bfox_bible_viewer;
		$bfox_bible_viewer->page();
	}

	function bfox_study_init()
	{

		add_action('admin_menu', 'bfox_study_menu');

		bfox_query_init();
		bfox_widgets_init();
	}

	function bfox_read()
	{
		require_once('read.php');
		bfox_read_menu();
	}

	add_action('init', 'bfox_study_init');

?>
