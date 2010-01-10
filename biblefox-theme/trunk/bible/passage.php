<?php get_header() ?>
<div id="content">
<div class="passagecolumn">

<?php if (bp_bible_has_passages()) : ?>
	<div class="widget" id="bible-passages">
		<h2 class="widgettitle"><?php _e('Bible Passages') ?>: <?php echo bp_bible_the_ref_str() ?></h2>
		<div class="passages_info"><?php echo bp_bible_history_desc(' \a\t g:i a') ?>: <?php echo bp_bible_mark_read_link() ?></div>

		<!-- Passages -->
	<?php while (bp_bible_passages()) : bp_bible_the_passage(); ?>
		<div class="post">
			<div class='passage-nav'><?php echo bp_bible_passage_ref_link('prev') . bp_bible_passage_ref_link('next') ?></div>
			<?php $iframe = new BfoxIframe(bp_bible_the_refs()) ?>
			<div class="bfox-iframe-wrap bfox-passage-iframe-wrap">
				<select class="bfox-iframe-select bfox-passage-iframe-select">
					<?php echo $iframe->select_options() ?>
				</select>
				<iframe class="bfox-iframe bfox-passage-iframe" src="<?php echo $iframe->url() ?>"></iframe>
			</div>
		</div>
		<!-- Passage Widgets -->
		<?php bp_bible_toc() ?>

		<?php if (is_user_logged_in()): ?>
			<div id="bible-passage-history" class="widget">
				<h2 class="widgettitle"><?php _e('My History for ') ?><?php echo bp_bible_the_ref_str() ?></h2>
				<?php bp_bible_history_list(array('refs' => bp_bible_the_refs(), 'style' => 'table', 'limit' => 10)) ?>
			</div>
		<?php endif; ?>
	<?php endwhile; ?>


	</div>
<?php endif ?>
		<?php if (is_user_logged_in()): ?>
		<div id="bible-friends-posts" class="widget">
			<h2 class="widgettitle"><?php _e('My Friends\' Blog Posts') ?></h2>
			<?php bp_bible_friends_posts() ?>
		</div>

		<div class="widget">
			<h2 class="widgettitle"><?php _e('Add a Bible Note', 'bp-bible') ?></h2>
			<?php bp_bible_notes_form() ?>
		</div>

		<div class="widget">
			<h2 class="widgettitle"><?php _e('My Bible Notes', 'bp-bible') ?></h2>
			<div id="bible-note-list-content">
				<?php bp_bible_notes_list() ?>
			</div>
		</div>

		<?php else: ?>
		<div id="bible-about" class="widget">
			<h2 class="widgettitle"><?php _e('Share your thoughts with your friends...') ?></h2>
			<p><?php _e('Biblefox is all about writing blog posts about the Bible and sharing them with your friends. If you log in and find some friends, you can read any blog posts that they write about this scripture.') ?></p>
		</div>
		<?php endif; ?>

</div>

	<!-- Passage Sidebar Widgets -->
	<div id="bible-sidebar">
		<?php if (is_user_logged_in()): ?>
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
			<h2 class="widgettitle"><?php _e('Plan your Bible reading...') ?></h2>
			<p><?php _e('With Biblefox, you can create a reading plan and track your progress as you read through the Bible.') ?></p>
		</div>
		<?php endif; ?>

		<div id="bible-options" class="widget">
			<h2 class="widgettitle"><?php _e('Bible Options') ?></h2>
			<?php bp_bible_options() ?>
		</div>
	</div>

</div>


<?php get_footer(); ?>
