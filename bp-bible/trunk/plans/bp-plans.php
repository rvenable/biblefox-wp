<?php

class BfoxBpPlans {
	const slug = 'plans';

	private static $editor;
	public static $plan_id = 0;
	public static $plan = NULL;
	public static $plans_url = '';

	public static function setup_root_component() {
		bp_core_add_root_component(self::slug);
	}

	public static function add_nav() {
		global $bp;

		self::$plans_url = $bp->loggedin_user->domain . self::slug . '/';

		/* Add the settings navigation item */
		bp_core_add_nav_item( __('Reading Plans'), 'plans');
		bp_core_add_nav_default(self::slug, 'bfox_bp_screen_my_plans', 'my-plans');

		bp_core_add_subnav_item(self::slug, 'my-plans', __('My Reading Plans'), self::$plans_url, 'bfox_bp_screen_my_plans', false, bp_is_home() );
		bp_core_add_subnav_item(self::slug, 'find-plans', __('Find a Plan'), self::$plans_url, 'bfox_bp_screen_find_plan', false, bp_is_home() );
		bp_core_add_subnav_item(self::slug, 'create-plan', __('Create a Plan'), self::$plans_url, 'bfox_bp_screen_create_plan', false, bp_is_home() );

		if (self::slug == $bp->current_component) {
			Biblefox::set_default_ref_url(Biblefox::ref_url_bible);

			if (self::$plan_id = BfoxPlans::slug_exists($bp->current_action, $bp->displayed_user->id, BfoxPlans::user_type_user)) {
				$plans_link = self::$plans_url . $bp->current_action . '/';

				$bp->current_item = $bp->current_action;
				$bp->current_action = 'view';

				self::$plan = BfoxPlans::get_plan(self::$plan_id);

				$bp->is_item_admin = /*is_site_admin() ||*/ self::is_owned(self::$plan);

				bp_core_add_nav_default(self::slug, 'bfox_bp_screen_view_plan', 'view');
				bp_core_add_subnav_item(self::slug, 'view', __('View Plan'), $plans_link, 'bfox_bp_screen_view_plan', false);
			}

			if (bp_is_home()) {
				$bp->bp_options_title = __('My Reading Plans');
			}
			else {
				/* If we are not viewing the logged in user, set up the current users avatar and name */
				$bp->bp_options_avatar = bp_core_get_avatar($bp->displayed_user->id, 1);
				$bp->bp_options_title = $bp->displayed_user->fullname;
			}
		}
	}

	const page_user_plans = 'my-plans';
	const page_find_plans = 'find-plans';
	const page_create_plan = 'create-plan';
	const page_edit_plan = 'edit-plan';

	public static function plan_url(BfoxReadingPlan $plan = NULL, $action = '') {
		global $bp;
		if (!is_null($plan)) return bp_core_get_user_domain($plan->owner_id) . self::slug . '/' . $plan->slug . '/' . $action;
		else return $bp->loggedin_user->domain . self::slug . '/' . $action;
	}

	public static function plan_link(BfoxReadingPlan $plan, $action = '', $title = '') {
		if (empty($title)) $title = $plan->name;
		return "<a href='" . self::plan_url($plan, $action) . "'>$title</a>";
	}

	public static function create_plan_link() {
		echo '<a href="' . self::plan_url(NULL, self::page_create_plan) . '">' . __('Create a Reading Plan', 'bp-plans') . '</a>';
	}

	public static function plan_chart(BfoxReadingPlan $plan, $max_cols = 0) {
		if (empty($max_cols)) $max_cols = 3;

		// If this plan is scheduled, not finished, and this is a user, not a blog, then use the history information
		$use_history = FALSE;//($plan->is_scheduled && !$my_sub->is_finished && ($my_sub->user_type == BfoxPlans::user_type_user));

		$unread_readings = array();
		if ($use_history) {
			$use_history = $plan->set_history(BfoxHistory::get_history(0, $plan->history_start_date(), NULL, TRUE));
			$crossed_out = '<br/>' . __('*Note: Crossed out passages indicate that you have finished reading that passage');
		}

		$sub_table = new BfoxHtmlTable("class='reading_plan_col'");

		// Create the table header
		$header = new BfoxHtmlRow();
		$header->add_header_col('#', '');
		if ($plan->is_scheduled) $header->add_header_col('Date', '');
		$header->add_header_col('Passage', '');
		//if (!empty($unread_readings)) $header->add_header_col('Unread', '');
		$sub_table->add_header_row($header);

		$total_refs = new BfoxRefs;

		foreach ($plan->readings as $reading_id => $reading) {
			$total_refs->add_refs($reading);

			// Create the row for this reading
			if ($reading_id == $plan->current_reading_id) $row = new BfoxHtmlRow("class='current'");
			else $row = new BfoxHtmlRow();

			// Add the reading index column
			$row->add_col($reading_id + 1);

			// Add the Date column
			if ($plan->is_scheduled) $row->add_col($plan->date($reading_id, 'M d'));

			// Add the bible reference column
			$attrs = '';
			if ($use_history) {
				// Calculate how much of this reading is unread
				$unread = $plan->get_unread($reading);

				// If this reading is 'read', then mark it as such
				if (!$unread->is_valid()) $attrs = "class='finished'";
			}
			$row->add_col(Biblefox::ref_link($reading->get_string(BibleMeta::name_short)), $attrs);

			// Add the History column
			/*if (!empty($unread_readings)) {
				if (isset($unread_readings[$reading_id])) $row->add_col(Biblefox::ref_link($unread_readings[$reading_id]->get_string(BibleMeta::name_short)));
				else $row->add_col();
			}*/

			// Add the row to the table
			$sub_table->add_row($row);
		}

		if ($plan->is_private) $is_private = ' (private)';

		$table = new BfoxHtmlTable("class='reading_plan'",
			"<b>$plan->name$is_private</b><br/>
			<small>" . $plan->desc_html() . "<br/>
			Schedule: " . $plan->schedule_desc() .
			"$crossed_out</small>");
		$table->add_row($sub_table->get_split_row($max_cols, 5));
		$table->add_row('', 1, array("<small>Combined passages covered by this plan: " . $total_refs->get_string(BibleMeta::name_short) . "</small>", "colspan='$max_cols'"));

		return $table->content();
	}

	public static function admin_tabs() {
		global $bp;

		$current_tab = $bp->action_variables[0];
		if (empty($current_tab)) $current_tab = 'view';

		$lis = array(
			array('view', __('Overview'), TRUE),
			array('edit', __('Edit'), $bp->is_item_admin),
			array('copy', __('Copy'), TRUE),
			array('mark-finished', __('Mark as finished'), $bp->is_item_admin && !self::$plan->is_finished),
			array('mark-unfinished', __('Mark as unfinished'), $bp->is_item_admin && self::$plan->is_finished),
			array('delete', __('Delete'), $bp->is_item_admin)
		);

		foreach ($lis as $li) {
			list($slug, $title, $use) = $li;
			if ($use) {
				?>
				<li<?php if ($slug == $current_tab) : ?> class="current"<?php endif; ?>><a href="<?php echo $link . $slug ?>"><?php echo $title ?></a></li>
				<?php
			}
		}
	}

	public static function confirm_page(BfoxReadingPlan $plan, $action, $confirm) {
		$hiddens = '';
		//if (!empty($_GET[self::var_plan_id])) $hiddens .= BfoxUtility::hidden_input(self::var_plan_id, $_GET[self::var_plan_id]);
		$hiddens .= BfoxUtility::hidden_input(self::var_action, $action);

		?>
		<form class='standard-form' action='<?php echo self::plan_url($plan) ?>' method='post'>
		<p><?php echo $confirm . $hiddens ?></p>
		<p><input id='save' type='submit' name='save' value='<?php echo __('Confirm') ?>' class='button'/></p>
		</form>

		<?php
	}

	const var_submit = 'save';

	const var_plan_id = BfoxQuery::var_plan_id;
	const var_plan_name = 'plan_name';
	const var_plan_description = 'plan_description';
	const var_plan_readings = 'plan_readings';
	const var_plan_passages = 'plan_passages';
	const var_plan_chunk_size = 'plan_chunk_size';
	const var_plan_is_private = 'plan_is_private';
	const var_plan_is_scheduled = 'plan_is_scheduled';
	const var_plan_start = 'plan_start';
	const var_plan_frequency = 'plan_frequency';
	const var_plan_freq_options = 'plan_freq_options';

	const var_user = 'user';
	const var_blog = 'blog';

	const var_action = 'plan_action';
	const action_edit = 'edit';
	const action_delete = 'delete';
	const action_subscribe = 'subscribe';
	const action_unsubscribe = 'unsubscribe';
	const action_mark_finished = 'mark-finished';
	const action_mark_unfinished = 'mark-unfinished';
	const action_copy = 'copy';

	public static function edit_plan(BfoxReadingPlan $plan) {

		if (empty($plan->id)) $url = self::$plans_url;
		else $url = self::plan_url($plan, 'edit');

		$table = new BfoxHtmlOptionTable("class='standard-form'", "action='$url' method='post'",
			BfoxUtility::hidden_input(self::var_plan_id, $plan->id) . BfoxUtility::hidden_input(self::var_action, self::action_edit),
			"<p><input id='save' type='submit' name='" . self::var_submit . "' value='" . __('Save') . "' class='button'/></p>");

		$passage_help = __('<p>This allows you to add passages of the Bible to your reading plan in big chunks.</p>
			<p>You can type passages of the bible in the box, and then set how many chapters you want to read at a time. The passages will be cut into sections and added to your reading plan.</p>
			<p>Type any passages in the box above. For instance, to make a reading plan of all the gospels you could type "Matthew, Mark, Luke, John". You can use bible abbreviations (ie. "gen" instead of "Genesis"), and even specify chapters and verses (ie. "gen 1-3"). Separate passages can be separated with a comma (\',\'), semicolon (\';\'), or on separate lines.</p>');

		if (empty($plan->id)) {
			$readings_label = __('Add Readings (Option 1)');
			$groups_label = __('Add Readings (Option 2)');
			$readings_help = '<p>' . __('Add scriptures to your reading plan. Each line you enter will be a different reading in the plan. If you want to automatically add bible passages, skip to the next section: ') . $groups_label . '</p>' .
				'<p>' . __('<b>Tip</b>: We will parse out any text that is not a bible reference. This means, if you found a cool reading plan online somewhere, you can just paste it straight in here. As long as each bible reading is on a separate line, we should be able to correctly parse all the bible references.') . '</p>';
			$passage_help .= __('<p>By the way, you can use both option 1 and 2 for adding passages. The passages from option 2 will be appended to the end of the passages added from option 1.</p>');
		}
		else {
			$readings_label = __('Edit Readings');
			$groups_label = __('Append More Readings');
			$reading_help = '<p>' . __('This is a list of all the current readings: edit these passages to modify your reading plan. Each line is a different reading in the plan.') . '</p>';
			$passage_help .= __('<p><b>Note</b>: Any passages you add here will be appended to the end of the current readings (in the "') . $readings_label . __('" box).</p>');
		}

		// Name
		$table->add_option(__('Reading Plan Name'), '', $table->option_text(self::var_plan_name, $plan->name, "size = '40'"),
			'<p>' . __('Give the reading plan a cool name.') . '</p>');

		// Description
		$table->add_option(__('Description'), '',
			$table->option_textarea(self::var_plan_description, $plan->description, 2, 50, ''),
			'<p>' . __('Add an optional description of this reading plan.') . '</p>');

		// Readings
		$table->add_option($readings_label, '',
			$table->option_textarea(self::var_plan_readings, implode("\n", $plan->reading_strings()), 15, 50),
			$readings_help);

		// Groups of Passages
		$table->add_option($groups_label, '',
			$table->option_textarea(self::var_plan_passages, '', 3, 50),
			"<br/><input name='" . self::var_plan_chunk_size . "' id='" . self::var_plan_chunk_size . "' type='text' value='1' size='4' maxlength='4'/> " . __('Chapters Per Reading') .
			$passage_help);

		// Private
		$table->add_option(__('Privacy'), '',
			$table->option_check(self::var_plan_is_private, __('Set as private'), $plan->is_private),
			'<p>' . __('Check this to set this reading plan as private. Private reading plans will not be shown to other readers looking at your reading plans. Other users will not be able to subscribe to this plan.') . '</p>');

		// Is Scheduled?
		$table->add_option(__('Use a reading schedule?'), '',
			$table->option_check(self::var_plan_is_scheduled, __('Yes, use a reading schedule'), $plan->is_scheduled),
			'<p>' . __('Check this to use a reading schedule for this plan. If unchecked, you can skip past the rest of the options and save your plan.') . '</p>');

		// Start Date
		$table->add_option(__('Start Date'), '',
			$table->option_text(self::var_plan_start, $plan->start_date('M j, Y'), "size='10' maxlength='20'"),
			'<p>' . __('Set the date at which this plan schedule will begin.') . '</p>');

		// Frequency
		$frequency_array = BfoxReadingPlan::frequency_array();
		$table->add_option(__('How often will this plan be read?'), '',
			$table->option_array(self::var_plan_frequency, array_map('ucfirst', $frequency_array[BfoxReadingPlan::frequency_array_daily]), $plan->frequency),
			'<p>' . __('Will this plan be read daily, weekly, or monthly?') . '</p>');

		// Frequency Options
		$days_week_array = BfoxReadingPlan::days_week_array();
		$table->add_option(__('Days of the Week'), '',
			$table->option_array(self::var_plan_freq_options, array_map('ucfirst', $days_week_array[BfoxReadingPlan::days_week_array_normal]), $plan->freq_options_array()),
			'<p>' . __('Which days of the week will you be reading? This only applies to plans that are read daily.') . '</p>');

		echo $table->content();
	}

	public static function is_owned(BfoxReadingPlan $plan) {
		global $bp;
		return (($bp->loggedin_user->id == $plan->owner_id) && (BfoxPlans::user_type_user == $plan->owner_type));
	}

	public static function must_own($plan) {
		if (!self::is_owned($plan)) {
			bp_core_add_message(__('The action you are trying to do can only be done by the owner of the reading plan!'), 'error');
			bp_core_redirect(self::plan_url($plan));
		}
	}

	public static function update_plan_from_input(BfoxReadingPlan $plan) {
		$plan->name = strip_tags(stripslashes($_POST[self::var_plan_name]));
		$plan->description = strip_tags(stripslashes($_POST[self::var_plan_description]));
		$plan->set_readings_by_strings(stripslashes($_POST[self::var_plan_readings]));
		$plan->add_passages(stripslashes($_POST[self::var_plan_passages]), $_POST[self::var_plan_chunk_size]);
		$plan->is_private = $_POST[self::var_plan_is_private];
		$plan->is_scheduled = $_POST[self::var_plan_is_scheduled];
		$plan->set_start_date($_POST[self::var_plan_start]);
		$plan->frequency = $_POST[self::var_plan_frequency];
		$plan->set_freq_options((array) $_POST[self::var_plan_freq_options]);
		$plan->finish_setting_plan();

		BfoxPlans::save_plan($plan);
	}

	public static function handle_input(BfoxReadingPlan $plan) {
		switch ($_POST[self::var_action]) {
			case self::action_edit:
				self::must_own($plan);

				if (isset($_POST[self::var_plan_name])) {
					self::update_plan_from_input($plan);
					bp_core_add_message(__('The reading plan was updated successfully!'));
					bp_core_redirect(self::plan_url($plan));
				}
				else {
					bp_core_add_message(__('You must enter a name for the reading plan.'), 'error');
					bp_core_redirect(self::plan_url($plan, 'edit'));
				}

				break;
			case self::action_delete:
				self::must_own($plan);

				BfoxPlans::delete_plan($plan);
				bp_core_add_message("Reading Plan ($plan->name) Deleted!");
				bp_core_redirect(self::$plans_url);

				break;
			case self::action_copy:
				$plan->set_as_copy();
				BfoxPlans::save_plan($plan);
				bp_core_add_message("Reading Plan ($plan->name) Saved!");
				bp_core_redirect(self::plan_url($plan));
				break;
			case self::action_mark_finished:
				self::must_own($plan);

				$plan->is_finished = TRUE;
				BfoxPlans::save_plan($plan);
				bp_core_add_message("Marked Reading Plan ($plan->name) as Finished!");
				bp_core_redirect(self::plan_url($plan));
				break;
			case self::action_mark_unfinished:
				self::must_own($plan);

				$plan->is_finished = FALSE;
				BfoxPlans::save_plan($plan);
				bp_core_add_message("Marked Reading Plan ($plan->name) as Unfinished!");
				bp_core_redirect(self::plan_url($plan));
				break;
		}
		$message = implode('<br/>', $messages);

		if (!empty($redirect)) wp_redirect(add_query_arg(BfoxQuery::var_message, urlencode($message), $redirect));
	}
}

function bfox_bp_screen_find_plan() {
	add_action('bp_template_title', 'bfox_bp_screen_find_plan_title');
	add_action('bp_template_content', 'bfox_bp_screen_find_plan_content');

	bp_core_load_template(apply_filters('bp_core_template_plugin', 'plugin-template'));
}

function bfox_bp_screen_find_plan_title() {
	_e('Find a Reading Plan');
}

function bfox_bp_screen_find_plan_content() {
	/*
	 * Copied from friends-loop.php:
	 */
	?>
	<p>The best way to find reading plans is to look at your friends' reading plans. You can copy their reading plans so that you can follow along with them.</p>
<div id="friends-loop">
	<?php if ( bp_has_friendships() ) : ?>

		<div class="pagination-links" id="pag">
			<?php bp_friend_pagination() ?>
		</div>

		<ul id="friend-list" class="item-list">
		<?php while ( bp_user_friendships() ) : bp_the_friendship(); ?>

			<li>
				<?php bp_friend_avatar_thumb() ?>
				<h4><?php bp_friend_link() ?></h4>
				<span class="activity"><?php bp_friend_last_active() ?></span>

				<div class="action">
					<a href="<?php bp_friend_url() ?>plans/">View Reading Plans</a>
				</div>
			</li>

		<?php endwhile; ?>
		</ul>

	<?php else: ?>

		<?php if ( bp_friends_is_filtered() ) : ?>
			<div id="message" class="info">
				<p><?php _e( "No friends matched your search filter terms", 'bp-plans' ) ?></p>
			</div>
		<?php else : ?>
			<div id="message" class="info">
				<p><?php bp_word_or_name( __( "Your friends list is currently empty", 'bp-plans' ), __( "%s's friends list is currently empty", 'bp-plans' ) ) ?></p>
			</div>
		<?php endif; ?>

		<?php if ( bp_is_home() && !bp_friends_is_filtered() ) : ?>
			<h3><?php _e( 'Why not make friends with some of these members?', 'bp-plans' ) ?></h3>
			<?php bp_friends_random_members() ?>
		<?php endif; ?>

	<?php endif;?>
</div>
	<?php
}

function bfox_bp_screen_create_plan() {
	add_action('bp_template_title', 'bfox_bp_screen_create_plan_title');
	add_action('bp_template_content', 'bfox_bp_screen_create_plan_content');

	bp_core_load_template(apply_filters('bp_core_template_plugin', 'plugin-template'));
}

function bfox_bp_screen_create_plan_title() {
	_e('Create a Reading Plan');
}

function bfox_bp_screen_create_plan_content() {
	echo BfoxBpPlans::edit_plan(new BfoxReadingPlan());
}

function bfox_bp_screen_view_plan() {
	BfoxUtility::enqueue_style('bfox_plans', 'plans/plans.css');

	if (!empty($_POST['save'])) BfoxBpPlans::handle_input(BfoxBpPlans::$plan);

	add_action('bp_template_content_header', 'bfox_bp_screen_view_plan_content_header');
	add_action('bp_template_title', 'bfox_bp_screen_view_plan_title');
	add_action('bp_template_content', 'bfox_bp_screen_view_plan_content');

	bp_core_load_template(apply_filters('bp_core_template_plugin', 'plugin-template'));
}

function bfox_bp_screen_view_plan_content_header() {
?>
	<ul class="content-header-nav">
		<?php BfoxBpPlans::admin_tabs() ?>
	</ul>
<?php
}

function bfox_bp_screen_view_plan_title() {
	global $bp;
	$current_tab = $bp->action_variables[0];
	if (empty($current_tab)) $current_tab = 'view';

	if ('view' == $current_tab) _e('Reading Plan Overview');
	elseif ('edit' == $current_tab) _e('Edit Reading Plan');
	else _e('Confirm Action');
}

function bfox_bp_screen_view_plan_content() {
}

/*add_action( 'plugins_loaded', 'BfoxBpPlans::setup_root_component', 1 );

add_action( 'wp', 'BfoxBpPlans::add_nav', 2 );
add_action( 'admin_menu', 'BfoxBpPlans::add_nav', 2 );
*/




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
	define ( 'BP_PLANS_SLUG', 'plans' );

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

/* The classes file should hold all database access classes and functions */
//require ( BP_BIBLE_DIR . '/bp-plans/bp-plans-classes.php' );

/* The ajax file should hold all functions used in AJAX queries */
//require ( BP_BIBLE_DIR . '/bp-plans/bp-plans-ajax.php' );

/* The cssjs file should set up and enqueue all CSS and JS files used by the component */
//require ( BP_BIBLE_DIR . '/bp-plans/bp-plans-cssjs.php' );

/* The templatetags file should contain classes and functions designed for use in template files */
require ( BP_BIBLE_DIR . '/plans/bp-plans-templatetags.php' );

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
	$bp->plans->id = 'plans';

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
	bp_core_new_nav_item( array(
		'name' => __( 'Reading Plans', 'bp-plans' ),
		'slug' => $bp->plans->slug,
		'position' => 80,
		'screen_function' => 'bp_plans_screen_my_plans',
		'default_subnav_slug' => 'my-plans'
	) );

	$plans_link = $bp->loggedin_user->domain . $bp->plans->slug . '/';

	/* Create two sub nav items for this component */
	bp_core_new_subnav_item( array(
		'name' => __( 'My Reading Plans', 'bp-plans' ),
		'slug' => 'my-plans',
		'parent_slug' => $bp->plans->slug,
		'parent_url' => $plans_link,
		'screen_function' => 'bp_plans_screen_my_plans',
		'position' => 10,
		'item_css_id' => 'my-plans-list'
	) );

	bp_core_new_subnav_item( array(
		'name' => __( 'Create a Plan', 'bp-plans' ),
		'slug' => 'create',
		'parent_slug' => $bp->plans->slug,
		'parent_url' => $plans_link,
		'screen_function' => 'bp_plans_screen_create',
		'position' => 20,
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
			Biblefox::set_default_ref_url(Biblefox::ref_url_bible);

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
			//$bp->current_action = $bp->action_variables[0];
			//array_shift($bp->action_variables);

			//pre($bp); exit;
			if (empty($bp->action_variables[0])) $bp->action_variables[0] = 'overview';

			//$bp->bp_options_title = $bp->plans->current_plan->name;
			//$bp->bp_options_avatar = bp_get_plan_avatar(array('plan' => $bp->plans->current_plan));

/*			$bp->current_item = $bp->current_action;
			$bp->current_action = 'view';
*/

//			BfoxBpPlans::$plan = BfoxPlans::get_plan(BfoxBpPlans::$plan_id);

	//		$bp->is_item_admin = /*is_site_admin() ||*/ BfoxBpPlans::is_owned(BfoxBpPlans::$plan);

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
				'position' => 30
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

	/**
	 * On this screen, as a quick example, users can send you a "High Five", by clicking a link.
	 * When a user sends you a high five, you receive a new notification in your
	 * notifications menu, and you will also be notified via email.
	 */

	if (!empty($_POST['save']) && ($_POST[BfoxBpPlans::var_action] == BfoxBpPlans::action_edit)) {
		require_once BFOX_PLANS_DIR . '/plans.php';
		if (isset($_POST[BfoxBpPlans::var_plan_name])) {
			$plan = new BfoxReadingPlan();
			BfoxBpPlans::update_plan_from_input($plan);
			bp_core_add_message(__('The reading plan was created successfully!'));
			bp_core_redirect(BfoxBpPlans::plan_url($plan));
		}
		else {
			bp_core_add_message(__('You must enter a name for the reading plan.'), 'error');
			bp_core_redirect(BfoxBpPlans::$plan_url);
		}
	}

	/**
	 * We need to run a check to see if the current user has clicked on the 'send high five' link.
	 * If they have, then let's send the five, and redirect back with a nice error/success message.
	 */
	if ( $bp->current_component == $bp->plans->slug && 'my-plans' == $bp->current_action && 'send-h5' == $bp->action_variables[0] ) {
		/* The logged in user has clicked on the 'send high five' link */
		if ( bp_is_home() ) {
			/* Don't let users high five themselves */
			bp_core_add_message( __( 'No self-fives! :)', 'bp-plans' ), 'error' );
		} else {
			if ( bp_plans_send_highfive( $bp->displayed_user->id, $bp->loggedin_user->id ) )
				bp_core_add_message( __( 'High-five sent!', 'bp-plans' ) );
			else
				bp_core_add_message( __( 'High-five could not be sent.', 'bp-plans' ), 'error' );
		}

		bp_core_redirect( $bp->displayed_user->domain . $bp->plans->slug . '/my-plans' );
	}

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


?>