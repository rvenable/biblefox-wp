<?php

function bfox_upgrade_post_ref_table_2($table) {
	global $wpdb;
	// For each ref_type 0, change to taxonomy 'post_tag'
	$wpdb->query("UPDATE $table SET taxonomy = 'post_tag' WHERE ref_type = 0");
	// For each ref_type 1, change to taxonomy 'post_content'
	$wpdb->query("UPDATE $table SET taxonomy = 'post_content' WHERE ref_type = 1");
	// Remove the ref_type column
	$wpdb->query("ALTER TABLE $table DROP COLUMN ref_type");
}

?>