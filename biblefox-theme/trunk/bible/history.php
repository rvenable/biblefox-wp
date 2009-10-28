<?php get_header() ?>

	<div class="content-header">
	</div>

	<?php $has_plans = bp_has_bible_history() ?>

	<div id="content">

		<h2><?php _e( "My Bible Reading History", 'bp-bible' ) ?></h2>

		<?php do_action( 'bp_before_my_plans_content' ) ?>

		<div class="left-menu">
			<?php //TODO: bp_plan_search_form() ?>

			<?php _e('Current Readings') ?>:<br/>
			<?php if ($readings = bp_plan_current_readings(array('max_readings' => 20))): ?>
				<?php echo $readings ?>
			<?php else: ?>
				<?php _e('There are no readings to display', 'bp-plans') ?>
			<?php endif ?>
		</div>

		<div class="main-column">
			<?php do_action( 'template_notices' ) // (error/success feedback) ?>

			<?php do_action( 'bp_before_my_plans_loop' ) ?>

			<div id="plans-loop">

				<?php if ( $has_plans ) : ?>

					<div class="pagination">

						<div class="pag-count">
							<?php bp_bible_history_pagination_count() ?>
						</div>

						<div class="pagination-links" id="<?php bp_bible_history_pag_id() ?>">
							<?php bp_bible_history_pagination() ?>
						</div>

					</div>

					<?php do_action( 'bp_before_bible_history_list' ) ?>

					<table id="bible-history-list" class="widefat">
						<?php while ( bp_bible_history_events() ) : bp_the_bible_history_event(); ?>
						<tr>
							<td><?php bp_bible_history_event_desc() ?></td>
							<td><?php bp_bible_history_event_ref_link() ?></td>
							<td><?php bp_bible_history_event_nice_date() ?></td>
							<td><?php bp_bible_history_event_date() ?></td>
							<td><?php bp_bible_history_event_toggle_link() ?></td>
						</tr>
						<?php endwhile; ?>
					</table>

					<?php do_action( 'bp_after_bible_history_list' ) ?>

				<?php else: ?>

						<div id="message" class="error">
							<p><?php _e( "No matching reading plans found.", 'bp-plans' ) ?></p>
						</div>

				<?php endif;?>

			</div>

			<?php do_action( 'bp_after_my_bible_historys_loop' ) ?>

		</div>

		<?php do_action( 'bp_after_my_bible_historys_content' ) ?>

	</div>

<?php get_footer() ?>