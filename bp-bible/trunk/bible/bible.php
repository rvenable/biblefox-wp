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

	public function old__construct($query_str = '') {
		global $user_ID;

		$redirect = FALSE;

		// Register the bible jquery scripts and styles
		BfoxUtility::register_script('bfox_jquery', 'bible/jquery/js/jquery-1.3.2.min.js');
		BfoxUtility::register_script('bfox_jquery_ui', 'bible/jquery/js/jquery-ui-1.7.2.custom.min.js', array('bfox_jquery'));
		BfoxUtility::register_style('bfox_jquery_ui', 'bible/jquery/css/overcast/jquery-ui-1.7.2.custom.css');

		// Register all the bible scripts and styles
		BfoxUtility::register_script('jquery_cookie', 'bible/jquery.cookie.js', array('bfox_jquery'));
		BfoxUtility::register_script('bfox_bible', 'bible/bible.js', array('bfox_jquery', 'bfox_jquery_ui', 'jquery_cookie'));
		BfoxUtility::register_style('bfox_bible', 'bible/bible.css', array('bfox_scripture', 'bfox_jquery_ui'));
		BfoxUtility::register_style('bfox_search', 'bible/search.css', array('bfox_bible'));

		Biblefox::set_default_ref_url(Biblefox::ref_url_bible);

		// Create an array of all the query args
		$q = array();
		$requests = array(BfoxQuery::var_page, BfoxQuery::var_reference, BfoxQuery::var_search, BfoxQuery::var_translation, BfoxQuery::var_toggle_read);
		foreach ($requests as $var) if (isset($_REQUEST[$var])) $q[$var] = $_REQUEST[$var];

		$query_str = trim(trim($query_str), '/');
		if (!empty($query_str)) {
			$vars = explode('/', $query_str);
			if (1 < count($vars)) list($q[BfoxQuery::var_translation], $q[BfoxQuery::var_reference]) = $vars;
			else list($q[BfoxQuery::var_reference]) = $vars;
		}

		// Empty pages need to go to the passage page (we need to do this here before we check for valid guest pages)
		if (empty($q[BfoxQuery::var_page])) $q[BfoxQuery::var_page] = BfoxQuery::page_passage;

		// If there is no user, we should only allow certain pages
		if (empty($user_ID)) {
			$valid_guest_pages = array(BfoxQuery::page_passage => TRUE, BfoxQuery::page_search => TRUE);
			if (!$valid_guest_pages[$q[BfoxQuery::var_page]]) auth_redirect();
		}
		// Perform any user-specific updates
		else {
			// Update the user's timezone from their cookies
			if (isset($_COOKIE[self::cookie_tz])) update_user_option($user_ID, self::user_option_tz, $_COOKIE[self::cookie_tz], TRUE);
			BfoxUtility::set_timezone_offset(get_user_option(self::user_option_tz));

			// If we are toggling is_read, then we should do it now, and redirect without the parameter
			if (!empty($q[BfoxQuery::var_toggle_read])) {
				BfoxHistory::toggle_is_read($q[BfoxQuery::var_toggle_read]);
				unset($q[BfoxQuery::var_toggle_read]);
				$redirect = TRUE;
			}

			// Save any notes
			if (isset($_REQUEST[self::var_note_id])) {
				$note_id = $_REQUEST[self::var_note_id];

				if (isset($_POST[self::var_note_submit])) {
					$note = BfoxNotes::get_note($note_id);
					$note->set_content(strip_tags(stripslashes($_POST[self::var_note_content])));
					BfoxNotes::save_note($note);
					$note_id = $note->id;
				}

				// Save the note_id as a user option
				update_user_option($user_ID, self::user_option_note_id, $note_id, TRUE);

				// Redirect without the note info
				$redirect = TRUE;
			}
		}

		// If we have a search string but no ref_str, we should try to extract refs from the search string
		if ((BfoxQuery::page_search == $q[BfoxQuery::var_page]) && empty($q[BfoxQuery::var_reference])) {
			list($q[BfoxQuery::var_reference], $q[BfoxQuery::var_search]) = self::extract_refs($q[BfoxQuery::var_search]);
			if (empty($q[BfoxQuery::var_search])) {
				unset($q[BfoxQuery::var_page]);
				$redirect = TRUE;
			}
		}

		// Get any passed in translations, save them, and redirect without them
		if (!empty($q[BfoxQuery::var_translation])) {
			BiblefoxMainBlog::set_trans_id($q[BfoxQuery::var_translation]);
			unset($q[BfoxQuery::var_translation]);
			$redirect = TRUE;
		}

		switch ($q[BfoxQuery::var_page]) {

			default:
			case BfoxQuery::page_passage:
				$refs = new BfoxRefs($q[BfoxQuery::var_reference]);

				// Get the last viewed passage
				$history = BfoxHistory::get_history(1);
				$last_viewed = reset($history);

				if ($refs->is_valid()) {
					require BFOX_BIBLE_DIR . '/page_passage.php';
					$this->page = new BfoxPagePassage($refs, new BfoxTrans(BiblefoxMainBlog::get_trans_id()), $last_viewed);
				}
				else {
					// If we don't have a valid bible ref, we should use the history
					if (!empty($last_viewed)) $refs = $last_viewed->refs;

					$redirect = TRUE;
					if ($refs->is_valid()) $q[BfoxQuery::var_reference] = $refs->get_string();
					else $q[BfoxQuery::var_reference] = 'Genesis 1';
				}
				break;

			case BfoxQuery::page_search:
				require BFOX_BIBLE_DIR . '/page_search.php';
				$this->page = new BfoxPageSearch($q[BfoxQuery::var_search], $q[BfoxQuery::var_reference], new BfoxTrans(BiblefoxMainBlog::get_trans_id()));
				break;
		}

		if ($redirect) wp_redirect(BfoxQuery::url($q));

		add_filter('wp_title', array(&$this, 'wp_title'), 10, 3);
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

		if (empty($user_ID)) $content = "<p>" . BiblefoxSite::loginout() . __(' to track the Bible passages you read.</p>');
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

	public static function readings() {
		global $user_ID;

		$plans = array();
		if (!empty($user_ID)) {
			$plans = self::get_plans();

			if (empty($plans)) $content = __('<p>You are not subscribed to any reading plans.</p>');
			else {
				$list = new BfoxHtmlList();

				foreach ($plans as $plan) if ($plan->is_current()) {
					// Show any unread readings before the current reading
					// And any readings between the current reading and the first unread reading after it
					foreach ($plan->readings as $reading_id => $reading) {
						$unread = $plan->get_unread($reading);
						$is_unread = $unread->is_valid();

						// If the passage is unread or current, add it
						if ($is_unread || ($reading_id >= $plan->current_reading_id)) {
							$ref_str = $plan->readings[$reading_id]->get_string();
							$url = Biblefox::ref_url($ref_str);

							if (!$is_unread) $finished = " class='finished'";
							else $finished = '';

							$list->add(BfoxUtility::nice_date($plan->time($reading_id)) . ": <a href='$url'$finished>$ref_str</a>", '', $plan->date($reading_id));
						}
						// Break after the first unread reading > current_reading
						if ($is_unread && ($reading_id > $plan->current_reading_id)) break;
					}
				}

				$content = $list->content(TRUE);
			}

			$content .= "<p><a href='" . BfoxQuery::page_url(BfoxQuery::page_plans) . "'>" . __('Edit Reading Plans') . "</a></p>";
		}
		else $content = "<p>" . BiblefoxSite::loginout() . __(' to see the current readings for your reading plans.</p>');


		return $content;
	}

	public static function sidebar() {
		?>
		<li>
			<h2><a href='<? echo BfoxQuery::page_url(BfoxQuery::page_plans) ?>'><?php _e('Current Readings') ?></a></h2>
			<?php echo self::readings() ?>
		</li>
		<li>
			<h2><?php _e('Recent History') ?></h2>
			<?php echo self::history() ?>
		</li>
		<?php
	}
}

?>