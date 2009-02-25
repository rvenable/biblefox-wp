<?php

/*
 This include file is for functions related to tables which correspond to a specific blog.
 (As opposed to tables which are for an entire WPMU installation)
 */

global $wpdb;
$wpdb->blog_tables[] = 'bfox_bible_ref';
$wpdb->bfox_bible_ref = $wpdb->prefix . 'bfox_bible_ref';

// TODO3: Remove this function (use $wpdb instead)
function bfox_get_blog_table_prefix($local_blog_id = 0)
{
	global $wpdb, $blog_id;
	if (0 == $local_blog_id) $local_blog_id = $blog_id;
	return $wpdb->base_prefix . $local_blog_id . '_bfox_';
}

?>
