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
			require_once BFOX_PLANS_DIR . '/plans.php';
			$_REQUEST[BfoxQuery::var_page] = BfoxQuery::page_plans;

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

	public static function plan_url(BfoxReadingPlan $plan, $action = '') {
		global $bp;
		return bp_core_get_user_domain($plan->owner_id) . self::slug . '/' . $plan->slug . '/' . $action;
	}

	public static function plan_link(BfoxReadingPlan $plan, $action = '', $title = '') {
		if (empty($title)) $title = $plan->name;
		return "<a href='" . self::plan_url($plan, $action) . "'>$title</a>";
	}

	public static function create_plan_link() {
		echo '<a href="' . $bp->loggedin_user->domain . self::slug . '/create-plan">' . __('Create a Reading Plan', 'buddypress') . '</a>';
	}

	public static function plan_chart(BfoxReadingPlan $plan, $max_cols = 3) {

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
		$header->add_header_col('', '');
		$header->add_header_col('Passage', '');
		if ($plan->is_scheduled) $header->add_header_col('Date', '');
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

			// Add the bible reference column
			$attrs = '';
			if ($use_history) {
				// Calculate how much of this reading is unread
				$unread = $plan->get_unread($reading);

				// If this reading is 'read', then mark it as such
				if (!$unread->is_valid()) $attrs = "class='finished'";
			}
			$row->add_col(Biblefox::ref_link($reading->get_string(BibleMeta::name_short)), $attrs);

			// Add the Date column
			if ($plan->is_scheduled) $row->add_col($plan->date($reading_id, 'M d'));

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

function bfox_bp_screen_my_plans() {
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

	add_action('bp_template_title', 'bfox_bp_screen_my_plans_title');
	add_action('bp_template_content', 'bfox_bp_screen_my_plans_content');

	bp_core_load_template(apply_filters('bp_core_template_plugin', 'plugin-template'));
}

function bfox_bp_screen_my_plans_title() {
	bp_word_or_name(__('My Reading Plans'), __("%s's Reading Plans"));
}

function bfox_bp_screen_my_plans_content() {
	global $bp;
	do_action('template_notices');
	$plans = BfoxPlans::get_plans(array(), $bp->displayed_user->id, BfoxPlans::user_type_user);
	$finished = array();

	if (!empty($plans)): ?>
		<h3>Current Reading Plans</h3>
		<p><?php bp_word_or_name(__('Here are all the reading plans you are currently using:'), __('These are the reading plans which %s is currently using:')) ?></p>
		<ul id="plan-list" class="item-list">
			<?php foreach ($plans as $plan) if (!$plan->is_finished): ?>

				<li>
					<h4><a href="<?php echo BfoxBpPlans::plan_url($plan) ?>" title="<?php echo $plan->name ?>"><?php echo $plan->name ?></a> - <?php echo $plan->schedule_desc() ?></h4>
					<?php echo $plan->desc_html() ?>
				</li>

			<?php else: $finished []= $plan; endif; ?>
		</ul>
		<h3>Finished Reading Plans</h3>
		<p><?php bp_word_or_name(__('Once you are finished using a reading plan, you can \'mark it as finished\'. It will be shown here so that you can remember the plans you have read.'), __('These are the reading plans which %s has already finished using:')) ?></p>
		<ul id="plan-list" class="item-list">
			<?php foreach ($finished as $plan): ?>

				<li>
					<h4><a href="<?php echo BfoxBpPlans::plan_url($plan) ?>" title="<?php echo $plan->name ?>"><?php echo $plan->name ?></a> - <?php echo $plan->schedule_desc() ?></h4>
				</li>

			<?php endforeach; ?>
		</ul>
	<?php else: ?>
		<div id="message" class="info">
			<p><?php bp_word_or_name(__("You haven't created any reading plans yet."), __("%s hasn't created any public reading plans yet.")) ?> <?php BfoxBpPlans::create_plan_link() ?></p>
		</div>
	<?php endif;
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
				<p><?php _e( "No friends matched your search filter terms", 'buddypress' ) ?></p>
			</div>
		<?php else : ?>
			<div id="message" class="info">
				<p><?php bp_word_or_name( __( "Your friends list is currently empty", 'buddypress' ), __( "%s's friends list is currently empty", 'buddypress' ) ) ?></p>
			</div>
		<?php endif; ?>

		<?php if ( bp_is_home() && !bp_friends_is_filtered() ) : ?>
			<h3><?php _e( 'Why not make friends with some of these members?', 'buddypress' ) ?></h3>
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
	global $bp;
	$current_tab = $bp->action_variables[0];
	if (empty($current_tab)) $current_tab = 'view';

	$plan = BfoxBpPlans::$plan;

	do_action('template_notices');

	if ('view' == $current_tab) echo BfoxBpPlans::plan_chart($plan);
	elseif ('edit' == $current_tab) echo BfoxBpPlans::edit_plan($plan);
	else {
		switch ($current_tab) {
			case BfoxBpPlans::action_delete:
				$confirm = __('Are you sure you want to delete ') . BfoxBpPlans::plan_link($plan) . __('?');
				break;
			case BfoxBpPlans::action_copy:
				$confirm = __('Are you sure you want to copy ') . BfoxBpPlans::plan_link($plan) . __('?');
				break;
			case BfoxBpPlans::action_mark_finished:
				$confirm = __('Are you sure you want to mark ') . BfoxBpPlans::plan_link($plan) . __(' as finished? This is for when you have finished reading everything in this plan.');
				break;
			case BfoxBpPlans::action_mark_unfinished:
				$confirm = __('Are you sure you want to mark ') . BfoxBpPlans::plan_link($plan) . __(' as unfinished?');
				break;
		}
		BfoxBpPlans::confirm_page($plan, $current_tab, $confirm);
	}
}

add_action( 'plugins_loaded', 'BfoxBpPlans::setup_root_component', 1 );

add_action( 'wp', 'BfoxBpPlans::add_nav', 2 );
add_action( 'admin_menu', 'BfoxBpPlans::add_nav', 2 );

?>