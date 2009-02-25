<?php

// Try to get some search text
if (empty($search_text)) $search_text = (string) $_GET[Bible::var_search];

$search_desc = "\"$search_text\"";

// See if we need to filter these search results by a bible reference
$refs_where = '';
if (!empty($_GET[Bible::var_reference]))
{
	$refs = RefManager::get_from_str($_GET[Bible::var_reference]);
	if ($refs->is_valid())
	{
		$ref_where = $refs->sql_where();
		$search_desc .= ' in ' . $refs->get_string();
	}
}

// Parse the search text into words
$words = str_word_count($search_text, 1);

$index_words = Translations::get_index_words($search_text);
$index_text = implode(' ', $index_words);
$match_all_text = '+' . implode('* +', $index_words) . '*';
$book_counts = bfox_search_boolean_books($match_all_text);
$book_count = $book_counts['all'];

/* TODO2: Implement the search suggestions
// We can only make phrase suggestions when there are long words
if (0 < count($index_words))
{
	$sugg_limit = 10;
	$sugg_count = 0;
	if ((1 < count($index_words)) || (0 == $book_count))
	{
		$exact = bfox_search_boolean('"' . $index_text . '"', $ref_where, $sugg_limit - $sugg_count);
		$sugg_count += count($exact);
	}

	if (0 < $sugg_limit - $sugg_count)
	{
		$specific = bfox_search_regular($index_text, $sugg_limit - $sugg_count);
		$sugg_count += count($specific);
	}

	if (0 < $sugg_limit - $sugg_count)
	{
		$other = bfox_search_expanded($index_text, $sugg_limit - $sugg_count);
		$sugg_count += count($other);
	}

	if (0 < $sugg_count)
	{
		$content .= "<h3>Suggestions - $search_text</h3>";
		$content .= '<table>';

		$content .= bfox_output_verses($exact, $words, 'Exact Matches');
		$content .= bfox_output_verses($specific, $words, 'Specific Suggestions');
		$content .= bfox_output_verses($other, $words, 'Other Suggestions');

		$content .= '</table>';
	}
}
*/

// Show the exact matches at the bottom
list($count, $map) = bfox_output_bible_group_counts('protest', $book_counts, $search_text);
?>
<div id="bible_search">
	<h3>Match All Words - <?php echo $search_desc ?></h3>
	<div id="match_all">
		<div class="verse_map_wrap">
			<div class="verse_map biblebox">
				<div class="head">Verse Map</div>
				<div class="inside">
					<?php echo $map ?>
				</div>
			</div>
		</div>
		<div class="results_wrap">
			<div class="results biblebox">
				<div class="head">Search Results</div>
				<?php
					$content .= '<div id="bible_search_results">';
					$content .= '<table>';
					$start = microtime(TRUE);
					$content .= bfox_output_verses(bfox_search_boolean($match_all_text, $ref_where), $words);
					$end = microtime(TRUE);
					$content .= '</table>';
					$content .= '</div>';
					echo $content;
				?>
				<div class="menu">This search took <?php echo($end - $start) ?> seconds</div>
			</div>
		</div>
		<div class="clear"></div>
	</div>
</div>
