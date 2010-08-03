<?php

define('BFOX_ACTIVITY_REF_TABLE_VERSION', 2);

class BfoxActivityRefDbTable extends BfoxRefDbTable {

	/**
	 * Saves a BuddyPress Activity
	 *
	 * @param stdObject $activity Row from the activity table
	 * @return boolean (TRUE if there were actually Bible references to save)
	 */
	public function save_activity($activity) {
		// Add any read passage strings
		if (!empty($activity->bfox_read_ref_str)) {
			bp_activity_update_meta($activity->id, 'bfox_read_ref_str', $activity->bfox_read_ref_str);
			do_action('bfox_save_activity_read_ref_str', $activity);
		}

		if ($success = $this->save_item($activity->id, apply_filters('bfox_save_activity_ref', $activity->bfox_ref, $activity))) {
			// If we successfully saved some Bible references, lets cache the string in the activity meta
			// That way we don't have to recalculate it each time which could be a pain
			// For example, recalculating the references for a blog post requires switching to that blog, getting the blog post and parsing it
			// Instead, we can just use this meta
			bp_activity_update_meta($activity->id, 'bfox_ref_str', $activity->bfox_ref->get_string(BibleMeta::name_short));
		}

		return $success;
	}

	public function save_data_row($data_row, $id_col, $content_col) {
		bfox_bp_activity_before_save($data_row);

		if (isset($data_row->bfox_ref)) return $this->save_activity($data_row);
		return false;
	}
}

/*
 * Initialization Functions
 */

/**
 * Returns the global instance of BfoxActivityRefDbTable
 *
 * @return BfoxActivityRefDbTable
 */
function bfox_activity_ref_table() {
	global $bp, $_bfox_activity_ref_table;
	if (!isset($_bfox_activity_ref_table)) $_bfox_activity_ref_table = new BfoxActivityRefDbTable($bp->activity->table_name);
	return $_bfox_activity_ref_table;
}

function bfox_activity_check_install() {
	$table = bfox_activity_ref_table();
	$table->check_install(BFOX_ACTIVITY_REF_TABLE_VERSION);
}
add_action('bfox_bp_check_install', 'bfox_activity_check_install');

/*
 * Management Functions
 */

/**
 * Calculates the bible references for an activity before it is saved
 *
 * @param $activity
 */
function bfox_bp_activity_before_save($activity) {
	global $bp;

	// For blog posts, index the full post content and tags
	if ($activity->component == $bp->blogs->id && $activity->type == 'new_blog_post') {
		switch_to_blog($activity->item_id);

		$ref = bfox_blog_post_get_ref($activity->secondary_item_id);

		restore_current_blog();
	}
	// For forum posts, index the full post text and tags
	elseif ($activity->component == $bp->groups->id && ($activity->type == 'new_forum_post' || $activity->type == 'new_forum_topic')) {
		// Get the forum post
		if ($activity->type == 'new_forum_topic') list($post) = bp_forums_get_topic_posts(array('topic_id' => $activity->secondary_item_id, 'per_page' => 1));
		else $post = bp_forums_get_post($activity->secondary_item_id);

		// Index the post text
		$ref = bfox_ref_from_content($post->post_text);

		// Only index the tags for the first post in a topic
		if ($activity->type == 'new_forum_topic') {
			$tags = bb_get_topic_tags($post->topic_id, array('fields' => 'names'));
			foreach ($tags as $tag) $ref->add_ref(bfox_ref_from_tag($tag));
		}
	}
	// For all other activities, just index the activity content
	else {
		$ref = bfox_ref_from_content($activity->content);
	}

	// Add any 'Bible Tag' read passage strings
	if ('activity_update' == $activity->type && isset($_REQUEST['bfox_read_ref_str'])) {
		$tag_ref = new BfoxRef($_REQUEST['bfox_read_ref_str']);
		if ($tag_ref->is_valid()) {
			$activity->bfox_read_ref_str = $tag_ref->get_string();
			$activity->action = str_replace('posted an update', __('posted an update about ', 'bfox') . $activity->bfox_read_ref_str, $activity->action);
			$ref->add_ref($tag_ref);
		}
	}

	if ($ref->is_valid()) $activity->bfox_ref = $ref;
}
add_action('bp_activity_before_save', 'bfox_bp_activity_before_save');

/**
 * Saves bible reference data for an activity after the activity was just saved
 *
 * @param $activity
 */
function bfox_bp_activity_after_save($activity) {
	if (isset($activity->bfox_ref)) {
		$table = bfox_activity_ref_table();
		$table->save_activity($activity);
	}
}
add_action('bp_activity_after_save', 'bfox_bp_activity_after_save');

function bfox_bp_activity_deleted_activities($activity_ids) {
	$table = bfox_activity_ref_table();
	$table->delete_simple_items($activity_ids);
}
add_action('bp_activity_deleted_activities', 'bfox_bp_activity_deleted_activities');

/*
 * Activity Query Functions
 */

/*
 * Enables the ability to scan activity search terms for Bible references
 *
 * Once they are found, removes the bible reference text from the search terms and uses
 * the more accurate Bible index to retrieve results
 */
function bfox_bp_activity_enable_search_term_refs() {
	add_filter('query', 'bfox_bp_bible_directory_activity_sql_hack');
}

function bfox_bp_activity_disable_search_term_refs() {
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
	if (0 == substr_compare($query, $activity_sql, 0, min(strlen($query), strlen($activity_sql)))) return apply_filters('bp_activity_get_sql', $query);

	// Activity Count Query
	$activity_sql = "SELECT count(a.id) FROM {$bp->activity->table_name} a";
	if (0 == substr_compare($query, $activity_sql, 0, min(strlen($query), strlen($activity_sql)))) return apply_filters('bp_activity_get_total_sql', $query);

	return $query;
}

function bfox_bp_activity_get_sql($sql) {
	// Check to see if this SQL has any search terms
	if (preg_match("/a.content LIKE '%(.*?)%'/i", $sql, $matches)) {
		// Check the search terms to see if there are any Bible references
		$leftovers = true;
		$ref = BfoxRefParser::simple($matches[1], $leftovers);
		if ($ref->is_valid()) {
			// Remove the bible references from the search terms
			if (!empty($leftovers)) $leftovers = "a.content LIKE '%$leftovers%'";
			else $leftovers = "1=1";
			$sql = str_replace($matches[0], $leftovers, $sql);

			// Modify the sql to have use the bible references table
			if (preg_match('/(SELECT)(.*)(WHERE.*)(ORDER.*)$/i', $sql, $matches)) {
				$table = bfox_activity_ref_table();

				array_shift($matches); // Get rid of the first match which is the entire string
				$matches[0] .= ' SQL_CALC_FOUND_ROWS ';
				$matches[1] .= ' ' . $table->join_sql('a.id') . ' ';
				$matches[2] .= ' AND ' . $table->seqs_where($ref) . ' GROUP BY a.id ';
				$sql = implode('', $matches);

				// Make sure that we change the total SQL to use FOUND_ROWS()
				add_filter('bp_activity_get_total_sql', 'bfox_bp_activity_get_total_sql');
			}
		}

	}

	return $sql;
}
add_filter('bp_activity_get_sql', 'bfox_bp_activity_get_sql');

function bfox_bp_activity_get_total_sql($sql) {
	// Remove ourselves from the filter, because we only want to happen on demand
	remove_filter('bp_activity_get_total_sql', 'bfox_bp_activity_get_total_sql');

	return 'SELECT FOUND_ROWS()';
}

/*
 * Settings Functions
 */

function bfox_bp_admin_activity_refresh_url($refresh = false) {
	$url = admin_url('admin.php?page=bfox-bp-settings');
	if ($refresh) return add_query_arg('bfox_activity_refresh', $refresh, $url);
	else return "$url#bible-refresh";
}

function bfox_bp_admin_activity_refresh_output_status() {
	extract(bfox_bp_admin_activity_refresh_status());
	if ($scan_total) {
		?>
		<p>
		<?php $date_finished ? printf(__('Indexing completed on %s (Biblefox version %s)', 'bfox'), date("Y-m-d H:i:s", $date_finished), $version) : _e('Indexing not finished...', 'bfox') ?><br/>
		<?php printf(__('Scanned %d activities (out of %d total activities): %d activities contained bible references', 'bfox'), $scan_total, $total, $index_total) ?>
		</p>
		<?php
	}
}

function bfox_bp_admin_activity_refresh_status() {
	return (array) get_site_option('bfox_bp_activity_refresh');
}

function bfox_bp_admin_activity_refresh_set_status($status) {
	$status['version'] = BFOX_VERSION;
	return update_site_option('bfox_bp_activity_refresh', $status);
}

function bfox_bp_admin_activity_refresh() {
	?>
		<h3 id="bible-refresh"><?php _e('Refresh Bible Index for all BuddyPress Activities', 'bfox') ?></h3>
		<p><?php _e('Every BuddyPress activity gets added to the Bible index based on the Bible references it contains. You can refresh your Bible index to make sure all activity is indexed properly (this is good to do after Biblefox upgrades).', 'bfox') ?></p>
		<?php bfox_bp_admin_activity_refresh_output_status() ?>
		<p><a class="button-primary" href="<?php echo bfox_bp_admin_activity_refresh_url(true) ?>"><?php _e('Refresh BuddyPress Activities', 'bfox') ?></a></p>
		<br/>
	<?php
}
add_action('bfox_bp_admin_page', 'bfox_bp_admin_activity_refresh', 21);

function bfox_bp_admin_activity_warnings() {
	extract(bfox_bp_admin_activity_refresh_status());
	if (!$date_finished) {
		function bfox_bp_admin_activity_warning() {
			echo "
			<div id='bfox-blog-activity-warning' class='updated fade'><p><strong>".__('Biblefox is almost ready.', 'bfox')."</strong> ".sprintf(__('You must <a href="%1$s">refresh your BuddyPress Bible index</a>.', 'bfox'), bfox_bp_admin_activity_refresh_url())."</p></div>
			";
		}
		add_action('admin_notices', 'bfox_bp_admin_activity_warning');
	}
}
add_action('bfox_bp_admin_init', 'bfox_bp_admin_activity_warnings');

function bfox_bp_admin_activity_check_refresh($show_settings) {
	if ($show_settings && $_GET['bfox_activity_refresh']) {
		$table = bfox_activity_ref_table();

		// If this is the first page of refreshing, delete all the activities
		$offset = (int) $_GET['offset'];
		if (0 == $offset) $table->delete_all();

		// Refresh this set of activities
		extract(BfoxRefDbTable::simple_refresh($table, 'id', 'content', $_GET['limit'], $offset));

		// Get old values from bfox_bp_admin_activity_refresh_status() and increment them with the new values from BfoxRefDbTable::simple_refresh())
		extract(bfox_bp_admin_activity_refresh_status());

		// If the previous status is finished, then we must be starting a new complete refresh
		if ($date_finished) {
			$blog_offset = $scan_total = $index_total = $blog_count = $date_finished = 0;
		}

		$scan_total += $scanned;
		$index_total += $indexed;

		$offset += $scanned;
		$is_running = ($offset < $total);

		if ($is_running) $date_finished = 0;
		else $date_finished = time();

		bfox_bp_admin_activity_refresh_set_status(compact('scan_total', 'index_total', 'total', 'date_finished'));

		?>
		<h3><?php _e('Refreshing Bible Index...', 'bfox') ?></h3>
		<?php bfox_bp_admin_activity_refresh_output_status() ?>

		<?php
		$next_url = add_query_arg(compact('offset'), bfox_bp_admin_activity_refresh_url(true));

		if ($is_running): ?>
		<p><?php _e("If your browser doesn't start loading the next page automatically click this link:", 'bfox'); ?> <a class="button" href="<?php echo $next_url ?>"><?php _e("Continue", 'bfox'); ?></a></p>
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

?>