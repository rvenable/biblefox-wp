<?php

define(BFOX_BIBLE_DIR, dirname(__FILE__));

// TODO3: get rid of old bfox-read page
define(BFOX_READ_SUBPAGE, 'bfox-read');
function bfox_read()
{
	require_once('read.php');
	bfox_read_menu();
}

define(BFOX_BIBLE_SUBPAGE, 'bfox-bible');

function bfox_bible_page_load()
{
	require BFOX_BIBLE_DIR . '/page_load.php';
}

function bfox_bible_page()
{
	global $bfox_viewer;
	$bfox_viewer->page();
}

function bfox_bible_menu()
{
	add_menu_page('Study the Bible', 'The Bible', 0, BFOX_BIBLE_SUBPAGE, 'bfox_bible_page');
	add_submenu_page(BFOX_BIBLE_SUBPAGE, 'Bible', 'Bible', 0, BFOX_BIBLE_SUBPAGE, 'bfox_bible_page');
	add_action('load-' . get_plugin_page_hookname(BFOX_BIBLE_SUBPAGE, BFOX_BIBLE_SUBPAGE), 'bfox_bible_page_load');
	add_submenu_page(BFOX_BIBLE_SUBPAGE, 'Advanced Reading Pane', 'Read', 0, BFOX_READ_SUBPAGE, 'bfox_read');
}

function bfox_bible_init()
{
	if (is_site_admin()) add_action('admin_menu', 'bfox_bible_menu');
}
add_action('init', 'bfox_bible_init');

?>