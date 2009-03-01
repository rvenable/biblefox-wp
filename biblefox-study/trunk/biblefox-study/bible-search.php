<?php

class BibleSearch
{
	public $description;
	public $last_search_time;
	private $text, $words, $index_words;
	private $trans_where;
	private $ref_where;
	private $limit, $offset;

	public function __construct($text)
	{
		$this->set_text($text);
		$this->refs_where = '';
		$this->last_search_time = 0;
		$this->set_limit();
	}

	public function set_text($text)
	{
		$this->text = $text;
		$this->description = "\"$text\"";

		// Parse the search text into words
		$this->words = str_word_count($text, 1);
		$this->index_words = Translations::get_index_words($text);
	}

	public function set_search_translation_id($trans_id)
	{
		$this->trans_where =  $wpdb->prepare('AND trans_id = %d', $trans_id);
	}

	public function set_refs(BibleRefs $refs)
	{
		$this->ref_where = 'AND ' . $refs->sql_where();
		$this->description .= ' in ' . $refs->get_string();
	}

	public function set_limit($limit = 40, $offset = 0)
	{
		global $wpdb;
		$this->limit = $wpdb->prepare('LIMIT %d, %d', $offset, $limit);
	}

	/**
	 * Performs a boolean full text search
	 *
	 * @return unknown
	 */
	private function search($match)
	{
		global $wpdb;

		$start = microtime(TRUE);

		$verse_ids = $wpdb->get_results("
			SELECT unique_id, $match as match_val
			FROM " . Translations::index_table . "
			WHERE $match $this->trans_where $this->ref_where
			GROUP BY unique_id
			ORDER BY unique_id ASC
			$this->limit");

		$end = microtime(TRUE);
		$this->last_search_time = $end - $start;

		$verses = array();
		foreach ($verse_ids as $verse) $verses[$verse->unique_id] = $verse->match_val;

		return $verses;
	}

	private function book_counts($match)
	{
		global $wpdb;
		$counts = $wpdb->get_results("
			SELECT book_id, COUNT(DISTINCT unique_id) AS count
			FROM " . Translations::index_table . "
			WHERE $match $this->trans_where
			GROUP BY book_id");

		$book_counts = array();
		foreach ($counts as $count) $book_counts[$count->book_id] = $count->count;

		return $book_counts;
	}

	public function search_boolean()
	{
		global $wpdb;
		return $this->search($wpdb->prepare('MATCH(index_text) AGAINST(%s IN BOOLEAN MODE)', '+' . implode('* +', $this->index_words) . '*'));
	}

	/**
	 * Performs a boolean full text search, but returns results as a list of verse counts per book
	 *
	 * @param string $text
	 * @return array Book counts
	 */
	function boolean_book_counts()
	{
		global $wpdb;
		return $this->book_counts($wpdb->prepare('MATCH(index_text) AGAINST(%s IN BOOLEAN MODE)', '+' . implode('* +', $this->index_words) . '*'));
	}

	private function output_group_counts($group, $counts)
	{
		global $bfox_book_groups, $bfox_links;

		$count = 0;
		$content = '';
		foreach ($bfox_book_groups[$group] as $child)
		{
			$child_count = 0;
			$child_content = '';

			if (isset($bfox_book_groups[$child])) list($child_count, $child_content) = $this->output_group_counts($child, $counts);
			else if (isset($counts[$child]))
			{
				$child_count = $counts[$child];
				$child_content = $bfox_links->search_link($this->text, bfox_get_book_name($child)) . "<span class='book_count'>$child_count</span>";
			}

			if (0 < $child_count)
			{
				$count += $child_count;
				$content .= "<li>$child_content</li>";
			}
		}

		return array($count,
		"<span class='book_group_title'>
			" . $bfox_links->search_link($this->text, bfox_get_book_name($group), $group) . "
			<span class='book_count'>$count</span>
		</span>
		<ul class='book_group'>
			$content
		</ul>");
	}

	public function output_verse_map($book_counts, $group = 'protest')
	{
		list($count, $map) = $this->output_group_counts($group, $book_counts);

		?>
		<div class="verse_map_wrap">
			<div class="verse_map roundbox">
				<div class="box_head">Verse Map</div>
				<div class="box_inside">
					<?php echo $map ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Creates an output string with a table row for each verse in the $verses data
	 *
	 * @param array $verses results from get_results() select statement with verse data
	 * @param array $words the list of words to highlight as having been used in the search
	 * @return string
	 */
	function output_verses($verses)
	{
		$count = count($verses);
		if (0 < $count)
		{
			global $wpdb, $bfox_trans, $bfox_links;

			// Get the verse data for these verses (from the global bible translation)
			$queries = array();
			foreach ($verses as $unique_id => $match) $queries []= $wpdb->prepare('unique_id = %d', $unique_id);
			$verses = $wpdb->get_results("SELECT * FROM $bfox_trans->table WHERE " . implode(' OR ', $queries));
			unset($queries);

			// Turn the words into keys
			$words = array_fill_keys($this->words, TRUE);

			$book = 0;
			$chapter = 0;
			$chapter_content = array();

			foreach ($verses as $verse)
			{
				if (($book != $verse->book_id) || ($chapter != $verse->chapter_id))
				{
					$book = $verse->book_id;
					$chapter = $verse->chapter_id;

					$book_name = bfox_get_book_name($book);
					$chap_name = "$book_name $chapter";
				}

				// TODO3: Find a good way to display footnotes in search (until then, just get rid of them)
				$verse->verse = preg_replace('/<footnote>.*<\/footnote>/Ui', '', $verse->verse);

				// Get the words in the verse as an associative array (use '_' as a part of a word)
				$verse_words = str_word_count($verse->verse, 2, '_');

				// For each word in the verse that is also a search word, bold it
				foreach (array_reverse($verse_words, TRUE) as $pos => $verse_word)
					if ($words[strtolower($verse_word)])
						$verse->verse = substr_replace($verse->verse, "<strong>$verse_word</strong>", $pos, strlen($verse_word));

				$ref_str = "$chap_name:$verse->verse_id";
				$link = $bfox_links->ref_link($ref_str);

				$chapter_content[$chap_name] .= "<div class='result_verse'><h4>$link</h4>$verse->verse</div>";
			}
		}

		$content = '';
		foreach ($chapter_content as $chap_name => $chap_content)
		{
			$link = $bfox_links->ref_link($chap_name);
			$content .=
			"<div class='result_chapter'>
			<h3>$link</h3>
			$chap_content
			</div>
			";
		}

		return $content;
	}

}

// Try to get some search text
if (empty($search_text)) $search_text = (string) $_GET[Bible::var_search];

$search = new BibleSearch($search_text);

// See if we need to filter these search results by a bible reference
$refs_where = '';
if (!empty($_GET[Bible::var_reference]))
{
	$refs = RefManager::get_from_str($_GET[Bible::var_reference]);
	if ($refs->is_valid())
		$search->set_refs($refs);
}

/* TODO2: Implement the search suggestions
$index_text = implode(' ', $index_words);

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

$book_counts = $search->boolean_book_counts();

// Show the exact matches at the bottom
?>
<div id="bible_search">
	<h3>Match All Words - <?php echo $search->description ?></h3>
	Note: Biblefox searches all available bible translations at once, and displays the results in your preferred translation, so the search words may not appear in all displayed results.
	<div id="match_all">
		<?php echo $search->output_verse_map($book_counts) ?>
		<div class="results_wrap">
			<div class="results roundbox">
				<div class="box_head">Search Results</div>
				<?php
					$verses = $search->search_boolean();
					echo $search->output_verses($verses);
				?>
				<div class="box_menu">This search took <?php echo $search->last_search_time ?> seconds</div>
			</div>
		</div>
		<div class="clear"></div>
	</div>
</div>
