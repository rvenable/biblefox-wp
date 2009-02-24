<?php

global $bfox_trans, $bfox_links;

// Override the global translation using the translation passed in
// TODO3: Do we really need to override the global translation?
if (!empty($_GET[Bible::var_translation])) $bfox_trans = Translations::get_translation($_GET[Bible::var_translation]);

// Get the bible page to view
$page = $_GET[Bible::var_page];

// If there is search text, we should search
if (!empty($_GET[Bible::var_search])) $page = Bible::page_search;

if (Bible::page_search == $page)
{
	// Try to get some search text
	$search_text = (string) $_GET[Bible::var_search];

	// If there is no bible reference, see if this search string is actually a bible reference
	if (empty($_GET[Bible::var_reference]))
	{
		// If it is a valid bible reference, show the bible passage page instead of the search page
		$refs = RefManager::get_from_str($_GET[Bible::var_search]);
		if ($refs->is_valid()) $page = Bible::page_passage;
	}
}

?>

<div id="bible" class="">
	<div id="bible_bar" class="biblebox">
		<h3>Bible Viewer</h3>
		<div class="inside">
			<ul id="bible_page_list">
				<li><a href="<?php echo $bfox_links->bible_page_url(Bible::page_passage) ?>">Passage</a></li>
				<li><a href="<?php echo $bfox_links->bible_page_url(Bible::page_commentary) ?>">Commentaries</a></li>
				<li><a href="<?php echo $bfox_links->bible_page_url(Bible::page_history) ?>">History</a></li>
			</ul>
			<form id="bible_search_form" action="admin.php" method="get">
				<input type="hidden" name="page" value="<?php echo BFOX_BIBLE_SUBPAGE; ?>" />
				<input type="hidden" name="<?php echo Bible::var_page ?>" value="<?php echo Bible::page_search; ?>" />
				<input type="text" name="<?php echo Bible::var_search ?>" value="" />
				<input type="submit" value="<?php _e('Search Bible', BFOX_DOMAIN); ?>" class="button" />
			</form>
		</div>
	</div>
	<div id="bible_page">
	<?php
		switch ($page)
		{
			case Bible::page_search:
				include('bible-search.php');
				break;
			case Bible::page_commentary:
			case Bible::page_history:
			case Bible::page_passage:
			default:
				include('bible-passage.php');
		}
	?>
	</div>
</div>
