<?php

class BfoxPlanEdit
{
	const option_user_plan_ids = 'bfox_user_plans';

	const var_list_id = 'list_id';
	const var_list_name = 'list_name';
	const var_list_description = 'list_description';
	const var_list_readings = 'list_readings';
	const var_list_passages = 'list_passages';
	const var_list_chunk_size = 'list_chunk_size';

	private $owner;
	private $owner_type;
	private $url;
	private $plan_ids;

	public function __construct($owner, $owner_type, $url, $plan_ids = array()) {
		$this->owner = $owner;
		$this->owner_type = $owner_type;
		$this->url = $url;
		$this->plan_ids = $plan_ids;
	}

	public function page_load()
	{
		$messages = array();

		if (isset($_POST['submit'])) {
			if (isset($_POST[self::var_list_id])) $list = BfoxPlans::get_list($_POST[self::var_list_id]);
			else {
				$list = new BfoxReadingList();

				$list->owner = $this->owner;
				$list->owner_type = $this->owner_type;
			}

			if (($this->owner_type == $list->owner_type) && ($this->owner == $list->owner)) {
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

	private static function get_user_plan_ids($user_id = 0)
	{
		return (array) get_user_option(self::option_user_plan_ids, $user_id, FALSE);
	}

	private static function set_user_plan_ids($plan_ids, $user_id = 0)
	{
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];
		update_user_option($user_id, self::option_user_plan_ids, $plan_ids, TRUE);
	}

	public static function edit_list_link($list_id) {
		return add_query_arg(self::var_list_id, $list_id, $this->url);
	}

	public function content() {
		if (!empty($_GET[BfoxQuery::var_message])) {
			?>
			<div id="page_message"><?php echo strip_tags(stripslashes(urldecode($_GET[BfoxQuery::var_message])), '<br/>') ?></div>
			<?php
			$_SERVER['REQUEST_URI'] = remove_query_arg(array(BfoxQuery::var_message), $_SERVER['REQUEST_URI']);
		}

		if (isset($_REQUEST[self::var_list_id])) self::list_content(BfoxPlans::get_list($_POST[self::var_list_id]));
		else $this->default_content();
	}

	private function default_content() {
		$plans = BfoxPlans::get_plans($this->plan_ids, $this->owner, $this->owner_type);

		// Get the reading lists for all these plans
		$list_ids = array();
		foreach ($plans as $plan) $list_ids []= $plan->list_id;
		$lists = BfoxPlans::get_lists($list_ids, $this->owner, $this->owner_type);

		?>

		<p>Reading plans allow you to organize how you read the Bible. You can create your own reading plans, or subscribe to someone else's plans.</p>

		<h3>My Schedules</h3>
		<p>These are schedules you are following:</p>

		<h3>Reading Plans</h3>
		<p>These are reading plans you have created or have subscribed to:</p>
		<?php self::plans_table($plans, $lists) ?>

		<h3>My Reading Lists</h3>
		<p>These are reading lists you have created or have subscribed to:</p>
		<?php self::lists_table($lists) ?>

		<h3>Create New Reading List</h3>
		<?php self::list_edit(new BfoxReadingList()) ?>

		<?php
	}

	private static function lists_table($lists) {
		?>
		<table id='reading_lists' class='widefat'>
			<thead>
			<tr>
				<th>Description</th>
				<th>Overview</th>
				<th>Options</th>
			</tr>
			</thead>
		<?php foreach ($lists as $list): ?>
			<tr>
				<td><?php echo $list->name ?> by <?php echo $list->owner_link() ?><br/><?php echo $list->description ?></td>
				<td><?php echo $list->reading_count() ?> readings: <?php echo BfoxBlog::ref_link($list->ref_string()) ?></td>
				<td>Edit<br/>Duplicate<br/>Delete</td>
			</tr>
		<?php endforeach ?>
		</table>
		<?php
	}

	private static function list_edit(BfoxReadingList $list) {
		?>
		<form>
		<table>
		<?php
			$passage_help_text = __('<p>This allows you to add passages of the Bible to your reading plan in big chunks.</p>
				<p>You can type passages of the bible in the box, and then set how many chapters you want to read at a time. The passages will be cut into sections and added to your reading plan.</p>
				<p>Type any passages in the box above. For instance, to make a reading plan of all the gospels you could type "Matthew, Mark, Luke, John".<br/>
				You can use bible abbreviations (ie. "gen" instead of "Genesis"), and even specify chapters and verses (ie. "gen 1-3").<br/>
				Separate passages can be separated with a comma (\',\'), semicolon (\';\'), or on separate lines.</p>');

			BfoxUtility::option_form_text(self::var_list_name, __('Reading List Name'), '', $list->name, "size = '40'");
			BfoxUtility::option_form_textarea(self::var_list_description, __('Description'), __('Add an optional description of this reading list.'), 2, 50, $list->description);
			BfoxUtility::option_form_textarea(self::var_list_readings, __('Readings'), $reading_help_text, 15, 50, $passages);
			BfoxUtility::option_form_textarea(self::var_list_passages, __('Add Groups of Passages'), "<input name='" . self::var_list_chunk_size . "' id='" . self::var_list_chunk_size . "' type='text' value='1' size='4' maxlength='4'/><br/>$passage_help_text", 3, 50, '');
		?>
		</table>
		<input type='submit' name='submit' value='Add New'/>
		</form>
		<?php
	}

	private static function list_content(BfoxReadingList $list) {
		?>
		<h3>Edit Reading List</h3>
		<?php self::list_edit($list) ?>
		<?php
	}

	private static function plans_table($plans, $lists) {
		?>
		<table id="reading_plans" class="widefat">
			<thead>
			<tr>
				<th>Description</th>
				<th>Reading List</th>
				<th>Status</th>
				<th>Schedule</th>
				<th>Options</th>
			</tr>
			</thead>
		<?php foreach ($plans as $plan): ?>
			<?php $list = $lists[$plan->list_id] ?>
			<tr>
				<td><?php echo $plan->name ?> by <?php echo $plan->owner_link() ?><br/><?php echo $plan->description ?></td>
				<td><?php echo $list->name ?><br/>by <?php echo $list->owner_link() ?></td>
				<td><?php echo 'status' ?></td>
				<td><?php echo $plan->start_str() ?> - <?php echo $plan->end_str() ?><?php if ($plan->is_recurring) echo ' (recurring)'?><br/><?php echo $plan->frequency_desc() ?></td>
				<td>Edit Plan<br/>Edit Readings<br/>Delete</td>
			</tr>
		<?php endforeach ?>
		</table>
		<?php
	}
}

?>