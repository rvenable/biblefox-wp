<?php

define(BFOX_BIBLE_DIR, dirname(__FILE__));

require_once BFOX_BIBLE_DIR . '/commentaries.php';
require_once BFOX_BIBLE_DIR . '/history.php';
require_once BFOX_BIBLE_DIR . '/page.php';
require_once BFOX_BIBLE_DIR . '/notes.php';

require_once BFOX_PLANS_DIR . '/plans.php';

class BfoxBible {

	const cookie_translation = 'bfox_trans_str';
	const cookie_note_id = 'bfox_note_id';
	const cookie_tz = 'bfox_timezone';
	const user_option_tz = 'bfox_timezone';

	const var_note_submit = 'note_submit';
	const var_note_id = 'note_id';
	const var_note_content = 'note_content';

	private $page;

	public function __construct($query_str = '') {

		$redirect = FALSE;

		// Register the bible jquery scripts and styles
		BfoxUtility::register_script('bfox_jquery', 'bible/jquery/js/jquery-1.3.2.min.js');
		BfoxUtility::register_script('bfox_jquery_ui', 'bible/jquery/js/jquery-ui-1.7.2.custom.min.js', array('bfox_jquery'));
		BfoxUtility::register_style('bfox_jquery_ui', 'bible/jquery/css/cupertino/jquery-ui-1.7.2.custom.css');

		// Register all the bible scripts and styles
		BfoxUtility::register_script('jquery_cookie', 'bible/jquery.cookie.js', array('bfox_jquery'));
		BfoxUtility::register_script('bfox_bible', 'bible/bible.js', array('bfox_jquery', 'bfox_jquery_ui', 'jquery_cookie'));
		BfoxUtility::register_style('bfox_bible', 'bible/bible.css', array('bfox_scripture', 'bfox_jquery_ui'));
		BfoxUtility::register_style('bfox_search', 'bible/search.css', array('bfox_bible'));

		Biblefox::set_default_ref_url(Biblefox::ref_url_bible);

		// Update the user's timezone from their cookies
		if (isset($_COOKIE[self::cookie_tz])) update_user_option($GLOBALS['user_ID'], self::user_option_tz, $_COOKIE[self::cookie_tz], TRUE);
		BfoxUtility::set_timezone_offset(get_user_option(self::user_option_tz));

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

		// If we are toggling is_read, then we should do it now, and redirect without the parameter
		if (!empty($q[BfoxQuery::var_toggle_read])) {
			BfoxHistory::toggle_is_read($q[BfoxQuery::var_toggle_read]);
			unset($q[BfoxQuery::var_toggle_read]);
			$redirect = TRUE;
		}

		// Save any notes
		if (isset($_REQUEST[self::var_note_id])) {
			$note_id = $_REQUEST[self::var_note_id];
			setcookie(self::cookie_note_id, $note_id, /* 30 days from now: */ time() + 60 * 60 * 24 * 30);

			if (isset($_POST[self::var_note_submit])) {
				$note = BfoxNotes::get_note($note_id);
				$note->set_content(strip_tags(stripslashes($_POST[self::var_note_content])));
				BfoxNotes::save_note($note);
			}

			// Redirect without the note info
			$redirect = TRUE;
		}

		// If we have a search string but no ref_str, we should try to extract refs from the search string
		if ((BfoxQuery::page_search == $q[BfoxQuery::var_page]) && empty($q[BfoxQuery::var_reference])) {
			list($q[BfoxQuery::var_reference], $q[BfoxQuery::var_search]) = self::extract_refs($q[BfoxQuery::var_search]);
			if (empty($q[BfoxQuery::var_search])) $redirect = TRUE;
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
					$this->page = new BfoxPagePassage($refs, self::get_input_trans($q), $last_viewed);
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
				$this->page = new BfoxPageSearch($q[BfoxQuery::var_search], $q[BfoxQuery::var_reference], self::get_input_trans($q));
				break;

			case BfoxQuery::page_commentary:
				require BFOX_BIBLE_DIR . '/page_commentaries.php';
				$this->page = new BfoxPageCommentaries();
				break;

			case BfoxQuery::page_plans:
				require BFOX_BIBLE_DIR . '/page_plans.php';
				$this->page = new BfoxPagePlans();
				break;
		}

		if ($redirect) wp_redirect(BfoxQuery::url($q));

		add_filter('wp_title', array(&$this, 'wp_title'), 10, 3);
	}

	public static function get_input_trans($q) {
		// Cookied Translations:
		// If we were passed a translation, use it and save the cookie
		// Otherwise, if we have a cookied translation, use it
		// Otherwise use the default translation
		if (!empty($q[BfoxQuery::var_translation])) {
			$translation = new BfoxTrans($q[BfoxQuery::var_translation]);
			setcookie(self::cookie_translation, $translation->id, /* 365 days from now: */ time() + 60 * 60 * 24 * 365);
		}
		elseif (!empty($_COOKIE[self::cookie_translation])) $translation = new BfoxTrans($_COOKIE[self::cookie_translation]);
		else $translation = new BfoxTrans();

		return $translation;
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
}

?>