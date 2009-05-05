<?php

require_once BFOX_BIBLE_DIR . '/quicknote.php';
require_once BFOX_BIBLE_DIR . '/commentaries.php';
require_once BFOX_BIBLE_DIR . '/page.php';

$search_str = $_REQUEST[BfoxQuery::var_search];
$ref_str = $_REQUEST[BfoxQuery::var_reference];
$trans_str = $_REQUEST[BfoxQuery::var_translation];

// Get the bible page to view
$page_name = $_REQUEST[BfoxQuery::var_page];

if (empty($page_name)) $page_name = BfoxQuery::page_passage;
elseif ((BfoxQuery::page_search == $page_name) && empty($ref_str))
{
	// See if the search is really a passage
	$refs = RefManager::get_from_str($search_str);
	if ($refs->is_valid())
	{
		$page_name = BfoxQuery::page_passage;
		$ref_str = $refs->get_string();
	}
}

global $bfox_bible_page;

switch ($page_name)
{
	case BfoxQuery::page_search:
		require BFOX_BIBLE_DIR . '/page_search.php';
		$bfox_bible_page = new BfoxPageSearch($search_str, $ref_str, $trans_str);
		break;

	case BfoxQuery::page_commentary:
		require BFOX_BIBLE_DIR . '/page_commentaries.php';
		$bfox_bible_page = new BfoxPageCommentaries();
		break;

	case BfoxQuery::page_plans:
		require BFOX_BIBLE_DIR . '/page_plans.php';
		$bfox_bible_page = new BfoxPagePlans();
		break;

	case BfoxQuery::page_passage:
	default:
		require BFOX_BIBLE_DIR . '/page_passage.php';
		$bfox_bible_page = new BfoxPagePassage($ref_str, $trans_str);
		break;
}

$bfox_bible_page->page_load();

?>