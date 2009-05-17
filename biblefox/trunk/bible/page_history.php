<?php

class BfoxPageHistory extends BfoxPage {

	public function content() {
		$history_array = BfoxHistory::get_history();

		$history_table = new BfoxHtmlTable("class='widefat'");
		$history_table->add_header_row('', 3, 'Passage', 'Time', 'Edit');
		foreach ($history_array as $history) {
			$ref_str = $history->refs->get_string();

			if ($history->is_read) {
				$intro = __('Read on');
				$toggle = __('Mark as Unread');
			}
			else {
				$intro = __('Viewed on');
				$toggle = __('Mark as Read');
			}

			$history_table->add_row('', 3,
				"<a href='" . BfoxQuery::passage_page_url($ref_str, $this->translation) . "'>$ref_str</a>",
				"$intro $history->time",
				"<a href='" . BfoxQuery::toggle_read_url($history->time) . "'>" . $toggle . "</a>");
		}

		echo "<h2>Reading History</h2><br/>";
		echo $history_table->content();
	}
}

?>