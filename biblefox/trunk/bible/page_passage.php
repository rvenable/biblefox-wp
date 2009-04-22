<?php

class BfoxPagePassage extends BfoxPage
{
	/**
	 * The bible references being used
	 *
	 * @var BibleRefs
	 */
	protected $refs;

	public function __construct($ref_str, $trans_str = '')
	{
		$this->refs = RefManager::get_from_str($ref_str);

		// If we don't have a valid bible ref, we should just create a bible reference
		if (!$this->refs->is_valid()) $this->refs = RefManager::get_from_str('Genesis 1');

		parent::__construct($trans_str);
	}

	public function get_title()
	{
		return $this->refs->get_string();
	}

	public function get_search_str()
	{
		return $this->refs->get_string(BibleMeta::name_short);
	}

	private static function ref_content(BibleRefs $refs, Translation $translation)
	{
		$visible = $refs->sql_where();
		$bcvs = BibleRefs::get_bcvs($refs->get_seqs());

		foreach ($bcvs as $book => $cvs)
		{
			$book_str = BibleRefs::create_book_string($book, $cvs);

			unset($ch1);
			foreach ($cvs as $cv)
			{
				if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
				list($ch2, $vs2) = $cv->end;
			}

			// Get the previous and next chapters as well
			$ch1 = max($ch1 - 1, BibleMeta::start_chapter);
			$ch2++;
			if ($ch2 >= BibleMeta::end_verse_min($book)) $ch2 = BibleMeta::end_verse_max($book);

			$content .= "
				<div class='ref_partition'>
					<div class='partition_header box_menu'>" . $book_str . " (Context:
						<a onclick='bfox_set_context_none(this)'>none</a>
						<a onclick='bfox_set_context_verses(this)'>verses</a>
						<a onclick='bfox_set_context_chapters(this)'>chapters</a>)
					</div>
					<div class='partition_body'>
						" . self::get_chapters_content($book, $ch1, $ch2, $visible, $translation) . "
					</div>
				</div>
				";
		}

		return $content;
	}

	private function ref_toc(BibleRefs $refs)
	{
		$bcvs = BibleRefs::get_bcvs($refs->get_seqs());

		foreach ($bcvs as $book => $cvs)
		{
			$book_name = BibleMeta::get_book_name($book);
			$end_chapter = BibleMeta::end_verse_max($book);

			?>
			<?php echo $book_name ?>
			<ul class='flat_toc'>
			<?php for ($ch = BibleMeta::start_chapter; $ch <= $end_chapter; $ch++): ?>
				<li><a href='<?php echo BfoxQuery::passage_page_url("$book_name $ch", $this->translation) ?>'><?php echo $ch ?></a></li>
			<?php endfor; ?>
			</ul>
			<?php
		}
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

	public function content()
	{
		global $bfox_history, $bfox_quicknote, $bfox_viewer;

		$bfox_quicknote->set_biblerefs($this->refs);

		$ref_str = $this->refs->get_string();

		?>

		<div id="bible_passage">
			<div id="bible_note_popup"></div>
			<div class="roundbox">
				<div class="box_head">
					<?php echo $ref_str ?>
					<a id="verse_layout_toggle" class="button" onclick="bfox_toggle_paragraphs()">Switch to Verse View</a>
				</div>
				<div>
					<div class="commentary_list">
						<div class="commentary_list_head">
							Commentary Blog Posts (<a href="<?php echo BfoxQuery::page_url(BfoxQuery::page_commentary) ?>">edit</a>)
						</div>
						<?php Commentaries::output_posts($this->refs); ?>
						<?php //self::output_quick_press(); ?>
					</div>
					<div class="reference">
						<?php echo self::ref_content($this->refs, $this->translation); ?>
					</div>
					<div class="clear"></div>
				</div>
				<div class="box_menu">
					<center>
						<?php echo $this->ref_toc($this->refs); ?>
					</center>
				</div>
			</div>
		</div>

		<?php

		// Update the read history to show that we viewed these scriptures
		$bfox_history->update($this->refs);
	}

	/**
	 * Return verse content for display in chapter groups
	 *
	 * @param integer $book
	 * @param integer $chapter1
	 * @param integer $chapter2
	 * @param string $visible
	 * @param Translation $trans
	 * @return string
	 */
	public static function get_chapters_content($book, $chapter1, $chapter2, $visible, Translation $trans = NULL)
	{
		if (is_null($trans)) $trans = $GLOBALS['bfox_trans'];

		$content = '';

		$book_name = BibleMeta::get_book_name($book);

		// Get the verse data from the bible translation
		$chapters = $trans->get_chapter_verses($book, $chapter1, $chapter2, $visible);

		if (!empty($chapters))
		{
			// We don't want to start with a hidden rule
			$add_rule = FALSE;

			foreach ($chapters as $chapter_id => $verses)
			{
				$is_hidden_chapter = TRUE;
				$prev_visible = TRUE;
				$index = 0;

				$sections = array();

				foreach ($verses as $verse)
				{
					if (0 == $verse->verse_id) continue;

					if ($verse->visible) $is_hidden_chapter = FALSE;

					if ($prev_visible != $verse->visible) $index++;
					$prev_visible = $verse->visible;

					$sections[$index] .= "
						<span class='bible_verse' verse='$verse->verse_id'>
							<b>$verse->verse_id</b>
							$verse->verse
						</span>
						";
				}
				$last_index = $index;

				if ($is_hidden_chapter)
				{
					$content .= "
						<span class='chapter hidden_chapter'>
							<h5>$chapter_id</h5>
							$sections[1]
						</span>
						";

					// Don't show a hidden rule immediately following a hidden chapter
					$add_rule = FALSE;
				}
				else
				{
					$chapter_content = '';
					foreach ($sections as $index => $section)
					{
						// Every odd numbered section is hidden
						if ($index % 2)
						{
							$chapter_content .= "
								<span class='hidden_verses'>
									$section
								</span>
								";

							// If we can add a rule, do it now
							// We don't want to add a rule for the last section, though
							if ($add_rule)// && ($last_index != $index))
							{
								$chapter_content .= "<hr class='hidden_verses_rule' />";

								// Don't add a rule immediately after this one
								$add_rule = FALSE;
							}
						}
						else
						{
							$chapter_content .= $section;

							// We only want to add a rule if the previous section was not hidden
							$add_rule = TRUE;
						}
					}

					$content .= "
						<span class='chapter visible_chapter'>
							<h5>$chapter_id</h5>
							$chapter_content
						</span>
						";
				}

			}

		}

		// Fix the footnotes
		// TODO3: this function does more than just footnotes
		$content = bfox_special_syntax($content);

		return $content;
	}
}

?>