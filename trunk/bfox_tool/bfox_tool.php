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

function update_selected_bfox_tool() {
	if (is_singular('bfox_tool')) {
		global $post, $_selected_bfox_tool_post_id;
		$_selected_bfox_tool_post_id = $post->ID;
		$_COOKIE['selected_bfox_tool'] = $post->ID;
		setcookie('selected_bfox_tool', $_COOKIE['selected_bfox_tool'], /* 30 days from now: */ time() + 60 * 60 * 24 * 30, '/');
	}
}
add_action('wp', 'update_selected_bfox_tool');

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