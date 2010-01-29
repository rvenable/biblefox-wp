<?php
/*************************************************************************

	Plugin Name: Biblefox for WordPress
	Plugin URI: http://tools.biblefox.com/
	Description: Allows your blog to become a bible commentary, and adds the entire bible text to your blog, so you can read, search, and study the bible all from your blog.
	Version: 0.6
	Author: Biblefox
	Author URI: http://biblefox.com

*************************************************************************/

// TODO2: Add the license file, and add copyright notice to all source files (see http://www.fsf.org/licensing/licenses/gpl-howto.html )
/*************************************************************************

	Copyright 2009 biblefox.com

	This file is part of Biblefox.

	Biblefox is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Biblefox is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with Biblefox.  If not, see <http://www.gnu.org/licenses/>.

*************************************************************************/

define(BFOX_VERSION, '0.6');
define(BFOX_DIR, dirname(__FILE__));
define(BFOX_REFS_DIR, BFOX_DIR . '/biblerefs');
define(BFOX_URL, WP_PLUGIN_URL . '/biblefox');
define(BFOX_TABLE_PREFIX, $GLOBALS['wpdb']->base_prefix . 'bfox_');

require_once BFOX_REFS_DIR . '/refs.php';
require_once BFOX_DIR . '/utility.php';
require_once BFOX_DIR . '/refs-table.php';
require_once BFOX_DIR . '/posts.php';
require_once BFOX_DIR . '/bfox-query.php';
require_once BFOX_DIR . '/bibletext.php';
require_once BFOX_DIR . '/shortfoot.php';
require_once BFOX_DIR . '/iframe.php';

//require_once("bfox-settings.php");

// TODO3: get blogplans.php working and included
//require_once BFOX_PLANS_DIR . '/blogplans.php';

class Biblefox {
	public $post_refs;

	/**
	 * @var BfoxRefsTable
	 */
	public $activity_refs;
}

global $biblefox;
$biblefox = new Biblefox();

class BfoxBlog {

	const var_bible_ref = 'bfox_ref';

	const hidden_ref_tag = 'bfox_hidden_ref';
	const col_bible_refs = 'bfox_col_ref';
	const post_type_bible = 'bfox_bible';

	const page_bible_settings = 'bfox-bible-settings';

	const user_level_bible_settings = 7;

	const settings_section_bible = 'bfox_bible_settings';

	/**
	 * Stores the home URL for this blog
	 *
	 * @var string
	 */
	private static $home_url;

	public static function init() {
		add_action('admin_menu', 'BfoxBlog::add_menu');
		add_action('admin_init', 'BfoxBlog::admin_init');

		self::$home_url = get_option('home');

		add_filter('get_post_tag', 'BfoxBlog::get_post_tag', 10, 2);

		// Styles
		wp_register_script('bfox-tooltip', BFOX_URL . '/includes/js/jquery-qtip/jquery.qtip-1.0.0-rc3.min.js', array('jquery'), BFOX_VERSION);
		wp_enqueue_style('bfox-scripture', BFOX_URL . '/includes/css/scripture.css', array(), BFOX_VERSION);
		wp_enqueue_style('bfox-blog', BFOX_URL . '/includes/css/biblefox-blog.css', array(), BFOX_VERSION);

		// Scripts
		wp_register_script('bfox-tooltip', BFOX_URL . '/includes/js/jquery-qtip/jquery.qtip-1.0.0-rc3.min.js', array('jquery'), BFOX_VERSION);
		wp_enqueue_script('bfox-blog', BFOX_URL . '/includes/js/biblefox-blog.js', array('jquery', 'bfox-tooltip'), BFOX_VERSION);

		bfox_query_init();
	}

	/**
	 * Checks to see if we are requesting tooltip content (ie. by an AJAX call), returns the content, and exits
	 */
	public static function check_for_tooltip() {
		if (isset($_REQUEST['bfox-tooltip-ref'])) {
			global $tooltip_refs;
			$tooltip_refs = new BfoxRefs($_REQUEST['bfox-tooltip-ref']);
			require BFOX_DIR . '/tooltip.php';
			exit;
		}
	}

	public static function add_menu() {

		// Bible Settings
		//add_settings_section(self::settings_section_bible, 'Bible Settings', 'BfoxBlog::bible_settings', 'reading');
		//add_settings_field('bfox_bible_translations', 'Enable Bible Translations', 'BfoxBlog::bible_setting_translations', 'reading', self::settings_section_bible);

		add_meta_box('bible-quick-view-div', __('Biblefox Bible'), 'BfoxBlog::quick_view_meta_box', 'post', 'normal', 'core');
		add_action('save_post', 'BfoxBlog::save_post', 10, 2);

		// Flush the hidden ref tags on the post-new screen
		add_action('admin_head-post-new.php', 'BfoxBlog::flush_tag_script');
	}

	public static function admin_init() {
		wp_enqueue_style('bfox-admin', BFOX_URL . '/includes/css/admin.css', array(), BFOX_VERSION);
		wp_enqueue_script('bfox-admin', BFOX_URL . '/includes/js/admin.js', array('sack'), BFOX_VERSION);
	}

	public static function save_post($post_id = 0, $post) {
		BfoxPosts::update_post($post, TRUE);
	}

	/**
	 * Output a script to flush the hidden bible ref to the tags
	 */
	public static function flush_tag_script() {
		if (!empty($_REQUEST[BfoxBlog::var_bible_ref])) {
			$hidden_refs = new BfoxRefs($_REQUEST[BfoxBlog::var_bible_ref]);
			if ($hidden_refs->is_valid()) {
				?>
				<script type='text/javascript'>
				//<![CDATA[
				jQuery(document).ready( function() {
					jQuery('#new-tag-post_tag').removeClass('form-input-tip').val('<?php echo $hidden_refs->get_string() ?>');
					jQuery('#bfox_hidden_refs').val('');
					tag_flush_to_text('post_tag');
					jQuery('#new-tag-post_tag').addClass('form-input-tip');
				});
				//]]>
				</script>
				<?php
			}
		}
	}

	/**
	 * Creates the form displaying the scripture quick view
	 *
	 */
	public static function quick_view_meta_box() {
		global $post_ID;
		$refs = BfoxPosts::get_post_refs($post_ID);

		if (!empty($_REQUEST[BfoxBlog::var_bible_ref])) {
			$hidden_refs = new BfoxRefs($_REQUEST[BfoxBlog::var_bible_ref]);
			if ($hidden_refs->is_valid()) {
				echo "<input id='bfox_hidden_refs' type='hidden' name='" . BfoxBlog::hidden_ref_tag . "' value='" . $hidden_refs->get_string() . "'/>";
				$refs->add_refs($hidden_refs);
			}
		}
		$is_valid = $refs->is_valid();
		if ($is_valid) $ref_str = $refs->get_string();

		// Create the form
		?>
		<?php if (empty($ref_str)): ?>
			<p>This post currently has no bible references.</p>
		<?php else: ?>
			<p>This post is currently referencing: <?php echo BfoxBlog::ref_link_ajax($ref_str) ?></p>
		<?php endif ?>
			<p>Add more bible references by typing them into the post, or adding them to the post tags.</p>
			<div class="hide-if-no-js">
				<h4>Quick Scripture Viewer</h4>
				<input type="text" name="new-bible-ref" id="new-bible-ref" size="16" value="" />
				<input type="button" class="button" id="view-bible-ref" value="View Scripture" tabindex="3" />
				<span class="howto"><?php _e('Type a bible reference (ie. "gen 1")'); ?></span>
				<br/>
			</div>

			<h4 id="bible-text-progress"><span id='bible_progress'><?php if ($is_valid) echo 'Viewing'?></span> <span id='bible_view_ref'><?php if ($is_valid) echo $refs->get_string(BibleMeta::name_short) ?></span></h4>
			<input type="hidden" name="bible-request-url" id="bible-request-url" value="<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" />
			<div id="bible-text"><?php if ($is_valid) echo bfox_get_ref_content_quick($refs) ?></div>
		<?php
	}

	/**
	 * Wraps some bible ref tooltip content around a bible link
	 *
	 * @param string $link
	 * @param string $ref_str
	 * @return string
	 */
	public static function link_add_ref_tooltip($link, $ref_str) {
		return '<span class="bible-tooltip">' . $link . '<a class="bible-tooltip-url" href="' . get_option('home') . '/?bfox-tooltip-ref=' . $ref_str . '"></a></span>';
	}

	/**
	 * Fixes a bible ref link options array so that it has a ref_str if it doesn't already
	 *
	 * @param $options
	 */
	public static function fix_ref_link_options(&$options) {
		// If there is no ref_str, try to get it from $refs->get_string($name)
		if (empty($options['ref_str']) && isset($options['refs']) && is_a($options['refs'], BfoxRefs) && $options['refs']->is_valid())
			$options['ref_str'] = $options['refs']->get_string($options['name']);
	}

	/**
	 * Creates a link from an array specifying bible ref link options
	 *
	 * Used to create links by BfoxBlog::ref_bible_link() and BfoxBlog::ref_archive_link()
	 *
	 * @param array $options
	 * @return string
	 */
	public static function ref_link_from_options($options = array()) {
		extract($options);
		$link = '';

		// Only create a link if we actually have a ref_str
		if (!empty($ref_str)) {
			// If there is no text, use the ref_str
			if (empty($text)) $text = $ref_str;

			// If there is no href, get it from the ref_bible_url function
			if (!isset($attrs['href'])) self::ref_bible_url($ref_str);

			// Add the bible-ref class
			if (!empty($attrs['class'])) $attrs['class'] .= ' ';
			$attrs['class'] .= 'bible-ref';

			$attr_str = '';
			foreach ($attrs as $attr => $value) $attr_str .= " $attr='$value'";

			$link = "<a$attr_str>$text</a>";

			if (!isset($disable_tooltip)) $link = self::link_add_ref_tooltip($link, $ref_str);
		}

		return $link;
	}

	/**
	 * Returns a URL to the external Bible reader of choice for a given Bible Ref
	 *
	 * Should be used whenever we want to link to the Bible page, as opposed to the Bible archive
	 *
	 * @param string $ref_str
	 * @return string
	 */
	public static function ref_bible_url($ref_str) {
		return str_replace('%ref%', urlencode($ref_str), apply_filters('bfox_blog_bible_url_template', 'http://biblefox.com/bible/%ref%'));
	}

	/**
	 * Returns a link to the external Bible reader of choice for a given Bible Ref
	 *
	 * Should be used whenever we want to link to the Bible page, as opposed to the Bible archive
	 *
	 * @param array $options
	 * @return string
	 */
	public static function ref_bible_link($options) {
		BfoxBlog::fix_ref_link_options($options);

		// If there is no href, get it from the bp_bible_ref_url() function
		if (!isset($options['attrs']['href'])) $options['attrs']['href'] = self::ref_bible_url($options['ref_str']);

		return BfoxBlog::ref_link_from_options($options);
	}

	/**
	 * Returns a url for the tag archive page using a Bible Reference as the tag filter
	 *
	 * Should be used whenever we want to link to the Bible archive, as opposed to the Bible reader
	 *
	 * @param string $ref_str
	 * @return string
	 */
	public static function ref_archive_url($ref_str) {
		$ref_str = urlencode($ref_str);

		// NOTE: This function imitates the WP get_tag_link() function, but instead of getting a tag slug, we use $ref_str
		global $wp_rewrite;
		$taglink = $wp_rewrite->get_tag_permastruct();

		if (empty($taglink)) $taglink = get_option('home') . '/?tag=' . $ref_str;
		else {
			$taglink = str_replace('%tag%', $ref_str, $taglink);
			$taglink = self::$home_url . user_trailingslashit($taglink, 'category');
		}

		return $taglink;
	}

	/**
	 * Returns a link for the tag archive page using a Bible Reference as the tag filter
	 *
	 * Should be used whenever we want to link to the Bible archive, as opposed to the Bible reader
	 *
	 * @param array $options
	 * @return string
	 */
	public static function ref_archive_link($options) {
		BfoxBlog::fix_ref_link_options($options);

		// If there is no href, get it from the self::ref_archive_url() function
		if (!isset($options['attrs']['href'])) $options['attrs']['href'] = self::ref_archive_url($options['ref_str']);

		return BfoxBlog::ref_link_from_options($options);
	}

	public static function ref_link_ajax($ref_str, $text = '', $attrs = '') {
		if (empty($text)) $text = $ref_str;

		return "<a href='#bible_ref' onclick='bible_text_request(\"$ref_str\")' $attrs>$text</a>";
	}

	public static function ref_write_url($ref_str, $home_url = '') {
		if (empty($home_url)) $home_url = self::$home_url;

		return rtrim($home_url, '/') . '/wp-admin/post-new.php?' . self::var_bible_ref . '=' . urlencode($ref_str);
	}

	public static function ref_write_link($ref_str, $text = '', $home_url = '') {
		if (empty($text)) $text = $ref_str;

		return "<a href='" . self::ref_write_url($ref_str, $home_url) . "'>$text</a>";
	}

	public static function ref_edit_posts_link($ref_str, $text = '') {
		if (empty($text)) $text = $ref_str;
		$href = self::$home_url . '/wp-admin/edit.php?tag=' . urlencode($ref_str);

		return "<a href='$href'>$text</a>";
	}

	/**
	 * Filters tags for bible references and changes their slugs to be bible reference friendly
	 *
	 * @param $term
	 * @return object $term
	 */
	public static function get_post_tag($term) {
		if ($refs = BfoxRefParser::no_leftovers($term->name)) $term->slug = urlencode($refs->get_string());
		return $term;
	}

	/**
	 * Returns a WP_Query object with the posts that contain the given BfoxRefs
	 *
	 * @param BfoxRefs $refs
	 * @return WP_Query
	 */
	public static function query_for_refs(BfoxRefs $refs) {
		BfoxBlogQueryData::set_post_ids(BfoxPosts::get_post_ids($refs));
		return new WP_Query(1);
	}

	/**
	 * Returns a BfoxRefs for the given tag string
	 *
	 * @param string $tag
	 * @return BfoxRefs
	 */
	public static function tag_to_refs($tag) {
		return new BfoxRefs($tag);
	}
}

add_action('init', 'BfoxBlog::init');
add_action('init', 'BfoxBlog::check_for_tooltip', 1000);

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
		if ('author' == $key) $new_columns[BfoxBlog::col_bible_refs] = __('Bible Verses');
	}
	return $new_columns;
}
add_filter('manage_posts_columns', 'bfox_manage_posts_columns');
//add_filter('manage_pages_columns', 'bfox_manage_posts_columns');

/**
 * Action function for displaying bible reference information in the edit posts list
 *
 * @param string $column_name
 * @param integer $post_id
 * @return none
 */
function bfox_manage_posts_custom_column($column_name, $post_id) {
	if (BfoxBlog::col_bible_refs == $column_name) {
		global $post;
		if (isset($post->bfox_bible_refs)) echo BfoxBlog::ref_edit_posts_link($post->bfox_bible_refs->get_string(BibleMeta::name_short));
	}

}
add_action('manage_posts_custom_column', 'bfox_manage_posts_custom_column', 10, 2);
//add_action('manage_pages_custom_column', 'bfox_manage_posts_custom_column', 10, 2);

/**
 * Loads the BuddyPress related features
 */
function bfox_bp_load() {
	require_once BFOX_DIR . '/biblefox-bp/biblefox-bp.php';
}
add_action('bp_init', 'bfox_bp_load');

?>