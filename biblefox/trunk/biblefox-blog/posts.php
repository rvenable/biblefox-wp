<?php

define(BFOX_POST_REFS_TABLE_VERSION, 1);

/**
 * Manages a DB table with a list of bible references for blog posts
 *
 * @author richard
 *
 */
class BfoxPostRefsDbTable extends BfoxRefsDbTable {
	const ref_type_tag = 0;
	const ref_type_content = 1;

	public function __construct() {
		global $wpdb;
		parent::__construct($wpdb->posts);
		$this->set_item_id_definition(array('item_id' => '%d', 'ref_type' => '%d'));
	}

	public function check_install($version = BFOX_POST_REFS_TABLE_VERSION) {
		if (get_option($this->table_name . '_version') < $version) {
			require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );
			dbDelta("CREATE TABLE $this->table_name (
					item_id BIGINT(20) NOT NULL,
					ref_type BOOLEAN NOT NULL,
					start MEDIUMINT UNSIGNED NOT NULL,
					end MEDIUMINT UNSIGNED NOT NULL,
					INDEX (item_id),
					INDEX (start, end)
				);"
			);
			update_option($this->table_name . '_version', $version);
		}
	}

	public function save_post_for_ref_type($post, $ref_type) {
		return $this->save_item(array('item_id' => $post->ID, 'ref_type' => $ref_type), bfox_blog_post_get_refs($post, $ref_type));
	}

	public function save_post($post) {
		$post_id = $post->ID;

		$content_refs_found = $tag_refs_found = false;
		if (!empty($post_id)) {
			$content_refs_found = $this->save_post_for_ref_type($post, self::ref_type_content);
			$tag_refs_found = $this->save_post_for_ref_type($post, self::ref_type_tag);
		}
		return $content_refs_found || $tag_refs_found;
	}

	public function refresh_select($id_col, $content_col, $limit = 0, $offset = 0) {
		return "* FROM $this->data_table_name WHERE post_type = 'post' ORDER BY $id_col ASC LIMIT $offset, $limit";
	}

	public function save_data_row($data_row, $id_col, $content_col) {
		return $this->save_post($data_row);
	}
}


/*
 * Initialization Functions
 */

function bfox_blog_post_init() {
	global $biblefox;
	$biblefox->post_refs = new BfoxPostRefsDbTable();
}
add_action('plugins_loaded', 'bfox_blog_post_init');
add_action('admin_menu', 'bfox_blog_post_init');
add_action('switch_blog', 'bfox_blog_post_init');

function bfox_blog_post_install() {
	global $biblefox;
	$biblefox->post_refs->check_install();
}
add_action('admin_menu', 'bfox_blog_post_install');

/*
 * Management Functions
 */

/**
 * Return the bible references for a given blog post
 *
 * @param $post
 * @param $ref_type
 * @return BfoxRefs
 */
function bfox_blog_post_get_refs($post, $ref_type = null) {
	if (!is_object($post)) $post = get_post($post);

	$refs = new BfoxRefs;

	// Get Bible references from content
	if (is_null($ref_type) || BfoxPostRefsDbTable::ref_type_content == $ref_type) $refs->add_refs(BfoxBlog::content_to_refs($post->post_content));

	// Get Bible references from tags
	if (is_null($ref_type) || BfoxPostRefsDbTable::ref_type_tag == $ref_type) {
		$tags = wp_get_post_tags($post->ID, array('fields' => 'names'));
		foreach ($tags as $tag) $refs->add_refs(BfoxBlog::tag_to_refs($tag));
	}

	return $refs;
}

/**
 * Save the bible references for a blog post
 *
 * @param integer $post_id
 * @param object $post
 */
function bfox_blog_save_post($post_id, $post) {
	global $biblefox;
	$biblefox->post_refs->save_post($post);
}
add_action('save_post', 'bfox_blog_save_post', 10, 2);

/**
 * Delete the bible references for a blog post
 *
 * @param integer $post_id
 */
function bfox_blog_delete_post($post_id) {
	global $biblefox;
	$biblefox->post_refs->delete_simple_items($post_id);
}
add_action('delete_post', 'bfox_blog_delete_post');

// HACK: Part of the 'add post for ref' link stuff
// Looks for a hidden ref tag and saves it
function bfox_blog_save_post_add_hidden_tag($post_id, $post) {
	// Try to get a hidden tag from form input
	if (isset($_POST[BfoxBlog::hidden_ref_tag])) {
		$new_tag_refs = new BfoxRefs($_POST[BfoxBlog::hidden_ref_tag]);
		if ($new_tag_refs->is_valid()) {
			$tags = wp_get_post_tags($post_id, array('fields' => 'names'));
			$tags []= $new_tag_refs->get_string();
			wp_set_post_tags($post_id, $tags);
		}
	}
}
add_action('save_post', 'bfox_blog_save_post_add_hidden_tag', 5, 2);

/*
 * Post Query Functions
 */

/**
 * Prepares a blog post query to look for bible references
 *
 * @param WP_Query $wp_query
 */
function bfox_blog_parse_query($wp_query) {
	global $biblefox;
	$biblefox->blog_query = $wp_query;

	// Bible Reference tags should redirect to a ref search
	if (!empty($wp_query->query_vars['tag'])) {
		$refs = BfoxBlog::tag_to_refs($wp_query->query_vars['tag']);
		if ($refs->is_valid()) wp_redirect(BfoxBlog::ref_blog_url($wp_query->query_vars['tag']));
	}

	// Check to see if the search string is a bible reference
	if (!empty($wp_query->query_vars['s'])) {
		// TODO: use leftovers
		$refs = BfoxBlog::tag_to_refs($wp_query->query_vars['s']);
		if ($refs->is_valid()) {
			$wp_query->query_vars['s'] = '';
			$wp_query->bfox_refs = $refs;
		}
	}
}
add_action('parse_query', 'bfox_blog_parse_query');

function bfox_blog_posts_join($join) {
	global $biblefox, $wpdb;
	if (isset($biblefox->blog_query->bfox_refs)) $join .= ' ' . $biblefox->post_refs->join_sql("$wpdb->posts.ID");
	return $join;
}
add_filter('posts_join', 'bfox_blog_posts_join');

function bfox_blog_posts_where($where) {
	global $biblefox;
	if (isset($biblefox->blog_query->bfox_refs)) $where .= ' AND ' . $biblefox->post_refs->seqs_where($biblefox->blog_query->bfox_refs);
	return $where;
}
add_filter('posts_where', 'bfox_blog_posts_where');

function bfox_blog_posts_groupby($sql) {
	global $biblefox, $wpdb;
	// Bible references searches need to group on the post ID
	if (isset($biblefox->blog_query->bfox_refs)) $sql .= " $wpdb->posts.ID";
	return $sql;
}
add_filter('posts_groupby', 'bfox_blog_posts_groupby');

/*
 * Content Filters
 */

// Replace bible references with bible links
add_filter('the_content', 'bfox_ref_replace_html');

// Add tooltips to Bible Ref tag links
add_filter('term_links-post_tag', 'bfox_add_tag_ref_tooltips');

/*
 * Settings Functions
 */

function bfox_blog_admin_post_refresh_url($network_refresh, $is_running = true) {
	if (!$is_running) $is_running .= "#bible-refresh";

	if ($network_refresh) $page = 'bfox-blog-network-settings';
	else $page = 'bfox-blog-settings';

	return admin_url("admin.php?page=$page&bfox_post_refresh=$is_running");
}

function bfox_blog_admin_post_refresh_output_status($network_refresh) {
	extract(bfox_blog_admin_post_refresh_status($network_refresh));
	if ($scan_total) {
		?>
		<p>
		<?php $date_finished ? printf(__('Indexing completed on %s (Biblefox version %s)', 'biblefox'), date("Y-m-d H:i:s", $date_finished), $version) : _e('Indexing not finished...', 'biblefox') ?><br/>
		<?php printf(__('Scanned %d blog posts: %d posts contained bible references', 'biblefox'), $scan_total, $index_total) ?>
		<?php if ($network_refresh) printf(__(' (%d blogs scanned)', 'biblefox'), $blog_count) ?>
		</p>
		<?php
	}
}

function bfox_blog_admin_post_refresh_status($network_refresh) {
	if ($network_refresh) return (array) get_site_option('bfox_blog_network_post_refresh');
	else return (array) get_option('bfox_blog_post_refresh');
}

function bfox_blog_admin_post_refresh_set_status($status, $network_refresh) {
	$status['version'] = BFOX_VERSION;
	if ($network_refresh) return update_site_option('bfox_blog_network_post_refresh', $status);
	else return update_option('bfox_blog_post_refresh', $status);
}

function bfox_blog_admin_post_refresh() {
	?>
		<h3 id="bible-refresh"><?php _e('Refresh Bible Index', 'biblefox') ?></h3>
		<p><?php _e('You can refresh the Bible index for your blog to make sure all blog posts are indexed properly (this is good to do after Biblefox upgrades).', 'biblefox') ?></p>
		<?php bfox_blog_admin_post_refresh_output_status(false) ?>
		<p><a class="button" href="<?php echo bfox_blog_admin_post_refresh_url(false) ?>"><?php _e('Refresh Bible Index', 'biblefox') ?></a></p>
	<?php
}
add_action('bfox_blog_admin_settings', 'bfox_blog_admin_post_refresh');

function bfox_blog_network_admin_post_refresh() {
	?>
		<h3 id="bible-refresh"><?php _e('Refresh Bible Index', 'biblefox') ?></h3>
		<p><?php _e('You can refresh the Bible index for all the blogs on your network to make sure all blog posts are indexed properly (this is good to do after Biblefox upgrades).', 'biblefox') ?></p>
		<?php bfox_blog_admin_post_refresh_output_status(true) ?>
		<p><a class="button" href="<?php echo bfox_blog_admin_post_refresh_url(true) ?>"><?php _e('Refresh Bible Index', 'biblefox') ?></a></p>
	<?php
}
add_action('bfox_blog_network_admin_settings', 'bfox_blog_network_admin_post_refresh');

function bfox_blog_admin_post_check_refresh($show_settings) {
	if ($show_settings && $_GET['bfox_post_refresh']) {
		global $wpdb, $biblefox;

		if ($_GET['page'] == 'bfox-blog-network-settings') {
			$network_refresh = true;
			$blog_ids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id >= %d AND site_id = '{$wpdb->siteid}' AND spam = '0' AND deleted = '0' AND archived = '0' ORDER BY blog_id ASC", $_GET['blog_id']));
		}
		else {
			$network_refresh = false;
			$blog_ids = array($GLOBALS['blog_id']);
		}

		extract(bfox_blog_admin_post_refresh_status($network_refresh));

		// If the previous status is finished, then we must be starting a new complete refresh
		if ($date_finished) {
			$blog_offset = $scan_total = $index_total = $blog_count = $date_finished = 0;
		}

		$limit = (int) $_GET['limit'];
		if (empty($limit)) $limit = 100;

		// Loop until we've reach the limit or run out of blogs to scan
		while ($limit > 0 && !empty($blog_ids)) {
			$blog_id = array_shift($blog_ids);
			if ($network_refresh) switch_to_blog($blog_id);

			if (0 == $blog_offset) {
				$biblefox->post_refs->check_install();
				$biblefox->post_refs->delete_all();
			}

			// Refresh this set of blog posts
			extract(BfoxRefsDbTable::simple_refresh($biblefox->post_refs, 'ID', '', $limit, $blog_offset));
			$limit -= $scanned;
			$scan_total += $scanned;
			$index_total += $indexed;

			// If the offset is >= the total posts, then we've finished with this blog
			$blog_offset += $scanned;
			if ($blog_offset >= $total) {
				$blog_offset = 0;
				$blog_id = 0;
				$blog_count++;
			}

			if ($network_refresh) restore_current_blog();
		}

		if (empty($blog_id) && !empty($blog_ids)) $blog_id = array_shift($blog_ids);
		$is_running = $blog_offset || $blog_id;
		$next_url = bfox_blog_admin_post_refresh_url($network_refresh, $is_running);

		if ($is_running) {
			$date_finished = 0;
			$next_url = add_query_arg(compact('blog_id', 'limit'), $next_url);
		}
		else $date_finished = time();

		bfox_blog_admin_post_refresh_set_status(compact('blog_offset', 'scan_total', 'index_total', 'blog_count', 'date_finished'), $network_refresh);

		?>
		<h3><?php _e('Refreshing Bible Index...', 'biblefox') ?></h3>
		<?php bfox_blog_admin_post_refresh_output_status($network_refresh) ?>

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

		$show_settings = false;
	}
	return $show_settings;
}
add_filter('bfox_blog_admin_show_settings', 'bfox_blog_admin_post_check_refresh');
add_filter('bfox_blog_network_admin_show_settings', 'bfox_blog_admin_post_check_refresh');

?>