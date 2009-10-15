<?php
/*************************************************************************

	Plugin Name: Biblefox-Blog
	Plugin URI: http://tools.biblefox.com/
	Description: Allows your blog to become a bible commentary, and adds the entire bible text to your blog, so you can read, search, and study the bible all from your blog.
	Version: 0.4.9
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

if (!defined(BFOX_BLOG_DIR)) define(BFOX_BLOG_DIR, dirname(__FILE__));

define(BFOX_DATA_DIR, BFOX_BLOG_DIR . '/data');
define(BFOX_REFS_DIR, BFOX_BLOG_DIR . '/biblerefs');
define(BFOX_TRANS_DIR, BFOX_BLOG_DIR . '/translations');

define(BFOX_BLOG_URL, WP_PLUGIN_URL . '/biblefox-blog');

define(BFOX_BLOG_TABLE_PREFIX, $GLOBALS['wpdb']->base_prefix . 'bfox_');

require_once BFOX_REFS_DIR . '/refs.php';

require_once BFOX_BLOG_DIR . '/utility.php';
require_once BFOX_BLOG_DIR . '/query.php';

include_once BFOX_TRANS_DIR . '/translations.php';

require_once BFOX_BLOG_DIR . '/posts.php';
require_once BFOX_BLOG_DIR . '/bfox-query.php';
require_once BFOX_BLOG_DIR . '/bibletext.php';
//require_once("bfox-settings.php");

// TODO3: get blogplans.php working and included
//require_once BFOX_PLANS_DIR . '/blogplans.php';

class Biblefox {

	const ref_url_blog = 'blog';
	const ref_url_bible = 'bible';

	const option_version = 'bfox_version';

	private static $default_ref_url = '';

/*	public static function init() {
		global $current_site;

		$old_ver = get_site_option(self::option_version);
		if (BFOX_VERSION != $old_ver) {
			@include_once BFOX_DIR . '/bible/upgrade.php';
			@include_once BFOX_DIR . '/blog/upgrade.php';
			update_site_option(self::option_version, BFOX_VERSION);
		}

		BfoxQuery::set_url((is_ssl() ? 'https://' : 'http://') . $current_site->domain . $current_site->path, !(TRUE === BFOX_NO_PRETTY_URLS));

		// Register all the global scripts and styles
		BfoxUtility::register_style('bfox_scripture', 'blog/scripture.css');
	}*/

	public static function set_default_ref_url($ref_url) {
		self::$default_ref_url = $ref_url;
	}

	public static function ref_url($ref_str, $ref_url = '') {
		if (empty($ref_url)) $ref_url = self::$default_ref_url;

		if (self::ref_url_bible == $ref_url) return BfoxQuery::ref_url($ref_str);
		else return BfoxBlog::ref_url($ref_str);
	}

	public static function ref_link($ref_str, $text = '', $ref_url = '', $attrs = '') {
		if (empty($text)) $text = $ref_str;

		if (!empty($attrs)) $attrs = ' ' . $attrs;
		return "<a href='" . self::ref_url($ref_str, $ref_url) . "'$attrs>$text</a>";
	}

}

//add_action('init', 'Biblefox::init');
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
		BfoxUtility::register_style('bfox_scripture', 'blog/scripture.css');

		bfox_query_init();
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
		BfoxUtility::enqueue_style('bfox_admin', 'blog/admin.css', array('bfox_scripture'));
		BfoxUtility::enqueue_script('bfox_admin', 'blog/admin.js', array('sack'));
	}

	/*public static function bible_settings() {
	}

	public static function bible_setting_translations() {
		$installed = BfoxTrans::get_installed();
		$enabled = BfoxTrans::get_enabled();

		foreach ($installed as $trans) {
			if (isset($enabled[$trans->short_name])) $checked = ' checked="checked"';
			else $checked = '';
			$id = "bfox_trans_$trans->id";
			echo "<input type='checkbox'$checked name='bfox_enable_translations' id='$id' value='$trans->id' /> <label for='$id'>$trans->long_name</label><br/>";
		}
	}*/

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

	public static function admin_url($page) {
		return self::$home_url . '/wp-admin/' . $page;
	}

	/**
	 * Returns a link to an admin page
	 *
	 * @param string $page The admin page (and any parameters)
	 * @param string $text The text to use in the link
	 * @return string
	 */
	public static function admin_link($page, $text = '') {
		if (empty($text)) $text = $page;

		return "<a href='" . self::admin_url($page) . "'>$text</a>";
	}

	public static function ref_url($ref_str) {
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

	public static function ref_link($ref_str, $text = '', $attrs = '') {
		if (empty($text)) $text = $ref_str;

		return "<a href='" . self::ref_url($ref_str) . "' $attrs>$text</a>";
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
	 * Return verse content for the given bible refs with minimum formatting
	 *
	 * @param BfoxRefs $refs
	 * @param BfoxTrans $trans
	 * @return string
	 */
	public static function get_verse_content(BfoxRefs $refs) {
		// Get the verse data from the bible translation
		$translation = new BfoxTrans();
		$formatter = new BfoxVerseFormatter();
		return $translation->get_verses($refs->sql_where(), $formatter);
	}

	public static function get_verse_content_foot(BfoxRefs $refs, $delete_footnotes = FALSE) {
		// TODO3: This is pretty hacky, if the shortcode regex ever changes, this regex has to change as well!

		// Get the verse content, and filter it using the <footnote> tags as if they were [footnote] shortcodes
		// The regex being used here should mirror the regex returned by get_shortcode_regex() and is being used similarly to do_shortcode(),
		//  the only difference being that we only need to look for <footnote> shortcodes (and using chevrons instead of brackets)
		if ($delete_footnotes) return preg_replace('/<(footnote)\b(.*?)(?:(\/))?>(?:(.+?)<\/footnote>)?/s', '', BfoxBlog::get_verse_content($refs));
		else $content = preg_replace_callback('/(.?)<(footnote)\b(.*?)(?:(\/))?>(?:(.+?)<\/\2>)?(.?)/s', 'do_shortcode_tag', BfoxBlog::get_verse_content($refs));
		return array($content, shortfoot_get_list());
	}

	/**
	 * Return verse content for the given bible refs formatted for email output
	 *
	 * @param BfoxRefs $refs
	 * @param BfoxTrans $trans
	 * @return string
	 */
	public static function get_verse_content_email(BfoxRefs $refs, BfoxTrans $trans = NULL) {
		// Pre formatting is for when we can't use CSS (ie. in an email)
		// We just replace the tags which would have been formatted by css with tags that don't need formatting
		// We also need to run the shortcode function to correctly output footnotes

		$mods = array(
			'<span class="bible_poetry_indent_2"></span>' => '<span style="margin-left: 20px"></span>',
			'<span class="bible_poetry_indent_1"></span>' => '',
			'<span class="bible_end_poetry"></span>' => "<br/>\n",
			'<span class="bible_end_p"></span>' => "<br/><br/>\n",
			'</footnote>' => '[/foot]',
			'<footnote>' => '[foot]'
		);

		return do_shortcode(str_replace(array_keys($mods), array_values($mods), self::get_verse_content($refs, $trans)));
	}
}

add_action('init', array('BfoxBlog', 'init'));

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

?>