<?php

	define('BFOX_BASE_TABLE_PREFIX', $GLOBALS['wpdb']->base_prefix . 'bfox_');

	// Defines for tables which only have one table name
	define('BFOX_BOOKS_TABLE', BFOX_BASE_TABLE_PREFIX . 'books');
	define('BFOX_SYNONYMS_TABLE', BFOX_BASE_TABLE_PREFIX . 'synonyms');
	define('BFOX_TRANSLATIONS_TABLE', BFOX_BASE_TABLE_PREFIX . 'translations');

	function bfox_get_default_version()
	{
		return $wpdb->get_var("SELECT id FROM " . BFOX_TRANSLATIONS_TABLE . " WHERE is_default = TRUE");
	}
	
	function bfox_get_verses_table_name($id)
	{
		if (!isset($id))
			$id = bfox_get_default_version();
		
		return BFOX_BASE_TABLE_PREFIX . "trans{$id}_verses";
	}
	
	require_once("bibletext.php");

	?>
