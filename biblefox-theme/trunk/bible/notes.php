<?php get_header() ?>

	<div class="content-header">

	</div>

	<div id="content">

		<h2><?php _e( "My Bible Notes", 'bp-bible' ) ?></h2>
		<p><?php _e('These are private notes that only you can see. You can use them to jot down quick thoughts about the Bible, then turn them into blog posts later.')?></p>

		<?php do_action( 'template_notices' ) // (error/success feedback) ?>

		<?php do_action( 'bp_before_bible_notes_latest_content' ) ?>

			<?php do_action( 'bp_before_bible_note_list_content' ) ?>

			<div class="bp-widget">
				<h4><?php _e('Add a Bible Note', 'bp-bible') ?></h4>

				<?php do_action( 'bp_before_bible_note_list_form' ) ?>

					<?php bp_bible_notes_form() ?>

			</div>
			<div class="bp-widget">
				<h4><?php _e('My Bible Notes', 'bp-bible') ?></h4>

				<div id="bible-note-list-content">

				<?php if ( function_exists('bp_bible_notes_list') ) : ?>
					<?php bp_bible_notes_list() ?>
				<?php endif; ?>

				</div>

				<?php do_action( 'bp_after_bible_note_list_form' ) ?>
			</div>

			<?php do_action( 'bp_after_bible_note_list_content' ) ?>

		<?php do_action( 'bp_after_bible_notes_latest_content' ) ?>

	</div>

<?php get_footer() ?>