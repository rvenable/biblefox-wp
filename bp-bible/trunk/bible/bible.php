<?php

if (!defined(BFOX_BIBLE_DIR)) define(BFOX_BIBLE_DIR, dirname(__FILE__));

require_once BFOX_BIBLE_DIR . '/commentaries.php';
require_once BFOX_BIBLE_DIR . '/history.php';
require_once BFOX_BIBLE_DIR . '/page.php';
require_once BFOX_BIBLE_DIR . '/notes.php';

require_once BFOX_PLANS_DIR . '/plans.php';

class BfoxBible {

	const cookie_tz = 'bfox_timezone';

	const user_option_tz = 'bfox_timezone';
	const user_option_note_id = 'bfox_note_id';

	const var_note_submit = 'note_submit';
	const var_note_id = 'note_id';
	const var_note_content = 'note_content';

	private $page;

	public $refs;
	public $translation;
	public $search_str;
	public $history_event;
	public $search_query;

	public function __construct(BfoxRefs $refs, BfoxTrans $translation, $search_str = '') {
		$this->refs = $refs;
		$this->translation = $translation;
		$this->search_str = $search_str;

		if (empty($search_str)) {
			// Get the last viewed passage
			$history = BfoxHistory::get_history(1);
			$last_viewed = reset($history);

			// If this isn't the same scripture we last viewed, update the read history to show that we viewed these scriptures
			if (empty($last_viewed) || ($refs->get_string() != $last_viewed->refs->get_string())) {
				BfoxHistory::view_passage($refs);
				$history = BfoxHistory::get_history(1);
				$last_viewed = reset($history);
			}

			$this->history_event = $last_viewed;
			$this->search_query = $this->refs->get_string(BibleMeta::name_short);
		}
		else {
			$this->search_query = $search_str;

			// TODO3 (HACK): The strtolower is because bible groups need to be lowercase for some reason
			if ($this->refs->is_valid()) $this->search_query .= ' in:' . strtolower($this->refs->get_string(BibleMeta::name_short));
		}
	}

	public function page() {
		$this->page->page();
	}

	public function wp_title($title, $sep, $seplocation) {
		$title = $this->page->get_title();
		if ('right' == $seplocation) return "$title $sep";
		else return "$sep $title";
	}

	/**
	 * Extract Bible References from a search string
	 *
	 * @param $search_str
	 * @return array (ref_str, search_str)
	 */
	private static function extract_refs($search_str) {

		// First try to extract refs using the 'in:' keyword
		list($new_search, $ref_str) = preg_split('/\s*in\s*:\s*/i', $search_str, 2);
		if (!empty($ref_str)) {
			$new_search = trim($new_search);
			$ref_str = trim($ref_str);

			if (empty($new_search)) {
				$refs = self::parse_search_ref_str($ref_str);
				if ($refs->is_valid()) $ref_str = $refs->get_string();
			}
			else $search_str = $new_search;
		}
		// If there was no 'in:' keyword...
		else {
			// Parse out any references in the string, using level 2, no whole books, and save the leftovers
			$refs = new BfoxRefs;
			$data = new BfoxRefParserData($refs, 2, FALSE, FALSE, TRUE);
			BfoxRefParser::parse_string($search_str, $data);

			// If we found bible references
			if ($refs->is_valid()) {
				$ref_str = $refs->get_string();

				// The leftovers become the new search string
				$search_str = trim($data->leftovers);
			}
		}

		return array($ref_str, $search_str);
	}

	public static function parse_search_ref_str($str) {
		return BfoxRefParser::with_groups($str);
	}

	public static function edit_note_url($note_id, $url) {
		// TODO3: This is a temporary link without the # until AJAX updates are finished
		//return $this->cbox_url(add_query_arg(self::var_note_id, $note_id, $this->url));
		return add_query_arg(self::var_note_id, $note_id, $url);
	}

	public static function history() {
		global $user_ID;

		if (empty($user_ID)) $content = "<p>" . bp_bible_loginout() . __(' to track the Bible passages you read.</p>');
		else {
			$history = BfoxHistory::get_history(15);
			$list = new BfoxHtmlList();

			foreach ($history as $event) $list->add($event->ref_link());

			$content = $list->content();
		}

		return $content;
	}

	public static function get_plans() {
		global $user_ID;

		$plans = array();

		$subs = BfoxPlans::get_user_subs($user_ID, BfoxPlans::user_type_user);

		$plan_ids = array();
		foreach ($subs as $sub) if ($sub->is_subscribed && !$sub->is_finished) $plan_ids []= $sub->plan_id;

		if (!empty($plan_ids)) $plans = BfoxPlans::get_plans($plan_ids);

		$earliest = '';
		foreach($plans as $plan) {
			$start_time = $plan->start_date();
			if (empty($earliest) || ($start_time < $earliest)) $earliest = $start_time;
		}

		if (!empty($earliest)) {
			$history_array = BfoxHistory::get_history(0, $earliest, NULL, TRUE);
			foreach ($plans as &$plan) $plan->set_history($history_array);
		}

		return $plans;
	}

}

?>