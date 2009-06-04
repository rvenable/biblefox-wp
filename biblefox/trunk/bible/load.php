<?php

define(BFOX_BIBLE_DIR, dirname(__FILE__));
define(BFOX_BIBLE_SUBPAGE, 'bfox-bible');

function bfox_bible_page_load()
{
	// Register all the bible scripts and styles
	BfoxUtility::register_script('jquery_cookie', 'bible/jquery.cookie.js', array('jquery'));
	BfoxUtility::register_script('bfox_bible', 'bible/bible.js', array('jquery', 'jquery_cookie'));
	BfoxUtility::register_style('bfox_bible', 'bible/bible.css', array('bfox_scripture'));
	BfoxUtility::register_style('bfox_search', 'bible/search.css', array('bfox_bible'));

	Biblefox::set_default_ref_url(Biblefox::ref_url_bible);

	require BFOX_BIBLE_DIR . '/page_load.php';

	add_filter('wp_title', 'bfox_bible_wp_title', 10, 3);
}

function bfox_bible_page()
{
	global $bfox_bible_page;
	$bfox_bible_page->page();
}

function bfox_bible_wp_title($title, $sep, $seplocation)
{
	global $bfox_bible_page;

	$title = $bfox_bible_page->get_title();
	if ('right' == $seplocation) return "$title $sep";
	else return "$sep $title";
}

?>