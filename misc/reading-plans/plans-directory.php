<?php

/* Define the slug for the component */
if (!defined('BFOX_PLANS_SLUG')) define('BFOX_PLANS_SLUG', 'plans');

function bfox_bp_plans_directory_setup_root_component() {
	bp_core_add_root_component(BFOX_PLANS_SLUG);
}
add_action('plugins_loaded', 'bfox_bp_plans_directory_setup_root_component', 2);

function bfox_bp_plans_setup_globals() {
	global $bp;
	$bp->grav_default->plan = apply_filters('bfox_bp_plan_gravatar_default', 'identicon');
}
add_action('bp_core_setup_globals', 'bfox_bp_plans_setup_globals');

function bfox_bp_plans_directory_setup() {
	global $bp;

	if ($bp->current_component == BFOX_PLANS_SLUG && empty($bp->displayed_user->id)) {
		if ('create' == $bp->current_action) {
			bfox_bp_plans_screen_create();
		}
		elseif (!empty($bp->current_action) && $plan_id = BfoxReadingPlan::slug_exists($bp->current_action)) {
			$bp->is_single_item = true;
			$plan = BfoxReadingPlan::plan($plan_id);

			// If this plan has been deleted and there aren't any users for it anymore, we shouldn't show it
			if ($plan->is_deleted && !$plan->user_count) bp_core_redirect(bfox_bp_plan_url());

			$bp->current_item = $bp->current_action;
			$bp->current_action = $bp->action_variables[0];
			array_shift($bp->action_variables);

			if (empty($bp->current_action)) $bp->current_action = 'overview';
			bfox_bp_plans_screen_plan_setup_nav($plan);
		}
		else {
			$bp->is_directory = true;

			bfox_bp_core_load_template(apply_filters('bfox_bp_plans_directory_template', 'plans/index'));
		}
	}
}
add_action('wp', 'bfox_bp_plans_directory_setup', 2);

function bfox_bp_plans_screen_plan_setup_nav(BfoxReadingPlan $plan) {
	global $bp;

	bfox_bp_plan($plan);

	$slug = BFOX_PLANS_SLUG;
	$plans_link = $plan->url();

	bp_core_new_nav_item(array(
		'name' => __('Reading Plans', 'bfox'),
		'slug' => $slug,
		'position' => 70,
		'screen_function' => 'bfox_bp_plans_screen_plan_view',
		'default_subnav_slug' => 'overview',
		'item_css_id' => $slug,
	));

	bp_core_new_nav_default(array('parent_slug' => $slug, 'screen_function' => 'bfox_bp_plans_screen_plan_view', 'subnav_slug' => 'overview'));

	bp_core_new_subnav_item( array(
		'name' => __( 'Overview', 'bp-plan' ),
		'slug' => 'overview',
		'parent_slug' => $slug,
		'parent_url' => $plans_link,
		'screen_function' => 'bfox_bp_plans_screen_plan_view',
		'position' => 130,
	) );

	bp_core_new_subnav_item( array(
		'name' => __( 'Edit Settings', 'bp-plan' ),
		'slug' => 'edit-details',
		'parent_slug' => $slug,
		'parent_url' => $plans_link,
		'screen_function' => 'bfox_bp_plans_screen_plan_view',
		'position' => 130,
		'user_has_access' => $plan->is_user_owner(), // Only the logged in user can access this on his/her profile
	) );

	bp_core_new_subnav_item( array(
		'name' => __( 'Edit Readings', 'bp-plan' ),
		'slug' => 'edit-readings',
		'parent_slug' => $slug,
		'parent_url' => $plans_link,
		'screen_function' => 'bfox_bp_plans_screen_plan_view',
		'position' => 130,
		'user_has_access' => $plan->is_user_owner(), // Only the logged in user can access this on his/her profile
	) );

	bp_core_new_subnav_item( array(
		'name' => __( 'Edit Avatar', 'bp-plan' ),
		'slug' => 'avatar',
		'parent_slug' => $slug,
		'parent_url' => $plans_link,
		'screen_function' => 'bfox_bp_plans_screen_plan_view',
		'position' => 130,
		'user_has_access' => $plan->is_user_owner(), // Only the logged in user can access this on his/her profile
	) );

	bp_core_new_subnav_item( array(
		'name' => __( 'Schedules', 'bp-plan' ),
		'slug' => 'schedules',
		'parent_slug' => $slug,
		'parent_url' => $plans_link,
		'screen_function' => 'bfox_bp_plans_screen_schedules',
		'position' => 130,
	) );

	bp_core_new_subnav_item( array(
		'name' => __( 'Add to Schedule', 'bp-plan' ),
		'slug' => 'add-schedule',
		'parent_slug' => $slug,
		'parent_url' => $plans_link,
		'screen_function' => 'bfox_bp_plans_screen_add_schedule',
		'position' => 130,
	) );

	if ($slug == $bp->current_component && 'copy' == $bp->current_action) {
		bp_core_new_subnav_item( array(
			'name' => __( 'Copy', 'bp-plan' ),
			'slug' => 'copy',
			'parent_slug' => $slug,
			'parent_url' => $plans_link,
			'screen_function' => 'bfox_bp_plans_screen_plan_view',
			'position' => 130,
		) );
	}

	if ($slug == $bp->current_component && 'delete' == $bp->current_action) {
		bp_core_new_subnav_item( array(
			'name' => __( 'Delete', 'bp-plan' ),
			'slug' => 'delete',
			'parent_slug' => $slug,
			'parent_url' => $plans_link,
			'screen_function' => 'bfox_bp_plans_screen_plan_view',
			'position' => 130,
		) );
	}
}

function bfox_bp_bible_study_setup_nav() {
	global $bp;

	$slug = BFOX_BIBLE_SLUG;
	$link = (!empty($bp->displayed_user->domain) ? $bp->displayed_user->domain : $bp->loggedin_user->domain) . $slug . '/';

	bp_core_new_nav_item(array(
		'name' => __('Bible Study', 'bfox'),
		'slug' => $slug,
		'position' => 21,
		'show_for_displayed_user' => true,
		'screen_function' => 'bfox_bp_bible_study_calendar_screen_view',
		'default_subnav_slug' => 'calendar',
	));

	bp_core_new_subnav_item( array(
		'name' => __('Calendar', 'bfox'),
		'slug' => 'calendar',
		'parent_slug' => $slug,
		'parent_url' => $link,
		'screen_function' => 'bfox_bp_bible_study_calendar_screen_view',
		'position' => 130,
	) );

	bp_core_new_subnav_item( array(
		'name' => __('Scheduled Plans', 'bfox'),
		'slug' => 'schedules',
		'parent_slug' => $slug,
		'parent_url' => $link,
		'screen_function' => 'bfox_bp_bible_study_schedules_screen_view',
		'position' => 130,
	) );

	if ($slug == $bp->current_component && ('schedule' == $bp->current_action || 'delete-schedule' == $bp->current_action)) {
		$schedule = bfox_bp_schedule(BfoxReadingSchedule::schedule($bp->action_variables[0]));
		if ($schedule->id) {
			if ($schedule->is_user_member($bp->displayed_user->id)) {
				if ('schedule' == $bp->current_action) {
					bfox_bp_plans_reading_redirect($schedule, $bp->action_variables[1], $bp->action_variables[2]);

					$bp->current_action = "schedule/$schedule->id";
					bp_core_new_subnav_item( array(
						'name' => __('View Schedule', 'bfox'),
						'slug' => $bp->current_action,
						'parent_slug' => $slug,
						'parent_url' => $link,
						'screen_function' => 'bfox_bp_bible_study_edit_schedule_screen_view',
						'position' => 130,
					));
				}
				elseif ('delete-schedule' == $bp->current_action) {
					$bp->current_action = "delete-schedule/$schedule->id";
					bp_core_new_subnav_item( array(
						'name' => __('Delete Schedule', 'bfox'),
						'slug' => $bp->current_action,
						'parent_slug' => $slug,
						'parent_url' => $link,
						'screen_function' => 'bfox_bp_bible_study_edit_schedule_screen_view',
						'position' => 130,
					) );
				}
			}
			else bp_core_redirect($schedule->url());
		}
		else bp_core_redirect($link);
	}

	do_action('bfox_bp_bible_study_setup_nav', $slug, $link);
}
add_action('wp', 'bfox_bp_bible_study_setup_nav', 2);
add_action('admin_menu', 'bfox_bp_bible_study_setup_nav', 2);

function bfox_bp_bible_study_setup_nav_group() {
	global $bp;
	if ($bp->current_component == $bp->groups->slug && $bp->is_single_item) {
		bp_core_new_subnav_item( array(
			'name' => __('Bible Study', 'bfox'),
			'slug' => BFOX_BIBLE_SLUG,
			'parent_slug' => $bp->groups->slug,
			'parent_url' => $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/',
			'position' => 21,
			'screen_function' => 'bfox_bp_bible_study_group_screen_view',
		));
	}
}
add_action('groups_setup_nav', 'bfox_bp_bible_study_setup_nav_group');

function bfox_bp_plans_reading_redirect(BfoxReadingSchedule $schedule, $reading_num, $action) {
	global $bp;

	// If there is a reading num specified
	if ($reading_num && ($reading_num <= $schedule->reading_count)) {
		$reading_id = $reading_num - 1;

		// If we are marking the reading as read
		if ('read' == $action) {
			BfoxReadingSchedule::add_progress(array($schedule->id => array($reading_id)), $bp->loggedin_user->id);
			bp_core_add_message(sprintf(__('Reading #%d marked as read.', 'bfox'), $reading_num));
			bp_core_redirect($schedule->url());
		}

		// Redirect to the Bible Reader
		$readings = $schedule->readings();
		if (isset($readings[$reading_id]) && $readings[$reading_id]->is_valid()) {
			bp_core_redirect(bfox_ref_bible_url($readings[$reading_id]->get_string()));
		}

		bp_core_redirect($schedule->url());
	}
}

/*
 * Screen Functions
 */

function bfox_bp_plans_screen_plan_view() {
	global $bp;

	if ( isset( $_POST['save'] ) ) {
		$plan = bfox_bp_plan();
		/* Check the nonce first. */
		if ($_POST['plan-name'] && check_admin_referer('plans_edit_plan_details')) {
			bfox_bp_plans_update_plan_details($plan);
			bp_core_add_message(__('The reading plan was updated.', 'bfox'));
			bp_core_redirect($plan->url() . 'edit-details');
		}
		elseif ($_POST['plan-readings'] && check_admin_referer('plans_edit_plan_readings')) {
			bfox_bp_plans_update_plan_readings($plan);
			bp_core_add_message(__('The reading plan was updated.', 'bfox'));
			bp_core_redirect($plan->url() . 'edit-readings');
		}
	}
	elseif (isset($_POST['copy']) && check_admin_referer('plans_copy_plan')) {
		$plan = bfox_bp_plan();
		if (!$plan->is_published) bfox_bp_plans_must_own($plan);

		$plan->set_as_copy($bp->loggedin_user->id, BfoxReadingPlan::owner_type_user);
		$plan->save();

		bp_core_add_message("Reading plan created: $plan->name!");
		bp_core_redirect($plan->url());
	}
	elseif (isset($_POST['delete']) && check_admin_referer('plans_delete_plan')) {
		$plan = bfox_bp_plan();
		bfox_bp_plans_must_own($plan);

		BfoxReadingPlan::delete_plan($plan);
		bp_core_add_message("Reading plan deleted: $plan->name!");
		bp_core_redirect(bfox_bp_plan_url());
	}

	if ('avatar' == bfox_bp_plan_screen()) {
		$plan = bfox_bp_plan();
		bfox_bp_plans_update_plan_avatar($plan);
	}

	bfox_bp_core_load_template(apply_filters('bfox_bp_plans_screen_plan_template', 'plans/single'));
}

function bfox_bp_plans_screen_schedules() {
	bfox_bp_core_load_template(apply_filters('bfox_bp_plans_screen_schedules_template', 'plans/plan-schedules'));
}

function bfox_bp_plans_screen_add_schedule() {
	global $bp;

	$plan = bfox_bp_plan();
	$schedule = bfox_bp_schedule($plan->new_schedule());

	if (isset($_POST['save']) && check_admin_referer('bfox_bp_plans_edit_schedule')) {
		bfox_bp_plans_save_schedule($schedule);

		bp_core_add_message(__('The reading plan was added to your schedule.', 'bfox'));
		bp_core_redirect($schedule->url());
	}

	bfox_bp_core_load_template(apply_filters('bfox_bp_plans_screen_add_schedule_template', 'plans/add-schedule'));
}

function bfox_bp_bible_study_group_screen_view() {
	global $bp;
	add_action('bp_template_content', 'bfox_bp_bible_study_group_screen_view_tabs', 1);

	$action = $bp->action_variables[0];
	if (empty($action)) $action = 'calendar';

	if ('calendar' == $action) add_action('bp_template_content', 'bfox_bp_bible_study_calendar_screen_view_content');
	elseif ('schedules' == $action) add_action('bp_template_content', 'bfox_bp_bible_study_schedules_screen_view_content');
	elseif ('schedule' == $action || 'delete-schedule' == $action) {
		$schedule = bfox_bp_schedule(BfoxReadingSchedule::schedule($bp->action_variables[1]));
		if ($schedule->id) {
			bfox_bp_plans_reading_redirect($schedule, $bp->action_variables[2], $bp->action_variables[3]);

			if (BfoxReadingSchedule::owner_type_group == $schedule->owner_type && $schedule->owner_id == $bp->groups->current_group->id) {
				bfox_bp_bible_study_edit_schedule_screen_view_save();
				add_action('bp_template_content', 'bfox_bp_bible_study_edit_schedule_screen_view_content');
			}
			else bp_core_redirect($schedule->url());
		}
		else bp_core_redirect($bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/' . BFOX_BIBLE_SLUG . '/');
	}

	bfox_bp_core_load_template(apply_filters('bfox_bp_bible_study_group_calendar_screen_template', 'groups/single/plugins'));
}

function bfox_bp_bible_study_calendar_screen_view() {
	add_action('bp_template_content', 'bfox_bp_bible_study_calendar_screen_view_content');
	bfox_bp_core_load_template(apply_filters('bfox_bp_bible_study_calendar_screen_template', 'members/single/plugins'));
}

function bfox_bp_bible_study_calendar_screen_view_content() {
	?>
	<div id="plans-calendar" class="datepicker"></div>
	<div id="plans-calendar-readings">
	<?php bfox_bp_plans_list_month_year_readings(date('m'), date('Y')) ?>
	</div>
	<?php
}

function bfox_bp_bible_study_schedules_screen_view() {
	add_action('bp_template_content', 'bfox_bp_bible_study_schedules_screen_view_content');
	bfox_bp_core_load_template(apply_filters('bfox_bp_bible_study_schedules_screen_template', 'members/single/plugins'));
}

function bfox_bp_bible_study_schedules_screen_view_content() {
	locate_template(array('plans/schedule-loop.php'), true);
}

function bfox_bp_bible_study_edit_schedule_screen_view() {
	bfox_bp_bible_study_edit_schedule_screen_view_save();
	add_action('bp_template_content', 'bfox_bp_bible_study_edit_schedule_screen_view_content');
	bfox_bp_core_load_template(apply_filters('bfox_bp_bible_study_edit_schedule_screen_template', 'members/single/plugins'));
}

function bfox_bp_bible_study_edit_schedule_screen_view_content() {
	locate_template(array('plans/view-schedule.php'), true);
}

function bfox_bp_plans_screen_create() {
	global $bp;

	do_action( 'bfox_bp_plans_screen_create' );

	$bp->plans->plan_creation_steps = apply_filters( 'plans_create_plan_steps', array(
		'plan-details' => array( 'name' => __( 'Plan Details', 'bfox' ), 'position' => 0 ),
		'plan-add-groups' => array( 'name' => __( 'Add Groups of Chapters', 'bfox' ), 'position' => 10 ),
		'plan-edit-readings' => array( 'name' => __( 'Edit Readings', 'bfox' ), 'position' => 20 ),
		'plan-avatar' => array( 'name' => __( 'Add an Avatar', 'bfox' ), 'position' => 30 ),
	) );

	/* If no current step is set, reset everything so we can start a fresh plan creation */
	if ( !$bp->plans->current_create_step = $bp->action_variables[1] ) {

		unset( $bp->plans->current_create_step );
		unset( $bp->plans->completed_create_steps );

		setcookie( 'bp_new_plan_id', false, time() - 1000, COOKIEPATH );
		setcookie( 'bp_completed_plan_create_steps', false, time() - 1000, COOKIEPATH );

		$reset_steps = true;
		bp_core_redirect(bfox_bp_plans_create_url(array_shift(array_keys($bp->plans->plan_creation_steps))));
	}

	/* If this is a creation step that is not recognized, just redirect them back to the first screen */
	if ( $bp->action_variables[1] && !$bp->plans->plan_creation_steps[$bp->action_variables[1]] ) {
		bp_core_add_message( __('There was an error saving reading plan details. Please try again.', 'bfox'), 'error' );
		bp_core_redirect(bfox_bp_plans_create_url());
	}

	/* Fetch the currently completed steps variable */
	if ( isset( $_COOKIE['bp_completed_plan_create_steps'] ) && !$reset_steps )
		$bp->plans->completed_create_steps = unserialize( stripslashes( $_COOKIE['bp_completed_plan_create_steps'] ) );

	/* Set the ID of the new plan, if it has already been created in a previous step */
	if ( isset( $_COOKIE['bp_new_plan_id'] ) ) $new_plan_id = (int) $_COOKIE['bp_new_plan_id'];
	else $new_plan_id = 0;


	$plan = bfox_bp_plan(BfoxReadingPlan::plan($new_plan_id));
	bfox_bp_plans_must_own($plan);

	/* If the save, upload or skip button is hit, lets calculate what we need to save */
	if ( isset($_POST['save']) ) {

		/* Check the nonce */
		check_admin_referer( 'plans_create_save_' . $bp->plans->current_create_step );

		if ( 'plan-details' == $bp->plans->current_create_step ) {
			if ( empty( $_POST['plan-name'] ) /*|| empty( $_POST['plan-desc'] )*/ ) {
				bp_core_add_message( __( 'Please fill in all of the required fields', 'bfox' ), 'error' );
				bp_core_redirect(bfox_bp_plans_create_url($bp->plans->current_create_step));
			}

			bfox_bp_plans_update_plan_details($plan);
		}
		elseif (( 'plan-add-groups' == $bp->plans->current_create_step ) || ( 'plan-edit-readings' == $bp->plans->current_create_step )) {
			bfox_bp_plans_update_plan_readings($plan);
		}

		do_action( 'plans_create_plan_step_save_' . $bp->plans->current_create_step );
		do_action( 'plans_create_plan_step_complete' ); // Mostly for clearing cache on a generic action name

		/**
		 * Once we have successfully saved the details for this step of the creation process
		 * we need to add the current step to the array of completed steps, then update the cookies
		 * holding the information
		 */
		if ( !in_array( $bp->plans->current_create_step, (array)$bp->plans->completed_create_steps ) )
			$bp->plans->completed_create_steps[] = $bp->plans->current_create_step;

		/* Reset cookie info */
		setcookie( 'bp_new_plan_id', $plan->id, time()+60*60*24, COOKIEPATH );
		setcookie( 'bp_completed_plan_create_steps', serialize( $bp->plans->completed_create_steps ), time()+60*60*24, COOKIEPATH );

		/* If we have completed all steps and hit done on the final step we can redirect to the completed plan */
		if ( count( $bp->plans->completed_create_steps ) == count( $bp->plans->plan_creation_steps ) && $bp->plans->current_create_step == array_pop( array_keys( $bp->plans->plan_creation_steps ) ) ) {
			unset( $bp->plans->current_create_step );
			unset( $bp->plans->completed_create_steps );

			/* Once we compelete all steps, record the plan creation in the activity stream. */
			/*plans_record_activity( array(
				'content' => apply_filters( 'plans_activity_created_plan', sprintf( __( '%s created the reading plan %s', 'bfox'), bp_core_get_userlink( $bp->loggedin_user->id ), '<a href="' . bp_get_plan_permalink( $plan ) . '">' . attribute_escape( $plan->name ) . '</a>' ) ),
				'primary_link' => apply_filters( 'plans_activity_created_plan_primary_link', bp_get_plan_permalink( $plan ) ),
				'component_action' => 'created_plan',
				'item_id' => $plan->id
			) );*/

			do_action( 'plans_plan_create_complete', $plan->id );

			bp_core_redirect($plan->url() . 'add-schedule/');
		} else {
			/**
			 * Since we don't know what the next step is going to be (any plugin can insert steps)
			 * we need to loop the step array and fetch the next step that way.
			 */
			foreach ( $bp->plans->plan_creation_steps as $key => $value ) {
				if ( $key == $bp->plans->current_create_step ) {
					$next = 1;
					continue;
				}

				if ( $next ) {
					$next_step = $key;
					break;
				}
			}

			bp_core_redirect(bfox_bp_plans_create_url($next_step));
		}
	}

	if ('plan-avatar' == $bp->plans->current_create_step) {
		bfox_bp_plans_update_plan_avatar($plan, true);
	}

	bfox_bp_core_load_template( apply_filters( 'bfox_bp_plans_template_screen_create', 'plans/create' ) );
}

function bfox_bp_plans_screen_notification_settings() {
	global $current_user; ?>
	<table class="notification-settings" id="plans-notification-settings">
		<tr>
			<th class="icon"></th>
			<th class="title"><?php _e( 'Reading Plans', 'buddypress' ) ?></th>
			<th class="yes"><?php _e( 'Yes', 'buddypress' ) ?></th>
			<th class="no"><?php _e( 'No', 'buddypress' )?></th>
		</tr>
		<tr>
			<td></td>
			<td><?php _e( 'Receive an email each day for each reading you have scheduled that day', 'bfox' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[notification_plans_readings]" value="yes" <?php if ( !get_usermeta( $current_user->id, 'notification_plans_readings') || 'yes' == get_usermeta( $current_user->id, 'notification_plans_readings') ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[notification_plans_readings]" value="no" <?php if ( 'no' == get_usermeta( $current_user->id, 'notification_plans_readings') ) { ?>checked="checked" <?php } ?>/></td>
		</tr>

		<?php do_action('plans_screen_notification_settings'); ?>
	</table>
<?php
}
add_action('bp_notification_settings', 'bfox_bp_plans_screen_notification_settings', 12);

/*
 * Management Functions
 */

function bfox_bp_plan_url($plan_slug = '') {
	global $bp;
	$url = $bp->root_domain . '/' . BFOX_PLANS_SLUG . '/';
	if (!empty($plan_slug)) $url .= $plan_slug . '/';
	return $url;
}

function bfox_bp_schedule_url($owner_url, $schedule_slug) {
	return $owner_url . 'bible/schedule/' . $schedule_slug . '/';
}

function bfox_bp_schedule_delete_url($owner_url, $schedule_slug) {
	return $owner_url . 'bible/delete-schedule/' . $schedule_slug . '/';
}

function bfox_bp_plans_create_url($step = '') {
	global $bp;
	$url = $bp->root_domain . '/' . BFOX_PLANS_SLUG . '/create/';
	if (!empty($step)) $url .= "step/$step/";
	return $url;
}

function bfox_bp_bible_study_calendar_url($user_id = 0) {
	global $bp;
	if ($user_id) $domain = bp_core_get_user_domain($user_id);
	else $domain = $bp->loggedin_user->domain;
	return $domain . BFOX_BIBLE_SLUG . '/calendar/';
}

function bfox_bp_plans_must_own(BfoxReadingPlan $plan = NULL) {
	if (!$plan->is_user_owner()) {
		bp_core_add_message(__('The action you are trying to do can only be done by the owner of the reading plan!'), 'error');
		bp_core_redirect(bp_get_plan_permalink($plan));
	}
}

function bfox_bp_plans_update_plan_details(BfoxReadingPlan $plan) {
	bfox_bp_plans_must_own($plan);

	$plan->name = strip_tags(stripslashes($_POST['plan-name']));
	$plan->description = strip_tags(stripslashes($_POST['plan-desc']), '<a><b><em><i><strong>');
	$plan->is_published = $_POST['plan-publish'];

	if (isset($_POST['plan-owner'])) {
		if (empty($_POST['plan-owner'])) $plan->set_user_owner();
		else {
			list($owner_type, $owner_id) = explode('-', $_POST['plan-owner'], 2);
			$plan->set_owner($owner_type, $owner_id);
		}
	}

	$plan->save();
}

function bfox_bp_plans_update_plan_readings(BfoxReadingPlan $plan) {
	bfox_bp_plans_must_own($plan);

	$update_readings = false;
	$old_readings = $plan->readings();

	if (isset($_POST['plan-readings'])) {
		$new_readings = BfoxReadingPlan::readings_from_lines(stripslashes($_POST['plan-readings']));

		$old_reading_string = implode('', BfoxReadingPlan::reading_strings($old_readings, BibleMeta::name_short));
		$new_reading_string = implode('', BfoxReadingPlan::reading_strings($new_readings, BibleMeta::name_short));
		if ($old_reading_string != $new_reading_string) $update_readings = true;
	}
	else $new_readings = $old_readings;

	if (isset($_POST['plan-chunks'])) {
		$chunk_readings = BfoxReadingPlan::readings_from_passages(stripslashes($_POST['plan-chunks']), $_POST['plan-chunk-size']);
		if (!empty($chunk_readings)) {
			$new_readings = array_merge($new_readings, $chunk_readings);
			$update_readings = true;
		}
	}

	if ($update_readings) $plan->update_readings($new_readings);
}

function bfox_bp_plans_update_plan_avatar(BfoxReadingPlan $plan, $is_create = false) {
	global $bp;

	bfox_bp_plans_must_own($plan);

	if (!$is_create) {
		/* If the group admin has deleted the admin avatar */
		if ('delete' == $bp->action_variables[0]) {
			/* Check the nonce */
			check_admin_referer('bfox_bp_plan_avatar_delete');
			if ( bp_core_delete_existing_avatar( array( 'item_id' => $plan->id, 'object' => 'plan' ) ) )
				bp_core_add_message( __( 'Your avatar was deleted successfully!', 'buddypress' ) );
			else bp_core_add_message( __( 'There was a problem deleting that avatar, please try again.', 'buddypress' ), 'error' );

			bp_core_redirect($plan->url() . 'avatar/');
		}
	}

	$bp->avatar_admin->step = 'upload-image';

	if (!empty($_FILES) && isset($_POST['upload'])) {
		if ($is_create) check_admin_referer('plans_create_save_plan-avatar');
		else check_admin_referer( 'bp_avatar_upload' );

		/* Pass the file to the avatar upload handler */
		if (bp_core_avatar_handle_upload($_FILES, 'bfox_bp_plans_avatar_upload_dir')) {
			$bp->avatar_admin->step = 'crop-image';

			/* Make sure we include the jQuery jCrop file for image cropping */
			add_action( 'wp', 'bp_core_add_jquery_cropper' );
		}
	}

	/* If the image cropping is done, crop the image and save a full/thumb version */
	if ( isset( $_POST['avatar-crop-submit'] ) ) {
		if ($is_create) check_admin_referer('plans_create_save_plan-avatar');
		else check_admin_referer( 'bp_avatar_cropstore' );

		if ( !bp_core_avatar_handle_crop( array( 'object' => 'plan', 'avatar_dir' => 'plan-avatars', 'item_id' => $plan->id, 'original_file' => $_POST['image_src'], 'crop_x' => $_POST['x'], 'crop_y' => $_POST['y'], 'crop_w' => $_POST['w'], 'crop_h' => $_POST['h'] ) ) )
			bp_core_add_message( __( 'There was a problem cropping the avatar, please try uploading it again', 'buddypress' ) );
		else bp_core_add_message( __( 'The new reading plan avatar was uploaded successfully!', 'buddypress' ) );
	}
}

function bfox_bp_plans_save_schedule(BfoxReadingSchedule $schedule) {
	$schedule->frequency((int) $_POST['schedule-frequency']);
	$schedule->days_of_week(implode('', (array) $_POST['schedule-days']));
	$schedule->set_start_date($_POST['schedule-start']);

	$schedule->set_label(strip_tags(stripslashes($_POST['schedule-label'])));
	$schedule->note = strip_tags(stripslashes($_POST['schedule-note']), '<a><b><em><i><strong>');

	if (isset($_POST['schedule-owner'])) {
		if (empty($_POST['schedule-owner'])) $schedule->set_user_owner();
		else {
			list($owner_type, $owner_id) = explode('-', $_POST['schedule-owner'], 2);
			$schedule->set_owner($owner_type, $owner_id);
		}
	}

	$schedule->save();
}

function bfox_bp_pagination_count($page, $per_page, $total, $type) {
	$from_num = bp_core_number_format( intval( ( $page - 1 ) * $per_page ) + 1 );
	$to_num = bp_core_number_format( ( $from_num + ( $per_page - 1 ) > $total ) ? $total : $from_num + ( $per_page - 1 ) );
	$total = bp_core_number_format( $total );

	echo sprintf(__( 'Viewing %s %s to %s (of %s %s)', 'bfox' ), $type, $from_num, $to_num, $total, $type); ?> &nbsp;
	<span class="ajax-loader"></span><?php
}

function bfox_bp_paginate_links($page, $per_page, $total, $extra_args = array()) {
	$args = array_merge(array('page' => '%#%', 'num' => $per_page), $extra_args);
	//if (, 's' => $_REQUEST['s'], 'sortby' => $this->sort_by, 'order' => $this->order);
	return paginate_links( array(
		'base' => add_query_arg($args),
		'format' => '',
		'total' => ceil($total / $per_page),
		'current' => $page,
		'prev_text' => '&larr;',
		'next_text' => '&rarr;',
		'mid_size' => 1
	));
}

function bfox_bp_plan_screen() {
	global $bp;
	if ($bp->current_component == $bp->groups->slug) return $bp->action_variables[0];
	return $bp->current_action;
}

function bfox_bp_plans_schedule_screen() {
	list ($screen, $junk) = explode('/', bfox_bp_plan_screen(), 2);
	return $screen;
}

function bfox_bp_bible_study_edit_schedule_screen_view_save() {
	$schedule = bfox_bp_schedule();
	if (!is_null($schedule)) {
		if (isset($_POST['save']) && check_admin_referer('bfox_bp_plans_edit_schedule')) {
			bfox_bp_plans_save_schedule($schedule);

			bp_core_add_message(__('The schedule was updated.', 'bfox'));
			bp_core_redirect($schedule->url());
		}
		elseif (isset($_POST['delete']) && check_admin_referer('bfox_bp_plans_delete_schedule')) {
			BfoxReadingSchedule::delete_schedule($schedule);

			bp_core_add_message(__('The schedule was deleted.', 'bfox'));
			bp_core_redirect(bfox_bp_bible_study_calendar_url());
		}
	}
}

function bfox_bp_plans_loop_args() {
	global $bp;

	wp_parse_str(bp_ajax_querystring('reading_plans'), $args);

	$type = $args['scope'];
	if ('personal' == $type) {
		$args['user_id'] = $bp->loggedin_user->id;
	}
	elseif ('following' == $type) {
		$args['user_id'] = (array) bp_follow_get_following(array('user_id' => $bp->loggedin_user->id));
		$args['is_published'] = true;
	}
	elseif ('friends' == $type) {
		$args['user_id'] = (array) friends_get_friend_user_ids($bp->loggedin_user->id);
		$args['is_published'] = true;
	}
	elseif ('groups' == $type) {
		$groups = groups_get_user_groups($bp->loggedin_user->id);
		$args['group_id'] = $groups['groups'];
	}
	else {
		$args['is_published'] = true;
	}

	$args['filter'] = $args['search_terms'];

	if (!$args['page']) $args['page'] = 1;
	if (!$args['per_page']) $args['per_page'] = 20;

	return $args;
}

function bfox_bp_plans_schedule_loop_args() {
	global $bp;
	$plan = bfox_bp_plan();

	wp_parse_str(bp_ajax_querystring('reading_schedules'), $args);
	if ($plan->id) {
		$args['plan_id'] = $plan->id;
		if ('my-schedules' == $bp->action_variables[0]) $args['user_id'] = $bp->loggedin_user->id;
		elseif ('friend-schedules' == $bp->action_variables[0]) $args['user_id'] = (array) friends_get_friend_user_ids($bp->loggedin_user->id);
	}
	else {
		$args = BfoxReadingSchedule::add_current_owner_to_args($args);
		$args['cache_latest_readings'] = true;
	}

	if (!$args['page']) $args['page'] = 1;
	if (!$args['per_page']) $args['per_page'] = 20;

	return $args;
}

function bfox_bp_plans_user_owner_options($user_id = 0) {
	if (!$user_id) $user_id = $GLOBALS['user_ID'];

	$results = BP_Groups_Member::get_is_admin_of($user_id);

	$options = array();
	foreach ((array) $results['groups'] as $group) {
		$id = BfoxOwnedObject::owner_type_group . '-' . $group->id;
		$options[$id] = stripslashes($group->name);
	}

	return $options;
}

function bfox_bp_plans_parse_checks($checks) {
	$readings = array();
	foreach ($checks as $check) if (!empty($check)) {
		list($schedule_id, $reading_id) = explode('-', $check, 2);
		$readings[(int) $schedule_id] []= (int) $reading_id;
	}
	return $readings;
}

function bfox_bp_plans_save_checks() {
	if (isset($_POST['bible_reading_save_checks'])) {
		global $bp;

		$old_checks = explode(',', $_POST['bible_reading_plan_old_checks']);
		$checks = (array) $_POST['bible_reading_plan_checkbox'];

		// If checked before, but not now delete
		$remove_readings = bfox_bp_plans_parse_checks(array_diff($old_checks, $checks));

		// If not checked before, but checked now add
		$add_readings = bfox_bp_plans_parse_checks(array_diff($checks, $old_checks));

		$schedule_ids = array_unique(array_merge(array_keys($remove_readings), array_keys($add_readings)));

		BfoxReadingSchedule::remove_progress($remove_readings, $bp->loggedin_user->id);
		BfoxReadingSchedule::add_progress($add_readings, $bp->loggedin_user->id);

		bp_core_redirect(wp_get_referer());
	}
}
add_action('wp', 'bfox_bp_plans_save_checks', 3);

function bfox_bp_plans_avatar_upload_dir() {
	global $bp;

	$plan = bfox_bp_plan();

	$path = BP_AVATAR_UPLOAD_PATH . '/' . BfoxReadingPlan::avatar_dir . '/' . $plan->id;
	$newbdir = $path;

	if ( !file_exists( $path ) )
		@wp_mkdir_p( $path );

	$newurl = str_replace( BP_AVATAR_UPLOAD_PATH, BP_AVATAR_URL, $path );
	$newburl = $newurl;
	$newsubdir = '/' . BfoxReadingPlan::avatar_dir . '/' . $group_id;

	return apply_filters( 'bfox_bp_plans_avatar_upload_dir', array( 'path' => $path, 'url' => $newurl, 'subdir' => $newsubdir, 'basedir' => $newbdir, 'baseurl' => $newburl, 'error' => false ) );
}

function bfox_bp_plans_bp_core_avatar_dir($avatar_dir, $object) {
	if ('plan' == $object) $avatar_dir = BfoxReadingPlan::avatar_dir;
	return $avatar_dir;
}
add_action('bp_core_avatar_dir', 'bfox_bp_plans_bp_core_avatar_dir', 10, 2);

function bfox_bp_plans_bp_core_gravatar_email($email, $item_id, $object) {
	global $bp;
	if (empty($email) && 'plan' == $object) $email = "{$item_id}-{$object}@{$bp->root_domain}";
	return $email;
}
add_action('bp_core_gravatar_email', 'bfox_bp_plans_bp_core_gravatar_email', 10, 3);

/*
 * Template Functions
 */

function bfox_bp_directory_plans_search_form() {
	global $bp;

	$search_value = __( 'Search anything...', 'buddypress' );
	if ( !empty( $_REQUEST['s'] ) )
	 	$search_value = $_REQUEST['s'];

?>
	<form action="" method="get" id="search-plans-form">
		<label><input type="text" name="s" id="plans_search" value="<?php echo attribute_escape($search_value) ?>"  onfocus="if (this.value == '<?php _e( 'Search anything...', 'buddypress' ) ?>') {this.value = '';}" onblur="if (this.value == '') {this.value = '<?php _e( 'Search anything...', 'buddypress' ) ?>';}" /></label>
		<input type="submit" id="plans_search_submit" name="plans_search_submit" value="<?php _e( 'Search', 'buddypress' ) ?>" />
	</form>
<?php
}

function bfox_bp_plans_schedule_tabs() {
	global $bp;
	$plan = bfox_bp_plan();
	$current_tab = $bp->action_variables[0];
?>
	<li<?php if ( 'all-schedules' == $current_tab || empty($current_tab)): ?> class="current"<?php endif; ?>><a href="<?php echo $plan->url() . 'schedules/' ?>"><?php _e('All Schedules', 'buddypress') ?></a></li>
	<li<?php if ( 'my-schedules' == $current_tab ): ?> class="current"<?php endif; ?>><a href="<?php echo $plan->url() . 'schedules/my-schedules/' ?>"><?php _e('My Schedules', 'buddypress') ?></a></li>
	<li<?php if ( 'friend-schedules' == $current_tab ): ?> class="current"<?php endif; ?>><a href="<?php echo $plan->url() . 'schedules/friend-schedules/' ?>"><?php _e('Friend Schedules', 'buddypress') ?></a></li>
<?php
}

function bfox_bp_bible_study_group_screen_view_tabs() {
	global $bp;

	$group_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/' . BFOX_BIBLE_SLUG . '/';

	$action = $bp->action_variables[0];
	if (empty($action)) $action = 'calendar';

	$nav = array(
		'calendar' => __('Calendar', 'bfox'),
		'schedules' => __('Scheduled Plans', 'bfox'),
	);

	if ('schedule' == $action || 'delete-schedule' == $action) {
		$schedule = bfox_bp_schedule();

		if ('schedule' == $action) $nav["schedule/$schedule->id"] = __('View Schedule', 'bfox');
		elseif ('delete-schedule' == $action) $nav["delete-schedule/$schedule->id"] = __('Delete Schedule', 'bfox');

		$action .= '/' . $schedule->id;
	}

	?>
	<div class="item-list-tabs no-ajax" id="subnav">
		<ul>
		<?php
		foreach ($nav as $slug => $label) {
			if ($slug == $action) $selected = ' class="current selected"';
			else $selected = '';

			$link = $group_link . $slug . '/';
			echo "<li id='$slug-personal-li'$selected><a id='$slug' href='$link'>$label</a></li>";
		}
		?>
		</ul>
	</div><!-- .item-list-tabs -->
	<?php
}

/*
 * Output functions
 */

function bfox_bp_plans_add_sidebar() {
	global $bp;
	if (BFOX_BIBLE_SLUG == bp_current_component() && bp_is_directory() && $bp->loggedin_user->id) {
		$ref = bfox_ref();
		$schedule_ids = bfox_bp_plans_get_active_schedule_ids();
		$revision_ids = array();
		foreach ($schedule_ids as $schedule_id) if ($_schedule->revision_id) $revision_ids []= $_schedule->revision_id;

		$args = array('show_checks' => true, 'user_id' => $bp->loggedin_user->id);
		?>
		<div id="bible-readings-for-passage" class="widget">
			<h3 class="widgettitle"><?php printf(__('Readings for %s', 'bfox'), $ref->get_string()) ?></h3>
			<form action="" method="post">
			<?php $checked_ids = bfox_bp_plans_list_readings($schedule_ids, BfoxReadingPlan::get_readings(array('revision_id' => $revision_ids, 'ref' => $ref)), $args); ?>
			<input type="hidden" name="bible_reading_plan_old_checks" value="<?php echo implode(',', $checked_ids) ?>" />
			<input type="submit" name="bible_reading_save_checks" value="<?php _e('Save', 'bfox') ?>" />
			</form>
		</div>
		<?php
	}
}
add_action('bfox_site_bp_inside_immediately_before_sidebar', 'bfox_bp_plans_add_sidebar');

function bfox_bp_plans_list_readings($schedule_ids, $readings, $args = array()) {
	extract($args);

	if (empty($ref_name)) $ref_name = BibleMeta::name_short;
	if (empty($date_format)) $date_format = 'M j';

	BfoxReadingSchedule::collect_checkbox_ids_start();

	if (!empty($schedule_ids)) {
	?>
	<ul class="bible-reading-plan-checklist<?php if ($is_item_list) echo ' item-list' ?>">
	<?php foreach ($schedule_ids as $schedule_id): $schedule = BfoxReadingSchedule::schedule($schedule_id); ?>
		<?php $plan = $schedule->plan() ?>
		<li class="bible-reading-schedule">
			<?php if ($is_item_list): ?>
			<div class="item-avatar">
				<a href="<?php echo $plan->url() ?>"><?php echo $plan->avatar( 'type=thumb&width=50&height=50' ) ?></a>
			</div>

			<div class="item">
				<div class="item-title"><a href="<?php echo $schedule->url($user_id) ?>"><?php echo $schedule->label() ?></a> <?php _e('by', 'bfox') ?> <?php echo $plan->owner_name() ?></div>

				<div class="item-desc"></div>
			<?php else: ?>
				<a href="<?php echo $schedule->url($user_id) ?>"><?php echo $schedule->label() ?></a>
			<?php endif ?>

				<ul class="item-reading-list">
				<?php $schedule_readings = $readings[$schedule->revision_id] ?>
				<?php if (empty($schedule_readings)): ?>
					<li class="bible-reading"><?php _e('No readings', 'bfox') ?></li>
				<?php else: ?>
					<?php foreach ($schedule_readings as $reading_id => $reading): ?>

					<li class="bible-reading">
						<label>
						<?php if ($show_checks) echo $schedule->reading_checkbox($reading_id) ?>
						<?php echo $schedule->reading_date($reading_id, $date_format) . ':' ?><?php if ($show_number) echo ' #' . ($reading_id + 1) ?></label>
						<?php echo bfox_ref_bible_link(array('ref_str' => $reading->get_string($ref_name), 'disable_tooltip' => true)) ?>
					</li>

					<?php endforeach ?>
				<?php endif ?>
				</ul>
			<?php if ($is_item_list): ?>
			</div>
			<?php endif ?>

		</li>
	<?php endforeach ?>
	</ul>
	<?php
	}
	else {
		echo '<p>' . __('No Reading Plans found') . '</p>';
	}

	return BfoxReadingSchedule::collect_checkbox_ids_end();
}

function bfox_bp_plans_list_by_date($schedule_ids, $start_date, $end_date = '', $args = array()) {
	if (empty($end_date)) $end_date = $start_date;

	$reading_ids = array();
	foreach ($schedule_ids as $schedule_id) {
		$schedule = BfoxReadingSchedule::schedule($schedule_id);
		$_reading_ids = $schedule->readings_in_range($start_date, $end_date);

		if (!empty($_reading_ids)) $reading_ids[$schedule->revision_id] = $_reading_ids;
	}

	return bfox_bp_plans_list_readings($schedule_ids, BfoxReadingPlan::get_readings(array('reading_ids' => $reading_ids)), $args);
}

function bfox_bp_plans_list_month_year_readings($month, $year) {
	global $bp;

	$start_date = date('Y-m-d', strtotime("$year-$month-01"));
	$end_date = date('Y-m-d', strtotime('+1 month -1 day', strtotime($start_date)));

	$schedule_ids = BfoxReadingSchedule::get(BfoxReadingSchedule::add_current_owner_to_args(array('date' => array($start_date, $end_date))));

	if ($bp->groups->current_group->id) $show_checks = groups_is_user_member($bp->loggedin_user->id, $bp->groups->current_group->id);
	else $show_checks = ($bp->loggedin_user->id == $bp->displayed_user->id);

	?>
		<h4 class="plans-calendar"><?php printf(__('Readings for %s', 'bfox'), date('F, Y', strtotime($start_date))) ?></h4>
		<form action="" method="post">
		<?php $checked_ids = bfox_bp_plans_list_by_date($schedule_ids, $start_date, $end_date, array('date_format' => 'F j (D)', 'is_item_list' => true, 'show_checks' => $show_checks)) ?>
		<br/>
	<?php if ($show_checks && !empty($schedule_ids)): ?>
		<p>
		<input type="hidden" name="bible_reading_plan_old_checks" value="<?php echo implode(',', $checked_ids) ?>" />
		<input type="submit" name="bible_reading_save_checks" value="<?php _e('Save Progress', 'bfox') ?>" />
		</p>
	<?php endif ?>
	<?php if ($show_checks): ?>
		<p>
		<a href="<?php echo bfox_bp_plan_url() ?>"><?php _e('Schedule a reading plan', 'bfox') ?></a>
		</p>
	<?php endif ?>
		</form>
	<?php
}

function bfox_bp_plan_divide_into_cols($array, $max_cols, $height_threshold = 0) {
	$count = count($array);
	if (0 < $count)
	{

		// The height_threshold is so that we don't divide into too many columns for small arrays
		// So, for instance, if we have 3 max columns and 5 array elements, and a threshold of 5, we shouldn't
		// divide that into 3 short columns, but one column of 5
		if (0 == $height_threshold)
			$cols = $max_cols;
		else
			$cols = ceil($count / $height_threshold);

		if ($cols > $max_cols) $cols = $max_cols;

		$array = array_chunk($array, ceil($count / $cols), TRUE);
	}
	return $array;
}

function bfox_bp_plan_chart($readings, BfoxReadingSchedule $schedule = null, $user_id = null) {
	if (empty($max_cols)) $max_cols = 3;

	$reading_cols = bfox_bp_plan_divide_into_cols($readings, $max_cols, 5);

	$date_col = '';
	$date_col_header = '';
	if (!is_null($schedule)) {
		$is_user_member = $user_id && $schedule->is_user_member($user_id);
		$date_col_header = '<th>Date</th>';
	}

	$header = '';
	$rows = array();
	$num_rows = 0;
	foreach ($reading_cols as $col_num => $reading_col) {
		$header .= "<th>#</th>$date_col_header<th>Passage</th>";
		$row = 0;
		foreach ($reading_col as $reading_id => $reading) {
			$reading_num = $reading_id + 1;
			$ref_str = bfox_ref_bible_link(array('ref' => $reading, 'name' => BibleMeta::name_short, 'disable_tooltip' => true));
			if (!is_null($schedule)) $date_col = '<td>' . ($is_user_member ? $schedule->reading_checkbox($reading_id) : '') . ' ' . $schedule->reading_date($reading_id, 'M j') . '</td>';
			$rows[$row] .= "<td>$reading_num</td>$date_col<td>$ref_str</td>";
			$row++;
		}
		if ($row > $num_rows) $num_rows = $row;
		else {
			while ($row < $num_rows) {
				$rows[$row] .= '<td></td><td></td>' . (is_null($schedule) ? '' : '<td></td>');
				$row++;
			}
		}
	}

	$content = "<table><thead><tr>$header</tr></thead><tbody>";
	foreach ($rows as $row) $content .= "<tr>$row</tr>";

	$content .= '</tbody></table>';

	return $content;
}

/***************************************************************************
 * Reading Plan Creation Process Template Tags
 **/

function bfox_bp_plan_creation_tabs() {
	global $bp;

	if ( !is_array( $bp->plans->plan_creation_steps ) )
		return false;

	if ( !$bp->plans->current_create_step )
		$bp->plans->current_create_step = array_shift( array_keys( $bp->plans->plan_creation_steps ) );

	$counter = 1;
	foreach ( $bp->plans->plan_creation_steps as $slug => $step ) {
		$is_enabled = bfox_bp_are_previous_plan_creation_steps_complete( $slug ); ?>

		<li<?php if ( $bp->plans->current_create_step == $slug ) : ?> class="current"<?php endif; ?>><?php if ( $is_enabled ) : ?><a href="<?php echo bfox_bp_plans_create_url($slug) ?>"><?php else: ?><span><?php endif; ?><?php echo $counter ?>. <?php echo $step['name'] ?><?php if ( $is_enabled ) : ?></a><?php else: ?></span><?php endif; ?></li><?php
		$counter++;
	}

	unset( $is_enabled );

	do_action( 'plans_creation_tabs' );
}

function bfox_bp_plan_creation_form_action() {
	global $bp;
	echo bfox_bp_plans_create_url($bp->action_variables[1]);
}

function bfox_bp_is_plan_creation_step( $step_slug ) {
	global $bp;

	/* Make sure we are in the plans component */
	if ( $bp->current_component != BFOX_PLANS_SLUG || 'create' != $bp->current_action )
		return false;

	/* If this the first step, we can just accept and return true */
	if ( !$bp->action_variables[1] && array_shift( array_keys( $bp->plans->plan_creation_steps ) ) == $step_slug )
		return true;

	/* Before allowing a user to see a plan creation step we must make sure previous steps are completed */
	if ( !bfox_bp_is_first_plan_creation_step() ) {
		if ( !bfox_bp_are_previous_plan_creation_steps_complete( $step_slug ) )
			return false;
	}

	/* Check the current step against the step parameter */
	if ( $bp->action_variables[1] == $step_slug )
		return true;

	return false;
}

function bfox_bp_is_plan_creation_step_complete( $step_slugs ) {
	global $bp;

	if ( !$bp->plans->completed_create_steps )
		return false;

	if ( is_array( $step_slugs ) ) {
		$found = true;

		foreach ( $step_slugs as $step_slug ) {
			if ( !in_array( $step_slug, $bp->plans->completed_create_steps ) )
				$found = false;
		}

		return $found;
	} else {
		return in_array( $step_slugs, $bp->plans->completed_create_steps );
	}

	return true;
}

function bfox_bp_are_previous_plan_creation_steps_complete( $step_slug ) {
	global $bp;

	/* If this is the first plan creation step, return true */
	if ( array_shift( array_keys( $bp->plans->plan_creation_steps ) ) == $step_slug )
		return true;

	reset( $bp->plans->plan_creation_steps );
	unset( $previous_steps );

	/* Get previous steps */
	foreach ( $bp->plans->plan_creation_steps as $slug => $name ) {
		if ( $slug == $step_slug )
			break;

		$previous_steps[] = $slug;
	}

	return bfox_bp_is_plan_creation_step_complete( $previous_steps );
}

function bp_plan_creation_previous_link() {
	echo bp_get_plan_creation_previous_link();
}
	function bp_get_plan_creation_previous_link() {
		global $bp;

		foreach ( $bp->plans->plan_creation_steps as $slug => $name ) {
			if ( $slug == $bp->action_variables[1] )
				break;

			$previous_steps[] = $slug;
		}

		return apply_filters( 'bp_get_plan_creation_previous_link', bfox_bp_plans_create_url(array_pop( $previous_steps )) );
	}

function bfox_bp_is_last_plan_creation_step() {
	global $bp;

	$last_step = array_pop( array_keys( $bp->plans->plan_creation_steps ) );

	if ( $last_step == $bp->plans->current_create_step )
		return true;

	return false;
}

function bfox_bp_is_first_plan_creation_step() {
	global $bp;

	$first_step = array_shift( array_keys( $bp->plans->plan_creation_steps ) );

	if ( $first_step == $bp->plans->current_create_step )
		return true;

	return false;
}

/*
 * Widgets
 */

class Bfox_Bp_Plans_Calendar_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(false, 'Reading Plan Calendar');
	}

	public function widget($args, $instance) {
		extract($args);

		// Only show this widget for users
		global $user_ID;
		if (empty($user_ID)) return;

		if (empty($instance['title'])) $instance['title'] = __('My Bible Readings', 'bfox');
		echo $before_widget . $before_title . $instance['title'] . $after_title;

		$schedule_ids = bfox_bp_plans_get_active_schedule_ids();
		//$start_date = date('Y-m-d', strtotime('last saturday +1 day')); // Start on Sundays
		$start_date = date('Y-m-d', strtotime('-3 days')); // Start 3 days ago
		$end_date = date('Y-m-d', strtotime('+3 days')); // Start 3 days from now

		if (empty($schedule_ids)) {
			echo __('You don\'t have any active reading plan schedules.', 'bfox');
		}
		else {
			$args = array('date_format' => 'l', 'show_checks' => true, 'user_id' => $user_ID);
			?>
			<form action="" method="post">
			<?php $checked_ids = bfox_bp_plans_list_by_date($schedule_ids, $start_date, $end_date, $args) ?>
			<input type="hidden" name="bible_reading_plan_old_checks" value="<?php echo implode(',', $checked_ids) ?>" />
			<input type="submit" name="bible_reading_save_checks" value="<?php _e('Save Progress', 'bfox') ?>" />
			</form>
			<br/>
			<a href="<?php echo bfox_bp_bible_study_calendar_url() ?>"><?php _e('View Calendar', 'bfox') ?></a>
			<?php
		}

		?>
		<br/>
		<a href="<?php echo bfox_bp_plan_url() ?>"><?php _e('Schedule a reading plan', 'bfox') ?></a></p>
		<?php

		echo $after_widget;
	}
}

function bfox_bp_plans_register_widgets() {
	register_widget('Bfox_Bp_Plans_Calendar_Widget');
}
add_action('bfox_bp_register_widgets', 'bfox_bp_plans_register_widgets');

?>