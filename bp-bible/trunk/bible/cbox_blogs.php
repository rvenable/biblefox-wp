<?php

class BfoxCboxBlogs extends BfoxCbox {

	// HACK: this post_count method is pretty hacky
	public $post_count = 0;

	public function content() {
		$this->output_posts($this->refs);
	}

	/**
	 * Outputs all the commentary posts for the given bible reference and user
	 *
	 * @param BfoxRefs $refs
	 * @param integer $user_id
	 */
	private function output_posts(BfoxRefs $refs, $user_id = NULL) {

		require_once BFOX_BIBLE_DIR . '/widgets.php';
		$widget = new BfoxFriendsPostsWidget();
		$widget->widget(array('refs' => $refs), array());

		return;

		// If no user, use the current user
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		global $wpdb;

		// Get the commentaries for this user
		$coms = BfoxCommentaries::get_for_user($user_id);

		$blog_ids = array();
		$internal_coms = array();
		foreach ($coms as $com) if (!empty($com->blog_id)) {
			$blog_ids []= $com->blog_id;
			$internal_coms []= $com;
		}

		$ref_str = $refs->get_string();

		// Output the posts for each commentary
		if (!empty($blog_ids)) {
			$blog_post_ids = BfoxPosts::get_post_ids_for_blogs($refs, $blog_ids);
			foreach ($internal_coms as $com) {
				$post_ids = $blog_post_ids[$com->blog_id];
				$posts = array();

				switch_to_blog($com->blog_id);

				if (!empty($post_ids)) {
					BfoxBlogQueryData::set_post_ids($post_ids);
					$query = new WP_Query(1);
					$post_count = $query->post_count;
				}
				else $post_count = 0;
				$this->post_count += $post_count;

				if (current_user_can('edit_posts')) $add_post = BfoxBlog::ref_write_link($ref_str, 'Add post', "http://$com->blog_url");
				else $add_post = '';

				?>
				<div class="cbox_sub">
					<div class="cbox_head">
						<span class="box_right"><?php echo $post_count ?> posts <?php echo $add_post ?></span>
						<a href="http://<?php echo $com->blog_url ?>"><?php echo $com->name ?></a>
					</div>
					<div class='cbox_body'>
					<?php while(!empty($post_ids) && $query->have_posts()) :?>
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
					<?php endwhile ?>
					</div>
				</div>
				<?php
				restore_current_blog();
			}
		}
	}
}

?>