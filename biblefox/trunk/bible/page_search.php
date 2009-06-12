<?php

require_once BFOX_BIBLE_DIR . '/bible-search.php';

class BfoxPageSearch extends BfoxPage {
	const var_page_num = 'results_page';

	/**
	 * Instance of BibleSearch for all the search functionality
	 *
	 * @var BibleSearch
	 */
	protected $search;

	public function __construct($search_str, $ref_str = '', Translation $translation) {
		parent::__construct();

		$this->translation = $translation;

		$this->search = new BibleSearch(strip_tags($search_str), $this->translation, $_REQUEST[self::var_page_num]);

		// See if we need to filter these search results by a bible reference
		if (!empty($ref_str)) $this->search->set_refs(BfoxBible::parse_search_ref_str($ref_str));

		BfoxUtility::enqueue_style('bfox_search');
	}

	private function page_url($page_num) {
		return add_query_arg(self::var_page_num, $page_num, $this->search->get_url());
	}

	public function get_title()
	{
		if (!empty($this->search->ref_str)) $ref_str = " in {$this->search->ref_str}";
		return "Search for '{$this->search->text}'$ref_str";
	}

	public function get_search_str()
	{
		return $this->search->text;
	}

	public function content()
	{
		$book_counts = $this->search->boolean_book_counts();
		$verses = $this->search->search_boolean();

		// Page links
		if (1 < $this->search->page) {
			$page_prev = "<a href='" . $this->page_url($this->search->page - 1) . "'>" . ($this->search->page - 1) . "</a>, ";
			if (1 < ($this->search->page - 1)) $page_prev = "<a href='" . $this->page_url(1) . "'>1</a> ... $page_prev";
		}
		$max_page = $this->search->get_num_pages();
		if ($max_page > $this->search->page) {
			$page_next = ", <a href='" . $this->page_url($this->search->page + 1) . "'>" . ($this->search->page + 1) . "</a>";
			if ($max_page > ($this->search->page + 1)) $page_next .= " ... <a href='" . $this->page_url($max_page) . "'>$max_page</a>";
		}
		if (!empty($page_prev) || !empty($page_next)) $page_links = "Page $page_prev<span class='page_current'>{$this->search->page}</span>$page_next";

		// Show the exact matches at the bottom
		?>
		<div id="bible_search">
			<div id="search_header">
				<h3>Match All Words - <?php echo $this->search->description ?></h3>
				Note: Biblefox searches all available bible translations at once, and displays the results in your preferred translation, so the exact search words may not appear in all results.
			</div>
			<div id="search_sidebar">
				<div class="verse_map roundbox">
					<div class="box_head">Bible Verses</div>
					<div id="verse_map_list">
						<ul>
							<li>
								<?php echo $this->search->output_verse_map($book_counts) ?>
							</li>
						</ul>
					</div>
				</div>
			</div>
			<div id="search_content">
				<div class="results roundbox">
					<div class="box_head"><span class='page_links'><?php echo $page_links ?></span>Search Results
					</div>
					<?php echo $this->search->output_verses($verses) ?>
					<div class="box_menu"><span class='page_links'><?php echo $page_links ?></span>This search took <?php echo $this->search->last_search_time ?> seconds
					</div>
				</div>
			</div>
			<div id="search_footer">
			</div>
		</div>
		<?php
	}
}

?>