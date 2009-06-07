<?php

require_once BFOX_BIBLE_DIR . '/ref_content.php';

class BfoxPagePassage extends BfoxPage {

	const var_history_id = 'history_id';
	const var_page_num = 'page_num';

	/**
	 * The bible references being used
	 *
	 * @var BibleRefs
	 */
	protected $refs;

	protected $history;
	protected $history_id = '';

	protected $cboxes = array();

	public function __construct($ref_str, $trans_str = '') {
		$this->refs = new BibleRefs($ref_str);

		// Get the last viewed
		$this->history = BfoxHistory::get_history(1);
		if (!empty($this->history)) $last_viewed = current($this->history);

		if ($this->refs->is_valid()) {
			// If this isn't the same scripture we last viewed, update the read history to show that we viewed these scriptures
			if (empty($last_viewed) || ($this->refs->get_string() != $last_viewed->refs->get_string())) BfoxHistory::view_passage($this->refs);
		}
		else {
			// If we don't have a valid bible ref, we should use the history
			if (!empty($last_viewed)) $this->refs = $last_viewed->refs;
			// If there is no history, show Genesis 1
			else $this->refs = new BibleRefs('Genesis 1');
		}

		// Get the passage history
		$this->history = BfoxHistory::get_history(25);
		if (!empty($this->history)) $last_viewed = current($this->history);
		if (!empty($_REQUEST[self::var_history_id])) $this->history_id = $_REQUEST[self::var_history_id];
		else $this->history_id = self::history_id($last_viewed->time);

		parent::__construct($trans_str);
	}

	public function get_title() {
		return $this->refs->get_string();
	}

	public function get_search_str() {
		return $this->refs->get_string(BibleMeta::name_short);
	}

	private static function history_id($time) {
		return 'hist_' . strtotime($time);
	}

	private static function history_url($history_id, $page_num = 0) {
		$url = add_query_arg(self::var_history_id, $history_id, BfoxQuery::page_url(BfoxQuery::page_passage)) . "#$history_id";
		if (!empty($page_num)) $url = add_query_arg(self::var_page_num, $page_num, $url);
		return $url;
	}

	public function content() {
		$history_table = new BfoxHtmlTable("class='widefat'");
		$history_table->add_header_row('', 3, 'Passage', 'Time', 'Edit');
		foreach ($this->history as $history) {
			$ref_str = $history->refs->get_string();

			if ($history->is_read) {
				$intro = __('Read on');
				$toggle = __('Mark as Unread');
			}
			else {
				$intro = __('Viewed on');
				$toggle = __('Mark as Read');
			}

			$history_id = self::history_id($history->time);

			$row = new BfoxHtmlRow("id='$history_id'",
				"<a href='" . self::history_url($history_id) . "'>$ref_str</a>",
				"$intro $history->time",
				"<a href='" . BfoxQuery::toggle_read_url($history->time, BfoxQuery::page_url(BfoxQuery::page_passage)) . "'>" . $toggle . "</a>");

			$is_selected = ($this->history_id == $history_id);
			if ($is_selected) {
				ob_start();
				BfoxRefContent::ref_content_paged($history->refs, $this->translation, self::history_url($history_id), self::var_page_num, $this->page_num);
				$ref_content = ob_get_clean();
				$row->add_sub_row($ref_content);
			}

			$history_table->add_row($row);
		}

		$ref_str = $this->refs->get_string();

		echo $history_table->content();
	}
}

?>