<?php
	/*
	 Plugin Name: biblefox-study
	 Plugin URI: http://tools.biblefox.com/study/
	 Description: A wordpress plugin for studying the bible. Plan. Read. Share.
	 Version: 0.1
	 Author: Biblefox
	 Author URI: http://biblefox.com
	 */

	function bfox_study_menu()
	{
		$min_user_level = 8;
		add_menu_page('Study the Bible', 'Study', 0, __FILE__, 'bfox_read');
		add_submenu_page(__FILE__, 'Read the Bible', 'Read', 0, __FILE__, 'bfox_read');
//		add_submenu_page(__FILE__, 'Make a Reading Plan', 'Make a Reading Plan', 0, __FILE__, 'create_plan');
	}
	
	function bfox_read()
	{
		echo "<h2>Read</h2>";
	};
	
	function bfox_study_init()
	{
		add_action('admin_menu', 'bfox_study_menu');
	}
	add_action('init', 'bfox_study_init');
	
?>
