<?php

/*
Template Tags
*/

function bfox_tool_content_for_ref(BfoxRef $ref) {
	global $wpdb;

	$local_db = bfox_tool_meta('local_db');
	$table = $wpdb->escape($local_db['table_name']);

	$index_row = $local_db['ref_index_row'];
	$index_row2 = $local_db['ref_index_row2'];
	if (empty($index_row2)) $ref_where = $ref->sql_where($index_row);
	else $ref_where = $ref->sql_where2($index_row, $index_row2);

	$content_row = $local_db['content_row'];

	$sql = $wpdb->prepare("SELECT * FROM $table WHERE $ref_where");
	$results = $wpdb->get_results($sql);

	$content = '';
	foreach ($results as $result) {
		$content .= $result->$content_row;
	}

	return apply_filters('bfox_tool_content_for_ref', $content, $ref);
}

?>