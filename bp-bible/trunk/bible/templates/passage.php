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

//get_header();
?>
<div id="content" class="passagecolumn">

<?php if (bp_bible_has_passages()) : ?>
	<div class="widget" id="bible-passages">
		<h2 class="widgettitle"><?php _e('Bible Passages') ?>: <?php echo bp_bible_the_ref_str() ?></h2>
		<div class="passages_info"><?php echo bp_bible_history_desc(' \a\t g:i a') ?>: <?php echo bp_bible_mark_read_link() ?></div>

		<!-- Passages -->
		<?php while (bp_bible_passages()) : bp_bible_the_passage(); ?>
		<div class='post'>
			<div class='passage-nav'><?php echo bp_bible_passage_ref_link('prev') . bp_bible_passage_ref_link('next') ?></div>
			<h3><?php echo bp_bible_the_ref_str() ?></h3>
			<div class='entry'>
				<?php echo bp_bible_the_passage_content() ?>
			</div>
		</div>
		<?php endwhile; ?>

		<!-- Footnotes -->
		<?php if ($footnotes = bp_bible_the_footnotes()): ?>
		<div class='post'>
			<h3><?php _e('Footnotes') ?></h3>
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
		<?php if (!function_exists('dynamic_sidebar') || !dynamic_sidebar('bible-passage-bottom')): ?>
		<div class="widget-error">
			<?php _e('Please log in and add widgets to this column.') ?> <a href="<?php echo get_option('siteurl') ?>/wp-admin/widgets.php?s=&amp;show=&amp;sidebar=bible-passage-bottom"><?php _e('Add Widgets') ?></a>
		</div>
		<?php endif; ?>
	</div>
<?php endif ?>
</div>

	<!-- Passage Sidebar Widgets -->
	<div id="bible-sidebar">
		<?php if (!function_exists('dynamic_sidebar') || !dynamic_sidebar('bible-passage-side')): ?>
		<div class="widget-error">
			<?php _e('Please log in and add widgets to this column.') ?> <a href="<?php echo get_option('siteurl') ?>/wp-admin/widgets.php?s=&amp;show=&amp;sidebar=bible-passage-side"><?php _e('Add Widgets') ?></a>
		</div>
		<?php endif; ?>
	</div>


<?php //get_footer(); ?>
