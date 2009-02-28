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

	private $page;
	private $refs;

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

	/**
	 * Called before loading the bible page
	 *
	 */
	public function page_load($args)
	{
		global $bfox_trans, $bfox_links, $bfox_bible_page;

		// Override the global translation using the translation passed in
		// TODO3: Do we really need to override the global translation?
		if (!empty($args[Bible::var_translation])) $bfox_trans = Translations::get_translation($args[Bible::var_translation]);

		// Get the bible page to view
		$page = $args[Bible::var_page];

		// If there is search text, we should search
		if (!empty($args[Bible::var_search])) $page = Bible::page_search;

		if (Bible::page_search == $page)
		{
			// Try to get some search text
			$search_text = (string) $args[Bible::var_search];

			// If there is no bible reference, see if this search string is actually a bible reference
			if (empty($args[Bible::var_reference]))
			{
				// If it is a valid bible reference, show the bible passage page instead of the search page
				$refs = RefManager::get_from_str($args[Bible::var_search]);
				if ($refs->is_valid()) $page = Bible::page_passage;
			}
		}

		switch ($page)
		{
			case Bible::page_commentary:
				Commentaries::manage_page_load();
				break;
		}

		$this->page = $page;
		if (isset($refs)) $this->refs = $refs;
	}

	public function page()
	{
		global $bfox_trans, $bfox_links;

		?>
		<div id="bible" class="">
			<div id="bible_bar" class="roundbox">
				<div class="box_head">Bible Viewer</div>
				<div class="box_inside">
					<ul id="bible_page_list">
						<li><a href="<?php echo $bfox_links->bible_page_url(Bible::page_passage) ?>">Passage</a></li>
						<li><a href="<?php echo $bfox_links->bible_page_url(Bible::page_commentary) ?>">Commentaries</a></li>
					</ul>
					<form id="bible_search_form" action="admin.php" method="get">
						<input type="hidden" name="page" value="<?php echo BFOX_BIBLE_SUBPAGE; ?>" />
						<input type="hidden" name="<?php echo Bible::var_page ?>" value="<?php echo Bible::page_search; ?>" />
						<input type="text" name="<?php echo Bible::var_search ?>" value="" />
						<input type="submit" value="<?php _e('Search Bible', BFOX_DOMAIN); ?>" class="button" />
					</form>
				</div>
			</div>
			<div id="bible_page">
			<?php
				switch ($this->page)
				{
					case Bible::page_search:
						include('bible-search.php');
						break;
					case Bible::page_commentary:
						Commentaries::manage_page();
						break;
					case Bible::page_history:
					case Bible::page_passage:
					default:
						$refs = $this->refs;
						include('bible-passage.php');
				}
			?>
			</div>
		</div>
		<?php
	}
}

?>