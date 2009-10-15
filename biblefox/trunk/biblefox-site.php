<?php
/*************************************************************************

	Plugin Name: Biblefox-Site
	Plugin URI: http://tools.biblefox.com/
	Description: Allows your blog to become a bible commentary, and adds the entire bible text to your blog, so you can read, search, and study the bible all from your blog.
	Version: 0.4.9
	Author: Biblefox
	Author URI: http://biblefox.com
Site Wide Only: true

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

define(BFOX_SITE_VERSION, '0.4.9');

define(BFOX_SITE_FILE, __FILE__);
define(BFOX_SITE_DIR, dirname(__FILE__));
define(BFOX_SITE_URL, WP_PLUGIN_URL . '/biblefox-site');

/**
 * Hacky function for showing DB errors. Hacked into the wpdb query filter to make sure that the errors always show.
 *
 * @param unknown_type $query
 * @return unknown
 */
function bfox_show_errors($query) {
	global $wpdb;
	define('DIEONDBERROR', 'die!');
	$wpdb->show_errors(true);
	return $query;
}
if (defined('BFOX_TESTBED')) add_filter('query', 'bfox_show_errors');

// TODO3: come up with something else
function pre($expr) { echo '<pre>'; print_r($expr); echo '</pre>'; }
function deb($expr) { global $bfox_debug_text; $bfox_debug_text .= print_r($expr, TRUE) . "\n"; }
function bfox_pre_deb() { global $bfox_debug_text; if (!empty($bfox_debug_text)) echo "<pre style='clear:both;'>$bfox_debug_text</pre>"; }
add_action('get_footer', 'bfox_pre_deb');
function bfox_posts_requests($request) { deb("REQUEST: $request"); return $request; }
//add_filter('posts_request', 'bfox_posts_requests'); // Uncomment this to show the WP_Query SQL request query

include_once BFOX_SITE_DIR . '/admin/admin-tools.php';

class BiblefoxSite {

	public static function new_blog_settings($blog_id, $user_id) {
		global $wpdb;

		switch_to_blog($blog_id);

		/*
		 * Blogroll Links
		 */

		// Change the old Wordpress.com link to Biblefox.com
		$wpdb->query($wpdb->prepare("UPDATE $wpdb->links
			SET link_url = %s, link_name = %s, link_rss = %s
			WHERE link_id = 1",
			'http://biblefox.com/',
			'Biblefox.com',
			'http://biblefox.com/feed/'));

		// Change the old Wordpress.org link to Biblefox.com/bible/
		// TODO3: actually implement bible feeds at /bible/feed/
		$wpdb->query($wpdb->prepare("UPDATE $wpdb->links
			SET link_url = %s, link_name = %s, link_rss = %s
			WHERE link_id = 2",
			'http://biblefox.com/bible/',
			'Bible Reader',
			'http://biblefox.com/bible/feed/'));

		restore_current_blog();
	}

	private static function admin_bar_dropdown($title, $url = '') {
		return "<a href='$url'><span class='wpabar-dropdown'>$title</span></a>";
	}

	public static function admin_bar() {
		global $current_site, $current_blog, $current_user, $user_ID;

		$login = bp_bible_loginout();
		$signup_url = 'http://' . $current_site->domain . $current_site->path . 'wp-signup.php';
		$drop_class = "class='wpabar-menupop' onmouseover='showNav(this)' onmouseout='hideNav(this)'";

		$left_side = new BfoxHtmlList();
		$right_side = new BfoxHtmlList();
		if (!empty($user_ID)) {
			$user_list = new BfoxHtmlList();
			$dashboards = new BfoxHtmlList();
			$new_posts = new BfoxHtmlList();
			$blog_options = new BfoxHtmlList();

			$active_blog = get_active_blog_for_user($user_ID);
			if (is_object($active_blog)) $default_url = $active_blog->siteurl . '/';
			else $default_url = 'http://' . $current_site->domain . $current_site->path;

			$current_url = 'http://' . $current_blog->domain . $current_blog->path;

			$profile_url = $default_url . 'wp-admin/profile.php';
			$dashboards_url = $default_url . 'wp-admin/';
			$new_posts_url = $default_url . 'wp-admin/post-new.php';
			$my_blogs_url = $default_url. 'wp-admin/blogs.php';

			$blogs = get_blogs_of_user($user_ID);
			$blog_count = count($blogs);
			foreach ($blogs as $blog) {
				$dashboards->add("<a href='$blog->siteurl/wp-admin/'>$blog->blogname</a>");
				$new_posts->add("<a href='$blog->siteurl/wp-admin/post-new.php'>$blog->blogname</a>");
			}

			$user_list->add("<a href='$my_blogs_url'>My Blogs</a>");
			$user_list->add("<a href='$profile_url'>Edit Profile</a>");
			$user_list->add("<a href='$signup_url'>" . __('Create a New Blog') . "</a>");
			$user_list->add($login);

			$add_url = add_query_arg('add_url', $current_url, BfoxQuery::page_url(BfoxQuery::page_commentary));
			$blog_options->add("<a href='$add_url'>Subscribe to Bible Feed</a>");
			// TODO3: Report as spam and mature
			//$blog_options->add("<a href='$add_url'>Report as spam</a>");
			//$blog_options->add("<a href='$add_url'>Report as mature</a>");

			$left_side->add(self::admin_bar_dropdown($current_user->user_login, $profile_url) . $user_list->content(), $drop_class);
			if (1 < $blog_count) {
				$left_side->add(self::admin_bar_dropdown(__('My Dashboards'), $dashboards_url) . $dashboards->content(), $drop_class);
				$left_side->add(self::admin_bar_dropdown(__('New Post'), $new_posts_url) . $new_posts->content(), $drop_class);
			}
			elseif (0 < $blog_count) {
				$left_side->add("<a href='$dashboards_url'>My Dashboard</a>");
				$left_side->add("<a href='$new_posts_url'>New Post</a>");
			}
			$right_side->add(self::admin_bar_dropdown(__('Blog Options')) . $blog_options->content(), $drop_class);
		}
		else {
			$left_side->add($login);
			$left_side->add("<a href='$signup_url'>" . __('Sign Up') . "</a>");
		}

		$biblefox = new BfoxHtmlList();
		$biblefox->add("<a href='" . BfoxQuery::url() . "'>Bible Reader</a>");

		$right_side->add(self::admin_bar_dropdown(__('Biblefox.com'), 'http://biblefox.com') . $biblefox->content(), $drop_class);

		return array($left_side->content(), $right_side->content());
	}

	public static function wpabar_defaults($defaults) {
		$defaults['show_admin'] = 1;
		return $defaults;
	}

	public static function query_vars($qvars) {
		// Add a query variable for bible references
		$qvars []= BfoxQuery::var_page;
		$qvars []= BfoxQuery::var_pretty_query;
		return $qvars;
	}

	public static function parse_request(WP $wp) {
		// We don't need wp_query for the bible viewer, so we can exit at the end of request parsing, before wp_query is called
		if (isset($wp->query_vars[BfoxQuery::var_page]) || isset($wp->query_vars[BfoxQuery::var_pretty_query])) {
			global $current_blog, $current_site;

			// The bible should always be on the main blog, so if it isn't just redirect it
			if (is_main_blog()) BiblefoxMainBlog::bible($wp->query_vars[BfoxQuery::var_pretty_query]);
			else wp_redirect((is_ssl() ? 'https://' : 'http://') . $current_site->domain . $current_site->path . substr($_SERVER['REQUEST_URI'], strlen($current_blog->path)));
			exit;
		}
	}

	/**
	 * Filter function for changine the email from name to Biblefox
	 *
	 * @param string $from_name
	 * @return string
	 */
	public static function wp_mail_from_name($from_name) {
		if ('WordPress' == $from_name) $from_name = 'Biblefox';
		return $from_name;
	}

	public static function init() {
		// Include the main_blog functionality if this is the main blog
		// HACK: is_main_blog isn't enough - when a new blog is created, it needs to be activated, which for instance:
		// The url http://demo.biblefox.com/wp-activate.php would go to blog #1, but the domain is demo.biblefox.com
		// so, is_main_blog() would return false even though we are using blog #1.
		// This was leading to main_blog.php not being loaded even though we were loading the main blog theme which needs it.
		global $blog_id;
		if (1 == $blog_id/*is_main_blog()*/) require_once BFOX_SITE_DIR . '/main_blog.php';

		//add_filter('query_vars', 'BiblefoxSite::query_vars');
		//add_action('parse_request', 'BiblefoxSite::parse_request');
		add_action('wpmu_new_blog', 'BiblefoxSite::new_blog_settings', 10, 2);
		add_filter('wp_mail_from_name', 'BiblefoxSite::wp_mail_from_name');
		wp_deregister_style('login');
		wp_register_style('login', BFOX_SITE_URL . '/login.css');

		/*
		 * Dashboard widgets
		 */

		// Primary
		add_filter('dashboard_primary_link', create_function('', 'return "http://biblefox.com/";'));
		add_filter('dashboard_primary_feed', create_function('', 'return "http://biblefox.com/feed/";'));
		add_filter('dashboard_primary_title', create_function('', 'return "Biblefox Blog";'));

		// Secondary
		add_filter('dashboard_secondary_link', create_function('', 'return "http://biblefox.com/category/featured/";'));
		add_filter('dashboard_secondary_feed', create_function('', 'return "http://biblefox.com/category/featured/feed/";'));
		add_filter('dashboard_secondary_title', create_function('', 'return "Featured Posts";'));

		// Adjust BuddyPres Admin Bar
		$new_logo = BFOX_SITE_URL . 'site/logo-text-admin-bar.png';
		add_filter('bp_admin_bar_logo_src', create_function('', "return '$new_logo';"));
		add_filter('bp_admin_bar_logo_alt_text', create_function('', "return 'Biblefox.com';"));
	}
}

function bfox_hide_widgets() {
	if (!is_main_blog()) {
		remove_action('plugins_loaded', 'bp_core_register_widgets');
		remove_action('plugins_loaded', 'bp_blogs_register_widgets');
		remove_action('plugins_loaded', 'bp_activity_register_widgets');
		remove_action('plugins_loaded', 'groups_register_widgets');
		remove_action('widgets_init', 'bp_bible_widgets_init');
		remove_action('init', 'bp_bible_register_sidebars');
	}
}

add_action('muplugins_loaded', 'bfox_hide_widgets');
add_action('init', 'BiblefoxSite::init');
add_filter('wpabar_defaults', 'BiblefoxSite::wpabar_defaults');

/*
 * MARKETING FUNCTIONS:
 */

function bfox_add_analytics() {
	?>
<script type="text/javascript">
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
try {
var pageTracker = _gat._getTracker("UA-6583355-1");
pageTracker._trackPageview();
} catch(err) {}</script>
	<?php
}

if (!defined('BFOX_TESTBED')) {
	add_action('wp_footer', 'bfox_add_analytics');
	add_action('admin_footer', 'bfox_add_analytics');
}

?>