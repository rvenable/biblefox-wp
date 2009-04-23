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
		parent::__construct($blog->blogname, $blog_url . BfoxPageCommentaries::feed_url, $blog_url, $is_enabled, $blog_id);
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

?>