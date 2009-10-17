<?php

	function bfox_reader_check_option($name, $label) {
		$id = "option_$name";

		return "<input type='checkbox' name='$name' id='$id' class='view_option'/><label for='$id'>$label</label>";
	}

	function bfox_reader_options() {
		$table = new BfoxHtmlList();
		$table->add(bfox_reader_check_option('jesus', __('Show Jesus\' words in red')));
		$table->add(bfox_reader_check_option('paragraphs', __('Display verses as paragraphs')));
		$table->add(bfox_reader_check_option('verse_nums', __('Hide verse numbers')));
		$table->add(bfox_reader_check_option('footnotes', __('Hide footnote links')));

		return $table->content();
	}

	function bfox_reader_tools_tab(BfoxRefs $refs) {
		global $user_ID;

		$tool_tabs = new BfoxHtmlTabs("id='tool_tabs' class='tabs'");

		if (!empty($user_ID)) {
			$url = BfoxQuery::page_url(BfoxQuery::page_passage);
			$cboxes = array();
			$cboxes['blogs'] = new BfoxCboxBlogs($refs, $url, 'commentaries', 'Blog Posts');
			$cboxes['notes'] = new BfoxCboxNotes($refs, $url, 'notes', 'My Bible Notes');

			ob_start();
			$cboxes['blogs']->content();
			$post_count = ' (' . $cboxes['blogs']->post_count . ')';
			$blog_content = ob_get_clean();

			ob_start();
			$cboxes['notes']->content();
			$note_content = ob_get_clean();

			$tool_tabs->add('blogs', __('Blog Posts') /*. $post_count*/, $blog_content /*. "<a href='" . BfoxQuery::page_url(BfoxQuery::page_commentary) . "'>Manage Blog Commentaries</a>"*/);
			$tool_tabs->add('notes', __('Notes'), $note_content);
		}
		$tool_tabs->add('options', __('Options'), bfox_reader_options());

		return $tool_tabs->content();
	}

get_header();

?>
<div id="content">
<div class="passagecolumn">

<?php if (bp_bible_has_passages()) : ?>
	<div class="widget" id="bible-passages">
		<h2 class="widgettitle"><?php _e('Bible Passages') ?>: <?php echo bp_bible_the_ref_str() ?></h2>
		<div class="passages_info"><?php echo bp_bible_history_desc(' \a\t g:i a') ?>: <?php echo bp_bible_mark_read_link() ?></div>

		<!-- Passages -->
		<?php while (bp_bible_passages()) : bp_bible_the_passage(); ?>
		<div class='post'>
			<div class='passage-nav'><?php echo bp_bible_ref_link('prev') . bp_bible_ref_link('next') ?></div>
			<h4><?php echo bp_bible_the_ref_str() ?></h4>
			<div class='entry'>
				<?php echo bp_bible_the_passage_content() ?>
			</div>
		</div>
		<?php endwhile; ?>

		<!-- Footnotes -->
		<?php if ($footnotes = bp_bible_the_footnotes()): ?>
		<div class='post'>
			<h4><?php _e('Footnotes') ?></h4>
			<div class='entry'>
				<ul>
				<?php foreach ($footnotes as $footnote): ?>
					<li><?php echo $footnote ?></li>
				<?php endforeach ?>
				</ul>
			</div>
		</div>
		<?php endif ?>

		<!-- Passage Widgets -->
		<?php bp_bible_toc() ?>

		<?php if (is_user_logged_in()): ?>
			<div id="bible-passage-history" class="widget">
				<h2 class="widgettitle"><?php _e('My History for ') ?><?php echo bp_bible_the_ref_str() ?></h2>
				<?php bp_bible_history(array('refs' => bp_bible_the_refs(), 'style' => 'table')) ?>
			</div>
		<?php endif; ?>
	</div>
<?php endif ?>
</div>

	<!-- Passage Sidebar Widgets -->
	<div id="bible-sidebar">
		<?php if (is_user_logged_in()): ?>
		<div id="bible-friends-posts" class="widget">
			<h2 class="widgettitle"><?php _e('My Friends\' Blog Posts') ?></h2>
			<?php bp_bible_friends_posts() ?>
		</div>

		<div id="bible-write-post" class="widget">
			<h2 class="widgettitle"><?php _e('Write a post about this passage') ?></h2>
			<?php bp_bible_post_form() ?>
		</div>

		<div id="bible-current-readings" class="widget">
			<h2 class="widgettitle"><?php _e('My Current Readings') ?></h2>
			<?php bp_bible_current_readings(array('max' => 10)) ?>
		</div>

		<div id="bible-history" class="widget">
			<h2 class="widgettitle"><?php _e('My Bible History') ?></h2>
			<?php bp_bible_history() ?>
		</div>

		<?php else: ?>
		<div id="bible-about" class="widget">
			<h2 class="widgettitle"><?php _e('Share your thoughts with your friends...') ?></h2>
			<p><?php _e('Biblefox is all about writing blog posts about the Bible and sharing them with your friends. If you log in and find some friends, you can read any blog posts that they write about this scripture.') ?></p>
		</div>
		<?php endif; ?>

		<div id="bible-options" class="widget">
			<h2 class="widgettitle"><?php _e('Bible Options') ?></h2>
			<?php bp_bible_options() ?>
		</div>
	</div>

</div>


<?php get_footer(); ?>
