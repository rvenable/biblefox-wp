<?php

require_once BFOX_BIBLE_DIR . '/bible-search.php';
require_once BFOX_BIBLE_DIR . '/refpage.php';

class BfoxPageSearch extends BfoxRefPage
{
	/**
	 * Enter description here...
	 *
	 * @var string
	 */
	private $search_text;

	public function __construct($search_text, BibleRefs $refs, Translation $translation)
	{
		$this->search_text = $search_text;
		parent::__construct($refs, $translation);
	}

	public function content()
	{
		$search_text = $this->search_text;

		// Try to get some search text
		if (empty($search_text)) $search_text = (string) $_GET[BfoxQuery::var_search];

		$search = new BibleSearch($search_text, $this->translation);

		// See if we need to filter these search results by a bible reference
		$refs_where = '';
		if (!empty($_GET[BfoxQuery::var_reference]))
		{
			$refs = RefManager::get_from_str($_GET[BfoxQuery::var_reference]);
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
						<div class="box_head">Search Results
							<form id="bible_view_search" action="admin.php" method="get">
								Display as:
								<input type="hidden" name="page" value="<?php echo BFOX_BIBLE_SUBPAGE ?>" />
								<input type="hidden" name="<?php echo BfoxQuery::var_page ?>" value="<?php echo BfoxQuery::page_search ?>" />
								<input type="hidden" name="<?php echo BfoxQuery::var_search ?>" value="<?php echo $search->text ?>" />
								<?php if (!empty($search->ref_str)): ?>
								<input type="hidden" name="<?php echo BfoxQuery::var_reference ?>" value="<?php echo $search->ref_str ?>" />
								<?php endif; ?>
								<?php Translations::output_select($this->translation->id) ?>
								<input type="submit" value="Go" class="button">
							</form>
						</div>
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
		<?php
	}
}

?>