<?php

// Try to get some search text
if (empty($search_text)) $search_text = (string) $_GET[Bible::var_search];

// See if we need to filter these search results by a bible reference
$refs_where = '';
if (!empty($_GET[Bible::var_reference]))
{
	$refs = RefManager::get_from_str($_GET[Bible::var_reference]);
	if ($refs->is_valid()) $ref_where = $refs->sql_where();
}

bfox_bible_text_search($search_text, $ref_where);

?>
