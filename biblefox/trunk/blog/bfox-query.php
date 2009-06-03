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
class BfoxBlogQueryData
{
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
	 * @param mixed $value Either an array of post ids or a BibleRefs to get post ids that contain those references
	 */
	public static function set_post_ids($value)
	{
		if (is_array($value)) self::$post_ids = $value;
		elseif ($value instanceof BibleRefs) self::$post_ids = BfoxPosts::get_post_ids($value);

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

	public static function set_display_refs(BibleRefs $refs)
	{
		self::add_pre_posts(self::create_ref_posts($refs));
	}

	public static function set_reading_plan($plan_id = 0, $reading_id = 0) {
		global $blog_id;

		$refs = new BibleRefs;
		$new_posts = array();

		$plan = BfoxPlans::get_plan($plan_id);
		if ($plan->is_current()) {

			// If there is no reading set, use the current reading
			// If there is a reading set, we need to decrement it to make it zero-based
			if (empty($reading_id)) $reading_id = $plan->current_reading_id;
			else $reading_id--;

			$refs->add($plan->readings[$reading_id]);
			$new_posts []= self::create_reading_post($plan, $reading_id);
		}

		if ($refs->is_valid()) self::set_post_ids($refs);
		if (!empty($new_posts)) self::add_pre_posts($new_posts);
	}

	/**
	 * Add verse content to a post array
	 *
	 * @param array $post
	 * @param BibleRefs $refs
	 * @param string $nav_bar
	 * @return array
	 */
	private static function add_verse_post_content($post, BibleRefs $refs, $nav_bar = '')
	{
		/*
		 * Add the verse content as 'bfox_pre_content' so that Wordpress doesn't filter it like regular content.
		 * But the verse content does contain footnotes, which we can use ShortFoot functionality for.
		 * Then once the footnotes have been found in the verse content, we can add the footnote list.
		 */

		// Get the verse content, and filter it using the <footnote> tags as if they were [footnote] shortcodes
		// The regex being used here should mirror the regex returned by get_shortcode_regex() and is being used similarly to do_shortcode(),
		//  the only difference being that we only need to look for <footnote> shortcodes (and using chevrons instead of brackets)
		$post['bfox_pre_content'] = $nav_bar . preg_replace_callback('/<(footnote)\b(.*?)(?:(\/))?>(?:(.+?)<\/\1>)?/s', 'do_shortcode_tag', BfoxBlog::get_verse_content($refs)) . $nav_bar;

		// The footnote list can go in 'post_content', because we want it to be filtered by Wordpress
		$post['post_content'] = shortfoot_get_list();

		return $post;
	}

	/**
	 * Returns an array of posts with content for the given bible references
	 *
	 * @param BibleRefs $refs
	 * @param string $title
	 * @return array of new_posts
	 */
	private function create_ref_posts(BibleRefs $refs, $title = '')
	{
		$bcvs = BibleRefs::get_bcvs($refs->get_seqs());

		foreach ($bcvs as $book => $cvs)
		{
			$book_name = BibleMeta::get_book_name($book);
			$ref_str = BibleRefs::create_book_string($book, $cvs);

			// Create a new bible refs for just this book (so we can later pass it into BfoxBlog::get_verse_content())
			$book_refs = new BibleRefs;

			// Get the first and last chapters
			unset($ch1);
			foreach ($cvs as $cv)
			{
				if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
				list($ch2, $vs2) = $cv->end;

				// Add the cv onto our book bible refs
				$book_refs->add_bcv($book, $cv);
			}

			// Create the navigation bar with the prev/write/next links
			$nav_bar = "<div class='bible_post_nav'>";
			if ($ch1 > BibleMeta::start_chapter)
			{
				$prev_ref_str = $book_name . ' ' . ($ch1 - 1);
				$nav_bar .= BfoxBlog::ref_link($prev_ref_str, "&lt; $prev_ref_str", "class='bible_post_prev'");
			}
			$nav_bar .= BfoxBlog::ref_write_link($ref_str, 'Write about this passage');
			if ($ch2 < BibleMeta::end_verse_max($book))
			{
				$next_ref_str = $book_name . ' ' . ($ch2 + 1);
				$nav_bar .= BfoxBlog::ref_link($next_ref_str, "$next_ref_str &gt;", "class='bible_post_next'");
			}
			$nav_bar .= "<br/><a href='" . BfoxQuery::passage_page_url($ref_str) . "'>View in Biblefox Bible Viewer</a></div>";

			$new_post = self::add_verse_post_content(array(), $book_refs, $nav_bar);
			$new_post['ID'] = -1;
			$new_post['post_title'] = $title . $ref_str;
			$new_post['bible_ref_str'] = $ref_str;
			$new_post['post_type'] = BfoxBlog::var_bible_ref;
			$new_post['post_date'] = current_time('mysql', false);
			$new_post['post_date_gmt'] = current_time('mysql', true);
			$new_post['bfox_permalink'] = BfoxBlog::ref_url($ref_str);
			$new_post['bfox_author'] = "<a href='" . BfoxQuery::passage_page_url($ref_str) . "'>Biblefox</a>";

			// Turn off comments
			$new_post['comment_status'] = 'closed';
			$new_post['ping_status'] = 'closed';

			$new_posts[] = ((object) $new_post);
		}

		return $new_posts;
	}

	/**
	 * Creates a post with reading content
	 *
	 * @param $plan
	 * @param $reading_id
	 * @return object new_post
	 */
	private function create_reading_post(BfoxReadingPlan $plan, $reading_id)
	{
		$refs = $plan->readings[$reading_id];
		$ref_str = $refs->get_string();

		// Create the navigation bar with the prev/write/next links
		$nav_bar = "<div class='bible_post_nav'>";
		if (isset($plan->readings[$reading_id - 1]))
		{
			$prev_ref_str = $book_name . ' ' . ($ch1 - 1);
			$nav_bar .= '<a href="' . BfoxBlog::reading_plan_url($plan->id, $reading_id - 1) . '" class="bible_post_prev">&lt; ' . $plan->readings[$reading_id - 1]->get_string() . '</a>';
		}
		$nav_bar .= BfoxBlog::ref_write_link($refs->get_string(), 'Write about this passage');
		if (isset($plan->readings[$reading_id + 1]))
		{
			$next_ref_str = $book_name . ' ' . ($ch2 + 1);
			$nav_bar .= '<a href="' . BfoxBlog::reading_plan_url($plan->id, $reading_id + 1) . '" class="bible_post_next">' . $plan->readings[$reading_id + 1]->get_string() . ' &gt;</a>';
		}
		$nav_bar .= "<br/><a href='" . BfoxQuery::passage_page_url($ref_str) . "'>View in Biblefox Bible Viewer</a></div>";

		$new_post = self::add_verse_post_content(array(), $refs, $nav_bar);
		$new_post['ID'] = -1;
		$new_post['post_title'] = $ref_str;
		$new_post['bible_ref_str'] = $ref_str;
		$new_post['post_type'] = BfoxBlog::var_bible_ref;
		$new_post['bfox_permalink'] = BfoxBlog::reading_plan_url($plan->id, $reading_id);
		$new_post['bfox_author'] = '<a href="' . BfoxBlog::reading_plan_url($plan->id) . '">' . $plan->name . ' (Reading ' . ($reading_id + 1) . ')</a>';

		// Set the date according to the reading plan if possible, otherwise set it to the current date
		if (isset($plan->dates[$reading_id]))
		{
			$new_post['post_date'] = $new_post['post_date_gmt'] = date('Y-m-d H:i:s', $plan->dates[$reading_id]);
		}
		else
		{
			$new_post['post_date'] = current_time('mysql', false);
			$new_post['post_date_gmt'] = current_time('mysql', true);
		}

		// Turn off comments
		$new_post['comment_status'] = 'closed';
		$new_post['ping_status'] = 'closed';

		return (object) $new_post;
	}
}

// Function for adding query variables for our plugin
function bfox_queryvars($qvars)
{
	// Add a query variable for bible references
	$qvars[] = BfoxBlog::var_bible_ref;
	$qvars[] = BfoxBlog::var_plan_id;
	$qvars[] = BfoxBlog::var_reading_id;
	return $qvars;
}

// Function to be run after parsing the query
function bfox_parse_query($wp_query)
{
	$showing_refs = FALSE;

	if ($wp_query->is_search)
	{
		$refs = new BibleRefs($wp_query->query_vars['s']);
		if ($refs->is_valid())
		{
			BfoxBlogQueryData::set_display_refs($refs);
			$showing_refs = TRUE;
		}
	}
	elseif (isset($wp_query->query_vars[BfoxBlog::var_plan_id]))
	{
		BfoxBlogQueryData::set_reading_plan($wp_query->query_vars[BfoxBlog::var_plan_id], $wp_query->query_vars[BfoxBlog::var_reading_id]);
		$wp_query->is_home = FALSE;
		$showing_refs = TRUE;
	}
	elseif (isset($wp_query->query_vars[BfoxBlog::var_bible_ref]))
	{
		$refs = new BibleRefs($wp_query->query_vars[BfoxBlog::var_bible_ref]);
		if ($refs->is_valid())
		{
			BfoxBlogQueryData::set_post_ids($refs);
			BfoxBlogQueryData::set_display_refs($refs);
			$wp_query->is_home = FALSE;
			$showing_refs = TRUE;
		}
	}

	if ($showing_refs) BfoxUtility::enqueue_style('bfox_scripture');
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
function bfox_add_special_content($content)
{
	global $post;

	// If this post have bible references, mention them at the beginning of the post
	if (isset($post->bfox_bible_refs)) $content = '<p>Scriptures Referenced: ' . BfoxBlog::ref_link($post->bfox_bible_refs->get_string()) . '</p>' . $content;

	// If this post has special biblefox pre content, prepend it
	// This special content is usually something that we don't want to be modified with the standard content,
	// but we do want it to be displayed with the standard content, so we add it here just before displaying.
	// Because of this, this function should be called after most the_content() filters have already run.
	if (isset($post->bfox_pre_content)) $content = $post->bfox_pre_content . $content;

	return $content;
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
 * Replaces bible references with bible links
 * @param string $str
 * @param integer $max_level
 * @return string
 */
function bfox_ref_replace($str, $max_level = 0)
{
	// Get all the bible reference substrings in this string
	$substrs = BibleMeta::get_bcv_substrs($str, $max_level);

	// Add each substring to our sequences
	foreach (array_reverse($substrs) as $substr)
	{
		$refs = new BibleRefs;

		// If there is a chapter, verse string use it
		if ($substr->cv_offset) BfoxRefParser::add_book_str($refs, $substr->book, substr($str, $substr->cv_offset, $substr->length - ($substr->cv_offset - $substr->offset)));
		// We are not currently adding whole books
		//else $refs->add_whole_book($substr->book);

		if ($refs->is_valid()) $str = substr_replace($str, BfoxBlog::ref_link($refs->get_string(), substr($str, $substr->offset, $substr->length)), $substr->offset, $substr->length);
	}

	return $str;
}

/**
 * Replaces bible references with bible links in a given html string
 * @param string $content
 * @return string
 */
function bfox_ref_replace_html($content)
{
	return bfox_process_html_text($content, 'bfox_ref_replace');
}

function bfox_query_init()
{
	add_filter('query_vars', 'bfox_queryvars' );
	add_action('parse_query', 'bfox_parse_query');
	add_filter('posts_where', 'bfox_posts_where');
	add_filter('posts_results', 'bfox_posts_results');
	add_filter('the_posts', 'bfox_the_posts');
	add_filter('post_link', 'bfox_the_permalink', 10, 2);

	// Replace bible references with bible links
	add_filter('the_content', 'bfox_ref_replace_html');

	// Add special content onto posts (this should happen later than most other the_content filters)
	add_filter('the_content', 'bfox_add_special_content', 20);

	add_filter('the_author', 'bfox_the_author');
	add_filter('get_edit_post_link', 'bfox_get_edit_post_link');
}

?>