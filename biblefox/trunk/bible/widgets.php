<?php

class BfoxBibleWidget extends WP_Widget {

	/**
	 * @var BfoxRefs
	 */
	private $refs;

	public function __construct($id_base = false, $name, $widget_options = array(), $control_options = array()) {
		parent::__construct($id_base, $name, $widget_options, $control_options);
		$this->refs = new BfoxRefs();
	}
}

class BfoxFriendsPostsWidget extends BfoxBibleWidget {

	public function __construct() {
		parent::__construct(false, 'Bible - Friends\' Posts Widget');
	}

	public function widget($args, $instance) {
		extract($args);
		if (empty($refs)) $ref = new BfoxRefs;

		echo $before_widget . $before_title . $instance['title'] . $after_title;

		?>
		<div class="cbox_sub">
			<div class="cbox_head">
				<span class="box_right"><?php echo $post_count ?> posts</span>
				<a href="">My Friends' Blog Posts</a>
			</div>
			<div class='cbox_body'>
		<?php

		// If no user, use the current user
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		global $wpdb;

		$friend_ids = array(1, 2);

		$total_post_count = 0;

		// Output the posts for each commentary
		if (!empty($friend_ids)) {
			$user_post_ids = BfoxPosts::get_post_ids_for_users($refs, $friend_ids);
			foreach ($user_post_ids as $blog_id => $post_ids) {
				$posts = array();

				switch_to_blog($blog_id);

				if (!empty($post_ids)) {
					BfoxBlogQueryData::set_post_ids($post_ids);
					$query = new WP_Query(1);
					$post_count = $query->post_count;
				}
				else $post_count = 0;
				$total_post_count += $post_count;

				while(!empty($post_ids) && $query->have_posts()) :?>
					<?php $query->the_post() ?>
					<div class="cbox_sub_sub">
						<div class='cbox_head'><strong><?php the_title(); ?></strong> (<?php echo bfox_the_refs(BibleMeta::name_short) ?>) by <?php the_author() ?> (<?php the_time('F jS, Y') ?>)</div>
						<div class='cbox_body box_inside'>
							<h3><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h3>
							<small><?php the_time('F jS, Y') ?>  by <?php the_author() ?></small>
							<div class="post_content">
								<?php the_content('Read the rest of this entry &raquo;') ?>
								<p class="postmetadata"><?php the_tags('Tags: ', ', ', '<br />'); ?> Posted in <?php the_category(', ') ?> | <?php edit_post_link('Edit', '', ' | '); ?>  <?php comments_popup_link('No Comments &#187;', '1 Comment &#187;', '% Comments &#187;'); ?></p>
							</div>
						</div>
					</div>
				<?php endwhile;
				restore_current_blog();
			}
		}
		?>
			</div>
		</div>
		<?php
		echo $after_widget;
	}
}

?>