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
	 * Sets some post IDs which we want to grab in the current WP_Query.
	 *
	 * These posts will be added to the query string via the bfox_posts_where() function
	 *
	 * @param mixed $value Either an array of post ids or a BfoxRefs to get post ids that contain those references
	 */
	public static function set_post_ids($value) {
		if (is_array($value)) self::$post_ids = $value;
		elseif ($value instanceof BfoxRefs) self::$post_ids = BfoxPosts::get_post_ids($value);

		// Use the post ids, even if there aren't any (if there aren't any, the query must return no posts - see bfox_posts_where())
		self::$use_post_ids = TRUE;
	}

	public static function use_post_ids() {
		return self::$use_post_ids;
	}

	public static function get_post_ids() {
		$post_ids = self::$post_ids;
		self::$post_ids = array();
		self::$use_post_ids = FALSE;

		return $post_ids;
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
	}
}

// Function for modifying the query WHERE statement
function bfox_posts_where($where) {
	// Check if we should use our post ids array
	if (BfoxBlogQueryData::use_post_ids()) {
		$post_ids = BfoxBlogQueryData::get_post_ids();

		// If there aren't any post ids, than this query shouldn't return any posts
		// Otherwise return the posts from the post ids
		if (empty($post_ids)) $where = 'AND 0';
		else $where .= ' AND ID IN (' . implode(',', $post_ids) . ') ';
	}
	return $where;
}

// Function for adjusting the posts after they have been queried
function bfox_the_posts($posts) {

	// Add any bible references to the post
	if (!empty($posts)) {
		$post_ids = array();
		foreach ($posts as $post) $post_ids []= $post->ID;

		// Get all the bible references for these posts
		$refs = BfoxPosts::get_refs($post_ids);
		foreach ($posts as &$post) if (isset($refs[$post->ID]) && $refs[$post->ID]->is_valid()) $post->bfox_bible_refs = $refs[$post->ID];
	}

	return $posts;
}

function bfox_the_refs($name = '', $link = TRUE) {
	global $post;
	if (isset($post->bfox_bible_refs)) {
		$ref_str = $post->bfox_bible_refs->get_string($name);
		if ($link) return BfoxBlog::ref_archive_link(array('ref_str' => $ref_str));
		else return $ref_str;
	}
}

/**
 * Replaces bible references with bible links in a given html string
 * @param string $content
 * @return string
 */
function bfox_ref_replace_html($content) {
	return BfoxRefParser::simple_html($content);
}

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
		$refs = BfoxBlog::tag_to_refs($tag);
		if ($refs->is_valid()) {
			$tag_link = BfoxBlog::link_add_ref_tooltip($tag_link, $refs->get_string());
		}
	}
	return $tag_links;
}

function bfox_query_init() {
	add_action('parse_query', 'bfox_parse_query');
	add_filter('posts_where', 'bfox_posts_where');
	add_filter('the_posts', 'bfox_the_posts');

	// Replace bible references with bible links
	add_filter('the_content', 'bfox_ref_replace_html');

	// Add tooltips to Bible Ref tag links
	add_filter('term_links-post_tag', 'bfox_add_tag_ref_tooltips');
}

?>