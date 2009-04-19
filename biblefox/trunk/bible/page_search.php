<?php

require_once BFOX_BIBLE_DIR . '/bible-search.php';

class BfoxPageSearch extends BfoxPage
{
	/**
	 * Instance of BibleSearch for all the search functionality
	 *
	 * @var BibleSearch
	 */
	protected $search;

	public function __construct($search_str, $ref_str = '', $trans_str = '')
	{
		parent::__construct($trans_str);

		$this->search = new BibleSearch($search_str, $this->translation);

		// See if we need to filter these search results by a bible reference
		if (!empty($ref_str)) $this->search->set_refs(RefManager::get_from_str($ref_str));
	}

	public function get_search_str()
	{
		return $this->search->text;
	}

	public function content()
	{
		$book_counts = $this->search->boolean_book_counts();

		// Show the exact matches at the bottom
		?>
		<div id="bible_search">
			<h3>Match All Words - <?php echo $this->search->description ?></h3>
			Note: Biblefox searches all available bible translations at once, and displays the results in your preferred translation, so the search words may not appear in all displayed results.
			<div id="match_all">
				<?php echo $this->search->output_verse_map($book_counts) ?>
				<div class="results_wrap">
					<div class="results roundbox">
						<div class="box_head">Search Results
							<form id="bible_view_search" action="admin.php" method="get">
								Display as:
								<input type="hidden" name="page" value="<?php echo BFOX_BIBLE_SUBPAGE ?>" />
								<input type="hidden" name="<?php echo BfoxQuery::var_page ?>" value="<?php echo BfoxQuery::page_search ?>" />
								<input type="hidden" name="<?php echo BfoxQuery::var_search ?>" value="<?php echo $this->search->text ?>" />
								<?php if (!empty($this->search->ref_str)): ?>
								<input type="hidden" name="<?php echo BfoxQuery::var_reference ?>" value="<?php echo $this->search->ref_str ?>" />
								<?php endif; ?>
								<?php Translations::output_select($this->translation->id) ?>
								<input type="submit" value="Go" class="button">
							</form>
						</div>
						<?php
							$verses = $this->search->search_boolean();
							echo $this->search->output_verses($verses);
						?>
						<div class="box_menu">This search took <?php echo $this->search->last_search_time ?> seconds</div>
					</div>
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}
}

?>