<?php global $bp_bible_search ?>
<?php get_header() ?>

	<div id="content">


		<?php do_action( 'template_notices' ) // (error/success feedback) ?>

		<?php do_action( 'bp_before_bible_search_latest_content' ) ?>

		<div class="left-menu">
			<div class="bp-widget">
				<h4><?php _e('Bible Verse Index', 'bp-bible') ?></h4>
				<?php do_action( 'bp_before_bible_search_counts_list' ) ?>
				<div id="verse_map_list">
					<ul>
						<li>
							<?php echo $bp_bible_search->output_verse_map($bp_bible_search->boolean_book_counts()) ?>
						</li>
					</ul>
				</div>
			</div>
		</div>

		<div class="main-column">

			<?php do_action( 'bp_before_bible_search_list_content' ) ?>

			<div class="bp-widget">
				<h4><?php _e('Bible Search Results: Match All Words', 'bp-bible') ?> - <?php echo $bp_bible_search->description ?></h4>
				<p><?php _e('Note: Biblefox searches all available bible translations at once, and displays the results in your preferred translation, so the exact search words may not appear in all results.', 'bp-bible')?></p>

				<div id="bible-search-list-content">

				<?php if ( function_exists('bp_bible_search_list') ) : ?>
					<?php bp_bible_search_list() ?>
				<?php endif; ?>

				</div>

				<?php do_action( 'bp_after_bible_search_list_form' ) ?>
			</div>

			<?php do_action( 'bp_after_bible_search_list_content' ) ?>

		<?php do_action( 'bp_after_bible_search_latest_content' ) ?>

		</div>

	</div>

<?php get_footer() ?>