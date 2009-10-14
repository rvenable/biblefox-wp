<?php get_header() ?>

	<?php if ( bp_has_plans() ) : while ( bp_plans() ) : bp_the_plan(); ?>

		<div class="content-header">
			<ul class="content-header-nav">
				<?php bp_plan_view_tabs(); ?>
			</ul>
		</div>

		<div id="content">

				<?php do_action( 'template_notices' ) // (error/success feedback) ?>

				<?php do_action( 'bp_before_plan_view_content' ) ?>

				<form action="<?php bp_plan_view_form_action() ?>" name="plan-settings-form" id="plan-settings-form" class="standard-form" method="post" enctype="multipart/form-data">

					<?php /* Overview */ ?>
					<?php if ( bp_is_plan_view_screen( 'overview' ) ) : ?>

						<h2><?php _e( 'Reading Plan Overview', 'bp-plans' ); ?></h2>

					<?php endif; ?>

					<?php /* Edit Plan Details */ ?>
					<?php if ( bp_is_plan_view_screen( 'edit-details' ) ) : ?>

						<h2><?php _e( 'Edit Settings', 'bp-plans' ); ?></h2>

						<?php do_action( 'bp_before_plan_details' ); ?>

						<label for="plan-name"><?php _e( 'Reading Plan Name', 'bp-plans' ) ?></label>
						<input type="text" name="plan-name" id="plan-name" value="<?php bp_plan_name() ?>" />

						<label for="plan-desc"><?php _e( 'Reading Plan Description', 'bp-plans' ) ?></label>
						<textarea name="plan-desc" id="plan-desc"><?php bp_plan_description_editable() ?></textarea>

						<label for="plan-start"><?php _e( 'Start Date', 'bp-plans' ) ?></label>
						<input type="text" name="plan-start" id="plan-start" value="<?php bp_plan_start_date() ?>" />

						<p>
							<label for="plan-privacy"><?php _e( 'Privacy Setting', 'bp-plans' ); ?></label>
							<input type="radio" name="plan-privacy" value="0"<?php bp_plan_privacy_setting(FALSE) ?> /> <?php _e( 'Public', 'bp-plans' ); ?>&nbsp;
							<input type="radio" name="plan-privacy" value="1"<?php bp_plan_privacy_setting(TRUE) ?> /> <?php _e( 'Private', 'bp-plans' ); ?>&nbsp;
						</p>

						<p>
							<label for="plan-schedule"><?php _e( 'Schedule', 'bp-plans' ); ?></label>
							<input type="radio" name="plan-schedule" value="0"<?php bp_plan_schedule_setting(0) ?> /> <?php _e( 'Unscheduled', 'bp-plans' ); ?>&nbsp;
							<input type="radio" name="plan-schedule" value="1"<?php bp_plan_schedule_setting(1) ?> /> <?php _e( 'Daily', 'bp-plans' ); ?>&nbsp;
							<input type="radio" name="plan-schedule" value="2"<?php bp_plan_schedule_setting(2) ?> /> <?php _e( 'Weekly', 'bp-plans' ); ?>&nbsp;
							<input type="radio" name="plan-schedule" value="3"<?php bp_plan_schedule_setting(3) ?> /> <?php _e( 'Monthly', 'bp-plans' ); ?>&nbsp;
						</p>

							<label for="plan-days[]"><?php _e( 'Which days of the week will you read?', 'bp-plans' ); ?></label>
						<div class="checkbox">
							<label><input type="checkbox" name="plan-days[]" id="plan-days" value="0"<?php bp_plan_day_of_week_setting(0) ?>/> <?php _e( 'Sun', 'bp-plans' ) ?></label>
							<label><input type="checkbox" name="plan-days[]" id="plan-days" value="1"<?php bp_plan_day_of_week_setting(1) ?>/> <?php _e( 'Mon', 'bp-plans' ) ?></label>
							<label><input type="checkbox" name="plan-days[]" id="plan-days" value="2"<?php bp_plan_day_of_week_setting(2) ?>/> <?php _e( 'Tue', 'bp-plans' ) ?></label>
							<label><input type="checkbox" name="plan-days[]" id="plan-days" value="3"<?php bp_plan_day_of_week_setting(3) ?>/> <?php _e( 'Wed', 'bp-plans' ) ?></label>
							<label><input type="checkbox" name="plan-days[]" id="plan-days" value="4"<?php bp_plan_day_of_week_setting(4) ?>/> <?php _e( 'Thu', 'bp-plans' ) ?></label>
							<label><input type="checkbox" name="plan-days[]" id="plan-days" value="5"<?php bp_plan_day_of_week_setting(5) ?>/> <?php _e( 'Fri', 'bp-plans' ) ?></label>
							<label><input type="checkbox" name="plan-days[]" id="plan-days" value="6"<?php bp_plan_day_of_week_setting(6) ?>/> <?php _e( 'Sat', 'bp-plans' ) ?></label>
						</div>
						<br/>

						<?php do_action( 'bp_after_plan_details' ); ?>

						<p><input type="submit" value="<?php _e( 'Save Changes', 'bp-plans' ) ?> &raquo;" id="save" name="save" /></p>
						<?php wp_nonce_field( 'plans_edit_plan_details' ) ?>

					<?php endif; ?>

					<?php /* Edit Plan Readings */ ?>
					<?php if ( bp_is_plan_view_screen( 'edit-readings' ) ) : ?>

						<h2><?php _e( 'Edit Readings', 'bp-plans' ); ?></h2>

						<?php do_action( 'bp_before_plan_readings' ); ?>

						<label for="plan-readings"><?php _e( 'Readings', 'bp-plans' ) ?></label>
						<p><?php _e('Enter the Bible passages you want to read in the box below. Each line is a reading in the reading plan.') ?></p>
						<textarea name="plan-readings" id="plan-readings"><?php bp_plan_readings_editable() ?></textarea>

						<label for="plan-chunks"><?php _e( 'Append Chapters', 'bp-plans' ) ?></label>
						<p><?php _e('Append additional chapters by typing them in here, then enter how many chapters you want to read per reading.', 'bp-plans') ?></p>
						<textarea name="plan-chunks" id="plan-chunks"></textarea>

						<label for="plan-chunk-size"><?php _e( 'Chapters per reading', 'bp-plans' ) ?></label>
						<input type="text" name="plan-chunk-size" id="plan-chunk-size" value="1" />
						<br/>
						<br/>

						<?php do_action( 'bp_after_plan_readings' ); ?>

						<p><input type="submit" value="<?php _e( 'Save Changes', 'bp-plans' ) ?> &raquo;" id="save" name="save" /></p>
						<?php wp_nonce_field( 'plans_edit_plan_readings' ) ?>

					<?php endif; ?>

					<?php /* Copy */ ?>
					<?php if ( bp_is_plan_view_screen( 'copy' ) ) : ?>
						<h2><?php _e( 'Copy Reading Plan?', 'bp-plans' ); ?></h2>
						<?php do_action( 'bp_before_plan_copy' ); ?>
						<p><?php _e('This will make a copy of the reading plan and assign it to you.')?></p>
						<p><input type="submit" value="<?php _e( 'Confirm', 'bp-plans' ) ?> &raquo;" id="copy" name="copy" /></p>
						<?php wp_nonce_field( 'plans_copy_plan' ) ?>
						<?php do_action( 'bp_after_plan_copy' ); ?>
					<?php endif; ?>

					<?php /* Delete */ ?>
					<?php if ( bp_is_plan_view_screen( 'delete' ) ) : ?>
						<h2><?php _e( 'Delete Reading Plan?', 'bp-plans' ); ?></h2>
						<?php do_action( 'bp_before_plan_delete' ); ?>
						<p><?php _e('This will permanently delete the reading plan. Are you sure you want to do this?')?></p>
						<p><input type="submit" value="<?php _e( 'Confirm', 'bp-plans' ) ?> &raquo;" id="delete" name="delete" /></p>
						<?php wp_nonce_field( 'plans_delete_plan' ) ?>
						<?php do_action( 'bp_after_plan_delete' ); ?>
					<?php endif; ?>

					<?php /* Mark Active/Inactive */ ?>
					<?php if ( bp_is_plan_view_screen( 'mark' ) ) : ?>

						<?php if (bp_plan_is_finished()) : ?>
							<h2><?php _e( 'Activate Reading Plan?', 'bp-plans' ); ?></h2>
						<?php else: ?>
							<h2><?php _e( 'Deactivate Reading Plan?', 'bp-plans' ); ?></h2>
						<?php endif ?>

						<?php do_action( 'bp_before_plan_mark' ); ?>
						<p><?php _e('When you are done reading a reading plan, you should deactivate it. Inactive reading plans don\'t show up in your regular readings.')?></p>
						<p><input type="submit" value="<?php _e( 'Confirm', 'bp-plans' ) ?> &raquo;" id="toggle-finished" name="toggle-finished" /></p>
						<?php wp_nonce_field( 'plans_toggle_finished_plan' ) ?>
						<?php do_action( 'bp_after_plan_mark' ); ?>
					<?php endif; ?>

					<?php /* This is important, don't forget it */ ?>
					<input type="hidden" name="plan-id" id="plan-id" value="<?php bp_plan_id() ?>" />

				</form>

				<?php do_action( 'bp_before_plan_view_chart' ); ?>

					<?php bp_plan_chart(); ?>

				<?php do_action( 'bp_after_plan_view_chart' ); ?>

				<?php do_action( 'bp_after_plan_view_content' ) ?>
		</div>

	<?php endwhile; endif; ?>

<?php get_footer() ?>
