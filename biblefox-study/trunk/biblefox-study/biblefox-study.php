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
	define(BFOX_READ_SUBPAGE, 'bfox-read');
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
		add_submenu_page(BFOX_ADMIN_FILE, 'Read the Bible', 'Read', 0, BFOX_READ_SUBPAGE, 'bfox_read');
		add_submenu_page(BFOX_ADMIN_FILE, 'Reading Plans', 'Reading Plans', 0, BFOX_PLAN_SUBPAGE, 'bfox_plan');

		//add_submenu_page(BFOX_ADMIN_FILE, 'Share with Friends', 'Share', 0, 'share', 'bfox_share');

		// These menu pages are only for the site admin
		if (is_site_admin())
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

		$refs = array();
		$reflist = bfox_parse_reflist($refStr);
		foreach($reflist as $ref) $refs[] = bfox_parse_ref($ref);
		if ((0 != $post_id) && (0 < count($refs)))
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
		require_once("bfox-plan.php");
		bfox_progress_page();
	}
	
	function bfox_read()
	{
		require_once("bfox-read.php");
		bfox_read_menu();
	}

	function bfox_plan()
	{
		require("bfox-plan.php");
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
	}
	
	function bfox_activate()
	{
		require_once("bfox-setup.php");
		bfox_initial_setup();
	}

	add_action('init', 'bfox_study_init');
	register_activation_hook(__FILE__, 'bfox_activate');
	
?>
