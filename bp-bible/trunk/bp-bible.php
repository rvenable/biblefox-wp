<?php
/*
Plugin Name: BuddyPress Bible
Plugin URI: http://tools.biblefox.com/bp-bible/
Description: A BuddyPress component to add the Bible to BuddyPress.
Version: 0.5
Revision Date: Aug 17, 2009
Requires at least: WPMU 2.8, BuddyPress 1.0.3
Tested up to: WPMU 2.8, BuddyPress 1.0.3
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html
Author: Biblefox
Author URI: http://biblefox.com
Site Wide Only: true
*/

define(BP_BIBLE_DIR, dirname(__FILE__));
define(BP_BIBLE_URL, WP_PLUGIN_URL . '/bp-bible');

define(BFOX_BIBLE_DIR, BP_BIBLE_DIR . '/bible');
define(BFOX_PLANS_DIR, BP_BIBLE_DIR . '/plans');

define(BP_BIBLE_BASE_TABLE_PREFIX, $GLOBALS['wpdb']->base_prefix . 'bfox_');

require_once BP_BIBLE_DIR . '/loop-template.php';
require_once BFOX_PLANS_DIR . '/bp-plans.php';
require_once BFOX_BIBLE_DIR . '/passage.php';
require_once BFOX_BIBLE_DIR . '/history.php';
require_once BFOX_BIBLE_DIR . '/bible.php';

/*************************************************************************************************************
 --- SKELETON COMPONENT V1.2.2 ---

 Contributors: apeatling, jeffsayre

 This is a bare-bones component that should provide a good starting block to building your own custom BuddyPress
 component.

 It includes some of the functions that will make it easy to get your component registering activity stream
 items, posting notifications, setting up widgets, adding AJAX functionality and also structuring your
 component in a standardized way.

 It is by no means the letter of the law. You can go about writing your component in any style you like, that's
 one of the best (and worst!) features of a PHP based platform.

 I would recommend reading some of the comments littered throughout, as they will provide insight into how
 things tick within BuddyPress.

 You should replace all references to the word 'example' with something more suitable for your component.

 IMPORTANT: DO NOT configure your component so that it has to run in the /plugins/buddypress/ directory. If you
 do this, whenever the user auto-upgrades BuddyPress - your custom component will be deleted automatically. Design
 your component to run in the /wp-content/plugins/ directory
 *************************************************************************************************************/

/* Define a constant that can be checked to see if the component is installed or not. */
define ( 'BP_BIBLE_IS_INSTALLED', 1 );

/* Define a constant that will hold the current version number of the component */
define ( 'BP_BIBLE_VERSION', '0.5' );

/* Define a constant that will hold the database version number that can be used for upgrading the DB
 *
 * NOTE: When table defintions change and you need to upgrade,
 * make sure that you increment this constant so that it runs the install function again.
 *
 * Also, if you have errors when testing the component for the first time, make sure that you check to
 * see if the table(s) got created. If not, you'll most likely need to increment this constant as
 * BP_BIBLE_DB_VERSION was written to the wp_usermeta table and the install function will not be
 * triggered again unless you increment the version to a number higher than stored in the meta data.
 */
define ( 'BP_BIBLE_DB_VERSION', '1' );

/* Define a slug constant that will be used to view this components pages (http://example.org/SLUG) */
if ( !defined( 'BP_BIBLE_SLUG' ) )
	define ( 'BP_BIBLE_SLUG', 'bible' );

/*
 * If you want the users of your component to be able to change the values of your other custom constants,
 * you can use this code to allow them to add new definitions to the wp-config.php file and set the value there.
 *
 *
 *	if ( !defined( 'BP_BIBLE_CONSTANT' ) )
 *		define ( 'BP_BIBLE_CONSTANT', 'some value' // or some value without quotes if integer );
 */

if ( file_exists( BP_BIBLE_DIR . '/bp-bible/languages/' . get_locale() . '.mo' ) )
	load_textdomain( 'bp-bible', BP_BIBLE_DIR . '/bp-bible/languages/' . get_locale() . '.mo' );

/**
 * The next step is to include all the files you need for your component.
 * You should remove or comment out any files that you don't need.
 */

/* The classes file should hold all database access classes and functions */
//require ( BP_BIBLE_DIR . '/bp-bible/bp-bible-classes.php' );

/* The ajax file should hold all functions used in AJAX queries */
//require ( BP_BIBLE_DIR . '/bp-bible/bp-bible-ajax.php' );

/* The cssjs file should set up and enqueue all CSS and JS files used by the component */
require ( BP_BIBLE_DIR . '/bp-bible/bp-bible-cssjs.php' );

/* The templatetags file should contain classes and functions designed for use in template files */
require ( BP_BIBLE_DIR . '/bp-bible/bp-bible-templatetags.php' );

/* The widgets file should contain code to create and register widgets for the component */
require ( BP_BIBLE_DIR . '/bp-bible/bp-bible-widgets.php' );

/* The notifications file should contain functions to send email notifications on specific user actions */
//require ( BP_BIBLE_DIR . '/bp-bible/bp-bible-notifications.php' );

/* The filters file should create and apply filters to component output functions. */
//require ( BP_BIBLE_DIR . '/bp-bible/bp-bible-filters.php' );

/**
 * bp_bible_install()
 *
 * Installs and/or upgrades the database tables for your component
 */
function bp_bible_install() {
	global $wpdb, $bp;

	if ( !empty($wpdb->charset) )
		$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";

	/**
	 * You'll need to write your table definition below, if you want to
	 * install database tables for your component. You can define multiple
	 * tables by adding SQL to the $sql array.
	 *
	 * Creating multiple tables:
	 * $bp->xxx->table_name is defined in bp_bible_setup_globals() below.
	 *
	 * You will need to define extra table names in that function to create multiple tables.
	 */
	$sql[] = "CREATE TABLE {$bp->bible->table_name} (
		  		id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  		field_1 bigint(20) NOT NULL,
		  		field_2 bigint(20) NOT NULL,
		  		field_3 bool DEFAULT 0,
			    KEY field_1 (field_1),
			    KEY field_2 (field_2)
		 	   ) {$charset_collate};";

	require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );

	/**
	 * The dbDelta call is commented out so the example table is not installed.
	 * Once you define the SQL for your new table, uncomment this line to install
	 * the table. (Make sure you increment the BP_BIBLE_DB_VERSION constant though).
	 */
	// dbDelta($sql);

	//update_site_option( 'bp-bible-db-version', BP_BIBLE_DB_VERSION );
}

/**
 * bp_bible_setup_globals()
 *
 * Sets up global variables for your component.
 */
function bp_bible_setup_globals() {
	global $bp, $wpdb;

	$bp->bible->table_name = $wpdb->base_prefix . 'bp_bible';
	$bp->bible->image_base = WP_PLUGIN_URL . '/bp-bible/images';
	$bp->bible->format_activity_function = 'bp_bible_format_activity';
	$bp->bible->format_notification_function = 'bp_bible_format_notifications';
	$bp->bible->slug = BP_BIBLE_SLUG;
	if (!empty($_COOKIE['bfox_trans_id'])) $bp->bible->trans_id = $_COOKIE['bfox_trans_id'];
	else $bp->bible->trans_id = 0;

	$bp->version_numbers->bible = BP_BIBLE_VERSION;
}
add_action( 'plugins_loaded', 'bp_bible_setup_globals', 5 );
add_action( 'admin_menu', 'bp_bible_setup_globals', 1 );

function bp_bible_setup_root_component() {
	/* Register 'bible' as a root component */
	bp_core_add_root_component( BP_BIBLE_SLUG );
}
add_action( 'plugins_loaded', 'bp_bible_setup_root_component', 1 );

/**
 * bp_bible_check_installed()
 *
 * Checks to see if the DB tables exist or if you are running an old version
 * of the component. If it matches, it will run the installation function.
 */
function bp_bible_check_installed() {
	global $wpdb, $bp;

	if ( !is_site_admin() )
		return false;

	/***
	 * If you call your admin functionality here, it will only be loaded when the user is in the
	 * wp-admin area, not on every page load.
	 */
	//require ( BP_BIBLE_DIR . '/bp-bible/bp-bible-admin.php' );

	/* Need to check db tables exist, activate hook no-worky in mu-plugins folder. */
	if ( get_site_option('bp-bible-db-version') < BP_BIBLE_DB_VERSION )
		bp_bible_install();
}
add_action( 'admin_menu', 'bp_bible_check_installed' );

/**
 * bp_bible_setup_nav()
 *
 * Sets up the navigation items for the component. This adds the top level nav
 * item and all the sub level nav items to the navigation array. This is then
 * rendered in the template.
 */
function bp_bible_setup_nav() {
	global $bp;

	/* Add 'Bible' to the main navigation */
	bp_core_new_nav_item( array(
		'name' => __( 'Bible', 'bp-bible' ),
		'slug' => $bp->bible->slug,
		'position' => 80,
		'screen_function' => 'bp_bible_screen_passage',
		'default_subnav_slug' => 'passage'
	) );

	$bible_link = $bp->loggedin_user->domain . $bp->bible->slug . '/';

	/* Create two sub nav items for this component */
	bp_core_new_subnav_item( array(
		'name' => __( 'Screen One', 'bp-bible' ),
		'slug' => 'passage',
		'parent_slug' => $bp->bible->slug,
		'parent_url' => $bible_link,
		'screen_function' => 'bp_bible_screen_passage',
		'position' => 10
	) );

	bp_core_new_subnav_item( array(
		'name' => __( 'Screen Two', 'bp-bible' ),
		'slug' => 'screen-two',
		'parent_slug' => $bp->bible->slug,
		'parent_url' => $bible_link,
		'screen_function' => 'bp_bible_screen_search',
		'position' => 20,
		'user_has_access' => bp_is_home() // Only the logged in user can access this on his/her profile
	) );

	/* Add a nav item for this component under the settings nav item. See bp_bible_screen_settings_menu() for more info */
	bp_core_new_subnav_item( array(
		'name' => __( 'Bible', 'bp-bible' ),
		'slug' => 'bible-admin',
		'parent_slug' => $bp->settings->slug,
		'parent_url' => $bp->loggedin_user->domain . $bp->settings->slug . '/',
		'screen_function' => 'bp_bible_screen_settings_menu',
		'position' => 40,
		'user_has_access' => bp_is_home() // Only the logged in user can access this on his/her profile
	) );

	/* Only execute the following code if we are actually viewing this component (e.g. http://example.org/bible) */
	if ( $bp->current_component == $bp->bible->slug ) {
		if ( bp_is_home() ) {
			/* If the user is viewing their own profile area set the title to "My Bible" */
			$bp->bp_options_title = __( 'My Bible', 'bp-bible' );
		} else {
			/* If the user is viewing someone elses profile area, set the title to "[user fullname]" */
			$bp->bp_options_avatar = bp_core_fetch_avatar( array( 'item_id' => $bp->displayed_user->id, 'type' => 'thumb' ) );
			$bp->bp_options_title = $bp->displayed_user->fullname;
		}
	}
}
//add_action( 'wp', 'bp_bible_setup_nav', 2 );
//add_action( 'admin_menu', 'bp_bible_setup_nav', 2 );

function bp_bible_directory_setup() {
	global $bp;

	if ( $bp->current_component == $bp->bible->slug /*&& empty( $bp->current_action )*/ ) {
		$bp->is_directory = true;

		Biblefox::set_default_ref_url(Biblefox::ref_url_bible);
		$redirect = FALSE;

		// Get any passed in translations, save them, and redirect without them
		if (!empty($_REQUEST[BfoxQuery::var_translation])) {
			bp_bible_set_trans_id($_REQUEST[BfoxQuery::var_translation]);
			$redirect = TRUE;
		}

		// Get the refs and search from the URL
		if (!empty($_REQUEST['search-terms'])) {
			$ref_str = $bp->current_action;
			$search_str = trim($_REQUEST['search-terms']);
			$redirect = TRUE;
		}
		else {
			$ref_str = '';
			$search_str = implode('/', $bp->action_variables);
			if (empty($search_str)) $search_str = $bp->current_action;
			else $ref_str = $bp->current_action;
		}

		$search_str = urldecode($search_str);
		$ref_str = urldecode($ref_str);

		// If we don't have a ref_str try to get one from the search_str
		if (empty($ref_str)) {
			list($ref_str, $search_str) = bp_bible_extract_search_refs($search_str);
			// If we found a ref_str and search_str, redirect with the correct URL
			if (!empty($ref_str) && !empty($search_str)) $redirect = TRUE;
		}

		if (empty($search_str)) {
			$input_refs = new BfoxRefs($ref_str);

			// If we are toggling is_read, then we should do it now, and redirect without the parameter
			if (!empty($_GET[BfoxQuery::var_toggle_read])) {
				BfoxHistory::toggle_is_read($_GET[BfoxQuery::var_toggle_read]);
				if ($input_refs->is_valid()) $redirect = TRUE;
			}

			if ($input_refs->is_valid()) {
				// Limit the refs to 20 chapters
				list($refs) = $input_refs->get_sections(20, 1);
			}
			else {
				// Get the last viewed passage
				$history = BfoxHistory::get_history(1);
				$last_viewed = reset($history);

				// If we don't have a valid bible ref, we should use the history
				if (!empty($last_viewed)) $refs = $last_viewed->refs;
				else $refs = new BfoxRefs();

				// If we don't have history, use Gen 1
				if (!$refs->is_valid()) $refs = new BfoxRefs('Gen 1');

				$redirect = TRUE;
			}
		}
		else {
			$refs = BfoxRefParser::with_groups($ref_str);
		}

		global $bp_bible;
		$bp_bible = new BfoxBible($refs, new BfoxTrans(bp_bible_get_trans_id()), $search_str);

		// If we need to redirect, do it
		// Otherwise, load the appropriate page
		if ($redirect) bp_core_redirect(bp_bible_bible_url($bp_bible));
		else {
			global $user_ID;
			update_user_option($user_ID, 'bp_bible_last_search', $bible->search_query);

			if (empty($bp_bible->search_str)) bp_bible_screen_passage();
			else bp_bible_screen_search();
		}
	}
}
add_action( 'wp', 'bp_bible_directory_setup', 2 );

function bp_bible_extract_search_refs($search_str) {

	// First try to extract refs using the 'in:' keyword
	list($new_search, $ref_str) = preg_split('/\s*in\s*:\s*/i', $search_str, 2);
	if (!empty($ref_str)) {
		$new_search = trim($new_search);
		$ref_str = trim($ref_str);

		if (empty($new_search)) {
			$refs = BfoxRefParser::with_groups($ref_str);
			if ($refs->is_valid()) $ref_str = $refs->get_string();
		}
		else $search_str = $new_search;
	}
	// If there was no 'in:' keyword...
	else {
		// Parse out any references in the string, using level 2, no whole books, and save the leftovers
		$refs = new BfoxRefs;
		$data = new BfoxRefParserData($refs, 2, FALSE, FALSE, TRUE);
		BfoxRefParser::parse_string($search_str, $data);

		// If we found bible references
		if ($refs->is_valid()) {
			$ref_str = $refs->get_string();

			// The leftovers become the new search string
			$search_str = trim($data->leftovers);
		}
	}

	return array($ref_str, $search_str);
}

function bp_bible_hack_scripts() {
	bp_bible_add_js();
	bp_bible_add_structure_css();
}
add_action( 'wp', 'bp_bible_hack_scripts', 20 );

/**
 * The following functions are "Screen" functions. This means that they will be run when their
 * corresponding navigation menu item is clicked, they should therefore pass through to a template
 * file to display output to the user.
 */

/**
 * bp_bible_screen_passage()
 *
 * Sets up and displays the screen output for the sub nav item "bible/passage"
 */
function bp_bible_screen_passage() {
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

	/**
	 * On this screen, as a quick example, users can send you a "High Five", by clicking a link.
	 * When a user sends you a high five, you receive a new notification in your
	 * notifications menu, and you will also be notified via email.
	 */

	/**
	 * We need to run a check to see if the current user has clicked on the 'send high five' link.
	 * If they have, then let's send the five, and redirect back with a nice error/success message.
	 */
	if ( $bp->current_component == $bp->bible->slug && 'passage' == $bp->current_action && 'send-h5' == $bp->action_variables[0] ) {
		/* The logged in user has clicked on the 'send high five' link */
		if ( bp_is_home() ) {
			/* Don't let users high five themselves */
			bp_core_add_message( __( 'No self-fives! :)', 'bp-bible' ), 'error' );
		} else {
			if ( bp_bible_send_highfive( $bp->displayed_user->id, $bp->loggedin_user->id ) )
				bp_core_add_message( __( 'High-five sent!', 'bp-bible' ) );
			else
				bp_core_add_message( __( 'High-five could not be sent.', 'bp-bible' ), 'error' );
		}

		bp_core_redirect( $bp->displayed_user->domain . $bp->bible->slug . '/passage' );
	}

	/* Add a do action here, so your component can be extended by others. */
	do_action( 'bp_bible_screen_passage' );

	/**
	 * Finally, load the template file. In this example it would load:
	 *    "wp-content/bp-themes/[active-member-theme]/bible/passage.php"
	 *
	 * The filter gives theme designers the ability to override template names
	 * and define their own theme filenames and structure
	 */
	bp_core_load_template( apply_filters( 'bp_bible_template_screen_passage', 'bible/passage' ) );
}

/**
 * bp_bible_screen_search()
 *
 * Sets up and displays the screen output for the sub nav item "bible/screen-two"
 */
function bp_bible_screen_search() {
	global $bp;

	/**
	 * On the output for this second screen, as an example, there are terms and conditions with an
	 * "Accept" link (directs to http://example.org/members/andy/bible/screen-two/accept)
	 * and a "Reject" link (directs to http://example.org/members/andy/bible/screen-two/reject)
	 */

	if ( $bp->current_component == $bp->bible->slug && 'screen-two' == $bp->current_action && 'accept' == $bp->action_variables[0] ) {
		if ( bp_bible_accept_terms() ) {
			/* Add a success message, that will be displayed in the template on the next page load */
			bp_core_add_message( __( 'Terms were accepted!', 'bp-bible' ) );
		} else {
			/* Add a failure message if there was a problem */
			bp_core_add_message( __( 'Terms could not be accepted.', 'bp-bible' ), 'error' );
		}

		/**
		 * Now redirect back to the page without any actions set, so the user can't carry out actions multiple times
		 * just by refreshing the browser.
		 */
		bp_core_redirect( $bp->loggedin_user->domain . $bp->current_component );
	}

	if ( $bp->current_component == $bp->bible->slug && 'screen-two' == $bp->current_action && 'reject' == $bp->action_variables[0] ) {
		if ( bp_bible_reject_terms() ) {
			/* Add a success message, that will be displayed in the template on the next page load */
			bp_core_add_message( __( 'Terms were rejected!', 'bp-bible' ) );
		} else {
			/* Add a failure message if there was a problem */
			bp_core_add_message( __( 'Terms could not be rejected.', 'bp-bible' ), 'error' );
		}

		/**
		 * Now redirect back to the page without any actions set, so the user can't carry out actions multiple times
		 * just by refreshing the browser.
		 */
		bp_core_redirect( $bp->loggedin_user->domain . $bp->current_component );
	}

	/**
	 * If the user has not Accepted or Rejected anything, then the code above will not run,
	 * we can continue and load the template.
	 */
	do_action( 'bp_bible_screen_search' );

	/* Finally load the plugin template file. */
	bp_core_load_template( apply_filters( 'bp_bible_template_screen_search', 'bible/search' ) );
}

function bp_bible_screen_settings_menu() {
	global $bp, $current_user, $bp_settings_updated, $pass_error;

	if ( isset( $_POST['submit'] ) && check_admin_referer('bp-bible-admin') ) {
		$bp_settings_updated = true;

		/**
		 * This is when the user has hit the save button on their settings.
		 * The best place to store these settings is in wp_usermeta.
		 */
		update_usermeta( $bp->loggedin_user->id, 'bp-bible-option-one', attribute_escape( $_POST['bp-bible-option-one'] ) );
	}

	add_action( 'bp_template_content_header', 'bp_bible_screen_settings_menu_header' );
	add_action( 'bp_template_title', 'bp_bible_screen_settings_menu_title' );
	add_action( 'bp_template_content', 'bp_bible_screen_settings_menu_content' );

	bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'plugin-template' ) );
}

	function bp_bible_screen_settings_menu_header() {
		_e( 'Bible Settings Header', 'bp-bible' );
	}

	function bp_bible_screen_settings_menu_title() {
		_e( 'Bible Settings', 'bp-bible' );
	}

	function bp_bible_screen_settings_menu_content() {
		global $bp, $bp_settings_updated; ?>

		<?php if ( $bp_settings_updated ) { ?>
			<div id="message" class="updated fade">
				<p><?php _e( 'Changes Saved.', 'bp-bible' ) ?></p>
			</div>
		<?php } ?>

		<form action="<?php echo $bp->loggedin_user->domain . 'settings/bible-admin'; ?>" name="bp-bible-admin-form" id="account-delete-form" class="bp-bible-admin-form" method="post">

			<input type="checkbox" name="bp-bible-option-one" id="bp-bible-option-one" value="1"<?php if ( '1' == get_usermeta( $bp->loggedin_user->id, 'bp-bible-option-one' ) ) : ?> checked="checked"<?php endif; ?> /> <?php _e( 'Do you love clicking checkboxes?', 'bp-bible' ); ?>
			<p class="submit">
				<input type="submit" value="<?php _e( 'Save Settings', 'bp-bible' ) ?> &raquo;" id="submit" name="submit" />
			</p>

			<?php
			/* This is very important, don't leave it out. */
			wp_nonce_field( 'bp-bible-admin' );
			?>

		</form>
	<?php
	}


/**
 * bp_bible_screen_notification_settings()
 *
 * Adds notification settings for the component, so that a user can turn off email
 * notifications set on specific component actions.
 */
function bp_bible_screen_notification_settings() {
	global $current_user;

	/**
	 * Under Settings > Notifications within a users profile page they will see
	 * settings to turn off notifications for each component.
	 *
	 * You can plug your custom notification settings into this page, so that when your
	 * component is active, the user will see options to turn off notifications that are
	 * specific to your component.
	 */

	 /**
	  * Each option is stored in a posted array notifications[SETTING_NAME]
	  * When saved, the SETTING_NAME is stored as usermeta for that user.
	  *
	  * For example, notifications[notification_friends_friendship_accepted] could be
	  * used like this:
	  *
	  * if ( 'no' == get_usermeta( $bp['loggedin_userid], 'notification_friends_friendship_accepted' ) )
	  *		// don't send the email notification
	  *	else
	  *		// send the email notification.
      */

	?>
	<table class="notification-settings" id="bp-bible-notification-settings">
		<tr>
			<th class="icon"></th>
			<th class="title"><?php _e( 'Bible', 'bp-bible' ) ?></th>
			<th class="yes"><?php _e( 'Yes', 'bp-bible' ) ?></th>
			<th class="no"><?php _e( 'No', 'bp-bible' )?></th>
		</tr>
		<tr>
			<td></td>
			<td><?php _e( 'Action One', 'bp-bible' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[notification_bible_action_one]" value="yes" <?php if ( !get_usermeta( $current_user->id,'notification_bible_action_one') || 'yes' == get_usermeta( $current_user->id,'notification_bible_action_one') ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[notification_bible_action_one]" value="no" <?php if ( get_usermeta( $current_user->id,'notification_bible_action_one') == 'no' ) { ?>checked="checked" <?php } ?>/></td>
		</tr>
		<tr>
			<td></td>
			<td><?php _e( 'Action Two', 'bp-bible' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[notification_bible_action_two]" value="yes" <?php if ( !get_usermeta( $current_user->id,'notification_bible_action_two') || 'yes' == get_usermeta( $current_user->id,'notification_bible_action_two') ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[notification_bible_action_two]" value="no" <?php if ( 'no' == get_usermeta( $current_user->id,'notification_bible_action_two') ) { ?>checked="checked" <?php } ?>/></td>
		</tr>

		<?php do_action( 'bp_bible_notification_settings' ); ?>
	</table>
<?php
}
//add_action( 'bp_notification_settings', 'bp_bible_screen_notification_settings' );

/**
 * bp_bible_record_activity()
 *
 * If the activity stream component is installed, this function will record activity items for your
 * component.
 *
 * You must pass the function an associated array of arguments:
 *
 *     $args = array(
 *       'item_id' => The ID of the main piece of data being recorded, for example a group_id, user_id, forum_post_id
 *       'component_name' => The slug of the component.
 *       'component_action' => The action being carried out, for example 'new_friendship', 'joined_group'. You will use this to format activity.
 *		 'is_private' => Boolean. Should this not be shown publicly?
 *       'user_id' => The user_id of the person you are recording this activity stream item for.
 *		 'secondary_item_id' => (optional) If the activity is more complex you may need a second ID. For example a group forum post needs the group_id AND the forum_post_id.
 *       'secondary_user_id' => (optional) If this activity applies to two users, provide the second user_id. Eg, Andy and John are now friends should show on both users streams
 *		 'recorded_time' => (optional) The time you want to set as when the activity was carried out (defaults to now)
 *     )
 */
function bp_bible_record_activity( $args ) {
	if ( function_exists('bp_activity_record') ) {
		extract( (array)$args );
		bp_activity_record( $item_id, $component_name, $component_action, $is_private, $secondary_item_id, $user_id, $secondary_user_id, $recorded_time );
	}
}

/**
 * bp_bible_delete_activity()
 *
 * If the activity stream component is installed, this function will delete activity items for your
 * component.
 *
 * You should use this when items are deleted, to keep the activity stream in sync. For example if a user
 * publishes a new blog post, it would record it in the activity stream. However, if they then make it private
 * or they delete it. You'll want to remove it from the activity stream, otherwise you will get out of sync and
 * bad links.
 */
function bp_bible_delete_activity( $args ) {
	if ( function_exists('bp_activity_delete') ) {
		extract( (array)$args );
		bp_activity_delete( $item_id, $component_name, $component_action, $user_id, $secondary_item_id );
	}
}

/**
 * bp_bible_format_activity()
 *
 * Formatting your activity items is the other important step in adding your custom component activity into
 * activity streams.
 *
 * The bp_bible_record_activity() function simply records ID's that are needed to fetch information about
 * the activity. The bp_bible_format_activity() will take those ID's and make something that is human readable.
 *
 * You'll notice in the function bp_bible_setup_globals() we set up a global called 'format_activity_function'.
 * This is the function name that the activity component will look at to format your component's activity when needed.
 *
 * This is where the 'component_action' variable set in bp_bible_record_activity() comes into play. For each
 * one of those actions, you will need to define how that activity action is rendered.
 *
 * You do not have to call this function anywhere, or pass any parameters, the activity component will handle it.
 */
function bp_bible_format_activity( $item_id, $user_id, $action, $secondary_item_id = false, $for_secondary_user = false ) {
	global $bp;

	/* $action is the 'component_action' variable set in the record function. */
	switch( $action ) {
		case 'accepted_terms':
			/* In this case, $item_id is the user ID of the user who accepted the terms. */
			$user_link = bp_core_get_userlink( $item_id );

			if ( !$user_link )
				return false;

			/***
			 * We return activity items as an array. The 'primary_link' is for RSS feeds, so when the reader clicks
			 * a new item header, it will go to this link (sometimes there is more than one link in an activity item).
			 */
			return array(
				'primary_link' => $user_link,
				'content' => apply_filters( 'bp_bible_accepted_terms_activity', sprintf( __( '%s accepted the really exciting terms and conditions!', 'bp-bible' ), $user_link ) . ' <span class="time-since">%s</span>', $user_link )
			);
		break;
		case 'rejected_terms':
			$user_link = bp_core_get_userlink( $item_id );

			if ( !$user_link )
				return false;

			return array(
				'primary_link' => $user_link,
				'content' => apply_filters( 'bp_bible_rejected_terms_activity', sprintf( __( '%s rejected the really exciting terms and conditions.', 'bp-bible' ), $user_link ) . ' <span class="time-since">%s</span>', $user_link )
			);
		break;
		case 'new_high_five':
			/* In this case, $item_id is the user ID of the user who recieved the high five. */
			$to_user_link = bp_core_get_userlink( $item_id );
			$from_user_link = bp_core_get_userlink( $user_id );

			if ( !$to_user_link || !$from_user_link )
				return false;

			return array(
				'primary_link' => $to_user_link,
				'content' => apply_filters( 'bp_bible_new_high_five_activity', sprintf( __( '%s high-fived %s!', 'bp-bible' ), $from_user_link, $to_user_link ) . ' <span class="time-since">%s</span>', $from_user_link, $to_user_link )
			);
		break;
	}

	/* By adding a do_action here, people can extend your component with new activity items. */
	do_action( 'bp_bible_format_activity', $action, $item_id, $user_id, $action, $secondary_item_id, $for_secondary_user );

	return false;
}

/**
 * bp_bible_format_notifications()
 *
 * Formatting notifications works in very much the same way as formatting activity items.
 *
 * These notifications are "screen" notifications, that is, they appear on the notifications menu
 * in the site wide navigation bar. They are not for email notifications.
 *
 * You do not need to make a specific notification recording function for your component because the
 * notification recorded functions are bundled in the core, which is required.
 *
 * The recording is done by using bp_core_add_notification() which you can search for in this file for
 * examples of usage.
 */
function bp_bible_format_notifications( $action, $item_id, $secondary_item_id, $total_items ) {
	global $bp;

	switch ( $action ) {
		case 'new_high_five':
			/* In this case, $item_id is the user ID of the user who sent the high five. */

			/***
			 * We don't want a whole list of similar notifications in a users list, so we group them.
			 * If the user has more than one action from the same component, they are counted and the
			 * notification is rendered differently.
			 */
			if ( (int)$total_items > 1 ) {
				return apply_filters( 'bp_bible_multiple_new_high_five_notification', '<a href="' . $bp->loggedin_user->domain . $bp->bible->slug . '/passage/" title="' . __( 'Multiple high-fives', 'bp-bible' ) . '">' . sprintf( __( '%d new high-fives, multi-five!', 'bp-bible' ), (int)$total_items ) . '</a>', $total_items );
			} else {
				$user_fullname = bp_core_get_user_displayname( $item_id, false );
				$user_url = bp_core_get_userurl( $item_id );
				return apply_filters( 'bp_bible_single_new_high_five_notification', '<a href="' . $user_url . '?new" title="' . $user_fullname .'\'s profile">' . sprintf( __( '%s sent you a high-five!', 'bp-bible' ), $user_fullname ) . '</a>', $user_fullname );
			}
		break;
	}

	do_action( 'bp_bible_format_notifications', $action, $item_id, $secondary_item_id, $total_items );

	return false;
}


/***
 * From now on you will want to add your own functions that are specific to the component you are developing.
 * For example, in this section in the friends component, there would be functions like:
 *    friends_add_friend()
 *    friends_remove_friend()
 *    friends_check_friendship()
 *
 * Some guidelines:
 *    - Don't set up error messages in these functions, just return false if you hit a problem and
 *		deal with error messages in screen or action functions.
 *
 *    - Don't directly query the database in any of these functions. Use database access classes
 * 		or functions in your bp-bible-classes.php file to fetch what you need. Spraying database
 * 		access all over your plugin turns into a maintainence nightmare, trust me.
 *
 *	  - Try to include add_action() functions within all of these functions. That way others will find it
 *		easy to extend your component without hacking it to pieces.
 */

/**
 * bp_bible_accept_terms()
 *
 * Accepts the terms and conditions screen for the logged in user.
 * Records an activity stream item for the user.
 */
function bp_bible_accept_terms() {
	global $bp;

	/**
	 * First check the nonce to make sure that the user has initiated this
	 * action. Remember the wp_nonce_url() call? The second parameter is what
	 * you need to check for.
	 */
	if ( !check_admin_referer( 'bp_bible_accept_terms' ) )
		return false;

	/***
	 * Here is a good example of where we can post something to a users activity stream.
	 * The user has excepted the terms on screen two, and now we want to post
	 * "Andy accepted the really exciting terms and conditions!" to the stream.
	 */
	bp_bible_record_activity(
		array(
			'item_id' => $bp->loggedin_user->id,
			'user_id' => $bp->loggedin_user->id,
			'component_name' => $bp->bible->slug,
			'component_action' => 'accepted_terms',
			'is_private' => 0
		)
	);

	/* See bp_bible_reject_terms() for an explanation of deleting activity items */
	bp_bible_delete_activity(
		array(
			'item_id' => $bp->loggedin_user->id,
			'user_id' => $bp->loggedin_user->id,
			'component_name' => $bp->bible->slug,
			'component_action' => 'rejected_terms'
		)
	);

	/***
	 * Remember, even though we have recorded the activity, we still need to tell
	 * the activity component how to format that activity item into something readable.
	 * In the bp_bible_format_activity() function, we need to make an entry for
	 * 'accepted_terms'
	 */

	/* Add a do_action here so other plugins can hook in */
	do_action( 'bp_bible_accept_terms', $bp->loggedin_user->id );

	/***
	 * You'd want to do something here, like set a flag in the database, or set usermeta.
	 * just for the sake of the demo we're going to return true.
	 */

	return true;
}

/**
 * bp_bible_reject_terms()
 *
 * Rejects the terms and conditions screen for the logged in user.
 * Records an activity stream item for the user.
 */
function bp_bible_reject_terms() {
	global $bp;

	if ( !check_admin_referer( 'bp_bible_reject_terms' ) )
		return false;

	/***
	 * In this example component, the user can reject the terms even after they have
	 * previously accepted them.
	 *
	 * If a user has accepted the terms previously, then this will be in their activity
	 * stream. We don't want both 'accepted' and 'rejected' in the activity stream, so
	 * we should remove references to the user accepting from all activity streams.
	 * A real world example of this would be a user deleting a published blog post.
	 */

	/* Delete any accepted_terms activity items for the user */
	bp_bible_delete_activity(
		array(
			'item_id' => $bp->loggedin_user->id,
			'user_id' => $bp->loggedin_user->id,
			'component_name' => $bp->bible->slug,
			'component_action' => 'accepted_terms'
		)
	);

	/* Now record the new 'rejected' activity item */
	bp_bible_record_activity(
		array(
			'item_id' => $bp->loggedin_user->id,
			'user_id' => $bp->loggedin_user->id,
			'component_name' => $bp->bible->slug,
			'component_action' => 'rejected_terms',
			'is_private' => 0
		)
	);

	do_action( 'bp_bible_reject_terms', $bp->loggedin_user->id );

	return true;
}

/**
 * bp_bible_send_high_five()
 *
 * Sends a high five message to a user. Registers an notification to the user
 * via their notifications menu, as well as sends an email to the user.
 *
 * Also records an activity stream item saying "User 1 high-fived User 2".
 */
function bp_bible_send_highfive( $to_user_id, $from_user_id ) {
	global $bp;

	if ( !check_admin_referer( 'bp_bible_send_high_five' ) )
		return false;

	/**
	 * We'll store high-fives as usermeta, so we don't actually need
	 * to do any database querying. If we did, and we were storing them
	 * in a custom DB table, we'd want to reference a function in
	 * bp-bible-classes.php that would run the SQL query.
	 */

	/* Get existing fives */
	$existing_fives = maybe_unserialize( get_usermeta( $to_user_id, 'high-fives' ) );

	/* Check to see if the user has already high-fived. That's okay, but lets not
	 * store duplicate high-fives in the database. What's the point, right?
	 */
	if ( !in_array( $from_user_id, (array)$existing_fives ) ) {
		$existing_fives[] = (int)$from_user_id;

		/* Now wrap it up and fire it back to the database overlords. */
		update_usermeta( $to_user_id, 'high-fives', serialize( $existing_fives ) );
	}

	/***
	 * Now we've registered the new high-five, lets work on some notification and activity
	 * stream magic.
	 */

	/***
	 * Post a screen notification to the user's notifications menu.
	 * Remember, like activity streams we need to tell the activity stream component how to format
	 * this notification in bp_bible_format_notifications() using the 'new_high_five' action.
	 */
	bp_core_add_notification( $from_user_id, $to_user_id, $bp->bible->slug, 'new_high_five' );

	/* Now record the new 'new_high_five' activity item */
	bp_bible_record_activity(
		array(
			'item_id' => $to_user_id,
			'user_id' => $from_user_id,
			'component_name' => $bp->bible->slug,
			'component_action' => 'new_high_five',
			'is_private' => 0
		)
	);

	/* We'll use this do_action call to send the email notification. See bp-bible-notifications.php */
	do_action( 'bp_bible_send_high_five', $to_user_id, $from_user_id );

	return true;
}

/**
 * bp_bible_get_highfives_for_user()
 *
 * Returns an array of user ID's for users who have high fived the user passed to the function.
 */
function bp_bible_get_highfives_for_user( $user_id ) {
	global $bp;

	if ( !$user_id )
		return false;

	return maybe_unserialize( get_usermeta( $user_id, 'high-fives' ) );
}

/**
 *
 */
function bp_bible_remove_screen_notifications() {
	global $bp;

	/**
	 * When clicking on a screen notification, we need to remove it from the menu.
	 * The following command will do so.
 	 */
	bp_core_delete_notifications_for_user_by_type( $bp->loggedin_user->id, $bp->bible->slug, 'new_high_five' );
}
add_action( 'bp_bible_screen_passage', 'bp_bible_remove_screen_notifications' );
add_action( 'xprofile_screen_display_profile', 'bp_bible_remove_screen_notifications' );

/**
 * bp_bible_remove_data()
 *
 * It's always wise to clean up after a user is deleted. This stops the database from filling up with
 * redundant information.
 */
function bp_bible_remove_data( $user_id ) {
	/* You'll want to run a function here that will delete all information from any component tables
	   for this $user_id */

	/* Remember to remove usermeta for this component for the user being deleted */
	delete_usermeta( $user_id, 'bp_bible_some_setting' );

	do_action( 'bp_bible_remove_data', $user_id );
}
add_action( 'wpmu_delete_user', 'bp_bible_remove_data', 1 );
add_action( 'delete_user', 'bp_bible_remove_data', 1 );

/**
 * bp_bible_load_buddypress()
 *
 * When we activate the component, we must make sure BuddyPress is loaded first (if active)
 * If it's not active, then the plugin should not be activated.
 */
function bp_bible_load_buddypress() {
	if ( function_exists( 'bp_core_setup_globals' ) )
		return true;

	/* Get the list of active sitewide plugins */
	$active_sitewide_plugins = maybe_unserialize( get_site_option( 'active_sitewide_plugins' ) );
	if ( isset( $active_sidewide_plugins['buddypress/bp-loader.php'] ) && !function_exists( 'bp_core_setup_globals' ) ) {
		require_once( WP_PLUGIN_DIR . '/buddypress/bp-loader.php' );
		return true;
	}

	/* If we get to here, BuddyPress is not active, so we need to deactive the plugin and redirect. */
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if ( file_exists( ABSPATH . 'wp-admin/includes/mu.php' ) )
		require_once( ABSPATH . 'wp-admin/includes/mu.php' );

	deactivate_plugins( basename(__FILE__), true );
	if ( function_exists( 'deactivate_sitewide_plugin') )
		deactivate_sitewide_plugin( basename(__FILE__), true );

	wp_redirect( get_blog_option( BP_ROOT_BLOG, 'home' ) . '/wp-admin/plugins.php' );
}
add_action( 'plugins_loaded', 'bp_bible_load_buddypress', 11 );

/***
 * Object Caching Support ----
 *
 * It's a good idea to implement object caching support in your component if it is fairly database
 * intensive. This is not a requirement, but it will help ensure your component works better under
 * high load environments.
 *
 * In parts of this example component you will see calls to wp_cache_get() often in template tags
 * or custom loops where database access is common. This is where cached data is being fetched instead
 * of querying the database.
 *
 * However, you will need to make sure the cache is cleared and updated when something changes. For example,
 * the groups component caches groups details (such as description, name, news, number of members etc).
 * But when those details are updated by a group admin, we need to clear the group's cache so the new
 * details are shown when users view the group or find it in search results.
 *
 * We know that there is a do_action() call when the group details are updated called 'groups_settings_updated'
 * and the group_id is passed in that action. We need to create a function that will clear the cache for the
 * group, and then add an action that calls that function when the 'groups_settings_updated' is fired.
 *
 * Bible:
 *
 *   function groups_clear_group_object_cache( $group_id ) {
 *	     wp_cache_delete( 'groups_group_' . $group_id );
 *	 }
 *	 add_action( 'groups_settings_updated', 'groups_clear_group_object_cache' );
 *
 * The "'groups_group_' . $group_id" part refers to the unique identifier you gave the cached object in the
 * wp_cache_set() call in your code.
 *
 * If this has completely confused you, check the function documentation here:
 * http://codex.wordpress.org/Function_Reference/WP_Cache
 *
 * If you're still confused, check how it works in other BuddyPress components, or just don't use it,
 * but you should try to if you can (it makes a big difference). :)
 */

function bp_bible_add_bible_nav_item() {
	?>
	<li<?php if ( bp_is_page( BP_BIBLE_SLUG ) ) : ?> class="selected"<?php endif; ?>>
		<a href="<?php echo get_option('home') ?>/<?php echo BP_BIBLE_SLUG ?>" title="<?php _e( 'Bible', 'bp-bible' ) ?>"><?php _e( 'Bible', 'bp-bible' ) ?></a>
	</li>
	<?php
}
add_action('bp_nav_items', 'bp_bible_add_bible_nav_item');

// The following two functions will force the active member theme for
// bible pages, even though they are technically under the root "home" blog
// from a WordPress point of view.

// TODO2: See how BP 1.1 changes this

/*function bp_bible_force_buddypress_theme( $template ) {
	global $bp;

	if ( $bp->current_component != $bp->bible->slug )
		return $template;

	$member_theme = get_site_option('active-member-theme');

	if ( empty($member_theme) )
		$member_theme = 'bpmember';

	add_filter( 'theme_root', 'bp_core_filter_buddypress_theme_root' );
	add_filter( 'theme_root_uri', 'bp_core_filter_buddypress_theme_root_uri' );

	return $member_theme;
}
add_filter( 'template', 'bp_bible_force_buddypress_theme' );

function bp_bible_force_buddypress_stylesheet( $stylesheet ) {
	global $bp;

	if ( $bp->current_component != $bp->bible->slug )
		return $stylesheet;

	$member_theme = get_site_option('active-member-theme');

	if ( empty( $member_theme ) )
		$member_theme = 'bpmember';

	add_filter( 'theme_root', 'bp_core_filter_buddypress_theme_root' );
	add_filter( 'theme_root_uri', 'bp_core_filter_buddypress_theme_root_uri' );

	return $member_theme;
}
add_filter( 'stylesheet', 'bp_bible_force_buddypress_stylesheet', 1, 1 );
*/

function bp_bible_get_trans_id() {
	global $bp;
	return $bp->bible->trans_id;
}

function bp_bible_set_trans_id($trans_id) {
	global $bp;
	setcookie('bfox_trans_id', $trans_id, /* 365 days from now: */ time() + 60 * 60 * 24 * 365, '/');
	$bp->bible->trans_id = $trans_id;
}

function bp_bible_set_search_str($str) {
	global $bp;
	$bp->bible->search_str = $str;
}

function bp_bible_get_search_str() {
	global $bp;
	return $bp->bible->search_str;
}

function bp_bible_loginout($redirect = '') {
	global $bp;
	if (empty($redirect)) $redirect = $bp->root_domain;

	if ( ! is_user_logged_in() )
		$link = '<a href="' . esc_url( wp_login_url($redirect) ) . '">' . __('Log in') . '</a>';
	else
		$link = '<a href="' . esc_url( wp_logout_url($redirect) ) . '">' . __('Log out') . '</a>';

	return apply_filters('bp_bible_loginout', $link);
}


?>