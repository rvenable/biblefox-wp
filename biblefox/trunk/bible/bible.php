<?php

define(BFOX_BIBLE_DIR, dirname(__FILE__));

require_once BFOX_BIBLE_DIR . '/quicknote.php';
require_once BFOX_BIBLE_DIR . '/commentaries.php';
require_once BFOX_BIBLE_DIR . '/history.php';
require_once BFOX_BIBLE_DIR . '/page.php';
require_once BFOX_BIBLE_DIR . '/notes.php';

require_once BFOX_PLANS_DIR . '/plans.php';

class BfoxBible {

	const cookie_translation = 'trans_str';

	private $page;

	public function __construct($query_str = '') {

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

		$page_name = $_REQUEST[BfoxQuery::var_page];
		$trans_str = $_REQUEST[BfoxQuery::var_translation];
		$ref_str = $_REQUEST[BfoxQuery::var_reference];
		$search_str = $_REQUEST[BfoxQuery::var_search];

		if (!empty($query_str)) {
			$vars = explode('/', $query_str);
			if (1 < count($vars)) list($trans_str, $ref_str) = $vars;
			else list($ref_str) = $vars;
		}


		// Cookied Translations:
		// If we were passed a translation, use it and save the cookie
		// Otherwise, if we have a cookied translation, use it
		// Otherwise use the default translation
		if (!empty($trans_str)) {
			$translation = new Translation($trans_str);
			setcookie(self::cookie_translation, $translation->id, /*30 days from now: */ time() * 60 * 60 * 24 * 30);
		}
		elseif (!empty($_COOKIE[self::cookie_translation])) $translation = new Translation($_COOKIE[self::cookie_translation]);
		else $translation = new Translation();

		// If we are toggling is_read, then we should do it now, and redirect without the parameter
		if (!empty($_REQUEST[BfoxQuery::var_toggle_read])) {
			BfoxHistory::toggle_is_read($_REQUEST[BfoxQuery::var_toggle_read]);
			wp_redirect(remove_query_arg(array(BfoxQuery::var_toggle_read), $_SERVER['REQUEST_URI']));
		}

		// If no page was specified, use the passage page
		if (empty($page_name)) $page_name = BfoxQuery::page_passage;
		// If we have a search string but no ref_str, we should try to extract refs from the search string
		elseif ((BfoxQuery::page_search == $page_name) && empty($ref_str)) {
			list($ref_str, $search_str) = self::extract_refs($search_str);
			if (empty($search_str)) wp_redirect(BfoxQuery::passage_page_url($ref_str));
		}

		switch ($page_name) {

			default:
			case BfoxQuery::page_reader:
				require BFOX_BIBLE_DIR . '/page_reader.php';
				$this->page = new BfoxPageReader($translation);
				break;

			case BfoxQuery::page_passage:
				require BFOX_BIBLE_DIR . '/page_passage.php';
				$this->page = new BfoxPagePassage($ref_str, $translation);
				break;

			case BfoxQuery::page_search:
				require BFOX_BIBLE_DIR . '/page_search.php';
				$this->page = new BfoxPageSearch($search_str, $ref_str, $translation);
				break;

			case BfoxQuery::page_commentary:
				require BFOX_BIBLE_DIR . '/page_commentaries.php';
				$this->page = new BfoxPageCommentaries($translation);
				break;

			case BfoxQuery::page_plans:
				require BFOX_BIBLE_DIR . '/page_plans.php';
				$this->page = new BfoxPagePlans($translation);
				break;

			case BfoxQuery::page_history:
				require BFOX_BIBLE_DIR . '/page_history.php';
				$this->page = new BfoxPageHistory($translation);
				break;
		}

		// TODO3: page_load might as well be called by the page constructor
		$this->page->page_load();

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
				if ($refs->is_valid()) {
					$page_name = BfoxQuery::page_passage;
					$ref_str = $refs->get_string();
				}
			}
			else $search_str = $new_search;
		}
		// If there was no 'in:' keyword...
		else {
			// Parse out any references in the string, using level 2, no whole books, and save the leftovers
			$refs = new BibleRefs;
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
}

?>