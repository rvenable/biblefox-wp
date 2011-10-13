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

function bfox_tool_iframe_select($query = false) {
	if (!$query) $query = bfox_tool_query();

	$selected_post_id = selected_bfox_tool_post_id();
	while ($query->have_posts()) {
		$query->the_post();

		$post_id = get_the_ID();
		$url = bfox_tool_source_url();
		$title = get_the_title();

		if ($post_id == $selected_post_id) $selected = " selected='selected'";
		else $selected = '';

		$content .= "<option name='$post_id' value='$url'$selected>$title</option>";
	}

	return '<select class="bfox-iframe-select">' . $content . '</select>';
}

?>