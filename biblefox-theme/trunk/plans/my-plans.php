<?php get_header() ?>

	<div class="content-header">
		<ul class="content-header-nav">
			<?php bp_plans_header_tabs() ?>
		</ul>
	</div>

	<?php $has_plans = bp_has_plans() ?>

	<div id="content">

		<h2><?php bp_word_or_name( __( "My Reading Plans", 'bp-plans' ), __( "%s's Reading Plans", 'bp-plans' ) ) ?> &raquo; <?php bp_plans_filter_title() ?></h2>

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
							<?php bp_plan_pagination_count() ?>
						</div>

						<div class="pagination-links" id="<?php bp_plan_pag_id() ?>">
							<?php bp_plan_pagination() ?>
						</div>

					</div>

					<?php do_action( 'bp_before_plan_list' ) ?>

					<ul id="plan-list" class="item-list">
						<?php while ( bp_plans() ) : bp_the_plan(); ?>

							<li>
								<?php bp_plan_avatar_thumb() ?>
								<h4><a href="<?php bp_plan_permalink() ?>"><?php bp_plan_name() ?></a> <span class="small"><?php echo bp_plan_is_owned() ? '' : " by " . bp_get_plan_owner_link() ?><?php echo bp_plan_is_private() ? ' (private)' : '' ?></span>
								<br/>
								<span class="small"><?php bp_plan_schedule_description() ?></span></h4>

								<div class="desc">
									<?php bp_plan_description() ?>
								</div>

								<?php do_action( 'bp_before_plan_list_item' ) ?>

								<div class="action">
									<?php bp_add_plan_button() ?>

									<?php do_action( 'bp_plan_list_item_action' ) ?>
								</div>
							</li>

						<?php endwhile; ?>
					</ul>

					<?php do_action( 'bp_after_plan_list' ) ?>

				<?php else: ?>

					<?php if ( bp_plans_show_no_plans_message() ) : ?>

						<div id="message" class="info">
							<p><?php bp_word_or_name( __( "You haven't created any reading plans yet.", 'bp-plans' ), __( "%s hasn't created any reading plans yet.", 'bp-plans' ) ) ?></p>
						</div>

					<?php else: ?>

						<div id="message" class="error">
							<p><?php _e( "No matching reading plans found.", 'bp-plans' ) ?></p>
						</div>

					<?php endif; ?>

				<?php endif;?>

			</div>

			<?php do_action( 'bp_after_my_plans_loop' ) ?>

		</div>

		<?php do_action( 'bp_after_my_plans_content' ) ?>

	</div>

<?php get_footer() ?>