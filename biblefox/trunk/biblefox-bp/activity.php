<?php

/*
 * Initialization Functions
 */

function bfox_activity_init() {
	global $bp, $biblefox;
	$biblefox->activity_refs = new BfoxRefsTable($bp->activity->table_name);
}
add_action('plugins_loaded', 'bfox_activity_init', 99);
add_action('admin_menu', 'bfox_activity_init', 99);

function bfox_activity_install() {
	global $biblefox;
	$biblefox->activity_refs->check_install(1);
}
add_action('admin_menu', 'bfox_activity_install');

/*
 * Management Functions
 */

function bfox_bp_activity_after_save($activity) {
	global $biblefox;
	$biblefox->activity_refs->save_item($activity->id, new BfoxRefs($activity->content));
}
add_action('bp_activity_after_save', 'bfox_bp_activity_after_save');

function bfox_bp_activity_deleted_activities($activity_ids) {
	global $biblefox;
	$biblefox->activity_refs->delete_simple_items($activity_ids);
}
add_action('bp_activity_deleted_activities', 'bfox_bp_activity_deleted_activities');

/*
 * Activity Query Functions
 */

function bfox_bp_has_activities_filter($filters, $args) {
	if (!empty($args['bfox_refs'])) {
		$refs = new BfoxRefs($args['bfox_refs']);
		if ($refs->is_valid()) $filters['bfox_refs'] = $refs;
	}
	return $filters;
}
add_filter('bp_has_activities_filter', 'bfox_bp_has_activities_filter', 10, 2);

function bfox_bp_activity_get_from_sql($from_sql, $filter) {
	if (isset($filter['bfox_refs'])) {
		global $biblefox;
		$from_sql .= ', ' . $biblefox->activity_refs->from_sql();
	}
	return $from_sql;
}
add_filter('bp_activity_get_from_sql', 'bfox_bp_activity_get_from_sql', 10, 2);

function bfox_bp_activity_get_where_conditions($wheres, $filter) {
	if (isset($filter['bfox_refs'])) {
		global $biblefox;
		$wheres []= $biblefox->activity_refs->join_where('a.id');
		$wheres []= $biblefox->activity_refs->refs_where($filter['bfox_refs']);
	}
	return $wheres;
}
add_filter('bp_activity_get_where_conditions', 'bfox_bp_activity_get_where_conditions', 10, 2);

?>