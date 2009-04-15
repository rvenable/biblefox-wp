<?php

require_once BFOX_BIBLE_DIR . '/quicknote.php';
require_once BFOX_BIBLE_DIR . '/commentaries.php';
require_once BFOX_BIBLE_DIR . '/bible.php';

Bible::set_url();

global $bfox_bible_page, $bfox_trans;

// Override the global translation using the translation passed in
// TODO3: Do we really need to override the global translation?
if (!empty($_GET[Bible::var_translation])) $translation = Translations::get_translation($_GET[Bible::var_translation]);
else $translation = $bfox_trans;

// Get the bible page to view
$page_name = $_GET[Bible::var_page];

// If there is search text, we should search
if (!empty($_GET[Bible::var_search])) $page_name = Bible::page_search;

$refs = new BibleRefs();

if (Bible::page_search == $page_name)
{
	// Try to get some search text
	$search_text = (string) $_GET[Bible::var_search];

	// If there is no bible reference, see if this search string is actually a bible reference
	if (empty($_GET[Bible::var_reference]))
	{
		// If it is a valid bible reference, show the bible passage page instead of the search page
		$refs = RefManager::get_from_str($_GET[Bible::var_search]);
		if ($refs->is_valid()) $page_name = Bible::page_passage;
	}
}

switch ($page_name)
{
	case Bible::page_passage:
		require BFOX_BIBLE_DIR . '/page_passage.php';
		$bfox_bible_page = new BfoxPagePassage($refs, $translation);
		break;
	case Bible::page_search:
		require BFOX_BIBLE_DIR . '/page_search.php';
		$bfox_bible_page = new BfoxPageSearch($search_text, $refs, $translation);
		break;
		/*
	case Bible::page_commentary:
		Commentaries::manage_page_load();
		break;
		*/
}

?>