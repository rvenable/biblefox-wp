<?php get_header() ?>

	<div class="content-header">
		<ul class="content-header-nav">
			<?php bp_bible_discussions_header_tabs() ?>
		</ul>
	</div>

	<div id="content">

		<h2><?php _e( "Bible Activity", 'bp-bible' ) ?> &raquo; <?php bp_bible_discussions_filter_title() ?></h2>

		<?php do_action( 'bp_before_bible_discussions_content' ) ?>

			<p><?php _e('Scriptures:') ?> <?php bp_bible_refs() ?></p>
		<div class="left-menu">
			<h3>Add Scriptures</h3>
			<p><?php _e('Add any other scriptures you have studied today:') ?></p>
			<p><?php bp_bible_add_scriptures_form() ?></p>
			<h3>Reading Plans</h3>
			<?php bp_bible_current_readings(array('max' => 30)) ?>
		</div>

		<div class="main-column">
			<?php do_action( 'template_notices' ) // (error/success feedback) ?>

			<?php do_action( 'bp_before_bible_discussions_loop' ) ?>

			<div id="bible-discussions-loop">

				<?php bp_bible_friends_posts(array('refs' => bp_get_bible_refs())) ?>

			</div>

			<?php do_action( 'bp_after_bible_discussions_loop' ) ?>

		</div>

		<?php do_action( 'bp_after_bible_discussions_content' ) ?>

	</div>

<?php get_footer() ?>