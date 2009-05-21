<?php

class BfoxCboxPlans extends BfoxCbox {

	const var_submit = 'submit';
	const var_plan_id = 'plan_id';
	const var_content = 'content';

	public function page_load() {

		/*if (isset($_POST[self::var_submit])) {
			$plan = BfoxPlans::get_plan($_POST[self::var_plan_id]);
			$plan->set_content(strip_tags(stripslashes($_POST[self::var_content])));
			BfoxPlans::save_plan($plan);
			wp_redirect($this->edit_plan_url($plan->id));
		}*/
	}

	public function content() {
		global $user_ID;

		$plans = BfoxPlans::get_owner_plans($user_ID, BfoxPlans::owner_type_user);

		foreach ($plans as $plan) {
			$schedule_ids []= $plan->schedule_id;
			$list_ids []= $plan->list_id;
		}

		$schedules = BfoxPlans::get_schedules($schedule_ids);
		$lists = BfoxPlans::get_lists($list_ids);

		$current_table = new BfoxHtmlTable("class='widefat'");
		$current_table->add_header_row('', 6, 'Date', '#', 'Reading List', 'Scriptures', 'Unread', 'Options');

		$upcoming_table = new BfoxHtmlTable("class='widefat'");
		$upcoming_table->add_header_row('', 6, 'Date', '#', 'Reading List', 'Scriptures', 'Unread', 'Options');

		foreach ($schedules as $schedule) {
			$list = $lists[$schedule->list_id];
			$reading_count = $list->reading_count();
			$reading_strings = $list->reading_strings();
			// Get the date information
			$dates = $schedule->get_dates($reading_count + 1);
			$current_date_index = BfoxReadingSchedule::current_date_index($dates);
			if ($reading_count <= $current_date_index) $current_date_index = -1;
			else {
				$current_table->add_row('', 6, date('M d', $dates[$current_date_index]), $current_date_index, $list->name, $reading_strings[$current_date_index], 'unread', 'mark as read');
				if ($current_date_index < ($reading_count - 1)) $upcoming_table->add_row('', 6, date('M d', $dates[$current_date_index + 1]), $current_date_index + 1, $list->name, $reading_strings[$current_date_index + 1], 'unread', 'mark as read');
			}

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

		echo $current_table->content();
		echo $upcoming_table->content();
	}
}

?>