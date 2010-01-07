<?php
/*
Plugin Name: Biblefox Reading Plans for BuddyPress
Plugin URI: http://example.org/my/awesome/bp/component
Description: This BuddyPress component is the greatest thing since sliced bread.
Version: 1.3
Revision Date: MMMM DD, YYYY
Requires at least: What WPMU version, what BuddyPress version? ( Example: WPMU 2.8.4, BuddyPress 1.1 )
Tested up to: What WPMU version, what BuddyPress version?
License: (Example: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html)
Author: Dr. Jan Itor
Author URI: http://example.org/some/cool/developer
Site Wide Only: true
*/

/* Define a constant that can be checked to see if the component is installed or not. */
define ( 'BP_PLANS_IS_INSTALLED', 1 );

/* Define a constant that will hold the current version number of the component */
define ( 'BP_PLANS_VERSION', '1.3' );

/* Define a constant that will hold the database version number that can be used for upgrading the DB
 *
 * NOTE: When table defintions change and you need to upgrade,
 * make sure that you increment this constant so that it runs the install function again.
 *
 * Also, if you have errors when testing the component for the first time, make sure that you check to
 * see if the table(s) got created. If not, you'll most likely need to increment this constant as
 * BP_PLANS_DB_VERSION was written to the wp_usermeta table and the install function will not be
 * triggered again unless you increment the version to a number higher than stored in the meta data.
 */
define ( 'BP_PLANS_DB_VERSION', '1' );

/* Define a slug constant that will be used to view this components pages (http://example.org/SLUG) */
if ( !defined( 'BP_PLANS_SLUG' ) )
	define ( 'BP_PLANS_SLUG', 'bible' );

define(BP_PLANS_BASE_TABLE_PREFIX, $GLOBALS['wpdb']->base_prefix . 'bfox_');

/*
 * If you want the users of your component to be able to change the values of your other custom constants,
 * you can use this code to allow them to add new definitions to the wp-config.php file and set the value there.
 *
 *
 *	if ( !defined( 'BP_PLANS_CONSTANT' ) )
 *		define ( 'BP_PLANS_CONSTANT', 'some value' // or some value without quotes if integer );
 */

/**
 * You should try hard to support translation in your component. It's actually very easy.
 * Make sure you wrap any rendered text in __() or _e() and it will then be translatable.
 *
 * You must also provide a text domain, so translation files know which bits of text to translate.
 * Throughout this example the text domain used is 'bp-plans', you can use whatever you want.
 * Put the text domain as the second parameter:
 *
 * __( 'This text will be translatable', 'bp-plans' ); // Returns the first parameter value
 * _e( 'This text will be translatable', 'bp-plans' ); // Echos the first parameter value
 */

if ( file_exists( WP_PLUGIN_DIR . '/bp-plans/languages/' . get_locale() . '.mo' ) )
	load_textdomain( 'bp-plans', WP_PLUGIN_DIR . '/bp-plans/languages/' . get_locale() . '.mo' );

/**
 * The next step is to include all the files you need for your component.
 * You should remove or comment out any files that you don't need.
 */

require_once BFOX_PLANS_DIR . '/plans.php';

/* The classes file should hold all database access classes and functions */
//require ( BP_BIBLE_DIR . '/bp-plans/bp-plans-classes.php' );

/* The ajax file should hold all functions used in AJAX queries */
//require ( BP_BIBLE_DIR . '/bp-plans/bp-plans-ajax.php' );

/* The cssjs file should set up and enqueue all CSS and JS files used by the component */
//require ( BP_BIBLE_DIR . '/bp-plans/bp-plans-cssjs.php' );

/* The templatetags file should contain classes and functions designed for use in template files */
require ( BP_BIBLE_DIR . '/bp-plans/bp-plans-templatetags.php' );

/* The widgets file should contain code to create and register widgets for the component */
//require ( BP_BIBLE_DIR . '/bp-plans/bp-plans-widgets.php' );

/* The notifications file should contain functions to send email notifications on specific user actions */
//require ( BP_BIBLE_DIR . '/bp-plans/bp-plans-notifications.php' );

/* The filters file should create and apply filters to component output functions. */
//require ( BP_BIBLE_DIR . '/bp-plans/bp-plans-filters.php' );

/**
 * bp_plans_install()
 *
 * Installs and/or upgrades the database tables for your component
 */
function bp_plans_install() {
	global $wpdb, $bp;

	if ( !empty($wpdb->charset) )
		$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";

	/**
	 * You'll need to write your table definition below, if you want to
	 * install database tables for your component. You can define multiple
	 * tables by adding SQL to the $sql array.
	 *
	 * Creating multiple tables:
	 * $bp->xxx->table_name is defined in bp_plans_setup_globals() below.
	 *
	 * You will need to define extra table names in that function to create multiple tables.
	 */
	$sql[] = "CREATE TABLE {$bp->plans->table_name} (
		  		id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  		field_1 bigint(20) NOT NULL,
		  		field_2 bigint(20) NOT NULL,
		  		field_3 bool DEFAULT 0,
			    KEY field_1 (field_1),
			    KEY field_2 (field_2)
		 	   ) {$charset_collate};";

	require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );

	/**
	 * The dbDelta call is commented out so the plans table is not installed.
	 * Once you define the SQL for your new table, uncomment this line to install
	 * the table. (Make sure you increment the BP_PLANS_DB_VERSION constant though).
	 */
	// dbDelta($sql);

	update_site_option( 'bp-plans-db-version', BP_PLANS_DB_VERSION );
}

/**
 * bp_plans_setup_globals()
 *
 * Sets up global variables for your component.
 */
function bp_plans_setup_globals() {
	global $bp, $wpdb;

	/* For internal identification */
	$bp->plans->id = 'bible';

	$bp->plans->table_name = $wpdb->base_prefix . 'bp_plans';
	$bp->plans->format_notification_function = 'bp_plans_format_notifications';
	$bp->plans->slug = BP_PLANS_SLUG;

	$bp->plans->plan_creation_steps = apply_filters( 'plans_create_plan_steps', array(
		'plan-details' => array( 'name' => __( 'Plan Details', 'bp-plans' ), 'position' => 0 ),
		'plan-add-groups' => array( 'name' => __( 'Add Groups of Chapters', 'bp-plans' ), 'position' => 10 ),
		'plan-edit-readings' => array( 'name' => __( 'Edit Readings', 'bp-plans' ), 'position' => 20 )
	) );

	/* Register this in the active components array */
	$bp->active_components[$bp->plans->slug] = $bp->plans->id;

	do_action( 'bp_plans_setup_globals' );
}
add_action( 'plugins_loaded', 'bp_plans_setup_globals', 5 );
add_action( 'admin_menu', 'bp_plans_setup_globals', 2 );

/**
 * bp_plans_check_installed()
 *
 * Checks to see if the DB tables exist or if you are running an old version
 * of the component. If it matches, it will run the installation function.
 */
function bp_plans_check_installed() {
	global $wpdb, $bp;

	if ( !is_site_admin() )
		return false;

	/**
	 * Add the component's administration tab under the "BuddyPress" menu for site administrators
	 *
	 * Use 'bp-general-settings' as the first parameter to add your submenu to the "BuddyPress" menu.
	 * Use 'wpmu-admin.php' if you want it under the "Site Admin" menu.
	 */
	require ( WP_PLUGIN_DIR . '/bp-plans/bp-plans-admin.php' );

	add_submenu_page( 'bp-general-settings', __( 'Reading Plans Admin', 'bp-plans' ), __( 'Reading Plans Admin', 'bp-plans' ), 'manage-options', 'bp-plans-settings', 'bp_plans_admin' );

	/* Need to check db tables exist, activate hook no-worky in mu-plugins folder. */
	if ( get_site_option('bp-plans-db-version') < BP_PLANS_DB_VERSION )
		bp_plans_install();
}
//add_action( 'admin_menu', 'bp_plans_check_installed' );

/**
 * bp_plans_setup_nav()
 *
 * Sets up the navigation items for the component. This adds the top level nav
 * item and all the sub level nav items to the navigation array. This is then
 * rendered in the template.
 */
function bp_plans_setup_nav() {
	global $bp;

	/* Add 'Reading Plans' to the main navigation */
/*	bp_core_new_nav_item( array(
		'name' => __( 'Reading Plans', 'bp-plans' ),
		'slug' => $bp->plans->slug,
		'position' => 80,
		'screen_function' => 'bp_plans_screen_my_plans',
		'default_subnav_slug' => 'my-plans'
	) );
*/
	$plans_link = $bp->loggedin_user->domain . $bp->plans->slug . '/';

	/* Create two sub nav items for this component */
	bp_core_new_subnav_item( array(
		'name' => __( 'Reading Plans', 'bp-plans' ),
		'slug' => 'my-plans',
		'parent_slug' => $bp->plans->slug,
		'parent_url' => $plans_link,
		'screen_function' => 'bp_plans_screen_my_plans',
		'position' => 110,
		'item_css_id' => 'my-plans-list'
	) );

	bp_core_new_subnav_item( array(
		'name' => __( 'Create a Plan', 'bp-plans' ),
		'slug' => 'create',
		'parent_slug' => $bp->plans->slug,
		'parent_url' => $plans_link,
		'screen_function' => 'bp_plans_screen_create',
		'position' => 120,
		'user_has_access' => bp_is_home() // Only the logged in user can access this on his/her profile
	) );

	/* Add a nav item for this component under the settings nav item. See bp_plans_screen_settings_menu() for more info */
	/*
	bp_core_new_subnav_item( array(
		'name' => __( 'Reading Plans', 'bp-plans' ),
		'slug' => 'plans-admin',
		'parent_slug' => $bp->settings->slug,
		'parent_url' => $bp->loggedin_user->domain . $bp->settings->slug . '/',
		'screen_function' => 'bp_plans_screen_settings_menu',
		'position' => 40,
		'user_has_access' => bp_is_home() // Only the logged in user can access this on his/her profile
	) );
	*/

	/* Only execute the following code if we are actually viewing this component (e.g. http://example.org/plans) */
	if ( $bp->current_component == $bp->plans->slug ) {

		if ($plan_id = BfoxPlans::slug_exists($bp->current_action, $bp->displayed_user->id, BfoxPlans::user_type_user)) {
			/* This is a single group page. */
			$bp->is_single_item = true;
			$bp->plans->current_plan = BfoxPlans::get_plan($plan_id);

			// Set whether the current use is an admin for this plan
			if ( is_site_admin() ) $bp->is_item_admin = 1;
			elseif ($bp->plans->current_plan->owner_type == BfoxPlans::user_type_user) $bp->is_item_admin = ($bp->plans->current_plan->owner_id == $bp->loggedin_user->id);
			elseif ($bp->plans->current_plan->owner_type == BfoxPlans::user_type_group) $bp->is_item_admin = groups_is_user_admin($bp->loggedin_user->id, $bp->plans->current_plan->owner_id);

			$plans_link = bp_get_plan_permalink($bp->plans->current_plan) . '/';

			/* When in a single group, the first action is bumped down one because of the
			   group name, so we need to adjust this and set the group name to current_item. */
			$bp->current_item = $bp->current_action;
			$bp->current_action = 'overview';

			if (empty($bp->action_variables[0])) $bp->action_variables[0] = 'overview';

			//$bp->bp_options_title = $bp->plans->current_plan->name;
			//$bp->bp_options_avatar = bp_get_plan_avatar(array('plan' => $bp->plans->current_plan));

			bp_core_new_nav_default( array(
				'parent_slug' => $bp->plans->slug,
				'screen_function' => 'bp_plans_screen_view',
				'subnav_slug' => 'overview'
			) );

			bp_core_new_subnav_item( array(
				'name' => __( 'View Plan', 'bp-plan' ),
				'slug' => 'overview',
				'parent_slug' => $bp->plans->slug,
				'parent_url' => $plans_link,
				'screen_function' => 'bp_plans_screen_view',
				'position' => 130
			) );
		}

		if ( bp_is_home() ) {
			/* If the user is viewing their own profile area set the title to "My Reading Plans" */
			$bp->bp_options_title = __( 'My Reading Plans', 'bp-plans' );
		} else {
			/* If the user is viewing someone elses profile area, set the title to "[user fullname]" */
			$bp->bp_options_avatar = bp_core_fetch_avatar( array( 'item_id' => $bp->displayed_user->id, 'type' => 'thumb' ) );
			$bp->bp_options_title = $bp->displayed_user->fullname;
		}
	}
}
add_action( 'wp', 'bp_plans_setup_nav', 2 );
add_action( 'admin_menu', 'bp_plans_setup_nav', 2 );

/********************************************************************************
 * Screen Functions
 *
 * Screen functions are the controllers of BuddyPress. They will execute when their
 * specific URL is caught. They will first save or manipulate data using business
 * functions, then pass on the user to a template file.
 */


/**
 * bp_plans_screen_my_plans()
 *
 * Sets up and displays the screen output for the sub nav item "plans/my-plans"
 */
function bp_plans_screen_my_plans() {
	global $bp;

	/**
	 * There are three global variables that you should know about and you will
	 * find yourself using often.
	 *
	 * $bp->current_component (string)
	 * This will tell you the current component the user is viewing.
	 *
	 * Example: If the user was on the page http://example.org/members/andy/groups/my-groups
	 *          $bp->current_component would equal 'groups'.
	 *
	 * $bp->current_action (string)
	 * This will tell you the current action the user is carrying out within a component.
	 *
	 * Example: If the user was on the page: http://example.org/members/andy/groups/leave/34
	 *          $bp->current_action would equal 'leave'.
	 *
	 * $bp->action_variables (array)
	 * This will tell you which action variables are set for a specific action
	 *
	 * Example: If the user was on the page: http://example.org/members/andy/groups/join/34
	 *          $bp->action_variables would equal array( '34' );
	 */

	/* Add a do action here, so your component can be extended by others. */
	do_action( 'bp_plans_screen_my_plans' );

	/**
	 * Finally, load the template file. In this example it would load:
	 *    "wp-content/bp-themes/[active-member-theme]/example/my-plans.php"
	 *
	 * The filter gives theme designers the ability to override template names
	 * and define their own theme filenames and structure
	 */
	bp_core_load_template( apply_filters( 'bp_plans_template_screen_my_plans', 'plans/my-plans' ) );
}

/**
 * bp_plans_screen_create()
 *
 * Sets up and displays the screen output for the sub nav item "plans/create"
 */
function bp_plans_screen_create() {
	global $bp;

	do_action( 'bp_plans_screen_create' );

	/* If no current step is set, reset everything so we can start a fresh plan creation */
	if ( !$bp->plans->current_create_step = $bp->action_variables[1] ) {

		unset( $bp->plans->current_create_step );
		unset( $bp->plans->completed_create_steps );

		setcookie( 'bp_new_plan_id', false, time() - 1000, COOKIEPATH );
		setcookie( 'bp_completed_plan_create_steps', false, time() - 1000, COOKIEPATH );

		$reset_steps = true;
		bp_core_redirect( $bp->loggedin_user->domain . $bp->plans->slug . '/create/step/' . array_shift( array_keys( $bp->plans->plan_creation_steps )  ) );
	}

	/* If this is a creation step that is not recognized, just redirect them back to the first screen */
	if ( $bp->action_variables[1] && !$bp->plans->plan_creation_steps[$bp->action_variables[1]] ) {
		bp_core_add_message( __('There was an error saving reading plan details. Please try again.', 'bp-plans'), 'error' );
		bp_core_redirect( $bp->loggedin_user->domain . $bp->plans->slug . '/create' );
	}

	/* Fetch the currently completed steps variable */
	if ( isset( $_COOKIE['bp_completed_plan_create_steps'] ) && !$reset_steps )
		$bp->plans->completed_create_steps = unserialize( stripslashes( $_COOKIE['bp_completed_plan_create_steps'] ) );

	/* Set the ID of the new plan, if it has already been created in a previous step */
	if ( isset( $_COOKIE['bp_new_plan_id'] ) ) $bp->plans->new_plan_id = $_COOKIE['bp_new_plan_id'];
	else $bp->plans->new_plan_id = 0;

	$bp->plans->current_plan = BfoxPlans::get_plan($bp->plans->new_plan_id);
	bp_plans_must_own($bp->plans->current_plan);

	/* If the save, upload or skip button is hit, lets calculate what we need to save */
	if ( isset( $_POST['save'] ) ) {

		/* Check the nonce */
		check_admin_referer( 'plans_create_save_' . $bp->plans->current_create_step );

		if ( 'plan-details' == $bp->plans->current_create_step ) {
			if ( empty( $_POST['plan-name'] ) /*|| empty( $_POST['plan-desc'] )*/ ) {
				bp_core_add_message( __( 'Please fill in all of the required fields', 'bp-plans' ), 'error' );
				bp_core_redirect( $bp->loggedin_user->domain . $bp->plans->slug . '/create/step/' . $bp->plans->current_create_step );
			}

			bp_plans_update_plan_details($bp->plans->current_plan);
			$bp->plans->new_plan_id = $bp->plans->current_plan->id;
		}
		elseif (( 'plan-add-groups' == $bp->plans->current_create_step ) || ( 'plan-edit-readings' == $bp->plans->current_create_step )) {
			bp_plans_update_plan_readings($bp->plans->current_plan);
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
		setcookie( 'bp_new_plan_id', $bp->plans->new_plan_id, time()+60*60*24, COOKIEPATH );
		setcookie( 'bp_completed_plan_create_steps', serialize( $bp->plans->completed_create_steps ), time()+60*60*24, COOKIEPATH );

		/* If we have completed all steps and hit done on the final step we can redirect to the completed plan */
		if ( count( $bp->plans->completed_create_steps ) == count( $bp->plans->plan_creation_steps ) && $bp->plans->current_create_step == array_pop( array_keys( $bp->plans->plan_creation_steps ) ) ) {
			unset( $bp->plans->current_create_step );
			unset( $bp->plans->completed_create_steps );

			/* Once we compelete all steps, record the plan creation in the activity stream. */
			/*plans_record_activity( array(
				'content' => apply_filters( 'plans_activity_created_plan', sprintf( __( '%s created the reading plan %s', 'bp-plans'), bp_core_get_userlink( $bp->loggedin_user->id ), '<a href="' . bp_get_plan_permalink( $bp->plans->current_plan ) . '">' . attribute_escape( $bp->plans->current_plan->name ) . '</a>' ) ),
				'primary_link' => apply_filters( 'plans_activity_created_plan_primary_link', bp_get_plan_permalink( $bp->plans->current_plan ) ),
				'component_action' => 'created_plan',
				'item_id' => $bp->plans->new_plan_id
			) );*/

			do_action( 'plans_plan_create_complete', $bp->plans->new_plan_id );

			bp_core_redirect( bp_get_plan_permalink( $bp->plans->current_plan ) );
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

			bp_core_redirect( $bp->loggedin_user->domain . $bp->plans->slug . '/create/step/' . $next_step );
		}
	}

	bp_core_load_template( apply_filters( 'bp_plans_template_screen_create', 'plans/create' ) );
}


/**
 * bp_plans_screen_view()
 *
 * Sets up and displays the screen output for the sub nav item "plans/view"
 */
function bp_plans_screen_view() {
	global $bp;

	do_action( 'bp_plans_screen_view' );

	// If the edit form has been submitted, save the edited details
	if ( isset( $_POST['save'] ) ) {
		/* Check the nonce first. */
		if ($_POST['plan-name'] && check_admin_referer('plans_edit_plan_details')) {
			bp_plans_update_plan_details($bp->plans->current_plan);
			bp_core_add_message(__('The reading plan was updated.', 'bp-plans'));
			bp_core_redirect(bp_get_plan_permalink($bp->plans->current_plan) . '/edit-details');
		}
		elseif ($_POST['plan-readings'] && check_admin_referer('plans_edit_plan_readings')) {
			bp_plans_update_plan_readings($bp->plans->current_plan);
			bp_core_add_message(__('The reading plan was updated.', 'bp-plans'));
			bp_core_redirect(bp_get_plan_permalink($bp->plans->current_plan) . '/edit-readings');
		}
	}
	elseif (isset($_POST['copy']) && check_admin_referer('plans_copy_plan')) {
		$plan = BfoxPlans::get_plan($_POST['plan-id']);
		if ($plan->is_private) bp_plans_must_own($plan);

		$plan->set_as_copy($bp->loggedin_user->id, BfoxPlans::user_type_user, bp_get_plan_permalink($plan));
		BfoxPlans::save_plan($plan);
		bp_core_add_message("Reading plan created: $plan->name!");
		bp_core_redirect($bp->loggedin_user->domain . $bp->plans->slug . '/my-plans');
	}
	elseif (isset($_POST['delete']) && check_admin_referer('plans_delete_plan')) {
		$plan = BfoxPlans::get_plan($_POST['plan-id']);
		bp_plans_must_own($plan);

		BfoxPlans::delete_plan($plan);
		bp_core_add_message("Reading plan deleted: $plan->name!");
		bp_core_redirect($bp->loggedin_user->domain . $bp->plans->slug . '/my-plans');
	}
	elseif (isset($_POST['toggle-finished']) && check_admin_referer('plans_toggle_finished_plan')) {
		$plan = BfoxPlans::get_plan($_POST['plan-id']);
		bp_plans_must_own($plan);

		$plan->is_finished = !$plan->is_finished;
		BfoxPlans::save_plan($plan);
		$active = $plan->is_finished ? 'inactive' : 'active';
		bp_core_add_message("Marked reading plan as $active: $plan->name!");
		bp_core_redirect($bp->loggedin_user->domain . $bp->plans->slug . '/my-plans/' . $active);
	}

	if ('print' == $bp->action_variables[0]) bp_core_load_template( apply_filters( 'bp_plans_template_screen_view', 'plans/print' ) );
	else bp_core_load_template( apply_filters( 'bp_plans_template_screen_view', 'plans/view' ) );
}

function bp_plans_must_own(BfoxReadingPlan $plan = NULL) {
	if (!bp_plan_is_owned($plan)) {
		bp_core_add_message(__('The action you are trying to do can only be done by the owner of the reading plan!'), 'error');
		bp_core_redirect(bp_get_plan_permalink($plan));
	}
}

function bp_plans_update_plan_details(BfoxReadingPlan $plan) {
	bp_plans_must_own($plan);

	$plan->name = strip_tags(stripslashes($_POST['plan-name']));
	$plan->description = strip_tags(stripslashes($_POST['plan-desc']), '<a><b><em><i><strong>');
	$plan->is_private = $_POST['plan-privacy'];
	$plan->is_scheduled = (bool) $_POST['plan-schedule'];
	$plan->set_start_date($_POST['plan-start']);
	$plan->frequency = max(0, $_POST['plan-schedule'] - 1);
	$plan->set_freq_options((array) $_POST['plan-days']);
	$plan->finish_setting_plan();

	BfoxPlans::save_plan($plan);
}

function bp_plans_update_plan_readings(BfoxReadingPlan $plan) {
	bp_plans_must_own($plan);

	$plan->set_readings_by_strings(stripslashes($_POST['plan-readings']));
	$plan->add_passages(stripslashes($_POST['plan-chunks']), $_POST['plan-chunk-size']);
	$plan->finish_setting_plan();

	BfoxPlans::save_plan($plan);
}

function bp_plans_user_plans_permalink() {
	global $bp;
	return $bp->loggedin_user->domain . $bp->plans->slug;
}

?>