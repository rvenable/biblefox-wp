<?php

	define('BFOX_BASE_TABLE_PREFIX', $GLOBALS['wpdb']->base_prefix . 'bfox_');

	// Defines for tables which only have one table name
	define('BFOX_BOOKS_TABLE', BFOX_BASE_TABLE_PREFIX . 'books');
	define('BFOX_SYNONYMS_TABLE', BFOX_BASE_TABLE_PREFIX . 'synonyms');
	define('BFOX_TRANSLATIONS_TABLE', BFOX_BASE_TABLE_PREFIX . 'translations');
	define('BFOX_BOOK_COUNTS_TABLE', BFOX_BASE_TABLE_PREFIX . 'book_counts');
	define('BFOX_TRANSLATION_INDEX_TABLE', BFOX_BASE_TABLE_PREFIX . 'trans_index');

	// User Levels
	define('BFOX_USER_LEVEL_MANAGE_PLANS', 7);
	define('BFOX_USER_LEVEL_MANAGE_USERS', 'edit_users');

	// Column Definitions
	define('BFOX_COL_TYPE_ID', 'BIGINT(20) UNSIGNED');

	// Query Variables
	define('BFOX_QUERY_VAR_BIBLE_REF', 'bible_ref');
	define('BFOX_QUERY_VAR_SPECIAL', 'bfox_special');
	define('BFOX_QUERY_VAR_ACTION', 'bfox_action');
	define('BFOX_QUERY_VAR_PLAN_ID', 'bfox_plan_id');
	define('BFOX_QUERY_VAR_READING_ID', 'bfox_reading_id');
	define('BFOX_QUERY_VAR_JOIN_BIBLE_REFS', 'join_bible_refs');

	require_once('bible-meta.php');
	require_once("bfox-settings.php");
	require_once("bfox-blog-specific.php");
	require_once("plan.php");
	require_once('links.php');
	require_once('marketing.php');

	// BibleRefs class
	require_once("ref.php");

	// Include files which need BibleRefs
	require_once("bibletext.php");
	require_once('quicknote.php');
	require_once("history.php");
	require_once('message.php');
	require_once("bfox-query.php");
	require_once('special.php');
	require_once('bfox-widgets.php');
	require_once('usfx.php');
	require_once('bfox-translations.php');
	require_once('commentaries.php');
	require_once('bible.php');

	// Returns the bible study blogs for a given user
	// Should be used in place of get_blogs_of_user() because the main biblefox.com blog should not count
	function bfox_get_bible_study_blogs($user_id)
	{
		// Get the blogs for the user
		$blogs = get_blogs_of_user($user_id);

		// The main biblefox blog does not count as a bible study blog
		unset($blogs[1]);

		return $blogs;
	}

	function bfox_divide_into_cols($array, $max_cols, $height_threshold = 0)
	{
		$count = count($array);
		if (0 < $count)
		{

			// The height_threshold is so that we don't divide into too many columns for small arrays
			// So, for instance, if we have 3 max columns and 5 array elements, and a threshold of 5, we shouldn't
			// divide that into 3 short columns, but one column of 5
			if (0 == $height_threshold)
				$cols = $max_cols;
			else
				$cols = ceil($count / $height_threshold);

			if ($cols > $max_cols) $cols = $max_cols;

			$array = array_chunk($array, ceil($count / $cols), TRUE);
		}
		return $array;
	}

	/*
	 This function converts a date string to the specified format, using the local timezone
	 Parameters:
	 date_str - should be a datetime string acceptable by strtotime()
		If date_str is not acceptable, 'today' will be used instead
	 format - should be a format string acceptable by date()

	 The function implements workarounds for some shortcomings of the strtotime() function:
	 Essentially, strtotime() accepts many useful strings such as 'today', 'next tuesday', '10/14/2008', etc.
	 These strings are calculated using the default timezone (date_default_timezone_get()), which isn't necessarily
	 the timezone set for the blog. In order to have full support for all those useful strings and still get results in our
	 desired timezone, we have to temporarily change the timezone, get the timestamp from strtotime(), format it using date(),
	 then finally reset the timezone back to its original state.
	 */
	function bfox_format_local_date($date_str, $format = 'm/d/Y')
	{
		// Get the current default timezone because we need to set it back when we are done
		$tz = date_default_timezone_get();

		// Get this blog's GMT offset (as an integer because date_default_timezone_set() doesn't support minute increments)
		$gmt_offset = (int)(get_option('gmt_offset'));

		// Invert the offset for use in date_default_timezone_set()
		$gmt_offset *= -1;

		// If the offset is positive (or 0), add the + to the beginning
		if ($gmt_offset >= 0) $gmt_offset = '+' . $gmt_offset;

		// Temporarily set the timezone to the blog's timezone
		date_default_timezone_set('Etc/GMT' . $gmt_offset);

		// Get the date string
		if (($time = strtotime($date_str)) === FALSE) $time = strtotime('today');
		$date_str = date($format, $time);

		// Set the timezone back to its previous setting
		date_default_timezone_set($tz);

		return $date_str;
	}

	function bfox_wp_mail_from_name($from_name)
	{
		if ('WordPress' == $from_name) $from_name = 'Biblefox';
		return $from_name;
	}
	add_filter('wp_mail_from_name', 'bfox_wp_mail_from_name');

	/*
	 This returns a link for logging in or for logging out.
	 It always goes to the login page for the main blog and redirects back to the page from which it was called.
	 This gives the whole site a common login place that seamlessly integrates with every blog.
	 */
	function bfox_loginout()
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

	/*
	 Filter function for allowing page titles to be used in the exclude parameter passed to wp_list_pages()
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

	function bfox_add_head_files()
	{
		?>
		<link rel="stylesheet" href="<?php echo get_option('siteurl') ?>/wp-content/mu-plugins/biblefox-study/scripture.css" type="text/css"/>
		<?php
	}
	add_action('wp_head', 'bfox_add_head_files');
	add_action('admin_head', 'bfox_add_head_files');

	function bfox_add_admin_head_files()
	{
		// use JavaScript SACK library for Ajax
		wp_print_scripts( array( 'sack' ));

		$url = get_option('siteurl');
		?>
		<link rel="stylesheet" href="<?php echo $url; ?>/wp-content/mu-plugins/biblefox-study/bible.css" type="text/css"/>
		<script type="text/javascript" src="<?php echo $url; ?>/wp-content/mu-plugins/biblefox-study/bible.js"></script>
		<?php
	}
	add_action('admin_head', 'bfox_add_admin_head_files');

	function bfox_admin_page_url($page_name)
	{
		return get_option('siteurl') . '/wp-admin/admin.php?page=' . $page_name;
	}

	/*
	 This function takes some html input ($html) and processes its text using the $func callback.
	 It will skip all html tags and call $func for each chunk of text.
	 The $func function should take the text as its parameter and return the modified text.
	 */
	function bfox_process_html_text($html, $func)
	{
		if (!is_callable($func)) return $html;

		$text_start = 0;
		while (1 == preg_match('/<[^<>]*[^<>\s][^<>]*>/', $html, $matches, PREG_OFFSET_CAPTURE, $text_start))
		{
			// Store the match data in more readable variables
			$text_end = (int) $matches[0][1];
			$pattern = (string) $matches[0][0];

			$text_len = $text_end - $text_start;
			if (0 < $text_len)
			{
				// Modify the data with the replacement text
				$replacement = call_user_func($func, substr($html, $text_start, $text_len));
				$html = substr_replace($html, $replacement, $text_start, $text_len);

				// Skip the rest of the replacement string
				$text_end = $text_start + strlen($replacement);
			}
			$text_start = $text_end + strlen($pattern);
		}

		$text_len = strlen($html) - $text_start;
		if (0 < $text_len)
		{
			// Modify the data with the replacement text
			$replacement = call_user_func($func, substr($html, $text_start, $text_len));
			$html = substr_replace($html, $replacement, $text_start, $text_len);
		}

		return $html;
	}

	// Removes all <tags> from html text
	function bfox_html_strip_tags($html)
	{
		// TODO: I don't think we need this function - we can use PHP's strip_tags()
		return preg_replace('/<[^<>]*[^<>\s][^<>]*>/', '', $html);
	}

	/**
	 * Returns whether a table exists or not
	 *
	 * @param string $table_name
	 * @return boolean
	 */
	function bfox_does_table_exist($table_name)
	{
		global $wpdb;
		return (bool) ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);
	}

	?>
