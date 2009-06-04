<?php

define(BFOX_BIBLE_DIR, dirname(__FILE__));

require_once BFOX_BIBLE_DIR . '/quicknote.php';
require_once BFOX_BIBLE_DIR . '/commentaries.php';
require_once BFOX_BIBLE_DIR . '/history.php';
require_once BFOX_BIBLE_DIR . '/page.php';
require_once BFOX_BIBLE_DIR . '/notes.php';

require_once BFOX_PLANS_DIR . '/plans.php';

class BfoxBible {

	private $page;

	public function __construct() {

		// Register all the bible scripts and styles
		BfoxUtility::register_script('jquery_cookie', 'bible/jquery.cookie.js', array('jquery'));
		BfoxUtility::register_script('bfox_bible', 'bible/bible.js', array('jquery', 'jquery_cookie'));
		BfoxUtility::register_style('bfox_bible', 'bible/bible.css', array('bfox_scripture'));
		BfoxUtility::register_style('bfox_search', 'bible/search.css', array('bfox_bible'));

		Biblefox::set_default_ref_url(Biblefox::ref_url_bible);


		$search_str = $_REQUEST[BfoxQuery::var_search];
		$ref_str = $_REQUEST[BfoxQuery::var_reference];
		$trans_str = $_REQUEST[BfoxQuery::var_translation];
		$toggle_read_time = $_REQUEST[BfoxQuery::var_toggle_read];

		// If we are toggling is_read, then we should do it now, and redirect without the parameter
		if (!empty($toggle_read_time)) {
			BfoxHistory::toggle_is_read($toggle_read_time);
			wp_redirect(remove_query_arg(array(BfoxQuery::var_toggle_read), $_SERVER['REQUEST_URI']));
		}

		// Get the bible page to view
		$page_name = $_REQUEST[BfoxQuery::var_page];

		if (empty($page_name)) $page_name = BfoxQuery::page_passage;
		elseif ((BfoxQuery::page_search == $page_name) && empty($ref_str)) {
			list($new_search, $ref_str) = preg_split('/\s*in\s*:\s*/i', $search_str, 2);

			if (!empty($ref_str)) {
				$new_search = trim($new_search);
				$ref_str = trim($ref_str);

				if (empty($new_search)) {
					$refs = BfoxRefParser::bible_search($ref_str);
					if ($refs->is_valid()) {
						$page_name = BfoxQuery::page_passage;
						$ref_str = $refs->get_string();
					}
				}
				else $search_str = $new_search;
			}
			else {
				// See if the search is really a passage
				list($refs, $leftovers) = BfoxRefParser::bible_search_leftovers($search_str);
				if ($refs->is_valid()) {
					$search_str = trim($leftovers);
					$ref_str = $refs->get_string();

					if (empty($search_str)) $page_name = BfoxQuery::page_passage;
				}
			}
		}

		switch ($page_name) {

			case BfoxQuery::page_search:
				require BFOX_BIBLE_DIR . '/page_search.php';
				$this->page = new BfoxPageSearch($search_str, $ref_str, $trans_str);
				break;

			case BfoxQuery::page_commentary:
				require BFOX_BIBLE_DIR . '/page_commentaries.php';
				$this->page = new BfoxPageCommentaries();
				break;

			case BfoxQuery::page_plans:
				require BFOX_BIBLE_DIR . '/page_plans.php';
				$this->page = new BfoxPagePlans();
				break;

			case BfoxQuery::page_history:
				require BFOX_BIBLE_DIR . '/page_history.php';
				$this->page = new BfoxPageHistory();
				break;

			case BfoxQuery::page_passage:
			default:
				require BFOX_BIBLE_DIR . '/page_passage.php';
				$this->page = new BfoxPagePassage($ref_str, $trans_str);
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
}

?>