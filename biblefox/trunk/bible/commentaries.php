<?php

/**
 * An abstract Class for holding basic data for individual commentaries
 *
 */
abstract class Commentary
{
	public $name, $feed_url, $blog_url, $is_enabled, $blog_id;

	/**
	 * Construct a Commentary
	 *
	 * @param string $name
	 * @param string $feed_url
	 * @param string $blog_url
	 * @param boolean $is_enabled
	 * @param integer $blog_id
	 */
	function __construct($name, $feed_url, $blog_url, $is_enabled, $blog_id)
	{
		$this->name = $name;
		$this->feed_url = $feed_url;
		$this->blog_url = $blog_url;
		$this->is_enabled = $is_enabled;
		$this->blog_id = $blog_id;
	}

	/**
	 * Output all of this commentary's posts for the given bible references
	 *
	 * @param BibleRefs $refs
	 */
	abstract public function output_posts(BibleRefs $refs);

	/**
	 * Output an individual commentary post
	 *
	 * @param object $post Object with post data as returned by bfox_get_posts_for_refs()
	 */
	public static function output_post($post)
	{
		$refs = bfox_get_post_bible_refs($post->ID);
		?>
		<div class="post">
			<h3>
				<a href="<?php echo get_permalink($post->ID) ?>"><?php echo $post->post_title ?></a>
				by <?php echo get_author_name($post->post_author) ?> (<? echo $refs->get_string() ?>)
			</h3>
			<div class="post_content">
				<?php echo $post->post_content ?>
			</div>
		</div>
		<?php
	}

}

/**
 * A Class for all commentary blogs that are stored internally on this site
 *
 */
class InternalCommentary extends Commentary
{
	/**
	 * Construct an internal commentary
	 *
	 * @param integer $blog_id
	 * @param object $blog Blog data as returned by get_blog_details()
	 * @param boolean $is_enabled
	 */
	function __construct($blog_id, $blog, $is_enabled)
	{
		$blog_url = $blog->domain . $blog->path;
		parent::__construct($blog->blogname, $blog_url . Commentaries::feed_url, $blog_url, $is_enabled, $blog_id);
	}

	/**
	 * Output all of this commentary's posts for the given bible references
	 *
	 * @param BibleRefs $refs
	 */
	public function output_posts(BibleRefs $refs)
	{
		switch_to_blog($this->blog_id);
		$posts = bfox_get_posts_for_refs($refs);

		?>
		<div class="biblebox">
			<div class="box_head">
				<span class="box_right"><?php echo count($posts) ?> posts</span>
				<a href="http://<?php echo $this->blog_url ?>"><?php echo $this->name ?></a>
			</div>
			<?php foreach ($posts as $post) self::output_post($post);?>
		</div>
		<?php
		restore_current_blog();
	}
}

/**
 * A Class for all commentary blogs that are stored externally on some other site, and therefore need to
 * access a web feed for the post data
 *
 */
class ExternalCommentary extends Commentary
{
	// TODO2: Implement ExternalCommentary construction
	function __construct($name, $url, $is_enabled)
	{
	}

	/**
	 * Output all of this commentary's posts for the given bible references
	 *
	 * @param BibleRefs $refs
	 */
	public function output_posts(BibleRefs $refs)
	{
		?>
		<li class="blog_com blog_com_loading postbox">
			<h3 class="blog_com_title"><a href="http://<?php echo $this->blog_url ?>"><?php echo $this->name ?></a>
			<span class="blog_com_status">Loading...</span>
			</h3>
		</li>
		<?php
	}
}

/**
 * Class for managing Blog Commentaries
 *
 */
class Commentaries
{
	const page = 'bfox-my-commentaries';
	const feed_url = 'bible/feed/';
	const serial_prefix = 'bfx';

	/**
	 * Outputs all the commentary posts for the given bible reference and user
	 *
	 * @param BibleRefs $refs
	 * @param integer $user_id
	 */
	public static function output_posts(BibleRefs $refs, $user_id = NULL)
	{
		// If no user, use the current user
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		// Get the commentaries for this user
		$coms = self::get_for_user($user_id);

		// Output the posts for each commentary
		foreach ($coms as $com) $com->output_posts($refs);
	}

	/**
	 * Returns an array of Commentary instances for each of the user's commentaries
	 *
	 * @param integer $user_id
	 * @return array Array of Commentary instances
	 */
	public static function get_for_user($user_id = NULL)
	{
		// If no user, use the current user
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		// Get the user's commentaries
		$value = get_user_option('bfox_commentaries', $user_id);

		// HACK: We have to unserialize the commentaries ourselves, because get_user_option() is somehow not able to
		// instantiate the commentary classes. To prevent get_user_option from unserializing, we prepend the serialized
		// string with a self::serial_prefix (see set_for_user()).
		if (is_string($value) && !empty($value)) $coms = unserialize(substr($value, strlen(self::serial_prefix)));
		else $coms = array();

		// Get this user's blogs and make sure they are all in our commentary list
		// If we find any that aren't we need to add them to our list
		$blogs = (array) get_blogs_of_user($user_id);
		foreach ($blogs as $blog)
		{
			if (!isset($coms[$blog->userblog_id]))
			{
				$coms[$blog->userblog_id] = new InternalCommentary($blog->userblog_id, $blog, TRUE);
			}
		}

		return $coms;
	}

	/**
	 * Sets an array of Commentary instances to the user's options
	 *
	 * @param array $coms Array of Commentary instances
	 * @param integer $user_id
	 */
	private static function set_for_user($coms, $user_id)
	{
		// HACK: We have to unserialize the commentaries ourselves (see get_for_user()), because get_user_option() is
		// somehow not able to instantiate the commentary classes. To prevent get_user_option from unserializing, we
		// prepend the serialized string with a self::serial_prefix.
		update_user_option($user_id, 'bfox_commentaries', self::serial_prefix . serialize($coms), TRUE);
	}

	/**
	 * Updates a user's commentaries using input from the My Commentaries form
	 *
	 * @param string $name
	 * @param string $url
	 * @param boolean $is_enabled
	 * @param integer $user_id
	 * @return string Message to output to user
	 */
	private static function update_from_form($name, $url, $is_enabled, $user_id = NULL)
	{
		// If no user, use the current user
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		// Parse the url
		$parsed = parse_url($url);

		// If the url had no scheme, try parsing it again with the http scheme
		if (!isset($parsed['scheme'])) $parsed = parse_url("http://$url");

		// Get the parsed domain and path
		$domain = $parsed['host'];
		$path = $parsed['path'];

		// We need to have the path for the blog, so if there is a /bible/feed attached, remove it
		if ($pos = strpos($path, self::feed_url)) $path = substr($path, 0, $pos);

		// If the URL is for a blog on this site, add it as an internal commentary
		if ($blog_id = get_blog_id_from_url($domain, $path))
		{
			$blog = get_blog_details($blog_id);
			$com = new InternalCommentary($blog_id, $blog, $is_enabled);
		}

		// If we successfully created a commentary with a valid blog id, then we can save it to the user's commentary list
		if (isset($com) && !empty($com->blog_id))
		{
			// Get the commentaries for this user
			$coms = self::get_for_user($user_id);

			// Update the commentary
			$coms[$com->blog_id] = $com;

			// Set the commentaries for this user
			self::set_for_user($coms, $user_id);

			return 'Commentary updated.';
		}

		return 'Commentary updated failed.';
	}

	/**
	 * Removes the given blog ids from the user's commentaries
	 *
	 * @param array $blog_ids
	 * @param integer $user_id
	 */
	private static function delete_for_user($blog_ids, $user_id = NULL)
	{
		// TODO2: This function will delete info for any blog, even a blog that a user belongs too.
		// However, blogs that the user belongs to will always be re-added via the get_for_user() when
		// displaying the user's commentaries, so it is currently pointless to delete them.

		// If no user, use the current user
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		$blog_ids = (array) $blog_ids;

		// Get the commentaries for this user
		$coms = self::get_for_user($user_id);

		// Update the commentary
		foreach ($blog_ids as $blog_id) unset($coms[$blog_id]);

		// Set the commentaries for this user
		self::set_for_user($coms, $user_id);
	}

	/**
	 * Called before loading the manage commentaries admin page
	 *
	 * Performs all the user's management edit requests before loading the page
	 *
	 */
	public static function manage_page_load()
	{
		$bfox_page_url = add_query_arg(BfoxQuery::var_page, BfoxQuery::page_commentary,  'admin.php?page=' . BFOX_BIBLE_SUBPAGE);

		$action = $_POST['action'];
		if ( isset($_POST['deleteit']) && isset($_POST['delete']) )
			$action = 'bulk-delete';

		switch($action)
		{
		case 'update':

			check_admin_referer('update-commentary');

			$message = self::update_from_form($_POST['name'], $_POST['url'], isset($_POST['is_enabled']) ? TRUE : FALSE);

			wp_redirect(add_query_arg(array('message' => urlencode($message)), $bfox_page_url));

			exit;
		break;

		case 'bulk-delete':
			check_admin_referer('bulk-commentaries');

			self::delete_for_user($_POST['delete']);

			wp_redirect(add_query_arg('message', urlencode('Commentaries deleted.'), $bfox_page_url));

			exit;
		break;
		}
	}

	/**
	 * Outputs the commentary management admin page
	 *
	 */
	public static function manage_page()
	{
		$bfox_page_url = add_query_arg(BfoxQuery::var_page, BfoxQuery::page_commentary,  'admin.php?page=' . BFOX_BIBLE_SUBPAGE);

		if (!empty($_GET['message'])): ?>
			<div id="message" class="updated fade"><p><?php echo urldecode($_GET['message']); ?></p></div>
			<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
		endif;

		include('manage-commentaries.php');
	}
}

?>