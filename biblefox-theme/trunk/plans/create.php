<?php get_header() ?>

	<div class="content-header">
		<ul class="content-header-nav">
			<?php bp_plan_creation_tabs(); ?>
		</ul>
	</div>

	<div id="content">
		<h2><?php _e( 'Create a Reading Plan', 'bp-plans' ) ?> <?php bp_plan_creation_stage_title() ?></h2>
		<?php do_action( 'template_notices' ) // (error/success feedback) ?>

		<?php do_action( 'bp_before_plan_creation_content' ) ?>

		<form action="<?php bp_plan_creation_form_action() ?>" method="post" id="plan-settings-form" class="standard-form" enctype="multipart/form-data">

			<!-- Reading Plan creation step 1: Basic plan details -->
			<?php if ( bp_is_plan_creation_step( 'plan-details' ) ) : ?>

				<?php do_action( 'bp_before_plan_details_creation_step' ); ?>

						<label for="plan-name">* <?php _e( 'Reading Plan Name', 'bp-plans' ) ?></label>
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

				<?php do_action( 'bp_after_plan_details_creation_step' ); ?>

				<?php wp_nonce_field( 'plans_create_save_plan-details' ) ?>

			<?php endif; ?>

			<!-- Reading Plan creation step 2: Add groups of scripture -->
			<?php if ( bp_is_plan_creation_step( 'plan-add-groups' ) ) : ?>

				<?php do_action( 'bp_before_plan_add_groups_creation_step' ); ?>

						<label for="plan-chunks"><?php _e( 'Append Chunks of Scripture', 'bp-plans' ) ?></label>
						<textarea name="plan-chunks" id="plan-chunks"></textarea>

						<label for="plan-chunk-size"><?php _e( 'Chunk size', 'bp-plans' ) ?></label>
						<input type="text" name="plan-chunk-size" id="plan-chunk-size" value="1" />
						<br/>

				<?php do_action( 'bp_after_plan_add_groups_creation_step' ); ?>

				<?php wp_nonce_field( 'plans_create_save_plan-add-groups' ) ?>

			<?php endif; ?>

			<!-- Reading Plan creation step 3: Edit readings -->
			<?php if ( bp_is_plan_creation_step( 'plan-edit-readings' ) ) : ?>

				<?php do_action( 'bp_before_plan_edit_readings_creation_step' ); ?>

						<label for="plan-readings"><?php _e( 'Readings', 'bp-plans' ) ?></label>
						<textarea name="plan-readings" id="plan-readings"><?php bp_plan_readings_editable() ?></textarea>

				<?php do_action( 'bp_after_plan_edit_readings_creation_step' ); ?>

				<?php wp_nonce_field( 'plans_create_save_plan-edit-readings' ) ?>

			<?php endif; ?>

			<?php do_action( 'plans_custom_create_steps' ) // Allow plugins to add custom plan creation steps ?>

			<?php do_action( 'bp_before_plan_creation_step_buttons' ); ?>

			<div class="submit" id="previous-next">
				<!-- Previous Button -->
				<?php if ( !bp_is_first_plan_creation_step() ) : ?>
					<input type="button" value="&larr; <?php _e('Previous Step', 'bp-plans') ?>" id="plan-creation-previous" name="previous" onclick="location.href='<?php bp_plan_creation_previous_link() ?>'" />
				<?php endif; ?>

				<!-- Next Button -->
				<?php if ( !bp_is_last_plan_creation_step() && !bp_is_first_plan_creation_step() ) : ?>
					<input type="submit" value="<?php _e('Next Step', 'bp-plans') ?> &rarr;" id="plan-creation-next" name="save" />
				<?php endif;?>

				<!-- Create Button -->
				<?php if ( bp_is_first_plan_creation_step() ) : ?>
					<input type="submit" value="<?php _e('Create Plan and Continue', 'bp-plans') ?> &rarr;" id="plan-creation-create" name="save" />
				<?php endif; ?>

				<!-- Finish Button -->
				<?php if ( bp_is_last_plan_creation_step() ) : ?>
					<input type="submit" value="<?php _e('Finish', 'bp-plans') ?> &rarr;" id="plan-creation-finish" name="save" />
				<?php endif; ?>
			</div>

			<?php if ( bp_is_plan_creation_step( 'plan-edit-readings' ) ) : ?>
				<p><?php bp_plan_chart(); ?></p>
			<?php endif; ?>

			<?php do_action( 'bp_after_plan_creation_step_buttons' ); ?>

			<!-- Don't leave out this hidden field -->
			<input type="hidden" name="plan_id" id="plan_id" value="<?php bp_get_plan_id() ?>" />
		</form>

		<?php do_action( 'bp_after_plan_creation_content' ) ?>

	</div>

<?php get_footer() ?>