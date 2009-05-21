<?php

class BfoxPlanEdit
{
	const var_submit = 'submit';

	const var_plan_id = 'plan_id';
	const var_plan_name = 'plan_name';
	const var_plan_description = 'plan_description';
	const var_plan_readings = 'plan_readings';
	const var_plan_passages = 'plan_passages';
	const var_plan_chunk_size = 'plan_chunk_size';
	const var_plan_start = 'plan_start';
	const var_plan_frequency = 'plan_frequency';
	const var_plan_freq_options = 'plan_freq_options';

	const var_user = 'user';
	const var_blog = 'blog';

	const var_action = 'plan_action';
	const action_remove = 'remove';
	const action_subscribe = 'subscribe';
	const action_copy = 'copy';

	private $user;
	private $user_type;
	private $url;

	private static $save;

	public function __construct($user_id, $user_type, $url) {
		$this->user_id = $user_id;
		$this->user_type = $user_type;
		$this->url = $url;

		self::$save = __('Save');
	}

	public static function add_head($base_url = '') {
		if (empty($base_url)) $base_url = get_option('siteurl');

		?>
		<link rel="stylesheet" href="<?php echo $base_url ?>/wp-content/mu-plugins/biblefox/plans/plans.css" type="text/css"/>
		<?php
	}

	public function page_load() {

		$messages = array();
		$redirect = '';

		if (!empty($_POST[self::var_submit])) {
			if (isset($_POST[self::var_plan_id])) {
				$plan = BfoxPlans::get_plan($_POST[self::var_plan_id]);

				if (isset($_POST[self::var_plan_name])) {
					$sub = BfoxPlans::get_sub($plan, $this->user_id, $this->user_type);

					if ($sub->is_owned) {
						$plan->name = stripslashes($_POST[self::var_plan_name]);
						$plan->description = stripslashes($_POST[self::var_plan_description]);
						$plan->set_readings_by_strings(stripslashes($_POST[self::var_plan_readings]));
						$plan->add_passages(stripslashes($_POST[self::var_plan_passages]), $_POST[self::var_plan_chunk_size]);
						$plan->set_start_date($_POST[self::var_plan_start]);
						$plan->frequency = $_POST[self::var_plan_frequency];
						$plan->set_freq_options((array) $_POST[self::var_plan_freq_options]);

						BfoxPlans::save_plan($plan);
						$messages []= "Reading Plan ($plan->name) Saved!";
						$redirect = $this->plan_url($plan->id);
					}
				}
			}
		}

		$message = implode('<br/>', $messages);

		if (!empty($redirect)) wp_redirect(add_query_arg(BfoxQuery::var_message, urlencode($message), $redirect));
	}


	private function is_owned(BfoxReadingSub $sub) {
		return (($sub->user_id == $this->user_id) && ($sub->user_type == $this->user_type));
	}

	private function user_name(BfoxReadingSub $sub, $me = '') {
		if (!empty($me) && $this->is_owned($sub)) return $me;
		return $sub->user_name();
	}

	private function user_link(BfoxReadingSub $sub, $str = '', $me = 'me') {
		if (empty($str)) $str = $this->user_name($sub, $me);

		if ($this->is_owned($sub)) $url = $this->url;
		else {
			if (BfoxPlans::user_type_blog == $sub->user_type) $var = self::var_blog;
			elseif (BfoxPlans::user_type_user == $sub->user_type) $var = self::var_user;
			$url = add_query_arg($var, $sub->user, $this->url);
		}

		return "<a href='$url'>$str</a>";

	}

	private function schedule_desc(BfoxReadingPlan $plan) {
		return $plan->start_str() . ' - ' . $plan->end_str() . (($plan->is_recurring) ? ' (recurring)' : '') . ' (' . $plan->frequency_desc() . ')';
	}

	public function content() {
		if (!empty($_GET[BfoxQuery::var_message])) {
			?>
			<div id="page_message"><?php echo strip_tags(stripslashes(urldecode($_GET[BfoxQuery::var_message])), '<br/>') ?></div>
			<?php
			$_SERVER['REQUEST_URI'] = remove_query_arg(array(BfoxQuery::var_message), $_SERVER['REQUEST_URI']);
		}


		if (isset($_GET[self::var_plan_id])) $this->view_plan(BfoxPlans::get_plan($_GET[self::var_plan_id]));
		else {
			if (!empty($_GET[self::var_user])) {
				$user_var = $_GET[self::var_user];

				$user = get_userdata($user_var);
				if (!$user) $user = get_userdatabylogin($user_var);
				if (!$user) $user = get_user_by_email($user_var);
				if (!$user) {
					echo "<h2>User Search Failed</h2><p>No user could be found for '$user_var'.</p>";
					$this->find_plans();
				}
				else {
					echo "<h2>Reading Plans for User: $user->display_name</h2>";
					$this->view_user_plans($user->ID, BfoxPlans::user_type_user);
				}
			}
			elseif (!empty($_GET[self::var_blog])) {
				$blog_var = $_GET[self::var_blog];

				$blog_id = get_id_from_blogname($blog_var);
				if (!$blog_id) $blog_id = (int) $blog_var;
				if (!$blog_id) {
					echo "<h2>Blog Search Failed</h2><p>No blog could be found for '$blog_var'.</p>";
					$this->find_plans();
				}
				else {
					echo "<h2>Reading Plans for blog: <a href='" . get_blogaddress_by_id($blog_id) . "'>" . get_blog_option($blog_id, 'blogname') . "</a></h2>";
					$this->view_user_plans($blog_id, BfoxPlans::user_type_blog);
				}
			}
			else {
				echo "<h2>Manage Reading Plans</h2>";
				$this->view_user_plans($this->user_id, $this->user_type);
			}
		}
	}

	/*
	private function content_plan(BfoxReadingPlan $plan) {
		$list_id = $_REQUEST[self::var_list_id];
		$schedule_id = $_REQUEST[self::var_schedule_id];

		// If this is already a valid plan, we can only change the schedule
		if ($plan->is_valid()) {

			$list = BfoxPlans::get_list($plan->list_id);

			// If a valid new schedule was specified through input, edit the plan
			// Otherwise, use the plan's schedule
			$schedule = BfoxPlans::get_schedule($schedule_id);
			if ($schedule->is_valid()) $this->edit_plan($plan, $list, $schedule);
			else {
				// Get the plan's schedule
				$schedule = BfoxPlans::get_schedule($plan->schedule_id);

				echo "<h2>Edit Reading Plan</h2>";
				echo "<h3>Plan Overview</h3>";
				echo $this->content_readings($list, $schedule);

				// If the plan's schedule is already set, just display the schedule
				// Otherwise, show the select schedule page
				if ($schedule->is_valid()) $this->content_schedule($schedule, $list);
				else $this->select_schedule($plan);
			}
		}
		else {

			// This isn't a valid plan, so check if we are creating one
			// Get the input list
			$list = BfoxPlans::get_list($list_id);

			// If the input list is valid, we are editing the plan
			// Otherwise the user needs to select a list
			if ($list->is_valid()) $this->edit_plan($plan, $list, BfoxPlans::get_schedule($schedule_id));
			else {
				echo "<h2>Create a Reading List</h2>";
				echo "<p>A reading list is just a list of bible passages for you to read. Enter a name and description for your list, then enter the bible passages you want it to contain. There are two ways to enter bible passages. You can enter them manually by typing bible passages, or you can add them automatically.</p>";
				//$this->select_list($plan);
				echo $this->edit_list(new BfoxReadingPlan());
			}
		}
	}

	private function content_list(BfoxReadingPlan $list) {
		echo "<h2>View Reading List</h2>";
		echo $this->content_readings($list);
		?>
		<?php if (!$this->is_owned($list)): ?>
		<h3>Subscribe</h3>
		<p>You are not currently subscribed to this reading list. You can subsribe to this list if you want to read these same readings.</p>

		<p><?php echo $this->select_list_link($list, __('Subscribe'))?></p>

		<h3>Schedules</h3>
		<p>You might also want to subscribe to some schedules for this reading list. USER is using the following schedules with this reading list. Select any of these schedules which you would also like to subscribe to.</p>

		<?php else: ?>
		<h3>Edit Reading List</h3>
		<?php $this->edit_list($list) ?>
		<?php endif ?>
		<?php
	}

	private function content_schedule(BfoxReadingPlan $schedule, BfoxReadingPlan $list = NULL) {
		echo "<h2>Edit Schedule</h2>";
		if (is_null($list) || !$list->is_valid()) {
			$list = BfoxPlans::get_list($schedule->list_id);
		}
		echo $this->content_readings($list, $schedule, 3);
		?>
		<?php echo $this->edit_list_link($list, __('Edit Reading List')) ?>
		<h3>Edit Reading Schedule</h3>
		<?php $this->edit_schedule($schedule, $list) ?>
		<?php
	}

	private function lists_table($lists) {
		?>
		<table id='reading_lists' class='widefat'>
			<thead>
			<tr>
				<th>Reading List</th>
				<th>Schedules</th>
				<th>Options</th>
			</tr>
			</thead>
		<?php foreach ($lists as $list): ?>
			<tr>
				<td><?php echo $list->name ?> by <?php echo $this->user_link($list) ?><br/><?php echo $list->description ?></td>
				<td><?php echo $list->reading_count() ?> readings: <?php echo BfoxBlog::ref_link($list->ref_string()) ?></td>
				<td><?php echo $this->edit_list_link($list, __('Edit')) ?><br/>Duplicate<br/>Delete</td>
			</tr>
		<?php endforeach ?>
		</table>
		<?php
	}

	private function edit_plan(BfoxReadingPlan $plan, BfoxReadingPlan $list, BfoxReadingPlan $schedule) {
		?>

		<h2><?php echo $list->name ?></h2>
		<form action='<?php echo $this->url ?>' method='post'>
		<input type='hidden' name='<?php echo self::var_plan_id ?>' value='<?php echo $plan->id ?>'/>

		<p><strong>Description:</strong> <?php echo $list->description ?></p>
		<p>This reading list is managed by <?php echo $this->user_link($list) ?></p>
		<input type='hidden' name='<?php echo self::var_list_id ?>' value='<?php echo $list->id ?>'/>

		<?php if ($schedule->is_valid()): ?>
		<h3>Reading Schedule</h3>
		<p>You have selected the following reading schedule:<br/>
		<?php echo $this->schedule_desc($schedule) ?></p>
		<input type='hidden' name='<?php echo self::var_schedule_id ?>' value='<?php echo $schedule->id ?>'/>
		<?php endif ?>

		<h3>Subscribe</h3>
		<p>Would you like to subscribe to this reading list?</p>
		<input type='submit' name='<?php echo self::var_submit ?>' value='<?php echo self::$save ?>' class='button'/>
		<?php if (!empty($plan->id)): ?>
		<input type='submit' name='<?php echo self::var_submit ?>' value='<?php echo self::$save_new_plan ?>' class='button'/>
		<?php endif ?>
		</form>
		<?php
	}

	private function select_list() {
		$popular_list_ids = BfoxPlans::get_popular_list_ids();
		$list_ids = $popular_list_ids;
		$lists = BfoxPlans::get_lists($list_ids, $this->user_id, $this->user_type);

		$popular = array_fill_keys($popular_list_ids, TRUE);

		$your_lists_table = new BfoxHtmlTable("class='widefat'");
		$popular_lists_table = new BfoxHtmlTable("class='widefat'");

		$header = new BfoxHtmlHeaderRow('', 'Reading List', 'Overview', '');
		$your_lists_table->add_header_row($header);
		$popular_lists_table->add_header_row($header);

		foreach ($lists as $list) {
			$row = new BfoxHtmlRow('',
				"$list->name<br/>by " . $this->user_link($list),
				"$list->description<br/>" . $list->reading_count() . " readings: " . BfoxBlog::ref_link($list->ref_string()),
				$this->select_list_link($list));
			if ($this->is_owned($list)) $your_lists_table->add_row($row);
			if ($popular[$list->id]) $popular_lists_table->add_row($row);
		}

		?>
		<h3>Select a Reading List</h3>
		<p>The first step of creating a reading plan is to select a list of Bible passages you want to read. You can use a reading plan that has already been created or <a href='#create'>create a new one</a>.</p>

		<?php if ($your_lists_table->row_count()): ?>
		<h4>Your Reading Lists</h4>
		<?php echo $your_lists_table->content() ?>
		<?php endif ?>

		<h4>Popular Reading Lists</h4>
		<?php echo $popular_lists_table->content() ?>

		<h4 id='create'>Create Reading List</h4>
		<?php echo $this->edit_list(new BfoxReadingPlan()) ?>
		<?php
	}

	private function select_schedule(BfoxReadingPlan $plan) {
		$for_list_schedule_ids = BfoxPlans::get_list_schedule_ids($plan->list_id);
		$schedule_ids = $for_list_schedule_ids;
		$schedules = BfoxPlans::get_schedules($schedule_ids, $this->user_id, $this->user_type);

		$list_ids = array($plan->list_id);
		foreach ($schedules as $schedule) $list_ids []= $schedule->list_id;
		$lists = BfoxPlans::get_lists($list_ids);

		$for_list = array_fill_keys($for_list_schedule_ids, TRUE);

		$for_list_table = new BfoxHtmlTable("class='widefat'");
		$for_list_table->add_header_row('', 3, 'Schedule', 'Owner');
		$your_schedules_table = new BfoxHtmlTable("class='widefat'");
		$your_schedules_table->add_header_row('', 3, 'Schedule', 'Reading List');

		foreach ($schedules as $schedule) {
			if ($for_list[$schedule->id]) $for_list_table->add_row('', 3,
				$this->schedule_desc($schedule),
				$this->user_link($schedule),
				$this->select_schedule_link($plan, $schedule));
			elseif ($this->is_owned($schedule) && isset($lists[$schedule->list_id])) {
				$list = $lists[$schedule->list_id];
				$your_schedules_table->add_row('', 3,
					$this->schedule_desc($schedule),
					"$list->name by " . $this->user_link($list),
					$this->select_schedule_link($plan, $schedule, __('Copy Schedule')));
			}
		}

		$list = $lists[$plan->list_id];


		?>
		<h3>Add a Reading Schedule</h3>
		<p>The next step of creating a reading plan is to schedule how often these bible passages will be read. You can <a href='#create'>create a new one</a> or <a href='#select'>select a reading schedule</a> that has already been created.</p>

		<h4 id='create'>Create a New Schedule</h4>
		<?php echo $this->edit_schedule(new BfoxReadingPlan(), $list, $plan->id) ?>

		<h4 id='select'>Select a Schedule</h4>

		<?php if ($for_list_table->row_count()): ?>
		<h5>Reading Schedules for this Reading List</h5>
		<?php echo $for_list_table->content() ?>
		<?php endif ?>

		<?php if ($your_schedules_table->row_count()): ?>
		<h5>Your Reading Schedules</h5>
		<p>These are reading schedules you are using for other reading lists. You can copy these schedules to use with this plan.</p>
		<?php echo $your_schedules_table->content() ?>
		<?php endif ?>

		<?php
	}

	private function plans_suggestions($schedules, $lists) {
		?>
		<table id="reading_plan_suggestions" class="widefat">
			<thead>
			<tr>
				<th>Reading List</th>
				<th>Schedules</th>
				<th>Options</th>
			</tr>
			</thead>
		<?php foreach ($schedules as $schedule): ?>
			<?php $list = $lists[$schedule->list_id] ?>
			<tr>
				<td><?php echo $schedule->name ?> by <?php echo $this->user_link($schedule) ?><br/><?php echo $schedule->description ?></td>
				<td><?php echo $list->name ?><br/>by <?php echo $this->user_link($list) ?></td>
				<td><?php echo 'status' ?></td>
				<td><?php echo $this->schedule_desc($schedule) ?></td>
				<td>Edit Plan<br/>Edit Readings<br/>Delete</td>
			</tr>
		<?php endforeach ?>
		</table>
		<?php
	}
	*/









	private function plan_url($plan_id) {
		return add_query_arg(self::var_plan_id, $plan_id, $this->url);
	}

	private function plan_link($plan_id, $str) {
		return "<a href='" . $this->plan_url($plan_id) . "'>$str</a>";
	}

	private function plan_action_url($plan_id, $action) {
		return add_query_arg(array(self::var_plan_id => $plan->id, self::var_action => $action), $this->url);
	}

	private function plan_action_link($plan_id, $action, $str) {
		return "<a href='" . $this->plan_action_url($plan_id, $action) . "'>$str</a>";
	}

	private function view_user_plans($user, $user_type) {

		$is_user = (($user == $this->user_id) && ($user_type == $this->user_type));

		$subs = BfoxPlans::get_user_subs($user, $user_type);

		foreach ($subs as $sub) $plan_ids []= $sub->plan_id;

		$plans = BfoxPlans::get_plans($plan_ids);

		$plans_table = new BfoxHtmlTable("id='reading_plans' class='widefat'");
		$plans_table->add_header_row('', 3, 'Reading Plan', 'Schedule', 'Options');
		if ($is_user) $plans_table->add_footer_row('', 3, $this->plan_link(0, __('Create a new reading plan')));

		foreach ($plans as $plan) $plans_table->add_row('', 3,
			$this->plan_link($plan->id, $plan->name) . "<br/>$plan->description",
			$this->schedule_desc($plan),
			$this->plan_action_link($plan->id, self::action_subscribe, __('Subscribe')));

		?>

		<p>Reading plans allow you to organize how you read the Bible. You can create your own reading plans, or subscribe to someone else's plans.</p>

		<h3>Reading Plans</h3>
		<p>These are reading plans you have created or have subscribed to:</p>
		<?php echo $plans_table->content() ?>
		<?php $this->find_plans() ?>
		<?php
	}

	private function find_plans() {
		list($post_url, $hiddens) = BfoxUtility::get_post_url($this->url);

		?>
		<h3>Find Reading Plans</h3>
		<p>You can look up reading plans that others have created so that you can subscribe them.</p>

		<form action='<?php echo $post_url ?>' method='get'>
		<?php echo $hiddens ?>
		<p>
		<input type='text' name='<?php echo self::var_user ?>' value='<?php echo $_GET[self::var_user] ?>'/>
		<input type='submit' value='User Search' class='button'/>
		</p>
		</form>

		<form action='<?php echo $post_url ?>' method='get'>
		<?php echo $hiddens ?>
		<p>
		<input type='text' name='<?php echo self::var_blog ?>' value='<?php echo $_GET[self::var_blog] ?>'/>
		<input type='submit' value='Blog Search' class='button'/>
		</p>
		</form>

		<?php
	}

	private function plan_chart(BfoxReadingPlan $plan, $max_cols = 3) {

		$reading_count = $plan->reading_count();

		$dates = array();
		$unread_readings = array();

		// Get the date information
		$dates = $plan->get_dates($reading_count + 1);
		$current_date_index = BfoxReadingPlan::current_date_index($dates);
		if ($reading_count == $current_date_index) $current_date_index = -1;

		// Get the history information
		// TODO2: Implement user reading history
		/*$history_refs = BfoxHistory::get_from($plan->start_date);
		if ($history_refs->is_valid()) {
			foreach ($plan->readings as $reading_id => $reading) {
				$unread = new BibleRefs();
				$unread->add_seqs($reading->get_seqs());
				$unread->sub_seqs($history_refs->get_seqs());
				$unread_readings[$reading_id] = $unread;
			}
		}*/

		$schedule_desc = "<br/><br/>Schedule: " . $this->schedule_desc($plan);

		$sub_table = new BfoxHtmlTable("class='reading_plan_col'");

		// Create the table header
		$header = new BfoxHtmlRow();
		$header->add_header_col('', '');
		$header->add_header_col('Passage', '');
		if (!empty($dates)) $header->add_header_col('Date', '');
		if (!empty($unread_readings)) $header->add_header_col('My Progress', '');
		$sub_table->add_header_row($header);

		foreach ($plan->readings as $reading_id => $reading) {
			// Create the row for this reading
			if ($reading_id == $current_date_index) $row = new BfoxHtmlRow("class='current'");
			else $row = new BfoxHtmlRow();

			// Add the reading index column and the bible reference column
			$row->add_col($reading_id + 1);
			$row->add_col(BfoxBlog::ref_link($reading->get_string(BibleMeta::name_short)));

			// Add the Date column
			if (!empty($dates)) {
				if (isset($dates[$reading_id])) $row->add_col(date('M d', $dates[$reading_id]));
				else $row->add_col();
			}

			// Add the History column
			if (!empty($unread_readings)) {
				if (isset($unread_readings[$reading_id])) $row->add_col($unread_readings[$reading_id]->get_string(BibleMeta::name_short));
				else $row->add_col();
			}

			// Add the row to the table
			$sub_table->add_row($row);
		}

		$table = new BfoxHtmlTable("class='reading_plan'", "<b>$plan->name</b><br/><small>$plan->description$schedule_desc</small>");
		$table->add_row($sub_table->get_split_row($max_cols, 5));

		return $table->content();
	}

	private function view_plan(BfoxReadingPlan $plan) {

		echo "<h2>View Reading Plan</h2>";

		$is_owned = FALSE;

		echo $this->plan_chart($plan);

		// Users for this plan table
		$subs = BfoxPlans::get_plan_subs($plan);

		$user_table = new BfoxHtmlTable("class='widefat'");
		$user_table->add_header_row('', 2, 'Type', 'User');
		foreach ($subs as $sub) {
			$type = '';
			if (BfoxPlans::user_type_user == $sub->user_type) $type = 'User';
			elseif (BfoxPlans::user_type_blog == $sub->user_type) $type = 'Blog';
			$user_table->add_row('', 2, $type, $this->user_link($sub) . (($sub->is_owned) ? ' (manager)' : ''));
			if ($this->is_owned($sub)) $is_owned = $sub->is_owned;
		}

		echo "<h3>Subscribers</h3>\n";
		echo "<p>These are the subscribers using this reading plan:</p>";
		echo $user_table->content();

		if ($is_owned) $this->edit_plan($plan);
	}

	private function edit_plan(BfoxReadingPlan $plan) {

		$table = new BfoxHtmlOptionTable("class='form-table'", "action='$this->url' method='post'",
			BfoxUtility::hidden_input(self::var_plan_id, $plan->id),
			"<p><input type='submit' name='" . self::var_submit . "' value='" . self::$save . "' class='button'/></p>");

		$passage_help_text = __('<p>This allows you to add passages of the Bible to your reading plan in big chunks.</p>
			<p>You can type passages of the bible in the box, and then set how many chapters you want to read at a time. The passages will be cut into sections and added to your reading plan.</p>
			<p>Type any passages in the box above. For instance, to make a reading plan of all the gospels you could type "Matthew, Mark, Luke, John".<br/>
			You can use bible abbreviations (ie. "gen" instead of "Genesis"), and even specify chapters and verses (ie. "gen 1-3").<br/>
			Separate passages can be separated with a comma (\',\'), semicolon (\';\'), or on separate lines.</p>');

		// Name
		$table->add_option(__('Reading List Name'), '', $table->option_text(self::var_plan_name, $plan->name, "size = '40'"), '');

		// Description
		$table->add_option(__('Description'), '',
			$table->option_textarea(self::var_plan_description, $plan->description, 2, 50, ''),
			'<br/>' . __('Add an optional description of this reading plan.'));

		// Readings
		$table->add_option(__('Readings'), '',
			$table->option_textarea(self::var_plan_readings, implode("\n", $plan->reading_strings()), 15, 50),
			'<br/>' . $reading_help_text);

		// Groups of Passages
		$table->add_option(__('Add Groups of Passages'), '',
			$table->option_textarea(self::var_plan_passages, '', 3, 50),
			"<br/><input name='" . self::var_plan_chunk_size . "' id='" . self::var_plan_chunk_size . "' type='text' value='1' size='4' maxlength='4'/><br/>$passage_help_text");

		// Start Date
		$table->add_option(__('Start Date'), '',
			$table->option_text(self::var_plan_start, $plan->start_str(), "size='10' maxlength='20'"),
			'<br/>' . __('Set the date at which this plan schedule will begin.'));

		// Frequency
		$frequency_array = BfoxReadingPlan::frequency_array();
		$table->add_option(__('How often will this plan be read?'), '',
			$table->option_array(self::var_plan_frequency, array_map('ucfirst', $frequency_array[BfoxReadingPlan::frequency_array_daily]), $plan->frequency),
			'<br/>' . __('Will this plan be read daily, weekly, or monthly?'));

		// Frequency Options
		$days_week_array = BfoxReadingPlan::days_week_array();
		$table->add_option(__('Days of the Week'), '',
			$table->option_array(self::var_plan_freq_options, array_map('ucfirst', $days_week_array[BfoxReadingPlan::days_week_array_normal]), $plan->freq_options_array()),
			'<br/>' . __('Which days of the week will you be reading?'));

		echo $table->content();
	}
}

?>