<?php

class Bible
{
	const page_passage = 'passage';
	const page_commentary = 'commentary';
	const page_search = 'search';
	const page_history = 'history';

	const var_page = 'bible_page';
	const var_translation = 'trans_id';
	const var_reference = 'bible_ref';
	const var_search = 'bible_search';

	private static $url = '';

	public static function set_url($url = '')
	{
		if (empty($url)) $url = get_option('home') . '/wp-admin?page=' . BFOX_BIBLE_SUBPAGE;
		self::$url = $url;
	}

	public static function page_url($page)
	{
		return add_query_arg(self::var_page, $page, self::$url);
	}

	public static function search_page_url($search_text, $ref_str = '', Translation $display_translation = NULL)
	{
		$url = add_query_arg(self::var_search, $search_text, self::page_url(self::page_search));
		if (!empty($ref_str)) $url = add_query_arg(self::var_reference, $ref_str, $url);
		if (!is_null($display_translation)) $url = add_query_arg(self::var_translation, $display_translation->id, $url);

		return $url;
	}

	public static function passage_page_url($ref_str, Translation $translation = NULL)
	{
		$url = add_query_arg(self::var_reference, $ref_str, self::page_url(self::page_passage));
		if (!is_null($translation)) $url = add_query_arg(self::var_translation, $translation->id, $url);

		return $url;
	}

	public static function output_quick_press()
	{
		global $user_ID;
		// This is an imitation of the QuickPress code from wp_dashboard_quick_press()
		$user_blogs = get_blogs_of_user($user_ID);
		pre($blogs);

		$blogs = array();
		foreach ($user_blogs as $blog_id => $blog)
		{
			switch_to_blog($blog_id);
			if (current_user_can('edit_posts'))
			{
				if (current_user_can('publish_posts'))
					$blog->publish_string = __('Publish');
				else
					$blog->publish_string = __('Submit for Review');

				$blog->quick_press_url = clean_url(admin_url('post.php'));

				$blogs[] = $blog;
			}
			restore_current_blog();
		}

		?>
		<div class="biblebox">
			<div class="box_head">Write a commentary post</div>
			<?php if (isset($blogs[0])): ?>
			<form name="post" action="<?php echo $blogs[0]->quick_press_url ?>" method="post" id="quick_press">
				<div class="box_inside">
					<div class="quick_write_input">
						<h4 id="quick-post-blog"><label for="blog"><?php _e('Blog') ?></label></h4>
						<select name="blog_id" id="blog" tabindex="1" onchange="eval(this.value)">
						<?php foreach ($blogs as $index => $blog): ?>
							<option value="<?php echo "bfox_quick_write_set_blog('$blog->quick_press_url', '$blog->publish_string')" ?>" <?php if (0 == $index) echo 'selected' ?>><?php echo $blog->blogname ?></option>
						<?php endforeach; ?>
						</select>
					</div>
					<div class="quick_write_input">
						<h4 id="quick-post-title"><label for="title"><?php _e('Title') ?></label></h4>
						<input type="text" name="post_title" id="title" tabindex="1" value="" />
					</div>
					<div class="quick_write_input">
						<h4 id="content-label"><label for="content"><?php _e('Content') ?></label></h4>
						<textarea name="content" id="content" class="mceEditor" rows="3" cols="15" tabindex="2"></textarea>
					</div>
					<div class="quick_write_input">
						<h4><label for="tags-input"><?php _e('Tags') ?></label></h4>
						<input type="text" name="tags_input" id="tags-input" tabindex="3" value="" />
					</div>
				</div>
				<div class="box_menu">
					<input type="hidden" name="action" id="quickpost-action" value="post-quickpress-save" />
					<input type="hidden" name="quickpress_post_ID" value="0" />
					<?php wp_nonce_field('add-post'); ?>
					<input type="submit" name="save" id="save-post" class="button" tabindex="4" value="<?php _e('Save Draft'); ?>" />
					<input type="reset" value="<?php _e( 'Cancel' ); ?>" class="button" />
					<span class="box_right">
					<input type="submit" name="publish" id="publish" accesskey="p" tabindex="5" class="button-primary" value="<?php echo $blogs[0]->publish_string ?>" />
					</span>
					<br class="clear" />
				</div>
			</form>
			<?php else: ?>
			<div class="box_inside">
				You have no blogs to post to.
			</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

?>