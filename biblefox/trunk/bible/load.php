<?php

define(BFOX_BIBLE_DIR, dirname(__FILE__));
define(BFOX_BIBLE_SUBPAGE, 'bfox-bible');

function bfox_bible_page_load()
{
	BfoxQuery::set_url(get_option('home') . '/wp-admin?page=' . BFOX_BIBLE_SUBPAGE);
	require BFOX_BIBLE_DIR . '/page_load.php';
}

function bfox_bible_page()
{
	global $bfox_bible_page;
	$bfox_bible_page->page();
}

function bfox_bible_menu()
{
	add_menu_page('Study the Bible', 'The Bible', 0, BFOX_BIBLE_SUBPAGE, 'bfox_bible_page');
	add_submenu_page(BFOX_BIBLE_SUBPAGE, 'Bible', 'Bible', 0, BFOX_BIBLE_SUBPAGE, 'bfox_bible_page');
	add_action('load-' . get_plugin_page_hookname(BFOX_BIBLE_SUBPAGE, BFOX_BIBLE_SUBPAGE), 'bfox_bible_page_load');
}

function bfox_bible_init()
{
	if (is_site_admin()) add_action('admin_menu', 'bfox_bible_menu');
}
add_action('init', 'bfox_bible_init');

?>