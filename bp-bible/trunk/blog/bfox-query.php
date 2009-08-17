<?php
/**
 * This file is for modifying the way wordpress queries work for our plugin
 * For information on how the WP query works, see:
 * 		http://codex.wordpress.org/Custom_Queries
 * 		http://codex.wordpress.org/Query_Overview
 *
 * @package BiblefoxBlog
 */

/**
 * Class for storing static data related to WP_Query for biblefox blogs
 *
 */
class BfoxBlogQueryData {

	/*
	 Problem:
	 WP appears to use the WP_Query class as if it were a singleton, even though it is not and is even instantiated more than once.

	 Because hooks which modify a query don't pass a reference to the query, the hook functions must rely on global functions to
	 return info about the query (such as is_home() or is_page()). These functions, however, only return information about the global
	 instance of WP_Query, leading to unintended results when there are multiple instances of WP_Query (such as for the Recent Posts widget).

	 The real solution should be to pass the instance to each hook/filter function. Until that happens this hack must be in place.

	 Solution: Save information we need statically, and clear it when we've finished. This means each instance of WP_Query has to finish using
	 this information before another instance begins.

	 This is not an ideal solution, the best solution would be to store this information with the instance of WP_Query, but WP does not provide
	 the necesary hook parameters.
	 */

	/**
	 * Stores the post_ids statically. These are created by set_post_ids() and cleared by get_post_ids()
	 *
	 * @var array
	 */
	private static $post_ids = array();
	private static $use_post_ids = FALSE;

	/**
	 * Store posts which we generate separately from WP_Query and add on to the front of the posts array returned from the query.
	 *
	 * @var array
	 */
	private static $pre_posts = array();

	/**
	 * Sets some post IDs which we want to grab in the current WP_Query.
	 *
	 * These posts will be added to the query string via the bfox_posts_where() function
	 *
	 * @param mixed $value Either an array of post ids or a BfoxRefs to get post ids that contain those references
	 */
	public static function set_post_ids($value)
	{
		if (is_array($value)) self::$post_ids = $value;
		elseif ($value instanceof BfoxRefs) self::$post_ids = BfoxPosts::get_post_ids($value);

		// Use the post ids, even if there aren't any (if there aren't any, the query must return no posts - see bfox_posts_where())
		self::$use_post_ids = TRUE;
	}

	public static function use_post_ids()
	{
		return self::$use_post_ids;
	}

	public static function get_post_ids()
	{
		$post_ids = self::$post_ids;
		self::$post_ids = array();
		self::$use_post_ids = FALSE;

		return $post_ids;
	}

	public static function add_pre_posts($new_posts)
	{
		self::$pre_posts = array_merge(self::$pre_posts, $new_posts);
	}

	public static function get_pre_posts()
	{
		$new_posts = self::$pre_posts;
		self::$pre_posts = array();
		return $new_posts;
	}

	public static function set_display_refs(BfoxRefs $refs, $title = '') {
		self::add_pre_posts(self::create_ref_posts($refs, $title));
	}

	/**
	 * Add verse content to a post array
	 *
	 * @param array $post
	 * @param BfoxRefs $refs
	 * @param string $nav_bar
	 * @return array
	 */
	public static function add_verse_post_content($post, BfoxRefs $refs, $nav_bar = '') {
		/*
		 * Add the verse content as 'bfox_pre_content' so that Wordpress doesn't filter it like regular content.
		 * But the verse content does contain footnotes, which we can use ShortFoot functionality for.
		 * Then once the footnotes have been found in the verse content, we can add the footnote list.
		 */

		list($content, $foot_list) = BfoxBlog::get_verse_content_foot($refs);
		$post['bfox_pre_content'] = $nav_bar . $content . $nav_bar;

		// The footnote list can go in 'post_content', because we want it to be filtered by Wordpress
		$post['post_content'] = $foot_list;

		return $post;
	}

	/**
	 * Returns an array of posts with content for the given bible references
	 *
	 * @param BfoxRefs $refs
	 * @param string $title
	 * @return array of new_posts
	 */
	private static function create_ref_posts(BfoxRefs $input_refs, $title = '') {
		// Limit the refs to 5 chapters
		list($refs) = $input_refs->get_sections(5, 1);

		$bcvs = BfoxRefs::get_bcvs($refs->get_seqs());

		foreach ($bcvs as $book => $cvs) {
			$book_name = BibleMeta::get_book_name($book);
			$ref_str = BfoxRefs::create_book_string($book, $cvs);

			// Create a new bible refs for just this book (so we can later pass it into BfoxBlog::get_verse_content())
			$book_refs = new BfoxRefs;

			// Get the first and last chapters
			unset($ch1);
			foreach ($cvs as $cv) {
				if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
				list($ch2, $vs2) = $cv->end;

				// Add the cv onto our book bible refs
				$book_refs->add_bcv($book, $cv);
			}

			// Create the navigation bar with the prev/write/next links
			$nav_bar = "<div class='bible_post_nav'>";
			if ($ch1 > BibleMeta::start_chapter) {
				$prev_ref_str = $book_name . ' ' . ($ch1 - 1);
				$nav_bar .= BfoxBlog::ref_link($prev_ref_str, "&lt; $prev_ref_str", "class='bible_post_prev'");
			}
			$nav_bar .= BfoxBlog::ref_write_link($ref_str, 'Write about this passage');
			if ($ch2 < BibleMeta::end_verse_max($book)) {
				$next_ref_str = $book_name . ' ' . ($ch2 + 1);
				$nav_bar .= BfoxBlog::ref_link($next_ref_str, "$next_ref_str &gt;", "class='bible_post_next'");
			}
			$nav_bar .= "<br/>" . Biblefox::ref_link($ref_str, __('View in advanced Bible reader'), Biblefox::ref_url_bible) . "</div>";

			$new_post = self::add_verse_post_content(array(), $book_refs, $nav_bar);
			$new_post['ID'] = -1;
			$new_post['post_title'] = $title . $ref_str;
			$new_post['bible_ref_str'] = $ref_str;
			$new_post['post_type'] = BfoxBlog::post_type_bible;
			$new_post['post_date'] = current_time('mysql', false);
			$new_post['post_date_gmt'] = current_time('mysql', true);
			$new_post['bfox_permalink'] = Biblefox::ref_url($ref_str);
			$new_post['bfox_author'] = Biblefox::ref_link('', __('Biblefox'), Biblefox::ref_url_bible);

			// Turn off comments
			$new_post['comment_status'] = 'closed';
			$new_post['ping_status'] = 'closed';

			$new_posts[] = ((object) $new_post);
		}

		return $new_posts;
	}
}

// Function to be run after parsing the query
function bfox_parse_query($wp_query) {
	$refs_is_valid = FALSE;

	if ($wp_query->is_tag) {
		$refs = new BfoxRefs($wp_query->query_vars['tag']);
		if ($refs_is_valid = $refs->is_valid()) {
			$wp_query->is_tag = FALSE;
			unset($wp_query->query_vars['tag']);
		}
	}
	elseif ($wp_query->is_search) {
		$refs = new BfoxRefs($wp_query->query_vars['s']);
		if ($refs_is_valid = $refs->is_valid()) {
			unset($wp_query->query_vars['s']);
		}
	}

	if ($refs_is_valid) {
		BfoxBlogQueryData::set_post_ids($refs);
		BfoxBlogQueryData::set_display_refs($refs, 'Bible: ');
		BfoxUtility::enqueue_style('bfox_scripture');
	}
}

// Function for modifying the query WHERE statement
function bfox_posts_where($where)
{
	// Check if we should use our post ids array
	if (BfoxBlogQueryData::use_post_ids())
	{
		$post_ids = BfoxBlogQueryData::get_post_ids();

		// If there aren't any post ids, than this query shouldn't return any posts
		// Otherwise return the posts from the post ids
		if (empty($post_ids)) $where = 'AND 0';
		else $where .= ' AND ID IN (' . implode(',', $post_ids) . ') ';
	}
	return $where;
}

// Function for modifying the posts array returned from the actual SQL query
function bfox_posts_results($posts)
{
	if (!empty($posts))
	{
		$post_ids = array();
		foreach ($posts as $post) $post_ids []= $post->ID;

		// Get all the bible references for these posts
		$refs = BfoxPosts::get_refs($post_ids);
		foreach ($posts as &$post) if (isset($refs[$post->ID]) && $refs[$post->ID]->is_valid()) $post->bfox_bible_refs = $refs[$post->ID];
	}

	return $posts;
}

// Function for adjusting the posts after they have been queried
function bfox_the_posts($posts)
{
	$posts = array_merge(BfoxBlogQueryData::get_pre_posts(), $posts);

	return $posts;
}

// Function for filtering the output of the_permalink()
function bfox_the_permalink($permalink, $post)
{
	if (isset($post->bfox_permalink))
		$permalink = $post->bfox_permalink;
	return $permalink;
}

/**
 * Adds special content onto the content string for Biblefox posts
 * @param string $content
 * @return string
 */
function bfox_add_special_content($content, $replace = FALSE) {
	global $post;

	// If this post has bible references, mention them at the beginning of the post
	//if (isset($post->bfox_bible_refs)) $content = '<p>Scriptures Referenced: ' . BfoxBlog::ref_link($post->bfox_bible_refs->get_string()) . '</p>' . $content;

	// If this post has special biblefox pre content, prepend it
	// This special content is usually something that we don't want to be modified with the standard content,
	// but we do want it to be displayed with the standard content, so we add it here just before displaying.
	// Because of this, this function should be called after most the_content() filters have already run.
	if (isset($post->bfox_pre_content)) {
		if ($replace) $content = '';
		$content = $post->bfox_pre_content . $content;
	}

	return $content;
}

function bfox_replace_content($content) {
	return bfox_add_special_content($content, TRUE);
}

function bfox_the_refs($name = '') {
	global $post;
	if (isset($post->bfox_bible_refs)) return Biblefox::ref_link($post->bfox_bible_refs->get_string($name));
}

function bfox_the_author($author)
{
	global $post;
	if (isset($post->bfox_author)) $author = $post->bfox_author;
	return $author;
}

// Function for updating the edit post link
function bfox_get_edit_post_link($link)
{
	global $post;

	// If this post is actually scripture then we should change the
	// edit post link to be a link to write a new post about this scripture
	if (isset($post->bible_ref_str)) $link = BfoxBlog::ref_write_url($post->bible_ref_str);

	return $link;
}

/**
 * Replaces bible references with bible links in a given html string
 * @param string $content
 * @return string
 */
function bfox_ref_replace_html($content) {
	return BfoxRefParser::simple_html($content);
}

function bfox_query_init() {
	add_action('parse_query', 'bfox_parse_query');
	add_filter('posts_where', 'bfox_posts_where');
	add_filter('posts_results', 'bfox_posts_results');
	add_filter('the_posts', 'bfox_the_posts');
	add_filter('post_link', 'bfox_the_permalink', 10, 2);

	// Replace bible references with bible links
	add_filter('the_content', 'bfox_ref_replace_html');

	// Add special content onto posts (this should happen later than most other the_content filters)
	add_filter('the_content', 'bfox_add_special_content', 20);
	// TODO3: footnotes are lost for excerpts
	add_filter('the_excerpt', 'bfox_replace_content', 20);

	add_filter('the_author', 'bfox_the_author');
	add_filter('get_edit_post_link', 'bfox_get_edit_post_link');
}

?>