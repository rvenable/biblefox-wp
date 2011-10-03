<?php

require_once BFOX_REF_DIR . '/bfox_bible_tool_link.php';

function bfox_tools_create_post_type() {
	register_post_type('bfox_tool',
		array(
			'description' => __('Bible Tools', 'bfox'),
			'labels' => array(
				'name' => __('Bible Tools', 'bfox'),
				'singular_name' => __('Bible Tool', 'bfox'),
				'edit_item' => __('Edit Bible Tool', 'bfox'),
				'new_item' => __('New Bible Tool', 'bfox'),
				'view_item' => __('View Tool', 'bfox')
			),
			'public' => true,
			'has_archive' => true,
			'rewrite' => array('slug' => 'bible-tools'),
			'supports' => array('title', 'excerpt', 'thumbnail'),
			'register_meta_box_cb' => 'bfox_tools_register_meta_box_cb',
		)
	);
}
add_action('init', 'bfox_tools_create_post_type');

// Flush the rewrite rules upon plugin activation
// See: http://codex.wordpress.org/Function_Reference/register_post_type#Flushing_Rewrite_on_Activation
function bfox_tools_flush_rewrite() {
	bfox_tools_create_post_type();
	flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'bfox_tools_flush_rewrite');

function bfox_tools_register_meta_box_cb() {
	add_meta_box('bfox-tool-link', __('External Link', 'bfox'), 'bfox_tools_link_meta_box_cb', 'bfox_tool', 'normal', 'high');
	add_meta_box('bfox-tool-localdb', __('Local Database', 'bfox'), 'bfox_tools_localdb_meta_box_cb', 'bfox_tool', 'normal', 'high');
}

/*
 * Meta Box Callbacks
 */

function bfox_tools_link_meta_box_cb() {
	?>
	<p><?php _e('Link to any Bible tool on the internet. You can even link to tools for specific resources.', 'bfox') ?></p>

	<p><label for="bfox-tool-url"><?php _e( 'Link URL', 'bfox' ) ?></label>
	<input type="text" name="bfox-tool-url" id="bfox-tool-url" value="<?php echo bfox_tool_meta('url') ?>" /></p>

	<?php
}

function bfox_tools_localdb_meta_box_cb() {
	global $post;
	$local_db = bfox_tool_meta('local_db');

	?>
	<p><?php _e('You can create your own Bible tool using your blog\'s database. This is especially useful for having your own local Bible translations.', 'bfox') ?></p>
	<p><?php _e('Create a database table with a row for each bible verse. Then enter the name of the table, and its columns here.', 'bfox') ?></p>

	<p><label for="bfox-tool-db-table-name"><?php _e( 'Database Table Name', 'bfox' ) ?></label>
	<input type="text" name="bfox-tool-localdb[table_name]" id="bfox-tool-db-table-name" value="<?php echo $local_db['table_name'] ?>" /></p>

	<p><label for="bfox-tool-db-content-row"><?php _e( 'Content Row', 'bfox' ) ?></label>
	<input type="text" name="bfox-tool-localdb[content_row]" id="bfox-tool-db-content-row" value="<?php echo $local_db['content_row'] ?>" /></p>

	<p><label for="bfox-tool-db-ref-index-row"><?php _e( 'Bible Reference Index Row', 'bfox' ) ?></label>
	<input type="text" name="bfox-tool-localdb[ref_index_row]" id="bfox-tool-db-ref-index-row" value="<?php echo $local_db['ref_index_row'] ?>" /></p>

	<?php

	/*
	In the future we might support Book, Chapter, Verse rows, but for now we only support the index row

	<p><label for="bfox-tool-db-book-row"><?php _e( 'Book Row', 'bfox' ) ?></label>
	<input type="text" name="bfox-tool-localdb[book_row]" id="bfox-tool-db-book-row" value="<?php echo $local_db['book_row'] ?>" /></p>

	<p><label for="bfox-tool-db-chapter-row"><?php _e( 'Chapter Row', 'bfox' ) ?></label>
	<input type="text" name="bfox-tool-localdb[chapter_row]" id="bfox-tool-db-chapter-row" value="<?php echo $local_db['chapter_row'] ?>" /></p>

	<p><label for="bfox-tool-db-verse-row"><?php _e( 'Verse Row', 'bfox' ) ?></label>
	<input type="text" name="bfox-tool-localdb[verse_row]" id="bfox-tool-db-verse-row" value="<?php echo $local_db['verse_row'] ?>" /></p>
	*/
}

/*
 * Saving Reading tools
 */

function bfox_tool_save_post($post_id, $post) {
	if ('bfox_tool' == $_POST['post_type']) {
		// See: http://codex.wordpress.org/Function_Reference/add_meta_box

		// verify this came from the our screen and with proper authorization,
		// because save_post can be triggered at other times
//		if ( !wp_verify_nonce( $_POST['bfox_tool_edit_schedule_nonce'], 'bfox' )) {
//			return $post_id;
//		}

		// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
		// to do anything
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return $post_id;

		// Check permissions
		if ( 'page' == $_POST['post_type'] ) {
			if ( !current_user_can( 'edit_page', $post_id ) )
				return $post_id;
		} else {
			if ( !current_user_can( 'edit_post', $post_id ) )
				return $post_id;
		}

		bfox_tool_update_meta('url', $_POST['bfox-tool-url'], $post_id);
		bfox_tool_update_meta('local_db', $_POST['bfox-tool-localdb'], $post_id);
	}
}
add_action('save_post', 'bfox_tool_save_post', 10, 2);

/*
 * Reading Tool Meta Data functions
 */

function bfox_tool_meta($key, $post_id = 0) {
	if (empty($post_id)) $post_id = $GLOBALS['post']->ID;
	$value = get_post_meta($post_id, '_bfox_tool_' . $key, true);
	return $value;
}

function bfox_tool_update_meta($key, $value, $post_id = 0) {
	if (empty($post_id)) $post_id = $GLOBALS['post']->ID;
	return update_post_meta($post_id, '_bfox_tool_' . $key, $value);
}

/*
Theme Templates
*/

function bfox_tool_template_redirect($template) {
	if ('bfox_tool' == get_query_var('post_type')) {
		if (is_singular('bfox_tool')) {
			load_bfox_template('single-bfox_tool');
			exit;
		}
		if (is_archive()) {
			load_bfox_template('archive-bfox_tool');
			exit;
		}
	}
}
add_action('template_redirect', 'bfox_tool_template_redirect');

/*
Template Tags
*/

// DEPRECATED, use bfox_tool_source_url()
function bfox_tool_url_for_ref(BfoxRef $ref) {
	return bfox_tool_source_url($ref);
}

function bfox_tool_source_linker(BfoxRef $ref = null) {
	global $_bfox_tool_source_linker;
	if (is_null($_bfox_tool_source_linker)) {
		$_bfox_tool_source_linker = new BfoxBibleToolLink();
		if (is_null($ref)) $ref = bfox_active_ref();
	}

	if (!is_null($ref)) $_bfox_tool_source_linker->setRef($ref);
	return $_bfox_tool_source_linker;
}

function bfox_tool_source_url($post_id = 0, BfoxRef $ref = null) {
	if (empty($post_id)) $post_id = $GLOBALS['post']->ID;
	$template = bfox_tool_meta('url', $post_id);

	if (empty($template)) {
		if (is_null($ref)) $ref = bfox_active_ref();
		return add_query_arg('src', true, bfox_ref_url($ref->get_string(), $post_id));
	}

	$linker = bfox_tool_source_linker($ref);
	return $linker->urlForTemplate($template);
}

function is_bfox_tool_link() {
	$url = bfox_tool_meta('url');
	return !empty($url);
}

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

function bfox_tool_query($args = array()) {
	$args['post_type'] = 'bfox_tool';
	return new WP_Query($args);
}

function update_selected_bfox_tool() {
	if (is_singular('bfox_tool')) {
		global $post, $_selected_bfox_tool_post_id;
		$_selected_bfox_tool_post_id = $post->ID;
		$_COOKIE['selected_bfox_tool'] = $post->ID;
		setcookie('selected_bfox_tool', $_COOKIE['selected_bfox_tool'], /* 30 days from now: */ time() + 60 * 60 * 24 * 30, '/');
	}
}
add_action('wp', 'update_selected_bfox_tool');

function selected_bfox_tool_post_id() {
	global $_selected_bfox_tool_post_id;

	if (!$_selected_bfox_tool_post_id) {
		// First try to get the selected BfoxTool from the cookies
		$post_id = $_COOKIE['selected_bfox_tool'];

		// Make sure that the cookied post id is actually a BfoxTool
		if ($post_id) {
			$post = &get_post($post_id);
			if ('bfox_tool' != $post->post_type) $post_id = 0;
		}

		// If we didn't get a BfoxTool from the cookies, just get the first one from a query
		if (!$post_id) {
			$tools = bfox_tool_query();
			if ($tools->have_posts()) {
				$post = $tools->next_post();
				$post_id = $post->ID;
			}
		}

		$_selected_bfox_tool_post_id = $post_id;
	}

	return $_selected_bfox_tool_post_id;
}

function the_selected_bfox_tool_post() {
	global $post;
	$post = get_post(selected_bfox_tool_post_id());
	setup_postdata($post);
}

function bfox_tool_query_vars($query_vars) {
	$query_vars []= 'ref';
	return $query_vars;
}
add_filter('query_vars', 'bfox_tool_query_vars');

function bfox_tool_parse_query($wp_query) {
	$post_type = $wp_query->query_vars['post_type'];
	if ('bfox_tool' == $post_type) {
		if (!empty($wp_query->query_vars['ref']))
			bfox_active_ref(new BfoxRef($wp_query->query_vars['ref']));
	}
}
add_action('parse_query', 'bfox_tool_parse_query');


?>