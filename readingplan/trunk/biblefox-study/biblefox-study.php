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
	define(BFOX_TRANSLATION_SUBPAGE, 'bfox-translations');

	function bfox_study_menu()
	{
		$min_user_level = 8;
		add_menu_page('Study the Bible', 'Study', 0, __FILE__, 'bfox_read');
		add_submenu_page(__FILE__, 'Read the Bible', 'Read', 0, __FILE__, 'bfox_read');
		add_submenu_page(__FILE__, 'Design a Reading Plan', 'Plan', 0, 'plan', 'bfox_plan');
		add_submenu_page(__FILE__, 'Share with Friends', 'Share', 0, 'share', 'bfox_share');
		add_submenu_page(__FILE__, 'Manage Translations', 'Translations', 0, BFOX_TRANSLATION_SUBPAGE, 'bfox_translations');
	}

	function bfox_read()
	{
		echo "<h2>Read the Bible</h2>";
	};
	
	function bfox_plan()
	{
		echo "<h2>Design a Reading Plan</h2>";
	};
	
	function bfox_share()
	{
		echo "<h2>Share with Friends</h2>";
		bfox_activate();
	};
	
	function bfox_translations()
	{
		require_once("bfox-translations.php");
		bfox_translations_page();
	};
	
	function bfox_study_init()
	{
		add_action('admin_menu', 'bfox_study_menu');
	}
	
	function bfox_activate()
	{
		require_once("bfox-setup.php");
		bfox_setup();
	}

	add_action('init', 'bfox_study_init');
	register_activation_hook(__FILE__, 'bfox_activate');
	
?>
