<?php

define(BFOX_BLOG_DIR, dirname(__FILE__));

define(BFOX_MANAGE_PLAN_SUBPAGE, 'bfox-manage-plan');
define(BFOX_PROGRESS_SUBPAGE, 'bfox-progress');

// User Levels
define('BFOX_USER_LEVEL_MANAGE_PLANS', 7);
define('BFOX_USER_LEVEL_MANAGE_USERS', 'edit_users');

require_once BFOX_BLOG_DIR . '/posts.php';
require_once BFOX_BLOG_DIR . '/bfox-plan.php';
require_once('bfox-blog-specific.php');
require_once('plans.php');
require_once('history.php');
require_once('bfox-query.php');
require_once('bfox-widgets.php');
require_once('bibletext.php');
require_once("bfox-settings.php");

require_once BFOX_PLANS_DIR . '/plans.php';

class BfoxBlog
{
	const var_bible_ref = 'bfox_bible_ref';
	const var_plan_id = 'bfox_plan_id';
	const var_reading_id = 'bfox_reading_id';

	const option_reading_plans = 'bfox_reading_plans';

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

		//add_meta_box('bible-tag-div', __('Scripture Tags'), 'bfox_post_scripture_tag_meta_box', 'post', 'normal', 'core');
		add_meta_box('bible-quick-view-div', __('Biblefox Bible'), 'bfox_post_scripture_quick_view_meta_box', 'post', 'normal', 'core');
		add_action('save_post', 'bfox_save_post', 10, 2);

		/*
		 * This would be the perfect way to add scripture tags for new posts, but wordpress doesn't call
		 * this tag for new posts (see get_tags_to_edit() which bails out on post_id of 0)
		 */
		//add_filter('tags_to_edit', 'bfox_tags_to_edit');

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

	public static function ref_write_url($ref_str)
	{
		return self::$home_url . '/wp-admin/post-new.php?' . self::var_bible_ref . '=' . urlencode($ref_str);
	}

	public static function ref_write_link($ref_str, $text = '')
	{
		if (empty($text)) $text = $ref_str;

		return "<a href='" . self::ref_write_url($ref_str) . "'>$text</a>";
	}

	public static function ref_edit_posts_link($ref_str, $text = '')
	{
		if (empty($text)) $text = $ref_str;
		$href = self::$home_url . '/wp-admin/edit.php?' . self::var_bible_ref . '=' . urlencode($ref_str);

		return "<a href='$href'>$text</a>";
	}

	public static function reading_plan_url($plan_id, $reading_id = -1)
	{
		$url = self::$home_url . '/?' . BfoxBlog::var_plan_id . '=' . $plan_id;
		if (0 <= $reading_id) $url .= '&' . BfoxBlog::var_reading_id . '=' . ($reading_id + 1);
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

		return $content;
	}

	/**
	 * Return verse content for the given bible refs formatted for email output
	 *
	 * @param BibleRefs $refs
	 * @param Translation $trans
	 * @return string
	 */
	public static function get_verse_content_email(BibleRefs $refs, Translation $trans = NULL)
	{
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

function bfox_save_post($post_id = 0, $post)
{
	if (!empty($post_id))
	{
		// Post Content Refs
		// Get the bible references from the post content
		$content_refs = RefManager::get_from_str(strip_tags($post->post_content), 0);
		// Save these bible references
		if ($content_refs->is_valid()) BfoxPosts::set_post_refs($post_id, $content_refs, BfoxPosts::ref_type_content);

		// Post Tag Refs
		$tags_refs = new BibleRefs();

		// Try to get a hidden tag from form input
		$new_tag_refs = RefManager::get_from_str($_POST[BfoxBlog::var_bible_ref]);
		if ($new_tag_refs->is_valid())
		{
			$tags_refs->add_seqs($new_tag_refs->get_seqs());
			$new_tag = $new_tag_refs->get_string(BibleMeta::name_short);
		}

		// Get the bible references from the post tags
		$tags = wp_get_post_tags($post_id, array('fields' => 'names'));
		foreach ($tags as &$tag)
		{
			$refs = RefManager::get_from_str($tag);
			if ($refs->is_valid())
			{
				$tag = $refs->get_string(BibleMeta::name_short);
				$tags_refs->add_seqs($refs->get_seqs());
			}

			if (trim($tag) == $new_tag) $new_tag = '';
		}

		if (!empty($new_tag)) $tags []= $new_tag;

		// Save these bible references
		BfoxPosts::set_post_refs($post_id, $tags_refs, BfoxPosts::ref_type_tag);
		// If we actually found some references, then re-save the tags again to use our modified tags
		if ($tags_refs->is_valid()) wp_set_post_tags($post_id, $tags);
	}
}

/* This function can be used if wordpress updates get_tags_to_edit()
function bfox_tags_to_edit($tags_to_edit)
{
	// If we have a bible reference passed as input, try to add it as a tag
	if (!empty($_REQUEST[BfoxBlog::var_bible_ref]))
	{
		$refs = RefManager::get_from_str($_REQUEST[BfoxBlog::var_bible_ref]);
		if ($refs->is_valid())
		{
			// Get the new tag string
			$new_tag = $refs->get_string(BibleMeta::name_short);

			// Only add the new tag if it hasn't already been added
			$tags = explode(',', $tags_to_edit);
			foreach ($tags as $tag) if (trim($tag) == $new_tag) $new_tag = '';
			if (!empty($new_tag))
			{
				$tags []= $new_tag;
				$tags_to_edit = implode(',', $tags);
			}
		}
	}

 	return $tags_to_edit;
}
*/

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
//	bfox_progress_page();
	if (current_user_can(BFOX_USER_LEVEL_MANAGE_USERS)) bfox_join_request_menu();
	include('my-blogs.php');
}

/**
 * Filter function for adding biblefox columns to the edit posts list
 *
 * @param $columns
 * @return array
 */
function bfox_manage_posts_columns($columns)
{
	// Create a new columns array with our new columns, and in the specified order
	// See wp_manage_posts_columns() for the list of default columns
	$new_columns = array();
	foreach ($columns as $key => $column)
	{
		$new_columns[$key] = $column;

		// Add the bible verse column right after 'author' column
		if ('author' == $key) $new_columns[BfoxBlog::var_bible_ref] = __('Bible Verses');
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
function bfox_manage_posts_custom_column($column_name, $post_id)
{
	if (BfoxBlog::var_bible_ref == $column_name)
	{
		global $post;
		if (isset($post->bfox_bible_refs)) echo BfoxBlog::ref_edit_posts_link($post->bfox_bible_refs->get_string(BibleMeta::name_short));
	}

}
add_action('manage_posts_custom_column', 'bfox_manage_posts_custom_column', 10, 2);
//add_action('manage_pages_custom_column', 'bfox_manage_posts_custom_column', 10, 2);

?>