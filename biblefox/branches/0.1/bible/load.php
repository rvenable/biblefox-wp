<?php

define(BFOX_BIBLE_DIR, dirname(__FILE__));
define(BFOX_BIBLE_SUBPAGE, 'bfox-bible');

function bfox_bible_page_load()
{
	require BFOX_BIBLE_DIR . '/page_load.php';

	add_action('wp_head', 'bfox_bible_wp_head');
	add_filter('wp_title', 'bfox_bible_wp_title', 10, 3);
}

function bfox_bible_page()
{
	global $bfox_bible_page;
	$bfox_bible_page->page();
}

function bfox_bible_wp_head()
{
	wp_print_scripts(array('jquery'));

	global $bfox_bible_page;
	$bfox_bible_page->print_scripts(get_option('siteurl'));
}

function bfox_bible_wp_title($title, $sep, $seplocation)
{
	global $bfox_bible_page;

	$title = $bfox_bible_page->get_title();
	if ('right' == $seplocation) return "$title $sep";
	else return "$sep $title";
}

?>