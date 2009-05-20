<?php

require_once BFOX_BIBLE_DIR . '/cbox_notes.php';

class BfoxPagePassage extends BfoxPage
{
	/**
	 * The bible references being used
	 *
	 * @var BibleRefs
	 */
	protected $refs;

	protected $history;

	protected $cboxes = array();

	public function __construct($ref_str, $trans_str = '')
	{
		$this->refs = RefManager::get_from_str($ref_str);

		// Get the passage history
		$this->history = BfoxHistory::get_history(5);
		if (!empty($this->history)) $last_viewed = current($this->history);

		if ($this->refs->is_valid()) {
			// If this isn't the same scripture we last viewed, update the read history to show that we viewed these scriptures
			if (empty($last_viewed) || ($this->refs->get_string() != $last_viewed->refs->get_string())) BfoxHistory::view_passage($this->refs);
		}
		else {
			// If we don't have a valid bible ref, we should use the history
			if (!empty($last_viewed)) $this->refs = $last_viewed->refs;
			// If there is no history, show Genesis 1
			else $this->refs = RefManager::get_from_str('Genesis 1');
		}

		$url = BfoxQuery::page_url(BfoxQuery::page_passage);
		$this->cboxes['notes'] = new BfoxCboxNotes($url, 'notes', 'My Bible Notes');

		parent::__construct($trans_str);
	}

	public function page_load() {
		foreach ($this->cboxes as $cbox) $cbox->page_load();
	}

	public function print_scripts($base_url)
	{
		?>
		<script type="text/javascript" src="<?php echo $base_url; ?>/wp-content/mu-plugins/biblefox/bible/jquery.cookie.js"></script>
		<?php
		parent::print_scripts($base_url);
	}

	public function get_title()
	{
		return $this->refs->get_string();
	}

	public function get_search_str()
	{
		return $this->refs->get_string(BibleMeta::name_short);
	}

	/**
	 * Outputs all the commentary posts for the given bible reference and user
	 *
	 * @param BibleRefs $refs
	 * @param integer $user_id
	 */
	public static function output_posts(BibleRefs $refs, $user_id = NULL) {
		// If no user, use the current user
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		global $wpdb;

		// Get the commentaries for this user
		$coms = BfoxCommentaries::get_for_user($user_id);

		$blog_ids = array();
		$internal_coms = array();
		foreach ($coms as $com)
			if (!empty($com->blog_id)) {
				$blog_ids []= $com->blog_id;
				$internal_coms []= $com;
			}

		// Output the posts for each commentary
		if (!empty($blog_ids)) {
			$blog_post_ids = BfoxPosts::get_post_ids_for_blogs($refs, $blog_ids);
			foreach ($internal_coms as $com) if (!empty($blog_post_ids[$com->blog_id])) {
				$post_ids = $blog_post_ids[$com->blog_id];
				$posts = array();

				switch_to_blog($com->blog_id);

				BfoxBlogQueryData::set_post_ids($post_ids);
				$query = new WP_Query(1);

				?>
				<div class="cbox_sub">
					<div class="cbox_head">
						<span class="box_right"><?php echo $query->post_count ?> posts</span>
						<a href="http://<?php echo $com->blog_url ?>"><?php echo $com->name ?></a>
					</div>
					<div class='cbox_body'>
					<?php while($query->have_posts()) :?>
						<?php $query->the_post() ?>
						<div class="cbox_sub_sub">
							<div class='cbox_head'><strong><?php the_title(); ?></strong> by <?php the_author() ?> (<?php the_time('F jS, Y') ?>)</div>
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

	private static function ref_content(BibleRefs $refs, Translation $translation, &$footnotes)
	{
		$visible = $refs->sql_where();
		$bcvs = BibleRefs::get_bcvs($refs->get_seqs());

		foreach ($bcvs as $book => $cvs)
		{
			$book_name = BibleMeta::get_book_name($book);
			$book_str = BibleRefs::create_book_string($book, $cvs);

			unset($ch1);
			foreach ($cvs as $cv)
			{
				if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
				list($ch2, $vs2) = $cv->end;
			}

			// Get the previous and next chapters as well
			$ch1 = max($ch1, BibleMeta::start_chapter);
			if ($ch2 >= BibleMeta::end_verse_min($book)) $ch2 = BibleMeta::end_verse_max($book);

			// Create the verse context string now before we get the surrounding chapters
			if ($ch1 == $ch2) $vs_context = "$book_name $ch1";
			else $vs_context = "$book_name $ch1-$ch2";
			if ($vs_context == $book_str) $vs_context = '';

			// Get the previous and next chapters as well
			$ch1 = max($ch1 - 1, BibleMeta::start_chapter);
			$ch2++;
			if ($ch2 >= BibleMeta::end_verse_min($book)) $ch2 = BibleMeta::end_verse_max($book);

			// Create the chapter context string now that we have the surrounding chapters
			if ($ch1 == $ch2) $ch_context = "$book_name $ch1";
			else $ch_context = "$book_name $ch1-$ch2";
			if (($ch_context == $book_str) || ($ch_context == $vs_context)) $ch_context = '';

			// Create the context links string
			$context = $book_str;
			if ($book_str != $book_name)
			{
				$links = array();
				if (!empty($vs_context)) $links []= "<a onclick='bfox_set_context_verses(this)'>$vs_context</a>";
				if (!empty($ch_context)) $links []= "<a onclick='bfox_set_context_chapters(this)'>$ch_context</a>";

				if (!empty($links)) $context = "<a onclick='bfox_set_context_none(this)'>$book_str</a> - Preview Context: " . implode(', ', $links);
			}

			$content .= "
				<div class='ref_partition'>
					<div class='partition_header box_menu'>$context</div>
					<div class='partition_body'>
						" . self::get_chapters_content($book, $ch1, $ch2, $visible, $footnotes, $translation) . "
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
		$history_table = new BfoxHtmlTable("class='widefat'");
		$history_table->add_header_row('', 3, 'Passage', 'Time', 'Edit');
		foreach ($this->history as $history) {
			$ref_str = $history->refs->get_string();

			if ($history->is_read) {
				$intro = __('Read on');
				$toggle = __('Mark as Unread');
			}
			else {
				$intro = __('Viewed on');
				$toggle = __('Mark as Read');
			}

			$history_table->add_row('', 3,
				"<a href='" . BfoxQuery::passage_page_url($ref_str, $this->translation) . "'>$ref_str</a>",
				"$intro $history->time",
				"<a href='" . BfoxQuery::toggle_read_url($history->time, BfoxQuery::page_url(BfoxQuery::page_passage)) . "'>" . $toggle . "</a>");
		}

		$ref_str = $this->refs->get_string();

		$footnotes = array();

		?>

		<div id="bible_passage">
			<div id="bible_note_popup"></div>
			<div class="roundbox">
				<div class="box_head">
					<?php echo $ref_str ?>
					<a id="verse_layout_toggle" class="button" onclick="bfox_toggle_paragraphs()">Switch to Verse View</a>
				</div>
					<div class="reference">
						<?php echo self::ref_content($this->refs, $this->translation, $footnotes); ?>
					</div>
					<div class="clear"></div>
				<div>
				</div>
				<div class="box_menu">
					<center>
						<?php echo $this->ref_toc($this->refs); ?>
					</center>
				</div>
			</div>
			<?php if (!empty($footnotes)): ?>
			<div class="roundbox">
				<div class="box_head">Footnotes</div>
				<div class="box_inside">
					<ul>
					<?php foreach ($footnotes as $index => $footnote): ?>
						<li><?php echo $footnote ?></li>
					<?php endforeach; ?>
					</ul>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<div id='history' class='cbox'>
			<div class='cbox_head'>Passage History</div>
			<div class='cbox_body box_inside'>
			<?php echo $history_table->content() ?>
			</div>
		</div>
		<?php
		echo $this->cboxes['notes']->cbox();
		?>
		<div id='commentaries' class='cbox'>
			<div class='cbox_head'>Commentaries</div>
			<div class='cbox_body'>
				<div class="commentary_list">
					<div class="commentary_list_head">
						Commentary Blog Posts (<a href="<?php echo BfoxQuery::page_url(BfoxQuery::page_commentary) ?>">edit</a>)
					</div>
					<?php self::output_posts($this->refs); ?>
					<?php //self::output_quick_press(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Return verse content for display in chapter groups
	 *
	 * @param integer $book
	 * @param integer $chapter1
	 * @param integer $chapter2
	 * @param string $visible
	 * @param array $footnotes
	 * @param Translation $trans
	 * @return string
	 */
	public static function get_chapters_content($book, $chapter1, $chapter2, $visible, &$footnotes, Translation $trans = NULL)
	{
		if (is_null($trans)) $trans = $GLOBALS['bfox_trans'];

		$content = '';
		$footnote_index = count($footnotes);

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

					// TODO3: Remove 'verse' attribute
					$sections[$index] .= "<span class='bible_verse' verse='$verse->verse_id'><b>$verse->verse_id</b> $verse->verse</span>\n";
				}
				$last_index = $index;

				if ($is_hidden_chapter)
				{
					$chapter_class = 'hidden_chapter';
					$chapter_content = $sections[1];

					// TODO3: Instead of removing footnotes, find a way to show them when showing hidden chapters
					// Remove any footnotes
					$ch_footnotes = BfoxUtility::find_footnotes($chapter_content);
					foreach (array_reverse($ch_footnotes) as $footnote) $chapter_content = substr_replace($chapter_content, '', $footnote[0], $footnote[1]);

					// Don't show a hidden rule immediately following a hidden chapter
					$add_rule = FALSE;
				}
				else
				{
					$chapter_class = 'visible_chapter';
					$chapter_content = '';
					foreach ($sections as $index => $section)
					{
						// Every odd numbered section is hidden
						if ($index % 2)
						{
							$chapter_content .= "<span class='hidden_verses'>\n$section\n</span>\n";

							// If we can add a rule, do it now
							// We don't want to add a rule for the last section, though
							if ($add_rule)// && ($last_index != $index))
							{
								$chapter_content .= "<hr class='hidden_verses_rule' />\n";

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

					$ch_footnotes = BfoxUtility::find_footnotes($chapter_content);
					$foot_count = count($ch_footnotes);
					if (0 < $foot_count)
					{
						foreach ($ch_footnotes as $index => $footnote)
						{
							$index += $footnote_index + 1;
							$footnotes[$index] = "<a name=\"footnote_$index\" href=\"#footnote_ref_$index\">[$index]</a> " . $footnote[2];
						}

						foreach (array_reverse($ch_footnotes, TRUE) as $index => $footnote)
						{
							$index += $footnote_index + 1;
							$chapter_content = substr_replace($chapter_content, "<a name='footnote_ref_$index' href='#footnote_$index' title='" . strip_tags($footnote[2]) . "'>[$index]</a>", $footnote[0], $footnote[1]);
						}

						$footnote_index += $foot_count;
					}
				}

				// TODO3: is h5 allowed in a span?
				$content .= "<span class='chapter $chapter_class'>\n<h5>$chapter_id</h5>\n$chapter_content</span>\n";
			}

		}

		return $content;
	}
}

?>