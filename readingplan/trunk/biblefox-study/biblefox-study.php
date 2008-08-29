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
	define(BFOX_READ_SUBPAGE, __FILE__);
	define(BFOX_PLAN_SUBPAGE, 'bfox-plan');
	define(BFOX_TRANSLATION_SUBPAGE, 'bfox-translations');
	define(BFOX_SETUP_SUBPAGE, 'bfox-setup');
	define(BFOX_DOMAIN, 'biblefox-study');

	function bfox_study_menu()
	{
		$min_user_level = 8;
		add_menu_page('Study the Bible', 'Study', 0, __FILE__, 'bfox_read');
		add_submenu_page(__FILE__, 'Read the Bible', 'Read', 0, BFOX_READ_SUBPAGE, 'bfox_read');
		add_submenu_page(__FILE__, 'Design a Reading Plan', 'Plan', 0, BFOX_PLAN_SUBPAGE, 'bfox_plan');
		add_submenu_page(__FILE__, 'Share with Friends', 'Share', 0, 'share', 'bfox_share');
		add_submenu_page(__FILE__, 'Manage Translations', 'Translations', 0, BFOX_TRANSLATION_SUBPAGE, 'bfox_translations');
		add_submenu_page(__FILE__, 'Biblefox Setup', 'Setup', 0, BFOX_SETUP_SUBPAGE, 'bfox_setup');
	}

	function bfox_read()
	{
		require("bfox-read.php");
	};
	
	function bfox_plan()
	{
		require("bfox-plan.php");
		echo "<h2>Design a Reading Plan</h2>";
		bfox_create_plan();
	};
	
	function bfox_share()
	{
		echo "<h2>Share with Friends</h2>";
	};

	function bfox_translations()
	{
		require_once("bfox-translations.php");
		bfox_translations_page();
	};
	
	function bfox_setup()
	{
		echo "<h2>Biblefox Setup</h2>";
		bfox_activate();
	};
	
	function bfox_study_init()
	{
		add_action('admin_menu', 'bfox_study_menu');
	}
	
	function bfox_activate()
	{
		require_once("bfox-setup.php");
		bfox_initial_setup();
	}

	add_action('init', 'bfox_study_init');
	register_activation_hook(__FILE__, 'bfox_activate');
	
?>
