<?php

define(BFOX_SITE_DIR, dirname(__FILE__));

include_once BFOX_SITE_DIR . '/wordpress-admin-bar/wordpress-admin-bar.php';
include_once BFOX_SITE_DIR . '/marketing.php';
include_once BFOX_SITE_DIR . '/shortfoot.php';

class BiblefoxSite
{
	/**
	 * Returns the bible study blogs for a given user
	 *
	 * Should be used in place of get_blogs_of_user() because the main biblefox.com blog should not count
	 *
	 * @param integer $user_id
	 * @return array of blogs (see get_blogs_of_user())
	 */
	public static function get_bible_study_blogs($user_id)
	{
		// Get the blogs for the user
		$blogs = get_blogs_of_user($user_id);

		// The main biblefox blog does not count as a bible study blog
		unset($blogs[1]);

		return $blogs;
	}

	/**
	 * This returns a link for logging in or for logging out
	 *
	 * It always goes to the login page for the main blog and redirects back to the page from which it was called.
	 * This gives the whole site a common login place that seamlessly integrates with every blog.
	 *
	 * @return unknown
	 */
	public static function loginout()
	{
		// From auth_redirect()
		if ( is_ssl() )
			$proto = 'https://';
		else
			$proto = 'http://';

		$old_url = urlencode($proto . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

		// From site_url()
		$site_url = 'http';
		if (force_ssl_admin()) $site_url .= 's'; // Use https

		// Always use the main blog for login/out
		global $current_blog;
		$site_url .= '://' . $current_blog->domain . $current_blog->path . 'wp-login.php?';

		// From wp_loginout()
		if (!is_user_logged_in())
			$link = '<a href="' . $site_url . 'redirect_to=' . $old_url . '">' . __('Log in') . '</a>';
		else
			$link = '<a href="' . wp_logout_url($old_url) . '">' . __('Log out') . '</a>';

		return $link;
	}

	public static function query_vars($qvars)
	{
		// Add a query variable for bible references
		$qvars[] = BfoxQuery::var_page;
		return $qvars;
	}

	public static function parse_request(WP $wp)
	{
		// We don't need wp_query for the bible viewer, so we can exit at the end of request parsing, before wp_query is called
		if (isset($wp->query_vars[BfoxQuery::var_page]))
		{
			global $current_blog, $current_site;

			// The bible should always be on the main blog, so if it isn't just redirect it
			if (is_main_blog()) require dirname(__FILE__) . '/bible-index.php';
			else wp_redirect((is_ssl() ? 'https://' : 'http://') . $current_site->domain . $current_site->path . substr($_SERVER['REQUEST_URI'], strlen($current_blog->path)));
			exit;
		}
	}

	public static function widget_bible_pages($args)
	{
		extract($args);
		$title = 'Bible';
		echo $before_widget . $before_title . $title . $after_title;
		BfoxQuery::sidebar_list();
		echo $after_widget;
	}

	public function init()
	{
		add_filter('query_vars', 'BiblefoxSite::query_vars');
		add_action('parse_request', 'BiblefoxSite::parse_request');
		register_sidebar_widget('Bible Pages', array('BiblefoxSite', 'widget_bible_pages'));
	}
}
add_action('init', array('BiblefoxSite', 'init'));

/**
 * Filter function for changine the email from name to Biblefox
 *
 * @param string $from_name
 * @return string
 */
function bfox_wp_mail_from_name($from_name)
{
	if ('WordPress' == $from_name) $from_name = 'Biblefox';
	return $from_name;
}
add_filter('wp_mail_from_name', 'bfox_wp_mail_from_name');

/**
 * Filter function for allowing page titles to be used in the exclude parameter passed to wp_list_pages()
 *
 * TODO2: See if we still need this function
 *
 * @param unknown_type $excludes
 * @return unknown
 */
function bfox_list_pages_excludes($excludes)
{
	global $wpdb;

	// Convert any string title excludes to a post id
	// wpdb query from get_page_by_title()
	foreach ($excludes as &$exclude)
		if (!is_integer($exclude))
			$exclude = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='page'", $exclude ));

	return $excludes;
}
add_filter('wp_list_pages_excludes', 'bfox_list_pages_excludes');

?>