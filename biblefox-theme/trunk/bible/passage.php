<?php $refs = bp_get_bible_refs() ?>
<?php get_header() ?>
<div id="content">
<div class="passagecolumn">

<?php if ($refs->is_valid()) : ?>
	<div class="widget" id="bible-passages">
		<h2 class="widgettitle"><?php _e('Bible Passages') ?>: <?php echo $refs->get_string() ?></h2>

		<!-- Passages -->
	<?php foreach (BfoxRefs::get_bcvs($refs->get_seqs()) as $book => $cvs): ?>
		<?php $passage_refs = new BfoxRefs(BfoxRefs::create_book_string($book, $cvs)) ?>
		<?php
		$prev_ref_str = $passage_refs->prev_chapter_string();
		$next_ref_str = $passage_refs->next_chapter_string();
		$links = '';
		if (!empty($prev_ref_str)) $links .= bp_bible_ref_link(array('ref_str' => $prev_ref_str, 'attrs' => array('class' => "ref_seq_prev"), 'disable_tooltip' => TRUE));
		if (!empty($next_ref_str)) $links .= bp_bible_ref_link(array('ref_str' => $next_ref_str, 'attrs' => array('class' => "ref_seq_next"), 'disable_tooltip' => TRUE));
		?>
		<div class="post">
			<div class='passage-nav'><?php echo $links ?></div>
			<?php $iframe = new BfoxIframe($passage_refs) ?>
			<div class="bfox-iframe-wrap bfox-passage-iframe-wrap">
				<select class="bfox-iframe-select bfox-passage-iframe-select">
					<?php echo $iframe->select_options() ?>
				</select>
				<iframe class="bfox-iframe bfox-passage-iframe" src="<?php echo $iframe->url() ?>"></iframe>
			</div>
		</div>
		<!-- Passage Widgets -->
		<?php
		$book_name = BibleMeta::get_book_name($book);
		$end_chapter = BibleMeta::end_verse_max($book);
		?>
		<div class="widget">
			<h2 class="widgettitle"><?php echo $book_name . __(' - Table of Contents', 'bp-bible') ?></h2>
			<ul class='flat_toc'>
			<?php for ($ch = BibleMeta::start_chapter; $ch <= $end_chapter; $ch++): ?>
				<li><?php echo bp_bible_ref_link(array('ref_str' => "$book_name $ch", 'text' => $ch, 'disable_tooltip' => TRUE)) ?></li>
			<?php endfor ?>
			</ul>
		</div>


		<?php if (is_user_logged_in()): ?>
			<div id="bible-passage-history" class="widget">
				<h2 class="widgettitle"><?php _e('My History for ') ?><?php echo $passage_refs->get_string() ?></h2>
				<?php bp_bible_history_list(array('refs' => $passage_refs, 'style' => 'table', 'limit' => 10)) ?>
			</div>
		<?php endif ?>
	<?php endforeach ?>


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
