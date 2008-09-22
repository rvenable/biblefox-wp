<?php

	/*
	 This include file is for functions related to tables which correspond to a specific blog.
	 (As opposed to tables which are for an entire WPMU installation)
	 */
	
	define('BFOX_BLOG_TABLE_PREFIX', $GLOBALS['wpdb']->prefix . 'bfox_');
	define('BFOX_TABLE_BIBLE_REF', BFOX_BLOG_TABLE_PREFIX . 'bible_ref');

	function bfox_get_blog_table_prefix($local_blog_id = 0)
	{
		global $wpdb, $blog_id;
		if (0 == $local_blog_id) $local_blog_id = $blog_id;
		return $wpdb->base_prefix . $local_blog_id . '_bfox_';
	}

	function bfox_get_verses_table_name($id = -1)
	{
		if (!isset($id) || (0 > $id))
			$id = bfox_get_default_version();
		
		return BFOX_BASE_TABLE_PREFIX . "trans{$id}_verses";
	}
		
	?>
