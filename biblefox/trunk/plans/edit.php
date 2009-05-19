<?php

class BfoxPlanEdit
{
	const var_submit = 'submit';

	const var_plan_id = 'plan_id';

	const var_list_id = 'list_id';
	const var_list_name = 'list_name';
	const var_list_description = 'list_description';
	const var_list_readings = 'list_readings';
	const var_list_passages = 'list_passages';
	const var_list_chunk_size = 'list_chunk_size';

	const var_schedule_id = 'schedule_id';
	const var_schedule_start = 'schedule_start';
	const var_schedule_frequency = 'schedule_frequency';
	const var_schedule_freq_options = 'schedule_freq_options';

	const var_user = 'user';
	const var_blog = 'blog';

	const var_action = 'plan_action';
	const action_remove = 'remove';

	private $owner;
	private $owner_type;
	private $url;

	private static $save;
	private static $save_new_plan;
	private static $save_new_list;
	private static $save_new_schedule;

	public function __construct($owner, $owner_type, $url) {
		$this->owner = $owner;
		$this->owner_type = $owner_type;
		$this->url = $url;

		self::$save = __('Save');
		self::$save_new_plan = __('Save New Plan');
		self::$save_new_list = __('Save New List');
		self::$save_new_schedule = __('Save New Schedule');
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

			$do_save_plan = FALSE;

			// If the plan parameter is set, we must be editing a plan
			// Otherwise we might be editing a schedule or list
			if (isset($_POST[self::var_plan_id])) {

				// Saving a Plan

				// Only bother saving a plan if we have changed the list or schedule
				if (isset($_POST[self::var_list_id]) || isset($_POST[self::var_schedule_id])) {

					$plan = BfoxPlans::get_plan($_POST[self::var_plan_id]);
					if ((self::$save_new_plan == $_POST[self::var_submit]) || !$this->is_owned($plan)) $this->set_as_new($plan);

					if (!empty($_POST[self::var_list_id])) {
						$list = BfoxPlans::get_list($_POST[self::var_list_id]);
						$this->save_input_list($list);

						// If the list is saved, valid, and different, update the plan
						if (!empty($list->id) && $list->is_valid() && ($list->id != $plan->list_id)) {
							$plan->list_id = $list->id;
							$do_save_plan = TRUE;

							// The schedule id must be reset because the old schedule no longer applies to this list
							$plan->schedule_id = 0;
						}
					}

					if (isset($_POST[self::var_schedule_id]) && $plan->is_valid()) {
						$schedule = BfoxPlans::get_schedule($_POST[self::var_schedule_id]);

						$this->save_input_schedule($schedule, BfoxPlans::get_list($plan->list_id));

						if (!empty($schedule->id) && $schedule->is_valid()) {
							$plan->schedule_id = $schedule->id;
							$do_save_plan = TRUE;
						}
					}
				}
			}
			else {

				// We do not have a plan parameter, so this could be a schedule or list edit
				$plan = new BfoxReadingPlan();
				$this->set_as_new($plan);

				if (isset($_POST[self::var_schedule_id])) {

					// Saving a Schedule
					$schedule = BfoxPlans::get_schedule($_POST[self::var_schedule_id]);
					$this->save_input_schedule($schedule, BfoxPlans::get_list($_POST[self::var_list_id]));

					if (!empty($schedule->id)) {
						// If this is a new schedule, we should make a new plan for it as well
						// We know it is new if its ID after saving is different from the inputed ID
						if ($_POST[self::var_schedule_id] != $schedule->id) {
							$plan->schedule_id = $schedule->id;
							$plan->list_id = $schedule->list_id;
							$do_save_plan = TRUE;
						}
						else {
							$messages []= "Saved Reading Schedule";
							$redirect = $this->edit_schedule_url($schedule->id);
						}
					}
				}
				elseif (isset($_POST[self::var_list_id])) {

					// Saving a Reading List
					$list = BfoxPlans::get_list($_POST[self::var_list_id]);
					$this->save_input_list($list);

					if (!empty($list->id)) {
						// If this is a new list, we should make a new plan for it as well
						// We know it is new if its ID after saving is different from the inputed ID
						if ($_POST[self::var_list_id] != $list->id) {
							$plan->list_id = $list->id;
							$do_save_plan = TRUE;
						}
						else {
							$messages []= "Saved Reading List: '$list->name'";
							$redirect = $this->edit_list_url($list->id);
						}
					}
				}
			}

			// Save the plan if we should, and redirect to its page
			if ($do_save_plan) {
				BfoxPlans::save_plan($plan);
				$redirect = $this->edit_plan_url($plan->id);
			}
		}

		$message = implode('<br/>', $messages);

		if (!empty($redirect)) wp_redirect(add_query_arg(BfoxQuery::var_message, urlencode($message), $redirect));
	}

	private function save_input_list(BfoxReadingList &$list) {

		$save = FALSE;

		if (isset($_POST[self::var_list_name])) {
			$list->name = stripslashes($_POST[self::var_list_name]);
			$list->description = stripslashes($_POST[self::var_list_description]);
			$list->set_readings_by_strings(stripslashes($_POST[self::var_list_readings]));
			$list->add_passages(stripslashes($_POST[self::var_list_passages]), $_POST[self::var_list_chunk_size]);
			$save = TRUE;
		}

		if ($save && $list->is_valid()) {
			// If the user selected 'save as new' or we don't own this list, we should use a copy of the list
			if ((self::$save_new_list == $_POST[self::var_submit]) || !$this->is_owned($list)) $this->set_as_new($list);

			BfoxPlans::save_list($list);
		}
	}

	private function save_input_schedule(BfoxReadingSchedule &$schedule, BfoxReadingList $list = NULL) {

		$save = FALSE;

		// If we have a valid list, that we don't already have set, set it for this schedule
		if (!is_null($list) && $list->is_valid() && ($schedule->list_id != $list->id)) {
			// If we are changing the list for an already saved schedule, we should use a copy
			if (!empty($schedule->id)) $this->set_as_new($schedule);

			// Set the list for this schedule
			$schedule->set_list($list);
			$save = TRUE;
		}

		// If there are schedule modifications, get those
		if (isset($_POST[self::var_schedule_start])) {
			$schedule->set_start_date($_POST[self::var_schedule_start]);
			$schedule->frequency = $_POST[self::var_schedule_frequency];
			$schedule->set_freq_options((array) $_POST[self::var_schedule_freq_options]);
			$save = TRUE;
		}

		if ($save && $schedule->is_valid()) {
			// If the user selected 'save as new' or we don't own this schedule, we should use a copy of the schedule
			if ((self::$save_new_schedule == $_POST[self::var_submit]) || (!$this->is_owned($schedule))) $this->set_as_new($schedule);

			BfoxPlans::save_schedule($schedule);
		}
	}

	private function create_list_link($str) {
		return "<a href='" . add_query_arg(self::var_list_id, 0, $this->url) . "'>$str</a>";
	}

	private function edit_plan_url($plan_id = 0) {
		return add_query_arg(self::var_plan_id, $plan_id, $this->url);
	}

	private function edit_plan_link(BfoxReadingPlan $plan, $str = '') {
		return "<a href='" . $this->edit_plan_url($plan->id) . "'>$str</a>";
	}

	private function edit_list_url($list_id = 0) {
		return add_query_arg(self::var_list_id, $list_id, $this->url);
	}

	private function edit_list_link(BfoxReadingList $list, $str = '') {
		if (empty($str)) $str = $list->name;

		return "<a href='" . $this->edit_list_url($list->id) . "'>$str</a>";
	}

	private function select_list_link(BfoxReadingList $list, $str = 'Select List') {
		return "<a href='" . add_query_arg(array(self::var_list_id => $list->id, self::var_plan_id => 0), $this->url) . "'>$str</a>";
	}

	private function remove_list_link(BfoxReadingList $list, $str = 'Remove List') {
		return "<a href='" . add_query_arg(array(self::var_list_id => $list->id, self::var_action => self::action_remove), $this->url) . "'>$str</a>";
	}

	private function edit_schedule_url($schedule_id = 0) {
		return add_query_arg(self::var_schedule_id, $schedule_id, $this->url);
	}

	private function edit_schedule_link(BfoxReadingSchedule $schedule, $str = 'Edit Schedule') {
		return "<a href='" . $this->edit_schedule_url($schedule->id) . "'>$str</a>";
	}

	private function select_schedule_link(BfoxReadingPlan $plan, BfoxReadingSchedule $schedule, $str = 'Select Schedule') {
		return "<a href='" . add_query_arg(array(self::var_schedule_id => $schedule->id, self::var_list_id => $schedule->list_id, self::var_plan_id => $plan->id), $this->url) . "'>$str</a>";
	}

	private function add_schedule_link(BfoxReadingList $list, $str = '') {
		return "<a href='" . add_query_arg(array(self::var_list_id => $list->id, self::var_schedule_id => 0), $this->url) . "'>$str</a>";
	}

	private function remove_schedule_link(BfoxReadingSchedule $schedule, $str = 'Remove Schedule') {
		return "<a href='" . add_query_arg(array(self::var_schedule_id => $schedule->id, self::var_action => self::action_remove), $this->url) . "'>$str</a>";
	}

	private function is_owned(BfoxReadingInfo $info) {
		return (($info->owner == $this->owner) && ($info->owner_type == $this->owner_type));
	}

	private function owner_name(BfoxReadingInfo $info, $me = '') {
		if (!empty($me) && $this->is_owned($info)) return $me;
		return $info->owner_name();
	}

	private function owner_link(BfoxReadingInfo $info, $str = '', $me = 'me') {
		if (empty($str)) $str = $this->owner_name($info, $me);

		if ($this->is_owned($info)) $url = $this->url;
		else {
			if (BfoxPlans::owner_type_blog == $info->owner_type) $var = self::var_blog;
			elseif (BfoxPlans::owner_type_user == $info->owner_type) $var = self::var_user;
			$url = add_query_arg($var, $info->owner, $this->url);
		}

		return "<a href='$url'>$str</a>";

	}

	private function get_input_owner() {
		if (!empty($_REQUEST[self::var_user])) return array($_REQUEST[self::var_user], BfoxPlans::owner_type_user);
		elseif (isset($_REQUEST[self::var_blog])) return array($_REQUEST[self::var_blog], BfoxPlans::owner_type_blog);
		return FALSE;
	}

	private function schedule_desc(BfoxReadingSchedule $schedule) {
		if ($schedule->is_valid()) return $schedule->start_str() . ' - ' . $schedule->end_str() . (($schedule->is_recurring) ? ' (recurring)' : '') . ' (' . $schedule->frequency_desc() . ') by ' . $this->owner_link($schedule);
	}

	private function set_as_new(BfoxReadingInfo &$info) {
		$info->id = 0;
		$info->owner = $this->owner;
		$info->owner_type = $this->owner_type;
	}

	public function content() {
		if (!empty($_GET[BfoxQuery::var_message])) {
			?>
			<div id="page_message"><?php echo strip_tags(stripslashes(urldecode($_GET[BfoxQuery::var_message])), '<br/>') ?></div>
			<?php
			$_SERVER['REQUEST_URI'] = remove_query_arg(array(BfoxQuery::var_message), $_SERVER['REQUEST_URI']);
		}

		$list_id = $_GET[self::var_list_id];
		$schedule_id = $_GET[self::var_schedule_id];

		if (isset($_GET[self::var_plan_id])) $this->content_plan(BfoxPlans::get_plan($_GET[self::var_plan_id]));
		elseif (!empty($schedule_id)) $this->content_schedule(BfoxPlans::get_schedule($schedule_id));
		elseif (isset($_GET[self::var_list_id])) {
			if (isset($_GET[self::var_schedule_id]) && (0 == $schedule_id)) {
				$list = BfoxPlans::get_list($list_id);
				echo "<h2>Create a Reading Schedule</h2>";
				echo "<p>Reading schedules allow you to set the pace for how fast you will be reading this reading list.</p>";
				echo $this->content_readings($list);
				echo $this->edit_schedule(new BfoxReadingSchedule(), $list);
			}
			elseif (!empty($list_id)) $this->content_list(BfoxPlans::get_list($list_id));
			else $this->content_plan(new BfoxReadingPlan());
		}
		/*elseif (isset($_GET[self::var_schedule_id])) $this->content_schedule(BfoxPlans::get_schedule($_GET[self::var_schedule_id]));
		elseif (isset($_GET[self::var_list_id])) $this->content_list(BfoxPlans::get_list($_GET[self::var_list_id]));*/
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
					$this->owner_content($user->ID, BfoxPlans::owner_type_user);
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
					$this->owner_content($blog_id, BfoxPlans::owner_type_blog);
				}
			}
			else {
				echo "<h2>Manage Reading Plans</h2>";
				$this->owner_content($this->owner, $this->owner_type);
			}
		}
	}

	private function owner_content($owner, $owner_type) {

		$is_owner = (($owner == $this->owner) && ($owner_type == $this->owner_type));

		$plans = BfoxPlans::get_owner_plans($owner, $owner_type);

		$list_ids = array();
		$schedule_ids = array();
		foreach ($plans as $plan) {
			$list_ids[$plan->list_id] = TRUE;
			$schedule_ids[$plan->schedule_id] = TRUE;
		}
		$schedule_ids = array_keys($schedule_ids);
		$list_ids = array_keys($list_ids);

		$lists = BfoxPlans::get_lists($list_ids, $owner, $owner_type);
		$schedules = BfoxPlans::get_schedules($schedule_ids, $owner, $owner_type);

		// Create an array of lists with their schedules
		$pls = array();
		foreach ($plans as $plan)
			if (!empty($plan->list_id) && isset($lists[$plan->list_id])) {
				if (!isset($pls[$plan->list_id])) $pls[$plan->list_id] = array();
				if (!empty($plan->schedule_id) && isset($schedules[$plan->schedule_id])) $pls[$plan->list_id] []= $plan->schedule_id;
			}

		// Create a reading plan table
		$plans_table = new BfoxHtmlTable("id='reading_plans' class='widefat'", '', '');
		$plans_table->add_header_row('', 4, 'Reading List', 'List Options', 'Schedules', 'Schedule Options');
		if ($is_owner) $plans_table->add_footer_row('', 4, $this->create_list_link(__('Create a new reading list')));

		// We have to use our own alternating classes
		$alternates = array('odd_row', 'even_row');
		$row_index = 0;

		foreach ($pls as $list_id => $schedule_ids) {
			$list = $lists[$list_id];
			$sched_rows = array();

			$row_attrs = "class='" . $alternates[$row_index % 2] . "'";
			$row = new BfoxHtmlRow($row_attrs);

			if ($is_owner) $list_options = $this->edit_list_link($list, __('Edit')) . "<br/>" . $this->remove_list_link($list, __('Remove'));
			else $list_options = $this->select_list_link($list, __('Subscribe'));

			$sched_count = count($schedule_ids);
			$row_count = $sched_count;
			if ($is_owner) $row_count++;

			// The first two table columns are for the reading list
			$row->add_col($this->edit_list_link($list) . " by " . $this->owner_link($list) . "<br/>$list->description", "rowspan='$row_count'");
			$row->add_col($list_options, "rowspan='$row_count'");

			// Add a row for each schedule
			foreach ($schedule_ids as $index => $schedule_id) {
				$schedule = $schedules[$schedule_id];

				if (!isset($row)) $row = new BfoxHtmlRow($row_attrs);

				if ($is_owner || ($index < ($sched_count - 1))) $sched_class = "class='noborder'";
				else $sched_class = '';

				if ($is_owner) $schedule_options = $this->edit_schedule_link($schedule, __('Edit')) . ' / ' . $this->remove_schedule_link($schedule, __('Remove'));
				else $schedule_options = $this->select_schedule_link(new BfoxReadingPlan(), $schedule, __('Subscribe'));

				$row->add_col($this->schedule_desc($schedule), $sched_class);
				$row->add_col($schedule_options, $sched_class);

				$plans_table->add_row($row);
				unset($row);
			}

			// If this is the owner, we should add a row for the 'Add a schedule' link
			if ($is_owner) {
				if (!isset($row)) $row = new BfoxHtmlRow($row_attrs);

				$row->add_col($this->add_schedule_link($list, __('Add a schedule')));
				$row->add_col('');
			}

			// If there is still a row left, we should add it to the table
			if (isset($row)) {
				// If this is not the owner, we must have not added any schedules, so add the 'No Schedules' row
				if (!$is_owner) {
					$row->add_col('No Schedules');
					$row->add_col('');
				}
				$plans_table->add_row($row);
			}

			$row_index++;
		}

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

	private function content_readings(BfoxReadingList $list, BfoxReadingSchedule $schedule = NULL, $max_cols = 3) {

		$reading_count = count($list->readings);

		$dates = array();
		$unread_readings = array();

		if (!is_null($schedule) && $schedule->is_valid()) {
			// Get the date information
			$dates = $schedule->get_dates($reading_count + 1);
			$current_date_index = BfoxReadingSchedule::current_date_index($dates);
			if ($reading_count == $current_date_index) $current_date_index = -1;

			// Get the history information
			// TODO2: Implement user reading history
			/*$history_refs = BfoxHistory::get_from($schedule->start_date);
			if ($history_refs->is_valid()) {
				foreach ($list->readings as $reading_id => $reading) {
					$unread = new BibleRefs();
					$unread->add_seqs($reading->get_seqs());
					$unread->sub_seqs($history_refs->get_seqs());
					$unread_readings[$reading_id] = $unread;
				}
			}*/

			$schedule_desc = "<br/><br/>Schedule: " . $this->schedule_desc($schedule);
		}

		$table = new BfoxHtmlTable("class='reading_plan_col'");

		// Create the table header
		$header = new BfoxHtmlRow();
		$header->add_header_col('', '');
		$header->add_header_col('Passage', '');
		if (!empty($dates)) $header->add_header_col('Date', '');
		if (!empty($unread_readings)) $header->add_header_col('My Progress', '');
		$table->add_header_row($header);

		foreach ($list->readings as $reading_id => $reading) {
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
			$table->add_row($row);
		}

		$table2 = new BfoxHtmlTable("class='reading_plan'", "<b>$list->name</b> by " . $this->owner_link($list) . "<br/><small>$list->description$schedule_desc</small>");
		$table2->add_row($table->get_split_row($max_cols, 5));

		return $table2->content();
	}

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
				echo $this->edit_list(new BfoxReadingList());
			}
		}
	}

	private function content_list(BfoxReadingList $list) {
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

	private function content_schedule(BfoxReadingSchedule $schedule, BfoxReadingList $list = NULL) {
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
				<td><?php echo $list->name ?> by <?php echo $this->owner_link($list) ?><br/><?php echo $list->description ?></td>
				<td><?php echo $list->reading_count() ?> readings: <?php echo BfoxBlog::ref_link($list->ref_string()) ?></td>
				<td><?php echo $this->edit_list_link($list, __('Edit')) ?><br/>Duplicate<br/>Delete</td>
			</tr>
		<?php endforeach ?>
		</table>
		<?php
	}

	private function edit_plan(BfoxReadingPlan $plan, BfoxReadingList $list, BfoxReadingSchedule $schedule) {
		?>

		<h2><?php echo $list->name ?></h2>
		<form action='<?php echo $this->url ?>' method='post'>
		<input type='hidden' name='<?php echo self::var_plan_id ?>' value='<?php echo $plan->id ?>'/>

		<p><strong>Description:</strong> <?php echo $list->description ?></p>
		<p>This reading list is managed by <?php echo $this->owner_link($list) ?></p>
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

	private function edit_list(BfoxReadingList $list) {
		$is_owned = $this->is_owned($list);

		$submit = '';
		if ($is_owned) $submit = "<input type='submit' name='" . self::var_submit . "' value='" . self::$save . "' class='button'/>";
		$submit .= "<input type='submit' name='" . self::var_submit . "' value='" . self::$save_new_list . "' class='button'/>";

		$table = new BfoxHtmlOptionTable("class='form-table'", "action='$this->url' method='post'",
			BfoxUtility::hidden_input(self::var_list_id, $list->id),
			"<p>$submit</p>");

		$passage_help_text = __('<p>This allows you to add passages of the Bible to your reading plan in big chunks.</p>
			<p>You can type passages of the bible in the box, and then set how many chapters you want to read at a time. The passages will be cut into sections and added to your reading plan.</p>
			<p>Type any passages in the box above. For instance, to make a reading plan of all the gospels you could type "Matthew, Mark, Luke, John".<br/>
			You can use bible abbreviations (ie. "gen" instead of "Genesis"), and even specify chapters and verses (ie. "gen 1-3").<br/>
			Separate passages can be separated with a comma (\',\'), semicolon (\';\'), or on separate lines.</p>');

		// Name
		$table->add_option(__('Reading List Name'), '', $table->option_text(self::var_list_name, $list->name, "size = '40'"), '');

		// Description
		$table->add_option(__('Description'), '',
			$table->option_textarea(self::var_list_description, $list->description, 2, 50, ''),
			'<br/>' . __('Add an optional description of this reading list.'));

		// Readings
		$table->add_option(__('Readings'), '',
			$table->option_textarea(self::var_list_readings, implode("\n", $list->reading_strings()), 15, 50),
			'<br/>' . $reading_help_text);

		// Groups of Passages
		$table->add_option(__('Add Groups of Passages'), '',
			$table->option_textarea(self::var_list_passages, '', 3, 50),
			"<br/><input name='" . self::var_list_chunk_size . "' id='" . self::var_list_chunk_size . "' type='text' value='1' size='4' maxlength='4'/><br/>$passage_help_text");

		echo $table->content();
	}

	private function edit_schedule(BfoxReadingSchedule $schedule, BfoxReadingList $list, $plan_id = 0) {
		$is_owned = $this->is_owned($schedule);

		$submit = '';
		if ($is_owned) $submit = "<input type='submit' name='" . self::var_submit . "' value='" . self::$save . "' class='button'/>";
		$submit .= "<input type='submit' name='" . self::var_submit . "' value='" . self::$save_new_schedule . "' class='button'/>";;

		if (!empty($plan_id)) $plan_id_input = BfoxUtility::hidden_input(self::var_plan_id, $plan_id);

		$table = new BfoxHtmlOptionTable("class='form-table'", "action='$this->url' method='post'",
			BfoxUtility::hidden_input(self::var_schedule_id, $schedule->id) . $plan_id_input,
			"<p>$submit</p>");

		// Reading List
		$table->add_option(__('Reading List'), '',
			"<p>" . $this->edit_list_link($list) . " by " . $this->owner_link($list) . "<br/>$list->description" . '</p>' . BfoxUtility::hidden_input(self::var_list_id, $list->id),
			'<br/>' . __('This is the list of Bible readings which this schedule follows.'));

		// Start Date
		$table->add_option(__('Start Date'), '',
			$table->option_text(self::var_schedule_start, $schedule->start_str(), "size='10' maxlength='20'"),
			'<br/>' . __('Set the date at which this schedule will begin.'));

		// Frequency
		$frequency_array = BfoxReadingSchedule::frequency_array();
		$table->add_option(__('How often will this plan be read?'), '',
			$table->option_array(self::var_schedule_frequency, array_map('ucfirst', $frequency_array[BfoxReadingSchedule::frequency_array_daily]), $schedule->frequency),
			'<br/>' . __('Will this plan be read daily, weekly, or monthly?'));

		// Frequency Options
		$days_week_array = BfoxReadingSchedule::days_week_array();
		$table->add_option(__('Days of the Week'), '',
			$table->option_array(self::var_schedule_freq_options, array_map('ucfirst', $days_week_array[BfoxReadingSchedule::days_week_array_normal]), $schedule->freq_options_array()),
			'<br/>' . __('Which days of the week will you be reading?'));

		echo $table->content();
	}

	private function select_list() {
		$popular_list_ids = BfoxPlans::get_popular_list_ids();
		$list_ids = $popular_list_ids;
		$lists = BfoxPlans::get_lists($list_ids, $this->owner, $this->owner_type);

		$popular = array_fill_keys($popular_list_ids, TRUE);

		$your_lists_table = new BfoxHtmlTable("class='widefat'");
		$popular_lists_table = new BfoxHtmlTable("class='widefat'");

		$header = new BfoxHtmlHeaderRow('', 'Reading List', 'Overview', '');
		$your_lists_table->add_header_row($header);
		$popular_lists_table->add_header_row($header);

		foreach ($lists as $list) {
			$row = new BfoxHtmlRow('',
				"$list->name<br/>by " . $this->owner_link($list),
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
		<?php echo $this->edit_list(new BfoxReadingList()) ?>
		<?php
	}

	private function select_schedule(BfoxReadingPlan $plan) {
		$for_list_schedule_ids = BfoxPlans::get_list_schedule_ids($plan->list_id);
		$schedule_ids = $for_list_schedule_ids;
		$schedules = BfoxPlans::get_schedules($schedule_ids, $this->owner, $this->owner_type);

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
				$this->owner_link($schedule),
				$this->select_schedule_link($plan, $schedule));
			elseif ($this->is_owned($schedule) && isset($lists[$schedule->list_id])) {
				$list = $lists[$schedule->list_id];
				$your_schedules_table->add_row('', 3,
					$this->schedule_desc($schedule),
					"$list->name by " . $this->owner_link($list),
					$this->select_schedule_link($plan, $schedule, __('Copy Schedule')));
			}
		}

		$list = $lists[$plan->list_id];


		?>
		<h3>Add a Reading Schedule</h3>
		<p>The next step of creating a reading plan is to schedule how often these bible passages will be read. You can <a href='#create'>create a new one</a> or <a href='#select'>select a reading schedule</a> that has already been created.</p>

		<h4 id='create'>Create a New Schedule</h4>
		<?php echo $this->edit_schedule(new BfoxReadingSchedule(), $list, $plan->id) ?>

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
				<td><?php echo $schedule->name ?> by <?php echo $this->owner_link($schedule) ?><br/><?php echo $schedule->description ?></td>
				<td><?php echo $list->name ?><br/>by <?php echo $this->owner_link($list) ?></td>
				<td><?php echo 'status' ?></td>
				<td><?php echo $this->schedule_desc($schedule) ?></td>
				<td>Edit Plan<br/>Edit Readings<br/>Delete</td>
			</tr>
		<?php endforeach ?>
		</table>
		<?php
	}
}

?>