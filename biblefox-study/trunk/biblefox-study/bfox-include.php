<?php

	define('BFOX_BASE_TABLE_PREFIX', $GLOBALS['wpdb']->base_prefix . 'bfox_');

	// Defines for tables which only have one table name
	define('BFOX_BOOKS_TABLE', BFOX_BASE_TABLE_PREFIX . 'books');
	define('BFOX_SYNONYMS_TABLE', BFOX_BASE_TABLE_PREFIX . 'synonyms');
	define('BFOX_TRANSLATIONS_TABLE', BFOX_BASE_TABLE_PREFIX . 'translations');

	require_once("bfox-settings.php");
	require_once("bfox-blog-specific.php");
	require_once("plan.php");

	// BibleRefs class
	require_once("ref.php");

	// Include files which need BibleRefs
	require_once("bibletext.php");
	require_once("history.php");
	require_once("bfox-query.php");
	require_once('special.php');
	require_once('bfox-widgets.php');

	// Returns the bible study blogs for a given user
	// Should be used in place of get_blogs_of_user() because the main biblefox.com blog should not count
	function bfox_get_bible_study_blogs($user_id)
	{
		// Get the blogs for the user
		$blogs = get_blogs_of_user($user_id);

		// The main biblefox blog does not count as a bible study blog
		unset($blogs[1]);

		return $blogs;
	}

	?>
