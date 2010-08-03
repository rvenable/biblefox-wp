<?php

define('BFOX_POST_REF_TABLE_VERSION', 1);

/**
 * Manages a DB table with a list of bible references for blog posts
 *
 * @author richard
 *
 */
class BfoxPostRefDbTable extends BfoxRefDbTable {
	const ref_type_tag = 0;
	const ref_type_content = 1;

	public function __construct() {
		global $wpdb;
		parent::__construct($wpdb->posts);
		$this->set_item_id_definition(array('item_id' => '%d', 'ref_type' => '%d'));
	}

	public function check_install($version = BFOX_POST_REF_TABLE_VERSION) {
		if (get_option($this->table_name . '_version') < $version) {
			require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );
			dbDelta("CREATE TABLE $this->table_name (
					item_id BIGINT(20) NOT NULL,
					ref_type BOOLEAN NOT NULL,
					start MEDIUMINT UNSIGNED NOT NULL,
					end MEDIUMINT UNSIGNED NOT NULL,
					KEY item_id (item_id),
					KEY sequence (start,end)
				);"
			);
			update_option($this->table_name . '_version', $version);
		}
	}

	public function save_post_for_ref_type($post, $ref_type) {
		return $this->save_item(array('item_id' => $post->ID, 'ref_type' => $ref_type), bfox_blog_post_get_ref($post, $ref_type));
	}

	public function save_post($post) {
		$post_id = $post->ID;

		$content_ref_found = $tag_ref_found = false;
		if (!empty($post_id)) {
			$content_ref_found = $this->save_post_for_ref_type($post, self::ref_type_content);
			$tag_ref_found = $this->save_post_for_ref_type($post, self::ref_type_tag);
		}
		return $content_ref_found || $tag_ref_found;
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

/**
 * Returns the global instance of BfoxPostRefDbTable
 *
 * @return BfoxPostRefDbTable
 */
function bfox_blog_post_ref_table($reset = false) {
	global $_bfox_post_ref_table;
	if (!isset($_bfox_post_ref_table) || $reset) $_bfox_post_ref_table = new BfoxPostRefDbTable();
	return $_bfox_post_ref_table;
}

function bfox_blog_post_reset() {
	bfox_blog_post_ref_table(true);
}
// We have to reset the post ref table every time we switch blogs
add_action('switch_blog', 'bfox_blog_post_reset');

function bfox_blog_post_install() {
	$table = bfox_blog_post_ref_table();
	$table->check_install();
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
 * @return BfoxRef
 */
function bfox_blog_post_get_ref($post, $ref_type = null) {
	if (!is_object($post)) $post = get_post($post);

	$ref = new BfoxRef;

	// Get Bible references from content
	if (is_null($ref_type) || BfoxPostRefDbTable::ref_type_content == $ref_type) $ref->add_ref(bfox_ref_from_content($post->post_content));

	// Get Bible references from tags
	if (is_null($ref_type) || BfoxPostRefDbTable::ref_type_tag == $ref_type) {
		$tags = wp_get_post_tags($post->ID, array('fields' => 'names'));
		foreach ($tags as $tag) $ref->add_ref(bfox_ref_from_tag($tag));
	}

	return $ref;
}

/**
 * Save the bible references for a blog post
 *
 * @param integer $post_id
 * @param object $post
 */
function bfox_blog_save_post($post_id, $post) {
	$table = bfox_blog_post_ref_table();
	$table->save_post($post);
}
add_action('save_post', 'bfox_blog_save_post', 10, 2);

/**
 * Delete the bible references for a blog post
 *
 * @param integer $post_id
 */
function bfox_blog_delete_post($post_id) {
	$table = bfox_blog_post_ref_table();
	$table->delete_simple_items($post_id);
}
add_action('delete_post', 'bfox_blog_delete_post');

/*
 * Post Query Functions
 */

/**
 * Prepares a blog post query to look for bible references
 *
 * @param WP_Query $wp_query
 */
function bfox_blog_parse_query($wp_query) {
	global $bfox_blog_query;
	$bfox_blog_query = $wp_query;

	// Bible Reference tags should redirect to a ref search
	if (!empty($wp_query->query_vars['tag'])) {
		$ref = bfox_ref_from_tag($wp_query->query_vars['tag']);
		if ($ref->is_valid()) {
			wp_redirect(bfox_ref_blog_url($wp_query->query_vars['tag']));
			die();
		}
	}

	// Check to see if the search string is a bible reference
	if (!empty($wp_query->query_vars['s'])) {
		// TODO: use leftovers
		$ref = bfox_ref_from_tag($wp_query->query_vars['s']);
		if ($ref->is_valid()) {
			$wp_query->query_vars['s'] = '';
			$wp_query->bfox_ref = $ref;
		}
	}
}
add_action('parse_query', 'bfox_blog_parse_query');

function bfox_blog_posts_join($join) {
	global $bfox_blog_query, $wpdb;
	if (isset($bfox_blog_query->bfox_ref)) {
		$table = bfox_blog_post_ref_table();
		$join .= ' ' . $table->join_sql("$wpdb->posts.ID");
	}
	return $join;
}
add_filter('posts_join', 'bfox_blog_posts_join');

function bfox_blog_posts_where($where) {
	global $bfox_blog_query;
	$table = bfox_blog_post_ref_table();
	if (isset($bfox_blog_query->bfox_ref)) $where .= ' AND ' . $table->seqs_where($bfox_blog_query->bfox_ref);
	return $where;
}
add_filter('posts_where', 'bfox_blog_posts_where');

function bfox_blog_posts_groupby($sql) {
	global $bfox_blog_query, $wpdb;
	// Bible references searches need to group on the post ID
	if (isset($bfox_blog_query->bfox_ref)) $sql .= " $wpdb->posts.ID";
	return $sql;
}
add_filter('posts_groupby', 'bfox_blog_posts_groupby');

/*
 * Content Filters
 */

// Replace bible references with bible links
add_filter('the_content', 'bfox_ref_replace_html');
add_filter('comment_text', 'bfox_ref_replace_html');
add_filter('the_excerpt', 'bfox_ref_replace_html');

/**
 * Finds any bible references in an array of tag links and adds tooltips to them
 *
 * Should be used to filter 'term_links-post_tag', called in get_the_term_list()
 *
 * @param array $tag_links
 * @return array
 */
function bfox_add_tag_ref_tooltips($tag_links) {
	if (!empty($tag_links)) foreach ($tag_links as &$tag_link) if (preg_match('/<a.*>(.*)<\/a>/', $tag_link, $matches)) {
		$tag = $matches[1];
		$ref = bfox_ref_from_tag($tag);
		if ($ref->is_valid()) {
			$tag_link = bfox_ref_bible_link(array('ref' => $ref, 'text' => $tag));
		}
	}
	return $tag_links;
}

// Add tooltips to Bible Ref tag links
add_filter('term_links-post_tag', 'bfox_add_tag_ref_tooltips');

/*
 * Admin Page Functions
 */

/**
 * Creates the form displaying the scripture quick view
 *
 */
function bfox_blog_quick_view_meta_box() {
	global $post_ID;
	$ref = bfox_blog_post_get_ref($post_ID);

	if (!empty($_REQUEST['bfox_ref'])) $ref->add_string($_REQUEST['bfox_ref']);

	$is_valid = $ref->is_valid();
	if ($is_valid) $ref_str = $ref->get_string();

	// Create the form
	?>
	<?php if (empty($ref_str)): ?>
		<p>This post currently has no bible references.</p>
	<?php else: ?>
		<p>This post is currently referencing: <?php echo bfox_blog_ref_link_ajax($ref_str) ?></p>
	<?php endif ?>
		<p>Add more bible references by typing them into the post, or adding them to the post tags.</p>
		<div class="hide-if-no-js">
			<h4>Quick Scripture Viewer</h4>
			<input type="text" name="new-bible-ref" id="new-bible-ref" size="16" value="" />
			<input type="button" class="button" id="view-bible-ref" value="View Scripture" tabindex="3" />
			<span class="howto"><?php _e('Type a bible reference (ie. "gen 1")', 'bfox'); ?></span>
			<br/>
		</div>

		<h4 id="bible-text-progress"><span id='bible_progress'><?php if ($is_valid) echo 'Viewing'?></span> <span id='bible_view_ref'><?php if ($is_valid) echo $ref->get_string(BibleMeta::name_short) ?></span></h4>
		<input type="hidden" name="bible-request-url" id="bible-request-url" value="<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" />
		<div id="bible-text"><?php if ($is_valid) echo bfox_get_ref_content_quick($ref) ?></div>
	<?php
}

/**
 * Bible post write link handling
 *
 * Pretty hacky, but better than previous javascript hack
 * HACK necessary until WP ticket 10544 is fixed: http://core.trac.wordpress.org/ticket/10544
 *
 * @param string $page
 * @param string $context
 * @param object $post
 */
function bfox_bible_post_link_setup($page, $context, $post) {
	if ((!$post->ID || 'auto-draft' == $post->post_status) && 'post' == $page && 'side' == $context && !empty($_REQUEST['bfox_ref'])) {
		$hidden_ref = new BfoxRef($_REQUEST['bfox_ref']);
		if ($hidden_ref->is_valid()) {
			global $wp_meta_boxes;
			// Change the callback function
			$wp_meta_boxes[$page][$context]['core']['tagsdiv-post_tag']['callback'] = 'bfox_post_tags_meta_box';

			function bfox_post_tags_meta_box($post, $box) {
				function bfox_wp_get_object_terms($terms) {
					$hidden_ref = new BfoxRef($_REQUEST['bfox_ref']);
					if ($hidden_ref->is_valid()) {
						$term = new stdClass;
						$term->name = $hidden_ref->get_string();
						$terms = array($term);
					}
					return $terms;
				}

				// We need our filter on wp_get_object_terms to get called, but it won't be if post->ID is 0, so we set it to -1
				add_action('wp_get_object_terms', 'bfox_wp_get_object_terms');
				post_tags_meta_box($post, $box);
				remove_action('wp_get_object_terms', 'bfox_wp_get_object_terms');
			}
		}
	}
}
add_action('do_meta_boxes', 'bfox_bible_post_link_setup', 10, 3);

/**
 * Filter function for adding biblefox columns to the edit posts list
 *
 * @param $columns
 * @return array
 */
function bfox_manage_posts_columns($columns) {
	// Create a new columns array with our new columns, and in the specified order
	// See wp_manage_posts_columns() for the list of default columns
	$new_columns = array();
	foreach ($columns as $key => $column) {
		$new_columns[$key] = $column;

		// Add the bible verse column right after 'author' column
		if ('author' == $key) $new_columns['bfox_col_ref'] = __('Bible References', 'bfox');
	}
	return $new_columns;
}
add_filter('manage_posts_columns', 'bfox_manage_posts_columns'); // Posts
//add_filter('manage_pages_columns', 'bfox_manage_posts_columns'); // Pages

/**
 * Action function for displaying bible reference information in the edit posts list
 *
 * @param string $column_name
 * @param integer $post_id
 * @return none
 */
function bfox_manage_posts_custom_column($column_name, $post_id) {
	if ('bfox_col_ref' == $column_name) {
		global $post;
		$ref = bfox_blog_post_get_ref($post);
		if ($ref->is_valid()) echo bfox_blog_ref_edit_posts_link($ref->get_string(BibleMeta::name_short));
	}
}
add_action('manage_posts_custom_column', 'bfox_manage_posts_custom_column', 10, 2); // Posts
//add_action('manage_pages_custom_column', 'bfox_manage_posts_custom_column', 10, 2); // Pages

/*
 * Settings Functions
 */

function bfox_blog_admin_post_refresh_url($network_refresh, $is_running = true) {
	if ($network_refresh) $page = menu_page_url('bfox-ms', false);
	else $page = menu_page_url('bfox-blog-settings', false);

	if ($is_running) return add_query_arg('bfox_post_refresh', $is_running, $page);
	return "$page#bible-refresh";
}

function bfox_blog_admin_post_refresh_output_status($network_refresh) {
	extract(bfox_blog_admin_post_refresh_status($network_refresh));
	if ($scan_total) {
		?>
		<p>
		<?php $date_finished ? printf(__('Indexing completed on %s (Biblefox version %s)', 'bfox'), date("Y-m-d H:i:s", $date_finished), $version) : _e('Indexing not finished...', 'bfox') ?><br/>
		<?php printf(__('Scanned %d blog posts: %d posts contained bible references', 'bfox'), $scan_total, $index_total) ?>
		<?php if ($network_refresh) printf(__(' (%d blogs scanned)', 'bfox'), $blog_count) ?>
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
		<h3 id="bible-refresh"><?php _e('Refresh Bible Index', 'bfox') ?></h3>
		<p><?php _e('You can refresh the Bible index for your blog to make sure all blog posts are indexed properly.', 'bfox') ?></p>
		<?php bfox_blog_admin_post_refresh_output_status(false) ?>
		<p><a class="button-primary" href="<?php echo bfox_blog_admin_post_refresh_url(false) ?>"><?php _e('Refresh Bible Index', 'bfox') ?></a></p>
		<br/>
	<?php
}
if (!is_multisite()) add_action('bfox_blog_admin_page', 'bfox_blog_admin_post_refresh');

function bfox_blog_network_admin_post_refresh() {
	?>
		<h3 id="bible-refresh"><?php _e('Refresh Bible Index for All Blogs', 'bfox') ?></h3>
		<p><?php _e('You can refresh the Bible index for all the blogs on your network to make sure all blog posts are indexed properly (this is good to do after Biblefox upgrades).', 'bfox') ?></p>
		<?php bfox_blog_admin_post_refresh_output_status(true) ?>
		<p><a class="button-primary" href="<?php echo bfox_blog_admin_post_refresh_url(true) ?>"><?php _e('Refresh All Blogs', 'bfox') ?></a></p>
		<br/>
	<?php
}
if (is_multisite()) add_action('bfox_ms_admin_page', 'bfox_blog_network_admin_post_refresh', 22);

function bfox_blog_admin_post_warnings() {
	extract(bfox_blog_admin_post_refresh_status(is_multisite()));
	if (!$date_finished) {
		function bfox_blog_admin_post_warning() {
			echo "
			<div id='bfox-blog-post-warning' class='updated fade'><p><strong>".__('Biblefox is almost ready.', 'bfox')."</strong> ".sprintf(__('You must <a href="%1$s">refresh your Bible reference index</a>.', 'bfox'), bfox_blog_admin_post_refresh_url(is_multisite(), false))."</p></div>
			";
		}
		add_action('admin_notices', 'bfox_blog_admin_post_warning');
	}
}
add_action('admin_init', 'bfox_blog_admin_post_warnings');

function bfox_blog_admin_post_check_refresh($show_settings) {
	if ($show_settings && $_GET['bfox_post_refresh']) {
		global $wpdb;
		$table = bfox_blog_post_ref_table();

		if ($_GET['page'] == 'bfox-ms') {
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
				$table->check_install();
				$table->delete_all();
			}

			// Refresh this set of blog posts
			extract(BfoxRefDbTable::simple_refresh($table, 'ID', '', $limit, $blog_offset));
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
		<h3><?php _e('Refreshing Bible Index...', 'bfox') ?></h3>
		<?php bfox_blog_admin_post_refresh_output_status($network_refresh) ?>

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

		$show_settings = false;
	}
	return $show_settings;
}
add_filter('bfox_blog_show_admin_page', 'bfox_blog_admin_post_check_refresh');
add_filter('bfox_ms_show_admin_page', 'bfox_blog_admin_post_check_refresh');

?>