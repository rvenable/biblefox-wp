<?php
	/*
	 Plugin Name: biblefox-study
	 Plugin URI: http://tools.biblefox.com/study/
	 Description: A wordpress plugin for studying the bible. Plan. Read. Share.
	 Version: 0.1
	 Author: Biblefox
	 Author URI: http://biblefox.com
	 */

	define(BFOX_FILE, __FILE__);
	define(BFOX_ADMIN_FILE, '../wp-content/mu-plugins/biblefox-study/biblefox-study.php');
//	define(BFOX_ADMIN_FILE, __FILE__);
	define(BFOX_PLAN_SUBPAGE, 'bfox-plan');
	define(BFOX_TRANSLATION_SUBPAGE, 'bfox-translations');
	define(BFOX_SETUP_SUBPAGE, 'bfox-setup');
	define(BFOX_PROGRESS_SUBPAGE, 'bfox-progress');
	define(BFOX_DOMAIN, 'biblefox-study');

	// Uncomment for testing DB queries
	define('DIEONDBERROR', 'die!');
	$wpdb->show_errors(true);

	function bfox_study_menu()
	{
		$min_user_level = 8;
		add_menu_page('Study the Bible', 'Study', 0, BFOX_ADMIN_FILE, 'bfox_progress');
		add_submenu_page(BFOX_ADMIN_FILE, 'Track my progress', 'My Progress', 0, BFOX_ADMIN_FILE, 'bfox_progress');
		add_submenu_page(BFOX_ADMIN_FILE, 'Reading Plans', 'Reading Plans', 0, BFOX_PLAN_SUBPAGE, 'bfox_plan');

		//add_submenu_page(BFOX_ADMIN_FILE, 'Share with Friends', 'Share', 0, 'share', 'bfox_share');

		// These menu pages are only for the site admin and only on the main blog site
		if (is_site_admin() && is_main_blog())
		{
			add_submenu_page(BFOX_ADMIN_FILE, 'Manage Translations', 'Translations', 10, BFOX_TRANSLATION_SUBPAGE, 'bfox_translations');
			add_submenu_page(BFOX_ADMIN_FILE, 'Biblefox Setup', 'Setup', 10, BFOX_SETUP_SUBPAGE, 'bfox_setup');
		}

		add_action('edit_form_advanced', 'bfox_edit_form_advanced');
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

	function bfox_edit_form_advanced()
	{
		require_once("bfox-write.php");
		bfox_form_edit_bible_refs();
	}

	function bfox_progress()
	{
		bfox_progress_page();
	}
	
	function bfox_plan()
	{
		bfox_create_plan();
	}
	
	function bfox_share()
	{
		echo "<h2>Share with Friends</h2>";
	}

	function bfox_translations()
	{
		require_once("bfox-translations.php");
		bfox_translations_page();
	}
	
	function bfox_setup()
	{
		echo "<h2>Biblefox Setup</h2>";
		bfox_activate();
	}
	
	function bfox_study_init()
	{
		require_once("bfox-include.php");
		
		add_action('admin_menu', 'bfox_study_menu');

		bfox_query_init();
		bfox_widgets_init();
	}
	
	function bfox_activate()
	{
		require_once("bfox-setup.php");
		bfox_initial_setup();
	}

	add_action('init', 'bfox_study_init');
	register_activation_hook(__FILE__, 'bfox_activate');

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
			if (!isset($_GET['page'])) $_GET['page'] = 'biblefox-study/biblefox-study.php';
		}
	}
	// Redirect the dashboard after loading all plugins (all plugins are finished loading shortly after the necessary $pagenow var is created)
	add_action('plugins_loaded', 'bfox_redirect_dashboard');

?>
