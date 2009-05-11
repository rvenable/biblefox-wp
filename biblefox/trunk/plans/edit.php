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

	private $owner;
	private $owner_type;
	private $url;

	private static $save;
	private static $save_new;

	public function __construct($owner, $owner_type, $url) {
		$this->owner = $owner;
		$this->owner_type = $owner_type;
		$this->url = $url;

		self::$save = __('Save');
		self::$save_new = __('Save as new');
	}

	public static function add_head()
	{
		?>
		<link rel="stylesheet" href="<?php echo get_option('siteurl') ?>/wp-content/mu-plugins/biblefox/plans/plans.css" type="text/css"/>
		<?php
	}

	public function page_load() {

		$messages = array();
		$redirect = '';

		if (!empty($_POST[self::var_submit])) {

			$plan = BfoxPlans::get_plan($_REQUEST[self::var_plan_id]);
			if (!$this->is_owned($plan)) $this->set_as_new($plan);

			$is_edit_plan = FALSE;

			if (isset($_POST[self::var_schedule_id])) {
				$schedule = BfoxPlans::get_schedule($_POST[self::var_schedule_id]);
				if ((self::$save_new == $_POST[self::var_submit]) || !$this->is_owned($schedule)) $this->set_as_new($schedule);

				$list = BfoxPlans::get_list($_REQUEST[self::var_list_id]);

				if (isset($_POST[self::var_schedule_start])) {
					$schedule->set_start_date($_POST[self::var_schedule_start]);
					$schedule->frequency = $_POST[self::var_schedule_frequency];
					$schedule->set_freq_options((array) $_POST[self::var_schedule_freq_options]);
					$schedule->set_end_date($list);
					$schedule->list_id = $list->id;
				}

				$is_edit_plan = empty($schedule->id);

				BfoxPlans::save_schedule($schedule);
				$messages []= "Saved Reading Schedule";

				$plan->schedule_id = $schedule->id;
				$plan->list_id = $schedule->list_id;

				$redirect = $this->edit_schedule_url($schedule->id);
			}
			elseif (isset($_POST[self::var_list_id])) {
				$list = BfoxPlans::get_list($_POST[self::var_list_id]);
				if ((self::$save_new == $_POST[self::var_submit]) || !$this->is_owned($list)) $this->set_as_new($list);

				if (isset($_POST[self::var_list_name])) {
					$list->name = $_POST[self::var_list_name];
					$list->description = $_POST[self::var_list_description];
					$list->set_readings_by_strings($_POST[self::var_list_readings]);
					$list->add_passages($_POST[self::var_list_passages], $_POST[self::var_list_chunk_size]);
				}

				$is_edit_plan = empty($list->id);

				BfoxPlans::save_list($list);
				$messages []= "Saved Reading List: '$list->name'";

				$plan->list_id = $list->id;

				$redirect = $this->edit_list_url($list->id);
			}

			if ($is_edit_plan) {
				BfoxPlans::save_plan($plan);
				$redirect = $this->edit_plan_url($plan->id);
			}
		}

		$message = implode('<br/>', $messages);

		if (!empty($redirect)) wp_redirect(add_query_arg(BfoxQuery::var_message, urlencode($message), $redirect));
	}

	private function add_plan_link($str = 'Add a Plan') {
		return "<a href='" . add_query_arg(self::var_plan_id, 0, $this->url) . "'>$str</a>";
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

	private function edit_list_link(BfoxReadingList $list, $str = 'Edit Reading List') {
		return "<a href='" . $this->edit_list_url($list->id) . "'>$str</a>";
	}

	private function select_list_link(BfoxReadingList $list, $str = 'Select List') {
		return "<a href='" . add_query_arg(array(self::var_list_id => $list->id, self::var_plan_id => 0), $this->url) . "'>$str</a>";
	}

	private function edit_schedule_url($schedule_id = 0) {
		return add_query_arg(self::var_schedule_id, $schedule_id, $this->url);
	}

	private function edit_schedule_link(BfoxReadingSchedule $schedule, $str = 'Edit Schedule') {
		return "<a href='" . $this->edit_schedule_url($schedule->id) . "'>$str</a>";
	}

	private function select_schedule_link(BfoxReadingSchedule $schedule, $str = 'Select Schedule') {
		return "<a href='" . add_query_arg(array(self::var_schedule_id => $schedule->id, self::var_list_id => $schedule->list_id, self::var_plan_id => 0), $this->url) . "'>$str</a>";
	}

	private function is_owned(BfoxReadingInfo $info) {
		return (($info->owner == $this->owner) && ($info->owner_type == $this->owner_type));
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

		if (isset($_REQUEST[self::var_plan_id])) $this->content_plan(BfoxPlans::get_plan($_REQUEST[self::var_plan_id]));
		elseif (isset($_REQUEST[self::var_schedule_id])) $this->content_schedule(BfoxPlans::get_schedule($_REQUEST[self::var_schedule_id]));
		elseif (isset($_REQUEST[self::var_list_id])) $this->content_list(BfoxPlans::get_list($_REQUEST[self::var_list_id]));
		else $this->default_content();
	}

	private function default_content() {
		$plans = BfoxPlans::get_owner_plans($this->owner, $this->owner_type);

		$list_ids = array();
		$schedule_ids = array();
		foreach ($plans as $plan) {
			$list_ids[$plan->list_id] = TRUE;
			$schedule_ids[$plan->schedule_id] = TRUE;
		}
		$schedule_ids = array_keys($schedule_ids);
		$list_ids = array_keys($list_ids);

		$lists = BfoxPlans::get_lists($list_ids, $this->owner, $this->owner_type);
		$schedules = BfoxPlans::get_schedules($schedule_ids, $this->owner, $this->owner_type);

		?>

		<p>Reading plans allow you to organize how you read the Bible. You can create your own reading plans, or subscribe to someone else's plans.</p>

		<h3>Reading Plans</h3>
		<p>These are reading plans you have created or have subscribed to:</p>
		<?php $this->plans_table($plans, $lists, $schedules) ?>
		<?php echo $this->add_plan_link() ?>
		<?php
	}

	private function content_readings(BfoxReadingList $list, BfoxReadingSchedule $schedule = NULL, $max_cols = 3) {

		$reading_count = count($list->readings);

		$dates = array();
		$unread_readings = array();

		if (!is_null($schedule)) {
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
		}

		$table = new BfoxHtmlTable("class='reading_plan'");

		// Create the table header
		$header = new BfoxHtmlRow();
		$header->add_header_col('', "width='1*'");
		$header->add_header_col('Passage', "width='10*'");
		if (!empty($dates)) $header->add_header_col('Date', "width='5*'");
		if (!empty($unread_readings)) $header->add_header_col('My Progress', "width='5*'");
		$table->add_header_row($header);

		foreach ($list->readings as $reading_id => $reading) {
			// Create the row for this reading
			if ($reading_id == $current_date_index) $row = new BfoxHtmlRow("class='current'");
			else $row = new BfoxHtmlRow('');

			// Add the reading index column and the bible reference column
			$row->add_col($reading_id + 1);
			$row->add_col(BfoxBlog::ref_link($reading->get_string()));

			// Add the Date column
			if (!empty($dates)) {
				if (isset($dates[$reading_id])) $row->add_col(date('M d', $dates[$reading_id]));
				else $row->add_col();
			}

			// Add the History column
			if (!empty($unread_readings)) {
				if (isset($unread_readings[$reading_id])) $row->add_col($unread_readings[$reading_id]->get_string());
				else $row->add_col();
			}

			// Add the row to the table
			$table->add_row($row);
		}

		return $table->content_split($max_cols, "class='reading_plan_columns'");
	}

	private function content_plan(BfoxReadingPlan $plan) {
		if (empty($plan->list_id) && isset($_GET[self::var_list_id])) $plan->list_id = $_GET[self::var_list_id];

		if (empty($plan->list_id)) $this->select_list($plan);
		elseif (empty($plan->schedule_id)) $this->select_schedule($plan);
		else $this->content_schedule(BfoxPlans::get_schedule($plan->schedule_id));
	}

	private function content_list(BfoxReadingList $list) {
		echo $this->content_readings($list);
		?>
		<h3>Edit Reading List</h3>
		<?php $this->edit_list($list) ?>
		<?php
	}

	private function content_schedule(BfoxReadingSchedule $schedule) {
		$list = BfoxPlans::get_list($schedule->list_id);
		echo $this->content_readings($list, $schedule, 3);
		?>
		<h3><?php echo $this->edit_list_link($list, 'Edit Reading List') ?></h3>
		<h3>Edit Reading Schedule</h3>
		<?php $this->edit_schedule($schedule) ?>
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
				<td><?php echo $list->name ?> by <?php echo $list->owner_link() ?><br/><?php echo $list->description ?></td>
				<td><?php echo $list->reading_count() ?> readings: <?php echo BfoxBlog::ref_link($list->ref_string()) ?></td>
				<td><?php echo $this->edit_list_link($list, __('Edit')) ?><br/>Duplicate<br/>Delete</td>
			</tr>
		<?php endforeach ?>
		</table>
		<?php
	}

	private function edit_list(BfoxReadingList $list) {
		$is_owned = $this->is_owned($list);

		$submit = '';
		if ($is_owned) $submit = "<input type='submit' name='" . self::var_submit . "' value='" . self::$save . "'/>";
		$submit .= "<input type='submit' name='" . self::var_submit . "' value='" . self::$save_new . "'/>";;

		$table = new BfoxHtmlOptionTable("class='form-table'", "action='$this->url' method='post'",
			BfoxUtility::hidden_input(self::var_list_id, $list->id),
			$submit);

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

	private function edit_schedule(BfoxReadingSchedule $schedule, $plan_id = 0) {
		$is_owned = $this->is_owned($schedule);

		$submit = '';
		if ($is_owned) $submit = "<input type='submit' name='" . self::var_submit . "' value='" . self::$save . "'/>";
		$submit .= "<input type='submit' name='" . self::var_submit . "' value='" . self::$save_new . "'/>";;

		if (!empty($plan_id)) $plan_id_input = BfoxUtility::hidden_input(self::var_plan_id, $plan_id);

		$table = new BfoxHtmlOptionTable("class='form-table'", "action='$this->url' method='post'",
			BfoxUtility::hidden_input(self::var_schedule_id, $schedule->id) .
			BfoxUtility::hidden_input(self::var_list_id, $schedule->list_id) .
			$plan_id_input,
			$submit);

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

		$header = new BfoxHtmlHeaderRow(array('Reading List', 'Overview', ''));
		$your_lists_table->add_header_row($header);
		$popular_lists_table->add_header_row($header);

		foreach ($lists as $list) {
			$row = new BfoxHtmlRow(array(
				"$list->name by " . $list->owner_link() . "<br/>$list->description",
				$list->reading_count() . " readings: " . BfoxBlog::ref_link($list->ref_string()),
				$this->select_list_link($list)
				));
			if ($this->is_owned($list)) $your_lists_table->add_row($row);
			if ($popular[$list->id]) $popular_lists_table->add_row($row);
		}

		?>
		Select a reading list that has already been created, or create a new one:
		<h3>Your Reading Lists</h3>
		<?php echo $your_lists_table->content() ?>
		<h3>Popular Reading Lists</h3>
		<?php echo $popular_lists_table->content() ?>
		<h3>Create Reading List</h3>
		<?php echo $this->edit_list(new BfoxReadingList()) ?>
		<?php
	}

	private static function schedule_desc(BfoxReadingSchedule $schedule) {
		return $schedule->start_str() . ' - ' . $schedule->end_str() . (($schedule->is_recurring) ? ' (recurring)' : '') . ' (' . $schedule->frequency_desc() . ')';
	}

	private function select_schedule(BfoxReadingPlan $plan) {
		$list = BfoxPlans::get_list($plan->list_id);
		$for_list_schedule_ids = BfoxPlans::get_list_schedule_ids($list->id);
		$schedule_ids = $for_list_schedule_ids;
		$schedules = BfoxPlans::get_schedules($schedule_ids);//, $this->owner, $this->owner_type);

		$for_list = array_fill_keys($for_list_schedule_ids, TRUE);

		$for_list_table = new BfoxHtmlTable("class='widefat'");

		$header = new BfoxHtmlRow();
		$for_list_table->add_header_row(new BfoxHtmlHeaderRow(array('Schedule', 'Owner', '')));

		foreach ($schedules as $schedule) {
			if ($for_list[$schedule->id]) {
				$for_list_table->add_row(new BfoxHtmlRow(array(
					self::schedule_desc($schedule),
					$schedule->owner_link(),
					$this->select_schedule_link($schedule)
					)));
			}
		}

		echo $this->content_readings($list);

		$new_schedule = new BfoxReadingSchedule();
		$new_schedule->list_id = $list->id;

		?>
		Select a reading schedule that has already been created, or create a new one:
		<h3>Reading Schedules for this Reading List</h3>
		<?php echo $for_list_table->content() ?>
		<h3>Create a New Schedule</h3>
		<?php echo $this->edit_schedule($new_schedule, $plan->id) ?>
		<?php
	}

	private function plans_table($plans, $lists, $schedules) {
		?>
		<table id="reading_plans" class="widefat">
			<thead>
			<tr>
				<th>Reading List</th>
				<th>Schedule</th>
				<th>Options</th>
			</tr>
			</thead>
		<?php foreach ($plans as $plan): ?>
			<?php $list = (isset($lists[$plan->list_id])) ? $lists[$plan->list_id] : new BfoxReadingList() ?>
			<?php $schedule = (isset($schedules[$plan->schedule_id])) ? $schedules[$plan->schedule_id] : new BfoxReadingSchedule() ?>
			<tr>
				<td><?php echo $list->name ?> by <?php echo $list->owner_link() ?><br/><?php echo $list->description ?></td>
				<td><?php echo $schedule->start_str() ?> - <?php echo $schedule->end_str() ?><?php if ($schedule->is_recurring) echo ' (recurring)'?> (<?php echo $schedule->frequency_desc() ?>)</td>
				<td><?php echo $this->edit_plan_link($plan, 'Edit') ?><br/>Remove Plan</td>
			</tr>
		<?php endforeach ?>
		</table>
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
				<td><?php echo $schedule->name ?> by <?php echo $schedule->owner_link() ?><br/><?php echo $schedule->description ?></td>
				<td><?php echo $list->name ?><br/>by <?php echo $list->owner_link() ?></td>
				<td><?php echo 'status' ?></td>
				<td><?php echo $schedule->start_str() ?> - <?php echo $schedule->end_str() ?><?php if ($schedule->is_recurring) echo ' (recurring)'?><br/><?php echo $schedule->frequency_desc() ?></td>
				<td>Edit Plan<br/>Edit Readings<br/>Delete</td>
			</tr>
		<?php endforeach ?>
		</table>
		<?php
	}
}

?>