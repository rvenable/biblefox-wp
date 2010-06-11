<?php

define('BFOX_ACTIVITY_REFS_TABLE_VERSION', 2);

class BfoxActivityRefsDbTable extends BfoxRefsDbTable {

	/**
	 * Saves a BuddyPress Activity
	 *
	 * @param stdObject $activity Row from the activity table
	 * @return boolean (TRUE if there were actually Bible references to save)
	 */
	public function save_activity($activity) {
		global $bp;

		// For blog posts, index the full post content and tags
		if ($activity->component == $bp->blogs->id && $activity->type == 'new_blog_post') {
			switch_to_blog($activity->item_id);

			$refs = bfox_blog_post_get_refs($activity->secondary_item_id);

			restore_current_blog();
		}
		// For forum posts, index the full post text and tags
		elseif ($activity->component == $bp->groups->id && ($activity->type == 'new_forum_post' || $activity->type == 'new_forum_topic')) {
			// Get the forum post
			if ($activity->type == 'new_forum_topic') list($post) = bp_forums_get_topic_posts(array('topic_id' => $activity->secondary_item_id, 'per_page' => 1));
			else $post = bp_forums_get_post($activity->secondary_item_id);

			// Index the post text
			$refs = bfox_refs_from_content($post->post_text);

			// Only index the tags for the first post in a topic
			if ($activity->type == 'new_forum_topic') {
				$tags = bb_get_topic_tags($post->topic_id, array('fields' => 'names'));
				foreach ($tags as $tag) $refs->add_refs(bfox_refs_from_tag($tag));
			}
		}
		// For all other activities, just index the activity content
		else {
			$refs = bfox_refs_from_content($activity->content);
		}

		// Add any read passage strings
		if (!empty($activity->bfox_read_ref_str)) {
			bp_activity_update_meta($activity->id, 'bfox_read_ref_str', $activity->bfox_read_ref_str);
			$refs->add_string($activity->bfox_read_ref_str);

			do_action('bfox_save_activity_read_ref_str', $activity);
		}

		return $this->save_item($activity->id, apply_filters('bfox_save_activity_refs', $refs, $activity));
	}

	public function save_data_row($data_row, $id_col, $content_col) {
		return $this->save_activity($data_row);
	}
}



/*
 * Initialization Functions
 */

/**
 * Returns the global instance of BfoxActivityRefsDbTable
 *
 * @return BfoxActivityRefsDbTable
 */
function bfox_activity_refs_table() {
	global $bp, $_bfox_activity_refs_table;
	if (!isset($_bfox_activity_refs_table)) $_bfox_activity_refs_table = new BfoxActivityRefsDbTable($bp->activity->table_name);
	return $_bfox_activity_refs_table;
}

function bfox_activity_check_install() {
	$table = bfox_activity_refs_table();
	$table->check_install(BFOX_ACTIVITY_REFS_TABLE_VERSION);
}
add_action('bfox_bp_check_install', 'bfox_activity_check_install');

/*
 * Management Functions
 */

function bfox_bp_activity_after_save($activity) {
	$table = bfox_activity_refs_table();
	$table->save_activity($activity);
}
add_action('bp_activity_after_save', 'bfox_bp_activity_after_save');

function bfox_bp_activity_deleted_activities($activity_ids) {
	$table = bfox_activity_refs_table();
	$table->delete_simple_items($activity_ids);
}
add_action('bp_activity_deleted_activities', 'bfox_bp_activity_deleted_activities');

/*
 * Activity Query Functions
 */

function bfox_bp_activity_set_refs(BfoxRefs $refs) {
	global $bfox_activity_refs;
	if ($refs->is_valid()) {
		$bfox_activity_refs = $refs;
		add_filter('query', 'bfox_bp_bible_directory_activity_sql_hack');
	}
	else bfox_bp_activity_unset_refs();
}

function bfox_bp_activity_unset_refs() {
	global $bfox_activity_refs;
	unset($bfox_activity_refs);
	remove_filter('query', 'bfox_bp_bible_directory_activity_sql_hack');
}

/**
 * HACK function needed because we need to filter the activity table queries, but BP doesn't support filtering SQL queries
 * See: http://trac.buddypress.org/ticket/1721
 *
 * Our workaround is to actually filter in the $wpdb->query() function (using the 'query' filter)
 *
 * @param string $query
 * @return string
 */
function bfox_bp_bible_directory_activity_sql_hack($query) {
	global $wpdb, $bp;

	// Check to see if this is the query called by the BP_Activity_Activity::get() function

	// Activity Query
	$select_sql = "SELECT a.*, u.user_email, u.user_nicename, u.user_login, u.display_name";
	$from_sql = " FROM {$bp->activity->table_name} a LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID";
	$activity_sql = "{$select_sql} {$from_sql}";
	if (0 == substr_compare($query, $activity_sql, 0, min(strlen($query), strlen($activity_sql)))) return bfox_bp_activity_get_sql($query);

	// Activity Count Query
	$activity_sql = "SELECT count(a.id) FROM {$bp->activity->table_name} a";
	if (0 == substr_compare($query, $activity_sql, 0, min(strlen($query), strlen($activity_sql)))) return bfox_bp_activity_get_total_sql($query);

	return $query;
}

function bfox_bp_activity_get_sql($sql) {
	global $bfox_activity_refs;
	if (isset($bfox_activity_refs) && preg_match('/(SELECT)(.*)(WHERE.*)(ORDER.*)$/i', $sql, $matches)) {
		$table = bfox_activity_refs_table();

		array_shift($matches); // Get rid of the first match which is the entire string
		$matches[0] .= ' SQL_CALC_FOUND_ROWS ';
		$matches[1] .= ' ' . $table->join_sql('a.id') . ' ';
		$matches[2] .= ' AND ' . $table->seqs_where($bfox_activity_refs) . ' GROUP BY a.id ';
		$sql = implode('', $matches);
	}
	return $sql;
}
//add_filter('bp_activity_get_sql', 'bfox_bp_activity_get_sql');

function bfox_bp_activity_get_total_sql($sql) {
	global $bfox_activity_refs;
	if (isset($bfox_activity_refs)) $sql = 'SELECT FOUND_ROWS()';
	return $sql;
}
//add_filter('bp_activity_get_total_sql', 'bfox_bp_activity_get_total_sql');

/*
 * Settings Functions
 */

function bfox_bp_admin_activity_refresh_url() {
	return admin_url('admin.php?page=bfox-bp-settings&bfox_activity_refresh=1');
}

function bfox_bp_admin_activity_refresh() {
	?>
		<h3><?php _e('Refresh Bible Index for all BuddyPress Activities', 'biblefox') ?></h3>
		<p><?php _e('Every BuddyPress activity gets added to the Bible index based on the Bible references it contains. You can refresh your Bible index to make sure all activity is indexed properly (this is good to do after Biblefox upgrades).', 'biblefox') ?></p>
		<p><a class="button-primary" href="<?php echo bfox_bp_admin_activity_refresh_url() ?>"><?php _e('Refresh BuddyPress Activities', 'biblefox') ?></a></p>
		<br/>
	<?php
}
add_action('bfox_bp_admin_page', 'bfox_bp_admin_activity_refresh', 21);

function bfox_bp_admin_activity_check_refresh($show_settings) {
	if ($show_settings && $_GET['bfox_activity_refresh']) {
		$table = bfox_activity_refs_table();

		// If this is the first page of refreshing, delete all the activities
		$offset = (int) $_GET['offset'];
		if (0 == $offset) $table->delete_all();

		// Refresh this set of activities
		extract(BfoxRefsDbTable::simple_refresh($table, 'id', 'content', $_GET['limit'], $offset));
		$scan_total = $_GET['scan_total'] + $scanned;
		$index_total = $_GET['index_total'] + $indexed;

		?>
		<h3><?php _e('Refreshing Bible Index...', 'biblefox') ?></h3>
		<p><?php printf(__('Scanned %d activities (out of %d total activities)<br/>%d contained bible references', 'biblefox'), $scan_total, $total, $index_total) ?></p>

		<?php
		$offset += $scanned;
		$next_url = add_query_arg(compact('offset', 'scan_total', 'index_total'), bfox_bp_admin_activity_refresh_url());

		if ($offset < $total): ?>
		<p><?php _e("If your browser doesn't start loading the next page automatically click this link:", 'biblefox'); ?> <a class="button" href="<?php echo $next_url ?>"><?php _e("Continue", 'biblefox'); ?></a></p>
		<script type='text/javascript'>
		<!--
		function nextpage() {
			location.href = "<?php echo $next_url ?>";
		}
		setTimeout( "nextpage()", 250 );
		//-->
		</script>
		<?php
		endif;
		$show_settings = false;
	}
	return $show_settings;
}
add_filter('bfox_bp_show_admin_page', 'bfox_bp_admin_activity_check_refresh');

/*
 * "What did you read?" functions
 */

function bfox_bp_activity_before_save($activity) {
	if ('activity_update' == $activity->type && isset($_REQUEST['bfox_read_ref_str'])) {
		$refs = new BfoxRefs($_REQUEST['bfox_read_ref_str']);
		if ($refs->is_valid()) {
			$activity->bfox_read_ref_str = $refs->get_string();
			$activity->action = str_replace('posted an update', __('posted an update about ', 'biblefox') . $activity->bfox_read_ref_str, $activity->action);
		}
	}
}
add_action('bp_activity_before_save', 'bfox_bp_activity_before_save');

?>