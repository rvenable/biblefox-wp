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
//	define(BFOX_ADMIN_FILE, __FILE__);
	define(BFOX_PLAN_SUBPAGE, 'bfox-plan');
	define(BFOX_MANAGE_PLAN_SUBPAGE, 'bfox-manage-plan');
	define(BFOX_ADMIN_TOOLS_SUBPAGE, 'bfox-admin-tools');
	define(BFOX_PROGRESS_SUBPAGE, 'bfox-progress');
	define(BFOX_READ_SUBPAGE, 'bfox-read');
	define(BFOX_BIBLE_SUBPAGE, 'bfox-bible');
	define(BFOX_DOMAIN, 'biblefox-study');

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
		$min_user_level = 8;
		add_submenu_page('profile.php', 'My Status', 'My Status', 0, BFOX_PROGRESS_SUBPAGE, 'bfox_progress');

		// Add the reading plan page to the Post menu along with the corresponding load action
		add_submenu_page('post-new.php', 'Reading Plans', 'Reading Plans', BFOX_USER_LEVEL_MANAGE_PLANS, BFOX_MANAGE_PLAN_SUBPAGE, 'bfox_manage_reading_plans');
		add_action('load-' . get_plugin_page_hookname(BFOX_MANAGE_PLAN_SUBPAGE, 'post-new.php'), 'bfox_manage_reading_plans_load');

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

			add_submenu_page('wpmu-admin.php', 'Admin Tools', 'Admin Tools', 10, BFOX_ADMIN_TOOLS_SUBPAGE, 'bfox_admin_tools');
		}

		add_meta_box('bible-tag-div', __('Scripture Tags'), 'bfox_post_scripture_tag_meta_box', 'post', 'normal', 'core');
		add_meta_box('bible-quick-view-div', __('Scripture Quick View'), 'bfox_post_scripture_quick_view_meta_box', 'post', 'normal', 'core');
		add_action('save_post', 'bfox_save_post');
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

	function bfox_save_post($post_id = 0)
	{
		$refStr = $_POST['bible_ref'];

		$refs = RefManager::get_from_str($refStr);
		if ((0 != $post_id) && (0 < $refs->get_count()))
		{
			require_once("bfox-write.php");
			bfox_set_post_bible_refs($post_id, $refs);
		}
	}

	function bfox_post_scripture_tag_meta_box($post)
	{
		// TODO3: Get rid of this include
		require_once("bfox-write.php");
		bfox_form_edit_scripture_tags();
	}

	function bfox_post_scripture_quick_view_meta_box($post)
	{
		// TODO3: Get rid of this include
		require_once("bfox-write.php");
		bfox_form_scripture_quick_view();
	}

	function bfox_progress()
	{
//		bfox_progress_page();
		if (current_user_can(BFOX_USER_LEVEL_MANAGE_USERS)) bfox_join_request_menu();
		include('my-blogs.php');
	}

	function bfox_plan()
	{
		bfox_create_plan();
	}

	function bfox_share()
	{
		echo "<h2>Share with Friends</h2>";
	}

	function bfox_admin_tools()
	{
		require_once("admin-tools.php");
		bfox_admin_tools_menu();
	}

	function bfox_study_init()
	{
		require_once("bfox-include.php");

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

	// Kill the dashboard by redirecting index.php to admin.php
	function bfox_redirect_dashboard()
	{
		// Check the global $pagenow variable
		// This var is set in wp-include/vars.php, which is called in wp-settings.php, in between creation of mu-plugins and regular plugins

		// If this is an admin screen and $pagenow says that we are at index.php, change it to use admin.php
		// And set the page to the My Progress page
		global $pagenow;
		if (is_admin() && ('index.php' == $pagenow))
		{
			$pagenow = 'admin.php';
			if (!isset($_GET['page'])) $_GET['page'] = BFOX_PROGRESS_SUBPAGE;
		}
	}
	// Redirect the dashboard after loading all plugins (all plugins are finished loading shortly after the necessary $pagenow var is created)
	if (!defined('BFOX_TESTBED')) add_action('plugins_loaded', 'bfox_redirect_dashboard');

?>
