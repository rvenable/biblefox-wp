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
<div id="content" class="narrowcolumn">

	<div id="bible" class="">
		<div id="bible_page">
		<?php if (bp_bible_has_passages()) : ?>
			<?php echo bfox_reader_tools_tab(bp_bible_the_refs()) ?>
			<div class='ref_content'>

				<!-- Bible Header -->
				<div class='bible_page_head'>
					<?php _e('Bible Reader') ?> - <?php echo bp_bible_the_ref_str() ?><br/>
					<small><?php //echo $bible->mark_read_link() ?></small>
				</div>

				<!-- Passages -->
				<?php while (bp_bible_passages()) : bp_bible_the_passage(); ?>
				<div class='ref_seq'>
					<div class='ref_seq_head'>
						<span class='ref_seq_title'><?php echo bp_bible_ref_link('prev') . bp_bible_ref_link('next') . bp_bible_the_ref_str() ?></span>
					</div>
					<div class='ref_seq_body'>
						<?php echo bp_bible_the_passage_content() ?>
					</div>
				</div>
				<?php endwhile; ?>

				<!-- Footnotes -->
				<?php if ($footnotes = bp_bible_the_footnotes()): ?>
				<div class='ref_seq'>
					<div class='ref_seq_head'><?php _e('Footnotes') ?></div>
					<div class='ref_seq_body'>
						<ul>
						<?php foreach ($footnotes as $footnote): ?>
							<li><?php echo $footnote ?></li>
						<?php endforeach ?>
						</ul>
					</div>
				</div>
				<?php endif ?>

				<!-- Passage Widgets -->
				<?php if (!function_exists('dynamic_sidebar') || !dynamic_sidebar('bible-passage2')): ?>
				<div class="widget-error">
					<?php _e('Please log in and add widgets to this column.') ?> <a href="<?php echo get_option('siteurl') ?>/wp-admin/widgets.php?s=&amp;show=&amp;sidebar=bible-passage"><?php _e('Add Widgets') ?></a>
				</div>
				<?php endif; ?>

			</div>
			<div class="clear"></div>
		<?php endif ?>
		</div>
	</div>
</div>

	<!-- Passage Sidebar Widgets -->
	<div id="sidebar">
		<?php if (!function_exists('dynamic_sidebar') || !dynamic_sidebar('bible-passage-sidebar2')): ?>
		<div class="widget-error">
			<?php _e('Please log in and add widgets to this column.') ?> <a href="<?php echo get_option('siteurl') ?>/wp-admin/widgets.php?s=&amp;show=&amp;sidebar=bible-passage-sidebar"><?php _e('Add Widgets') ?></a>
		</div>
		<?php endif; ?>
	</div>


<?php //get_footer(); ?>
