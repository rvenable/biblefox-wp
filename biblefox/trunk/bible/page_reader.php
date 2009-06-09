<?php

require_once BFOX_BIBLE_DIR . '/ref_content.php';

class BfoxPageReader extends BfoxPage {

	const var_query = 'query';
	const var_plan_id = 'plan_id';
	const var_reading_id = 'reading_id';
	const var_page_num = 'page_num';

	const query_home = 'home';
	const query_plan = 'plan';

	private $query = self::query_home;
	private $plans = array();
	private $plan_id = 0;
	private $reading_id = BfoxReadingPlan::reading_id_invalid;
	private $page_num = 0;

	public function __construct() {
		parent::__construct();

		global $user_ID;

		$subs = BfoxPlans::get_user_subs($user_ID, BfoxPlans::user_type_user);

		$plan_ids = array();
		foreach ($subs as $sub) if ($sub->is_subscribed && !$sub->is_finished) $plan_ids []= $sub->plan_id;

		if (!empty($plan_ids)) $this->plans = BfoxPlans::get_plans($plan_ids);

		$earliest = '';
		foreach($this->plans as $plan) {
			$start_time = $plan->raw_start_date();
			if (empty($earliest) || ($start_time < $earliest)) $earliest = $start_time;
		}

		if (!empty($earliest)) {
			$history_array = BfoxHistory::get_history(0, $earliest, 0, TRUE);
			foreach ($this->plans as &$plan) $plan->set_history($history_array);
		}

		if (!empty($_REQUEST[self::var_plan_id])) $this->plan_id = $_REQUEST[self::var_plan_id];
		if (!empty($_REQUEST[self::var_reading_id])) $this->reading_id = $_REQUEST[self::var_reading_id];
		if (!empty($_REQUEST[self::var_page_num])) $this->page_num = $_REQUEST[self::var_page_num];
	}

	private function reading_url($plan_id, $reading_id, $page_num = 0) {
		$url = add_query_arg(array(self::var_query => $this->query, self::var_plan_id => $plan_id, self::var_reading_id => $reading_id), BfoxQuery::page_url(BfoxQuery::page_reader)) . "#plan_{$plan_id}_$reading_id";
		if (!empty($page_num)) $url = add_query_arg(self::var_page_num, $page_num, $url);
		return $url;
	}

	public function page_load() {

		/*if (isset($_POST[self::var_submit])) {
			$plan = BfoxPlans::get_plan($_POST[self::var_plan_id]);
			$plan->set_content(strip_tags(stripslashes($_POST[self::var_content])));
			BfoxPlans::save_plan($plan);
			wp_redirect($this->edit_plan_url($plan->id));
		}*/
	}

	private function create_reading_row(BfoxReadingPlan $plan, $reading_id, $is_unread = TRUE) {

		if (!$is_unread) $finished = " finished";
		else $finished = '';

		$ref_str = $plan->readings[$reading_id]->get_string();

		if (($this->plan_id == $plan->id) && ($this->reading_id == $reading_id)) {
			ob_start();
			BfoxRefContent::ref_content_paged($plan->readings[$reading_id], $this->translation, $this->reading_url($plan->id, $reading_id), self::var_page_num, $this->page_num);
			$ref_content = ob_get_clean();
		}
		else $ref_content = BfoxRefContent::ref_loader($ref_str);
		$url = $this->reading_url($plan->id, $reading_id);

		return BfoxRefContent::passage_row("<a href='$url' id='plan_{$plan->id}_$reading_id'><div class='reading_date'>" . date('l, M jS', $plan->dates[$reading_id]) . "</div><div class='reading_title$finished'>$plan->name #" . ($reading_id + 1) . ": $ref_str</div></a>",
			"<a href='" . BfoxQuery::reading_plan_url($plan->id) . "'>View plan</a>Mark as Read",
			$ref_content);
	}

	public function content() {
		if (!empty($this->plans)) {

			$list = new BfoxHtmlList("class='passage_list ui-accordion ui-widget ui-helper-reset'");

			foreach ($this->plans as $plan) if ($plan->is_current()) {
				if (empty($this->plan_id)) $this->plan_id = $plan->id;

				foreach ($plan->readings as $reading_id => $reading) {
					$unread = $plan->get_unread($reading);
					$is_unread = $unread->is_valid();

					// If the passage is unread or current, add it
					if ($is_unread || ($reading_id >= $plan->current_reading_id)){
						if (BfoxReadingPlan::reading_id_invalid == $this->reading_id) $this->reading_id = $reading_id;

						$list->add($this->create_reading_row($plan, $reading_id, $is_unread), $plan->dates[$reading_id]);
					}
				}
			}

			echo $list->content(TRUE);
			BfoxRefContent::ref_js();
		}
	}
}

?>