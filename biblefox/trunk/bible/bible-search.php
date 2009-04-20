<?php

class BibleSearch
{
	public $text, $description;
	public $last_search_time;
	public $ref_str;
	private $words, $index_words;
	private $trans_where;
	private $ref_where;
	private $limit, $offset;

	/**
	 * The bible translation to display the verses in
	 *
	 * @var Translation
	 */
	private $display_translation;

	public function __construct($text, Translation $translation)
	{
		$this->set_text($text);
		$this->display_translation = $translation;
		$this->ref_str = '';
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
		if ($refs->is_valid())
		{
			$this->ref_str = $refs->get_string();
			$this->ref_where = 'AND ' . $refs->sql_where();
			$this->description .= ' in ' . $this->ref_str;
		}
	}

	public function set_limit($limit = 40, $offset = 0)
	{
		global $wpdb;
		$this->limit = $wpdb->prepare('LIMIT %d, %d', $offset, $limit);
	}

	private function link($search, $display_ref_str = '', $actual_ref_str = '')
	{
		if (empty($actual_ref_str)) $actual_ref_str = $display_ref_str;
		if (!empty($display_ref_str)) $ref_title = " in $display_ref_str";

		return "<a href='" . BfoxQuery::search_page_url($search, $actual_ref_str, $this->display_translation) . "' title='Search for \"$search\"$ref_title'>$display_ref_str</a>";
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
		$count = 0;
		$content = '';
		foreach (BibleMeta::$book_groups[$group] as $child)
		{
			$child_count = 0;
			$child_content = '';

			if (isset(BibleMeta::$book_groups[$child])) list($child_count, $child_content) = $this->output_group_counts($child, $counts);
			else if (isset($counts[$child]))
			{
				$child_count = $counts[$child];
				$child_content = $this->link($this->text, BibleMeta::get_book_name($child)) . "<span class='book_count'>$child_count</span>";
			}

			if (0 < $child_count)
			{
				$count += $child_count;
				$content .= "<li>$child_content</li>";
			}
		}

		return array($count,
		"<span class='book_group_title'>
			" . $this->link($this->text, BibleMeta::get_book_name($group), $group) . "
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
				<div id="verse_map_list">
					<ul class='book_group'>
						<li>
							<?php echo $map ?>
						</li>
					</ul>
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
			global $wpdb;

			// Get the verse data for these verses (from the global bible translation)
			$queries = array();
			foreach ($verses as $unique_id => $match) $queries []= $wpdb->prepare('unique_id = %d', $unique_id);
			$verses = $wpdb->get_results("SELECT * FROM {$this->display_translation->table} WHERE " . implode(' OR ', $queries));
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

					$book_name = BibleMeta::get_book_name($book);
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
				$chapter_content[$chap_name] .= "<div class='result_verse'><h4><a href='" . BfoxQuery::passage_page_url($ref_str, $this->display_translation) . "'>$ref_str</a></h4>$verse->verse</div>";
			}

			$content = '';
			foreach ($chapter_content as $chap_name => $chap_content)
			{
				$content .=
				"<div class='result_chapter'>
				<h3><a href='" . BfoxQuery::passage_page_url($chap_name, $this->display_translation) . "'>$chap_name</a></h3>
				$chap_content
				</div>
				";
			}
		}
		else $content = '<div class="box_inside">No verses match your search</div>';

		return $content;
	}
}

?>