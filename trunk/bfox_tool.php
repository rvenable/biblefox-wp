<?php

require_once BFOX_REF_DIR . '/bfox_bible_tool_link.php';
require_once BFOX_REF_DIR . '/bfox_bible_tool_api.php';

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

	load_bfox_template('config-bfox_tool');
}
add_action('init', 'bfox_tools_create_post_type');

function bfox_tool_content_ajax() {
	if (!wp_verify_nonce($_REQUEST['bfox-ajax-nonce'], 'bfox-ajax')) die;

	set_bfox_ref(new BfoxRef($_REQUEST['ref']));

	ob_start();
	load_bfox_template('content-bfox_tool');
	$html = ob_get_clean();

	$response = json_encode(array(
		'html' => $html,
		'nonce' => wp_create_nonce('bfox-ajax'),
	));

	header('Content-Type: application/json');
	echo $response;

	exit;
}
add_action('wp_ajax_nopriv_bfox-tool-content', 'bfox_tool_content_ajax');
add_action('wp_ajax_bfox-tool-content', 'bfox_tool_content_ajax');

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
 * Classes
 */

class BfoxBibleToolController {
	var $tools;
	private $_activeShortName = '';

	private static $_sharedInstance = NULL;

	/**
	 * @return BfoxBibleToolController
	 */
	static function sharedInstance() {
		if (is_null(self::$_sharedInstance)) {
			self::$_sharedInstance = new BfoxBibleToolController();
		}
		return self::$_sharedInstance;
	}

	function addTool(BfoxBibleTool $tool) {
		$this->tools[$tool->shortName] = $tool;
		if (empty($this->_activeShortName)) $this->_activeShortName = $tool->shortName;
	}

	/**
	 * @param string $shortName
	 * @return BfoxBibleTool
	 */
	function toolForShortName($shortName = '') {
		if (empty($shortName)) $shortName = $this->_activeShortName;
		if (isset($this->tools[$shortName])) return $this->tools[$shortName];
		return null;
	}

	function setActiveTool($shortName) {
		$tool = $this->toolForShortName($shortName);
		if (!is_null($tool)) $this->_activeShortName = $tool->shortName;
	}

	function activeTool() {
		return $this->toolForShortName($this->_activeShortName);
	}

	function select($options = array()) {
		extract($options);

		$activeTool = $this->activeTool();
		foreach ($this->tools as $tool) {
			if ($tool == $activeTool) $selected = " selected='selected'";
			else $selected = '';

			$content .= "<option name='$tool->shortName' value='$tool->shortName'$selected>$tool->longName</option>";
		}

		return "<select $attrs>" . $content . '</select>';
	}
}

class BfoxBibleTool {
	/**
	 * @var BfoxBibleToolApi
	 */
	var $api;
	var $shortName;
	var $longName;

	function __construct(BfoxBibleToolApi $api, $shortName = '', $longName = '') {
		$this->api = $api;
		if (empty($shortName)) $shortName = $api->bible;
		if (empty($longName)) $longName = $shortName;
		$this->shortName = $shortName;
		$this->longName = $longName;
	}

	function echoContentForRef(BfoxRef $ref) {
		$this->api->echoContentForRef($ref);
	}
}

class BfoxLocalWPBibleToolApi extends BfoxLocalBibleToolApi {
	function rowsForRef(BfoxRef $ref) {
		global $wpdb;

		$tableName = $wpdb->escape($this->tableName);

		$indexCol = $wpdb->escape($this->indexCol);
		$indexCol2 = $wpdb->escape($this->indexCol2);

		if (empty($indexCol2)) $refWhere = $ref->sql_where($indexCol);
		else $refWhere = $ref->sql_where2($indexCol, $indexCol2);

		$sql = $wpdb->prepare("SELECT * FROM $tableName WHERE $refWhere");
		$rows = $wpdb->get_results($sql);

		return $rows;
	}
}

class BfoxWPBibleToolIframeApi extends BfoxBibleToolIframeApi {
	function echoContentForUrl($url) {
?>
	<div class="bfox-tool-iframe">
		<?php parent::echoContentForUrl($url); ?>
	</div>
<?php
	}
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
	$query_vars []= 'tool';

	return $query_vars;
}
add_filter('query_vars', 'bfox_tool_query_vars');

function bfox_tool_parse_request($wp) {
	$post_type = $wp->query_vars['post_type'];
	if ('bfox_tool' == $post_type) {
		// Bible Tools need to have a Bible Reference
		$ref = bfox_ref();
		if (!$ref->is_valid()) {
			// If no Bible reference is passed in try to use the last viewed ref
			if (empty($wp->query_vars['ref'])) {
				$ref = new BfoxRef(bfox_tool_last_viewed_ref_str());
			}
			else {
				$ref = new BfoxRef($wp->query_vars['ref']);
			}

			// If we still don't have a valid ref, use Genesis 1
			if (!$ref->is_valid()) {
				$ref = new BfoxRef('Genesis 1');
			}

			// Set the active Bible reference
			set_bfox_ref($ref);
		}

		// Keep the ref_str in the query_vars
		$wp->query_vars['ref'] = $ref->get_string();

		// Save the ref_str as the last viewed ref str
		bfox_tool_set_last_viewed_ref_str($wp->query_vars['ref']);

		$bfoxTools = BfoxBibleToolController::sharedInstance();
		$toolName = $wp->query_vars['tool'];
		if (empty($toolName)) {
			$toolName = bfox_last_viewed_tool();
			$bfoxTools->setActiveTool($toolName);
		}
		else {
			$tool = $bfoxTools->toolForShortName($toolName);
			if (!is_null($tool)) {
				$oldToolName = bfox_last_viewed_tool();
				if ($oldToolName != $toolName) {
					set_bfox_last_viewed_tool($toolName);
				}
				$bfoxTools->setActiveTool($toolName);
			}
		}

	}
}
add_action('parse_request', 'bfox_tool_parse_request');

?>