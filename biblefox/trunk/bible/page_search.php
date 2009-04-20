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

	public function print_scripts($base_url)
	{
		parent::print_scripts($base_url);
		?>
		<link rel="stylesheet" href="<?php echo $base_url; ?>/wp-content/mu-plugins/biblefox/bible/search.css" type="text/css"/>
		<?php
	}

	public function content()
	{
		$book_counts = $this->search->boolean_book_counts();

		// Show the exact matches at the bottom
		?>
		<div id="bible_search">
			<h3>Match All Words - <?php echo $this->search->description ?></h3>
			Note: Biblefox searches all available bible translations at once, and displays the results in your preferred translation, so the exact search words may not appear in all results.
			<div id="match_all">
				<?php echo $this->search->output_verse_map($book_counts) ?>
				<div class="results_wrap">
					<div class="results roundbox">
						<div class="box_head">Search Results
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