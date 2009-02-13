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

	define(BFOX_ADMIN_FILE, '../wp-content/mu-plugins/biblefox-study/biblefox-study.php');
//	define(BFOX_ADMIN_FILE, __FILE__);
	define(BFOX_PLAN_SUBPAGE, 'bfox-plan');
	define(BFOX_MANAGE_PLAN_SUBPAGE, 'bfox-manage-plan');
	define(BFOX_TRANSLATION_SUBPAGE, 'bfox-translations');
	define(BFOX_ADMIN_TOOLS_SUBPAGE, 'bfox-admin-tools');
	define(BFOX_PROGRESS_SUBPAGE, 'bfox-progress');
	define(BFOX_READ_SUBPAGE, 'bfox-read');
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

	function bfox_study_menu()
	{
		$min_user_level = 8;
		add_submenu_page('profile.php', 'My Status', 'My Status', 0, BFOX_PROGRESS_SUBPAGE, 'bfox_progress');

		// Add the reading plan page to the Post menu
		// Also add the corresponding load action
		add_submenu_page('post-new.php', 'Reading Plans', 'Reading Plans', BFOX_USER_LEVEL_MANAGE_PLANS, BFOX_MANAGE_PLAN_SUBPAGE, 'bfox_manage_reading_plans');
		add_action('load-' . get_plugin_page_hookname(BFOX_MANAGE_PLAN_SUBPAGE, 'post-new.php'), 'bfox_manage_reading_plans_load');

		//add_submenu_page(BFOX_ADMIN_FILE, 'Share with Friends', 'Share', 0, 'share', 'bfox_share');

		if (is_site_admin())
		{
			add_menu_page('Study the Bible', 'The Bible', 0, BFOX_READ_SUBPAGE, 'bfox_read');
			add_submenu_page(BFOX_READ_SUBPAGE, 'Advanced Reading Pane', 'Read', 0, BFOX_READ_SUBPAGE, 'bfox_read');
			add_submenu_page(BFOX_READ_SUBPAGE, 'Passage History', 'History', 0, BFOX_READ_SUBPAGE, 'bfox_read');
		}

		// These menu pages are only for the site admin
		if (is_site_admin())
		{
			// Add the translation page to the WPMU admin menu
			// Also add the corresponding load action
			add_submenu_page('wpmu-admin.php', 'Manage Translations', 'Translations', 10, BFOX_TRANSLATION_SUBPAGE, 'bfox_manage_translations');
			add_action('load-' . get_plugin_page_hookname(BFOX_TRANSLATION_SUBPAGE, 'wpmu-admin.php'), 'bfox_manage_translations_load');

			add_submenu_page('wpmu-admin.php', 'Admin Tools', 'Admin Tools', 10, BFOX_ADMIN_TOOLS_SUBPAGE, 'bfox_admin_tools');
		}

		add_meta_box('bible-tag-div', __('Scripture Tags'), 'bfox_post_scripture_tag_meta_box', 'post', 'normal', 'core');
		add_meta_box('bible-quick-view-div', __('Scripture Quick View'), 'bfox_post_scripture_quick_view_meta_box', 'post', 'normal', 'core');
		add_action('save_post', 'bfox_save_post');
	}

	function bfox_save_post($post_id = 0)
	{
		$refStr = $_POST['bible_ref'];

		$refs = new BibleRefs($refStr);
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
	add_action('plugins_loaded', 'bfox_redirect_dashboard');

?>
