<?php

class BfoxPlanEdit
{
	const var_plan_id = 'plan_id';

	const var_list_submit = 'list_submit';
	const var_list_id = 'list_id';
	const var_list_name = 'list_name';
	const var_list_description = 'list_description';
	const var_list_readings = 'list_readings';
	const var_list_passages = 'list_passages';
	const var_list_chunk_size = 'list_chunk_size';

	const var_schedule_id = 'schedule_id';

	private $owner;
	private $owner_type;
	private $url;

	private static $save_list;
	private static $save_new_list;

	public function __construct($owner, $owner_type, $url) {
		$this->owner = $owner;
		$this->owner_type = $owner_type;
		$this->url = $url;

		self::$save_list = __('Save List');
		self::$save_new_list = __('Save as new List');
	}

	private function create_list_by_input($input) {
		if (!empty($input[self::var_list_id])) $list = BfoxPlans::get_list($input[self::var_list_id]);
		else $list = new BfoxReadingList();

		if (isset($input[self::var_list_name])) $list->name = $input[self::var_list_name];
		if (isset($input[self::var_list_description])) $list->description = $input[self::var_list_description];
		if (isset($input[self::var_list_readings])) $list->set_readings_by_strings($input[self::var_list_readings]);
		if (isset($input[self::var_list_passages])) $list->add_passages($input[self::var_list_passages], $input[self::var_list_chunk_size]);

		return $list;
	}

	public static function add_head()
	{
		?>
		<link rel="stylesheet" href="<?php echo get_option('siteurl') ?>/wp-content/mu-plugins/biblefox/plans/plans.css" type="text/css"/>
		<?php
	}

	public function page_load() {
		$messages = array();

		/*$plan_id = $_POST[self::var_plan_id];
		$list_id = $_POST[self::var_list_id];
		$schedule_id = $_POST[self::var_schedule_id];

		if (!empty($schedule_id)) {
			// Edit Schedule
		}
		elseif (!empty($list_id) || !empty($plan_id)) {
			// Edit List
		}
		elseif (isset($_POST[self::var_plan_id])) {
			if ($is_post_schedule) {
				// Save schedule
			}
			elseif ($is_post_list) {

			}

		}*/

		if (isset($_POST[self::var_list_submit])) {

			// If we are saving an old list, get the list from the DB
			// Otherwise, create a new list and set the new owner
			if ((self::$save_list == $_POST[self::var_list_submit]) && !empty($_POST[self::var_list_id]))
				$list = BfoxPlans::get_list($_POST[self::var_list_id]);
			else {
				$list = new BfoxReadingList();

				$list->owner = $this->owner;
				$list->owner_type = $this->owner_type;
			}

			// We can only save lists that we own
			if ($this->is_owned($list)) {
				$list->name = $_POST[self::var_list_name];
				$list->description = $_POST[self::var_list_description];
				$list->set_readings_by_strings($_POST[self::var_list_readings]);
				$list->add_passages($_POST[self::var_list_passages], $_POST[self::var_list_chunk_size]);

				BfoxPlans::save_list($list);
				$messages []= "Saved Reading List: '$list->name'";
			}
			else $messages []= "You cannot modify this list because you are not its owner.";
		}

		$message = implode('<br/>', $messages);

		// If there is a message, redirect to show the message
		// Otherwise if there is no message, but this was still an update, redirect so that refreshing the page won't try to resend the update
		if (!empty($message)) wp_redirect(add_query_arg(BfoxQuery::var_message, urlencode($message), BfoxQuery::page_url(BfoxQuery::page_plans)));
		elseif (!empty($_POST['update'])) wp_redirect(BfoxQuery::page_url(BfoxQuery::page_plans));
	}

	private function edit_plan_link(BfoxReadingPlan $plan, $str = '') {
		return "<a href='" . add_query_arg(self::var_plan_id, $plan->id, $this->url) . "'>$str</a>";
	}

	private function edit_list_link(BfoxReadingList $list, $str = '') {
		if (empty($str)) $str = $list->name;

		return "<a href='" . add_query_arg(self::var_list_id, $list->id, $this->url) . "'>$str</a>";
	}

	private function is_owned(BfoxReadingInfo $info) {
		return (($info->owner == $this->owner) && ($info->owner_type == $this->owner_type));
	}

	public function content() {
		if (!empty($_GET[BfoxQuery::var_message])) {
			?>
			<div id="page_message"><?php echo strip_tags(stripslashes(urldecode($_GET[BfoxQuery::var_message])), '<br/>') ?></div>
			<?php
			$_SERVER['REQUEST_URI'] = remove_query_arg(array(BfoxQuery::var_message), $_SERVER['REQUEST_URI']);
		}

		if (isset($_REQUEST[self::var_plan_id])) $this->page_plan(BfoxPlans::get_plan($_REQUEST[self::var_plan_id]));
		elseif (isset($_REQUEST[self::var_list_id])) $this->list_content(BfoxPlans::get_list($_REQUEST[self::var_list_id]));
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

		<h3>My Reading Lists</h3>
		<p>These are reading lists you have created or have subscribed to:</p>
		<?php $this->lists_table($lists) ?>

		<h3>Create New Reading List</h3>
		<?php $this->list_edit(new BfoxReadingList()) ?>

		<?php
	}

	private function page_plan(BfoxReadingPlan $plan) {
		$list = BfoxPlans::get_list($plan->list_id);
		$schedule = BfoxPlans::get_schedule($plan->schedule_id);
		echo $this->content_plan($plan, $list, $schedule);
	}

	private function content_plan(BfoxReadingPlan $plan, BfoxReadingList $list, BfoxReadingSchedule $schedule, $max_cols = 2) {

		$reading_count = count($list->readings);

		// Get the date information
		$dates = $schedule->get_dates($reading_count + 1);
		$current_date_index = BfoxReadingSchedule::current_date_index($dates);
		if ($reading_count == $current_date_index) $current_date_index = -1;

		// Get the history information
		$unread_readings = array();

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

	private function list_edit(BfoxReadingList $list) {
		$is_owned = $this->is_owned($list);
		?>

		<?php if (!empty($list->id) && !$is_owned): ?>
		<p>You do not own this reading list. If you make changes they will be saved as a new reading list.</p>
		<?php endif ?>
		<form action='<?php echo $this->url ?>' method='post'>
		<input type='hidden' name='<?php echo self::var_list_id ?>' value='<?php echo $list->id ?>'/>
		<table>
		<?php
			$passage_help_text = __('<p>This allows you to add passages of the Bible to your reading plan in big chunks.</p>
				<p>You can type passages of the bible in the box, and then set how many chapters you want to read at a time. The passages will be cut into sections and added to your reading plan.</p>
				<p>Type any passages in the box above. For instance, to make a reading plan of all the gospels you could type "Matthew, Mark, Luke, John".<br/>
				You can use bible abbreviations (ie. "gen" instead of "Genesis"), and even specify chapters and verses (ie. "gen 1-3").<br/>
				Separate passages can be separated with a comma (\',\'), semicolon (\';\'), or on separate lines.</p>');

			BfoxUtility::option_form_text(self::var_list_name, __('Reading List Name'), '', $list->name, "size = '40'");
			BfoxUtility::option_form_textarea(self::var_list_description, __('Description'), __('Add an optional description of this reading list.'), 2, 50, $list->description);
			BfoxUtility::option_form_textarea(self::var_list_readings, __('Readings'), $reading_help_text, 15, 50, implode("\n", $list->reading_strings()));
			BfoxUtility::option_form_textarea(self::var_list_passages, __('Add Groups of Passages'), "<input name='" . self::var_list_chunk_size . "' id='" . self::var_list_chunk_size . "' type='text' value='1' size='4' maxlength='4'/><br/>$passage_help_text", 3, 50, '');
		?>
		</table>
		<?php if ($is_owned): ?>
		<input type='submit' name='<?php echo self::var_list_submit ?>' value='<?php echo self::$save_list ?>'/>
		<?php endif ?>
		<input type='submit' name='<?php echo self::var_list_submit ?>' value='<?php echo self::$save_new_list ?>'/>
		</form>
		<?php
	}

	private function list_content(BfoxReadingList $list) {
		?>
		<h3>Edit Reading List</h3>
		<?php $this->list_edit($list) ?>
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
			<?php $list = $lists[$plan->list_id] ?>
			<?php $schedule = $schedules[$plan->schedule_id] ?>
			<tr>
				<td><?php echo $list->name ?> by <?php echo $list->owner_link() ?><br/><?php echo $list->description ?></td>
				<td><?php echo $schedule->start_str() ?> - <?php echo $schedule->end_str() ?><?php if ($schedule->is_recurring) echo ' (recurring)'?> <?php echo $schedule->frequency_desc() ?></td>
				<td><?php echo $this->edit_plan_link($plan, __('Edit Plan')) ?><br/>Remove Plan</td>
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