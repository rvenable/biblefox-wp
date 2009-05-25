<?php

class BfoxCboxPlans extends BfoxCbox {

	const var_submit = 'submit';
	const var_plan_id = 'plan_id';
	const var_content = 'content';

	private $plans = array();
	private $history = array();

	public function __construct($url, $id = '', $title = '') {
		parent::__construct($url, $id, $title);

		global $user_ID;

		$subs = BfoxPlans::get_user_subs($user_ID, BfoxPlans::user_type_user);

		$plan_ids = array();
		foreach ($subs as $sub) if ($sub->is_subscribed && !$sub->is_finished) $plan_ids []= $sub->plan_id;

		if (!empty($plan_ids)) $this->plans = BfoxPlans::get_plans($plan_ids);
	}

	public function get_earliest_time() {
		$earliest = '';
		foreach($this->plans as $plan) {
			$start_time = $plan->raw_start_date();
			if (empty($earliest) || ($start_time < $earliest)) $earliest = $start_time;
		}

		return $earliest;
	}

	public function set_history($history_array) {
		foreach ($this->plans as &$plan) $plan->set_history($history_array);
	}

	public function page_load() {

		/*if (isset($_POST[self::var_submit])) {
			$plan = BfoxPlans::get_plan($_POST[self::var_plan_id]);
			$plan->set_content(strip_tags(stripslashes($_POST[self::var_content])));
			BfoxPlans::save_plan($plan);
			wp_redirect($this->edit_plan_url($plan->id));
		}*/
	}

	private static function create_reading_row(BfoxReadingPlan $plan, $reading_id) {

		$unread = $plan->get_unread($plan->readings[$reading_id]);
		if (!$unread->is_valid()) $ref_attrs = "class='finished'";
		else $ref_attrs = '';

		$ref_str = $plan->readings[$reading_id]->get_string();
		return new BfoxHtmlRow($attrs,
			date('M d', $plan->dates[$reading_id]),
			$reading_id + 1,
			"<a href='" . BfoxQuery::reading_plan_url($plan->id) . "'>$plan->name</a>",
			array("<a href='" . BfoxQuery::passage_page_url($ref_str) . "'>$ref_str</a>", $ref_attrs));

	}

	public function content() {
		if (!empty($this->plans)) {

			$current_table = new BfoxHtmlTable("class='widefat'", '', '');
			$current_table->add_header_row('', 4, 'Date', '#', 'Reading List', 'Scriptures');

			$upcoming_table = new BfoxHtmlTable("class='widefat'");
			$upcoming_table->add_header_row('', 4, 'Date', '#', 'Reading List', 'Scriptures');

			foreach ($this->plans as $plan) if ($plan->is_current()) {
				//pre($plan);
				$current_table->add_row($this->create_reading_row($plan, $plan->current_reading_id));

				// Add upcoming rows
				for ($i = 1; $i <= 3; $i++) if (($plan->current_reading_id + $i) < count($plan->readings)) $upcoming_table->add_row($this->create_reading_row($plan, $plan->current_reading_id + $i));
			}

			echo "<h3>Current Readings</h3>";
			echo $current_table->content();
			echo "<h3>Upcoming Readings</h3>";
			echo $upcoming_table->content();
		}
	}
}

?>