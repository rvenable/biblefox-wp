<?php get_header() ?>
<div id="content">
<div class="passagecolumn">

<?php if (bp_bible_has_passages()) : ?>
	<div class="widget" id="bible-passages">
		<h2 class="widgettitle"><?php _e('Bible Passages') ?>: <?php echo bp_bible_the_ref_str() ?></h2>

		<!-- Passages -->
		<?php while (bp_bible_passages()) : bp_bible_the_passage(); ?>
		<div class="cbox_sub_sub">
			<div class='cbox_head'><strong><?php echo bp_bible_the_ref_str() ?></strong> last read ..</div>
			<div class="passages_info"><?php echo bp_bible_history_desc(' \a\t g:i a') ?>: <?php echo bp_bible_mark_read_link() ?></div>
			<div class='cbox_body'>
				<div class="post">
					<div class='passage-nav'><?php echo bp_bible_ref_link('prev') . bp_bible_ref_link('next') ?></div>
					<h4><?php echo bp_bible_the_ref_str() ?></h4>
					<div class='entry'>
						<?php echo bp_bible_the_passage_content() ?>
					</div>
				</div>
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
						<?php bp_bible_history_list(array('refs' => bp_bible_the_refs(), 'style' => 'table', 'limit' => 10)) ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endwhile; ?>


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
			<?php bp_bible_history_list(array('limit' => 10)) ?>
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
