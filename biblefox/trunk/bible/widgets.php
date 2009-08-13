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

		// If no user, use the current user
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		$friends_url = bp_core_get_user_domain($user_id) . 'friends/my-friends/all-friends';

		?>
		<div class="cbox_sub">
			<div class="cbox_head">
				<a href="<?php echo $friends_url ?>">My Friends' Blog Posts</a>
			</div>
			<div class='cbox_body'>
		<?php

		$total_post_count = 0;

		$friend_ids = array();
		if (class_exists(BP_Friends_Friendship)) {
			$friend_ids = BP_Friends_Friendship::get_friend_user_ids($user_id);

			$mem_dir_url = bp_core_get_root_domain() . '/members/';

			if (!empty($friend_ids)) {
				global $wpdb;

				// Add the current user to the friends so that we get his posts as well
				$friend_ids []= $user_id;

				$user_post_ids = BfoxPosts::get_post_ids_for_users($refs, $friend_ids);

				if (!empty($user_post_ids)) foreach ($user_post_ids as $blog_id => $post_ids) {
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
				else {
					printf(__('None of your friends have written any posts about %s.
					You can %s.
					You can also find more friends using the %s.'),
					$refs->get_string(),
					"<a href='$write_url'>" . __('write your own post') . "</a>",
					"<a href='$mem_dir_url'>" . __('members directory') . "</a>");
				}
			}
			else {
				printf(__('This menu shows you any blog posts written by your friends about this passage.
				You don\'t currently have any friends. That\'s okay, because you can find some friends using our %s.'),
				"<a href='$mem_dir_url'>" . __("members directory") . "</a>");
			}
		}
		else {
			_e('This widget requires BuddyPress.');
		}

		?>
			</div>
		</div>
		<?php
		echo $after_widget;
	}
}

?>