<?php


require_once BFOX_BIBLE_DIR . '/bible-search.php';

class BfoxPageSearch /*extends BfoxPage */{
	const var_page_num = 'results_page';

/*	private function page_url($page_num) {
		return add_query_arg(self::var_page_num, $page_num, $search->get_url());
	}

	public function get_title() {
		if (!empty($search->ref_str)) $ref_str = " in {$search->ref_str}";
		return "Search for '{$search->text}'$ref_str";
	}
*/
}

function bp_bible_search_page_url($search, $page_num) {
	return add_query_arg(BfoxPageSearch::var_page_num, $page_num, $search->get_url());
}

global $bp_bible;

$search = new BibleSearch(strip_tags($bp_bible->search_str), $bp_bible->translation, $_REQUEST[BfoxPageSearch::var_page_num]);

$refs = $bp_bible->refs;
if ($refs->is_valid()) {
	$search->set_refs($refs);
}

$search_str = $search->text;

// See if we need to filter these search results by a bible reference
/*if (!empty($ref_str)) {
	$refs = BfoxRefParser::with_groups($ref_str);
}
*/
//BfoxUtility::enqueue_style('bfox_search');

//BiblefoxMainBlog::set_search_str($search_str);

$book_counts = $search->boolean_book_counts();
$verses = $search->search_boolean();

// Page links
if (1 < $search->page) {
	$page_prev = "<a href='" . bp_bible_search_page_url($search, $search->page - 1) . "'>" . ($search->page - 1) . "</a>, ";
	if (1 < ($search->page - 1)) $page_prev = "<a href='" . bp_bible_search_page_url($search, 1) . "'>1</a> ... $page_prev";
}
$max_page = $search->get_num_pages();
if ($max_page > $search->page) {
	$page_next = ", <a href='" . bp_bible_search_page_url($search, $search->page + 1) . "'>" . ($search->page + 1) . "</a>";
	if ($max_page > ($search->page + 1)) $page_next .= " ... <a href='" . bp_bible_search_page_url($search, $max_page) . "'>$max_page</a>";
}
if (!empty($page_prev) || !empty($page_next)) $page_links = "Page $page_prev<span class='page_current'>{$search->page}</span>$page_next";

?>

<div id="content" class="narrowcolumn">

	<div id="bible" class="">
		<div id="bible_page">
		<div id="bible_search">
			<div id="search_header">
				<h3>Match All Words - <?php echo $search->description ?></h3>
				Note: Biblefox searches all available bible translations at once, and displays the results in your preferred translation, so the exact search words may not appear in all results.
			</div>
			<div id="search_sidebar">
				<div class="verse_map roundbox">
					<div class="box_head">Bible Verses</div>
					<div id="verse_map_list">
						<ul>
							<li>
								<?php echo $search->output_verse_map($book_counts) ?>
							</li>
						</ul>
					</div>
				</div>
			</div>
			<div id="search_content">
				<div class="results roundbox">
					<div class="box_head"><span class='page_links'><?php echo $page_links ?></span>Search Results
					</div>
					<?php echo $search->output_verses($verses) ?>
					<div class="box_menu"><span class='page_links'><?php echo $page_links ?></span>This search took <?php echo $search->last_search_time ?> seconds
					</div>
				</div>
			</div>
			<div id="search_footer">
			</div>
		</div>
		</div>
	</div>
</div>


	<!-- Passage Sidebar Widgets -->
	<div id="sidebar">
		<?php if (!function_exists('dynamic_sidebar') || !dynamic_sidebar('bible-search-side')): ?>
		<div class="widget-error">
			<?php _e('Please log in and add widgets to this column.') ?> <a href="<?php echo get_option('siteurl') ?>/wp-admin/widgets.php?s=&amp;show=&amp;sidebar=bible-passage-side"><?php _e('Add Widgets') ?></a>
		</div>
		<?php endif; ?>
	</div>
