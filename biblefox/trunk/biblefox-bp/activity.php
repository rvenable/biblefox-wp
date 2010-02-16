<?php

define(BFOX_ACTIVITY_REFS_TABLE_VERSION, 2);

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
			$refs = BfoxBlog::content_to_refs($post->post_text);

			// Only index the tags for the first post in a topic
			if ($activity->type == 'new_forum_topic') {
				$tags = bb_get_topic_tags($post->topic_id, array('fields' => 'names'));
				foreach ($tags as $tag) $refs->add_refs(BfoxBlog::tag_to_refs($tag));
			}
		}
		// For all other activities, just index the activity content
		else {
			$refs = BfoxBlog::content_to_refs($activity->content);
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

function bfox_activity_init() {
	global $bp, $biblefox;
	$biblefox->activity_refs = new BfoxActivityRefsDbTable($bp->activity->table_name);
}
add_action('plugins_loaded', 'bfox_activity_init', 99);
add_action('admin_menu', 'bfox_activity_init', 99);

function bfox_activity_install() {
	global $biblefox;
	$biblefox->activity_refs->check_install(BFOX_ACTIVITY_REFS_TABLE_VERSION);
}
add_action('admin_menu', 'bfox_activity_install');

/*
 * Management Functions
 */

function bfox_bp_activity_after_save($activity) {
	global $biblefox;
	$biblefox->activity_refs->save_activity($activity);
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

function bfox_bp_activity_set_refs(BfoxRefs $refs) {
	global $bfox_activity_refs;
	if ($refs->is_valid()) $bfox_activity_refs = $refs;
	else unset($bfox_activity_refs);
}

function bfox_bp_activity_unset_refs() {
	global $bfox_activity_refs;
	unset($bfox_activity_refs);
}

function bfox_bp_activity_get_sql($sql) {
	global $biblefox, $bfox_activity_refs;
	if (isset($bfox_activity_refs) && preg_match('/(SELECT)(.*)(WHERE.*)(ORDER.*)$/i', $sql, $matches)) {
		array_shift($matches); // Get rid of the first match which is the entire string
		$matches[0] .= ' SQL_CALC_FOUND_ROWS ';
		$matches[1] .= ' ' . $biblefox->activity_refs->join_sql('a.id') . ' ';
		$matches[2] .= ' AND ' . $biblefox->activity_refs->seqs_where($bfox_activity_refs) . ' GROUP BY a.id ';
		$sql = implode('', $matches);
	}
	return $sql;
}
add_filter('bp_activity_get_sql', 'bfox_bp_activity_get_sql');

function bfox_bp_activity_get_total_sql($sql) {
	global $biblefox, $bfox_activity_refs;
	if (isset($bfox_activity_refs)) $sql = 'SELECT FOUND_ROWS()';
	return $sql;
}
add_filter('bp_activity_get_total_sql', 'bfox_bp_activity_get_total_sql');

/*
 * Settings Functions
 */

function bfox_bp_admin_activity_refresh_url() {
	return admin_url('admin.php?page=bfox-bp-settings&bfox_activity_refresh=1');
}

function bfox_bp_admin_activity_refresh() {
	?>
		<h3><?php _e('Refresh Bible Index', 'biblefox') ?></h3>
		<p><?php _e('You can refresh your Bible index to make sure all activity is indexed properly (this is good to do after Biblefox upgrades).', 'biblefox') ?></p>
		<p><a class="button" href="<?php echo bfox_bp_admin_activity_refresh_url() ?>"><?php _e('Refresh Bible Index', 'biblefox') ?></a></p>
	<?php
}
add_action('bfox_bp_admin_settings', 'bfox_bp_admin_activity_refresh');

function bfox_bp_admin_activity_check_refresh($show_settings) {
	if ($show_settings && $_GET['bfox_activity_refresh']) {
		global $biblefox;

		// If this is the first page of refreshing, delete all the activities
		$offset = (int) $_GET['offset'];
		if (0 == $offset) $biblefox->activity_refs->delete_all();

		// Refresh this set of activities
		extract(BfoxRefsDbTable::simple_refresh($biblefox->activity_refs, 'id', 'content', $_GET['limit'], $offset));
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
add_filter('bfox_bp_admin_show_settings', 'bfox_bp_admin_activity_check_refresh');

/*
 * "What did you read?" functions
 */

function bfox_bp_activity_before_save($activity) {
	if ('activity_update' == $activity->type && isset($_REQUEST['bfox_read_ref_str'])) {
		$refs = new BfoxRefs($_REQUEST['bfox_read_ref_str']);
		if ($refs->is_valid()) {
			$activity->bfox_read_ref_str = $refs->get_string();
			$activity->action = str_replace(array('posted an update', 'in the group'), array(__('read ', 'biblefox') . $activity->bfox_read_ref_str, 'with the group'), $activity->action);
		}
	}
}
add_action('bp_activity_before_save', 'bfox_bp_activity_before_save');

?>