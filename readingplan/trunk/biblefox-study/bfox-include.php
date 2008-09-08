<?php

	define('BFOX_BASE_TABLE_PREFIX', $GLOBALS['wpdb']->base_prefix . 'bfox_');

	// Defines for tables which only have one table name
	define('BFOX_BOOKS_TABLE', BFOX_BASE_TABLE_PREFIX . 'books');
	define('BFOX_SYNONYMS_TABLE', BFOX_BASE_TABLE_PREFIX . 'synonyms');
	define('BFOX_TRANSLATIONS_TABLE', BFOX_BASE_TABLE_PREFIX . 'translations');

	require_once("bfox-settings.php");
	require_once("bfox-blog-specific.php");
	require_once("bibletext.php");
	require_once("bfox-query.php");

	?>
