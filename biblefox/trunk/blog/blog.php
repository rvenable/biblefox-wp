<?php

define(BFOX_MANAGE_PLAN_SUBPAGE, 'bfox-manage-plan');
define(BFOX_PROGRESS_SUBPAGE, 'bfox-progress');

// Query Variables
define('BFOX_QUERY_VAR_BIBLE_REF', 'bible_ref');
define('BFOX_QUERY_VAR_SPECIAL', 'bfox_special');
define('BFOX_QUERY_VAR_ACTION', 'bfox_action');
define('BFOX_QUERY_VAR_PLAN_ID', 'bfox_plan_id');
define('BFOX_QUERY_VAR_READING_ID', 'bfox_reading_id');
define('BFOX_QUERY_VAR_JOIN_BIBLE_REFS', 'join_bible_refs');

// User Levels
define('BFOX_USER_LEVEL_MANAGE_PLANS', 7);
define('BFOX_USER_LEVEL_MANAGE_USERS', 'edit_users');

require_once('bfox-blog-specific.php');
require_once('plan.php');
require_once('history.php');
require_once('bfox-query.php');
require_once('special.php');
require_once('bfox-widgets.php');

class BfoxBlog
{
	public static function init()
	{
		add_action('admin_menu', array('BfoxBlog', 'add_menu'));

		bfox_query_init();
		bfox_widgets_init();
	}

	public static function add_menu()
	{
		add_submenu_page('profile.php', 'My Status', 'My Status', 0, BFOX_PROGRESS_SUBPAGE, 'bfox_progress');

		// Add the reading plan page to the Post menu along with the corresponding load action
		add_submenu_page('post-new.php', 'Reading Plans', 'Reading Plans', BFOX_USER_LEVEL_MANAGE_PLANS, BFOX_MANAGE_PLAN_SUBPAGE, 'bfox_manage_reading_plans');
		add_action('load-' . get_plugin_page_hookname(BFOX_MANAGE_PLAN_SUBPAGE, 'post-new.php'), 'bfox_manage_reading_plans_load');

		add_meta_box('bible-tag-div', __('Scripture Tags'), 'bfox_post_scripture_tag_meta_box', 'post', 'normal', 'core');
		add_meta_box('bible-quick-view-div', __('Scripture Quick View'), 'bfox_post_scripture_quick_view_meta_box', 'post', 'normal', 'core');
		add_action('save_post', 'bfox_save_post');
	}
}

add_action('init', array('BfoxBlog', 'init'));

	function bfox_save_post($post_id = 0)
	{
		$refStr = $_POST['bible_ref'];

		$refs = RefManager::get_from_str($refStr);
		if ((0 != $post_id) && $refs->is_valid())
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
