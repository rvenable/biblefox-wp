<?php

define(BFOX_BLOG_DIR, dirname(__FILE__));

define(BFOX_MANAGE_PLAN_SUBPAGE, 'bfox-manage-plan');
define(BFOX_PROGRESS_SUBPAGE, 'bfox-progress');

// User Levels
define('BFOX_USER_LEVEL_MANAGE_PLANS', 7);
define('BFOX_USER_LEVEL_MANAGE_USERS', 'edit_users');

require_once BFOX_BLOG_DIR . '/posts.php';
require_once('bfox-blog-specific.php');
require_once('plan.php');
require_once('history.php');
require_once('bfox-query.php');
require_once('special.php');
require_once('bfox-widgets.php');
require_once('bibletext.php');
require_once("bfox-settings.php");

class BfoxBlog
{
	const var_bible_ref = 'bfox_bible_ref';
	const var_special = 'bfox_special';
	const var_action = 'bfox_action';
	const var_plan_id = 'bfox_plan_id';
	const var_reading_id = 'bfox_reading_id';
	const var_join_bible_refs = 'join_bible_refs';

	/**
	 * Stores the home URL for this blog
	 *
	 * @var string
	 */
	private static $home_url;

	public static function init()
	{
		add_action('admin_menu', array('BfoxBlog', 'add_menu'));

		self::$home_url = get_option('home');

		bfox_query_init();
		bfox_widgets_init();
	}

	public static function add_menu()
	{
		add_submenu_page('profile.php', 'My Status', 'My Status', 0, BFOX_PROGRESS_SUBPAGE, 'bfox_progress');

		// Add the reading plan page to the Post menu along with the corresponding load action
		add_submenu_page('post-new.php', 'Reading Plans', 'Reading Plans', BFOX_USER_LEVEL_MANAGE_PLANS, BFOX_MANAGE_PLAN_SUBPAGE, 'bfox_manage_reading_plans');
		add_action('load-' . get_plugin_page_hookname(BFOX_MANAGE_PLAN_SUBPAGE, 'post-new.php'), 'bfox_manage_reading_plans_load');

		add_meta_box('bible-tag-div', __('Scripture Tags'), 'bfox_post_scripture_tag_meta_box', 'post', 'normal', 'core');
		add_meta_box('bible-quick-view-div', __('Scripture Quick View'), 'bfox_post_scripture_quick_view_meta_box', 'post', 'normal', 'core');
		add_action('save_post', 'bfox_save_post');

		add_action('admin_head', 'BfoxBlog::admin_head');
	}

	public static function admin_head()
	{
		// use JavaScript SACK library for Ajax
		wp_print_scripts(array('sack'));

		$url = get_option('siteurl');
		?>
		<link rel="stylesheet" href="<?php echo $url ?>/wp-content/mu-plugins/biblefox/scripture.css" type="text/css"/>
		<link rel="stylesheet" href="<?php echo $url ?>/wp-content/mu-plugins/biblefox/blog/admin.css" type="text/css"/>
		<script type="text/javascript" src="<?php echo $url ?>/wp-content/mu-plugins/biblefox/blog/admin.js"></script>
		<?php
	}

	public static function add_scripture()
	{
		?>
		<link rel="stylesheet" href="<?php echo get_option('siteurl') ?>/wp-content/mu-plugins/biblefox/scripture.css" type="text/css"/>
		<?php
	}

	public static function admin_url($page)
	{
		return self::$home_url . '/wp-admin/' . $page;
	}

	/**
	 * Returns a link to an admin page
	 *
	 * @param string $page The admin page (and any parameters)
	 * @param string $text The text to use in the link
	 * @return string
	 */
	public static function admin_link($page, $text = '')
	{
		if (empty($text)) $text = $page;

		return "<a href='" . self::admin_url($page) . "'>$text</a>";
	}

	public static function ref_url($ref_str)
	{
		return self::$home_url . '/?' . self::var_bible_ref . '=' . urlencode($ref_str);
	}

	public static function ref_link($ref_str, $text = '', $attrs = '')
	{
		if (empty($text)) $text = $ref_str;

		return "<a href='" . self::ref_url($ref_str) . "' $attrs>$text</a>";
	}

	public static function ref_link_ajax($ref_str, $text = '', $attrs = '')
	{
		if (empty($text)) $text = $ref_str;

		return "<a href='#bible_ref' onclick='bible_text_request(\"$ref_str\")' $attrs>$text</a>";
	}

	public static function ref_write_link($ref_str, $text = '')
	{
		if (empty($text)) $text = $ref_str;
		$href = self::$home_url . '/wp-admin/post-new.php?' . self::var_bible_ref . '=' . urlencode($ref_str);

		return "<a href='$href'>$text</a>";
	}

	public static function reading_plan_url($plan_id, $action = NULL, $reading_id = NULL)
	{
		$url = self::$home_url . '/?' . BfoxBlog::var_plan_id . '=' . $plan_id;
		if (!empty($action)) $url .= '&' . BfoxBlog::var_action . '=' . $action;
		if (!empty($reading_id)) $url .= '&' . BfoxBlog::var_reading_id . '=' . ($reading_id + 1);
		return $url;
	}

	/**
	 * Return verse content for the given bible refs with minimum formatting
	 *
	 * @param BibleRefs $refs
	 * @param Translation $trans
	 * @return string
	 */
	public static function get_verse_content(BibleRefs $refs, Translation $trans = NULL)
	{
		if (is_null($trans)) $trans = $GLOBALS['bfox_trans'];

		$content = '';

		$verses = $trans->get_verses($refs->sql_where());

		foreach ($verses as $verse)
		{
			if ($verse->verse_id != 0) $content .= '<b>' . $verse->verse_id . '</b> ';
			$content .= $verse->verse;
		}

		// Fix the footnotes
		// TODO3: this function does more than just footnotes
		$content = bfox_special_syntax($content);

		return $content;
	}

	/**
	 * Return verse content for the given bible refs formatted for email output
	 *
	 * @param BibleRefs $refs
	 * @param Translation $trans
	 * @return unknown
	 */
	public static function get_verse_content_email(BibleRefs $refs, Translation $trans = NULL)
	{
		// Pre formatting is for when we can't use CSS (ie. in an email)
		// We just replace the tags which would have been formatted by css with tags that don't need formatting
		$content =
			str_replace('<span class="bible_poetry_indent_2"></span>', '<span style="margin-left: 20px"></span>',
				str_replace('<span class="bible_poetry_indent_1"></span>', '',
					str_replace('<span class="bible_end_poetry"></span>', "<br/>\n",
						str_replace('<span class="bible_end_p"></span>', "<br/><br/>\n",
							self::get_verse_content($refs, $trans)))));

		return $content;
	}
}

add_action('init', array('BfoxBlog', 'init'));

	function bfox_save_post($post_id = 0)
	{
		$refStr = $_POST[BfoxBlog::var_bible_ref];

		$refs = RefManager::get_from_str($refStr);
		if ((0 != $post_id) && $refs->is_valid())
		{
			// TODO3: stop saving to old posts table
			require_once("bfox-write.php");
			bfox_set_post_bible_refs($post_id, $refs);

			// Save the refs to the post refs table
			BfoxPosts::set_post_refs($post_id, $refs, FALSE);
		}
	}

	function bfox_post_scripture_tag_meta_box($post)
	{
		// TODO3: Get rid of this include
		require_once("bfox-write.php");
		bfox_form_edit_scripture_tags();
	}

	function bfox_post_scripture_quick_view_meta_box($post)
	{
		// TODO3: Get rid of this include
		require_once("bfox-write.php");
		bfox_form_scripture_quick_view();
	}

	function bfox_progress()
	{
//		bfox_progress_page();
		if (current_user_can(BFOX_USER_LEVEL_MANAGE_USERS)) bfox_join_request_menu();
		include('my-blogs.php');
	}

?>