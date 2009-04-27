<?php

/**
 * An abstract Class for holding basic data for individual commentaries
 *
 */
abstract class BfoxComm
{
	public $name, $feed_url, $blog_url, $is_enabled, $blog_id;

	/**
	 * Construct a BfoxComm
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
		// TODO2: wpautop was just added, but maybe we should be using the_content() instead.
		// This would require using the wp_query loop, but would make things more consistent.

		$refs = bfox_get_post_bible_refs($post->ID);
		?>
		<div class="post">
			<h3>
				<a href="<?php echo get_permalink($post->ID) ?>"><?php echo $post->post_title ?></a>
				by <?php echo get_author_name($post->post_author) ?> (<? echo $refs->get_string() ?>)
			</h3>
			<div class="post_content">
				<?php echo wpautop($post->post_content) ?>
			</div>
		</div>
		<?php
	}

}

/**
 * A Class for all commentary blogs that are stored internally on this site
 *
 */
class BfoxCommIn extends BfoxComm
{
	/**
	 * Construct an internal commentary
	 *
	 * @param integer $blog_id
	 * @param object $blog Blog data as returned by get_blog_details()
	 * @param boolean $is_enabled
	 */
	function __construct($blog_id, $blog, $is_enabled = TRUE)
	{
		$blog_url = $blog->domain . $blog->path;
		parent::__construct($blog->blogname, $blog_url . BfoxCommentaries::feed_url, $blog_url, $is_enabled, $blog_id);
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
class BfoxCommEx extends BfoxComm
{
	// TODO2: Implement BfoxCommEx construction
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

class BfoxCommentaries
{
	const feed_url = 'bible/feed/';
	const serial_prefix = 'bfx';

	/**
	 * Returns an array of BfoxComm instances for each of the user's commentaries
	 *
	 * @param integer $user_id
	 * @return array Array of BfoxComm instances
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

		return $coms;
	}

	/**
	 * Sets an array of BfoxComm instances to the user's options
	 *
	 * @param array $coms Array of BfoxComm instances
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
	 * @param string $url
	 * @param integer $user_id
	 * @return string Message to output to user
	 */
	public static function add_url($url, $user_id = NULL)
	{
		if (!empty($url))
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
				$com = new BfoxCommIn($blog_id, $blog);
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

				return "Added commentary: '$com->name'";
			}
		}

		return "Failed to add commentary using URL: $url";
	}

	/**
	 * Removes the given blog ids from the user's commentaries
	 *
	 * @param array $blog_ids
	 * @param integer $user_id
	 */
	public static function update($enabled_ids, $delete_ids, $user_id = NULL)
	{
		$messages = array();

		// If no user, use the current user
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		// Get the commentaries for this user
		$coms = self::get_for_user($user_id);

		// Delete any commentaries
		$delete_ids = (array) $delete_ids;
		foreach ($delete_ids as $blog_id)
		{
			$messages []= "Deleted '{$coms[$blog_id]->name}'";
			unset($coms[$blog_id]);
		}

		// Update the enabled flags
		$enabled_ids = array_fill_keys((array) $enabled_ids, TRUE);
		foreach ($coms as &$com)
		{
			$enabled = isset($enabled_ids[$com->blog_id]);
			if ($com->is_enabled && !$enabled) $messages []= "Disabled '{$com->name}'";
			elseif (!$com->is_enabled && $enabled) $messages []= "Enabled '{$com->name}'";
			$com->is_enabled = $enabled;
		}

		// Set the commentaries for this user
		self::set_for_user($coms, $user_id);

		return $messages;
	}
}

?>