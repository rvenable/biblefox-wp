<?php

/**
 * Base class for all pages in the bible viewer
 *
 */
abstract class BfoxPage
{
	/**
	 * The bible translation being used
	 *
	 * @var Translation
	 */
	protected $translation;

	public function __construct(Translation $translation)
	{
		$this->translation = $translation;
	}

	public function page_load() {}

	protected abstract function content();

	public function page()
	{
		?>
		<div id="bible" class="">
			<div id="bible_bar" class="roundbox">
				<div class="box_head">Bible Viewer</div>
				<div class="box_inside">
					<ul id="bible_page_list">
						<li><a href="<?php echo BfoxQuery::page_url(BfoxQuery::page_passage) ?>">Passage</a></li>
						<li><a href="<?php echo BfoxQuery::page_url(BfoxQuery::page_commentary) ?>">Commentaries</a></li>
					</ul>
					<form id="bible_search_form" action="admin.php" method="get">
						<input type="hidden" name="page" value="<?php echo BFOX_BIBLE_SUBPAGE; ?>" />
						<input type="hidden" name="<?php echo BfoxQuery::var_page ?>" value="<?php echo BfoxQuery::page_search ?>" />
						<input type="hidden" name="<?php echo BfoxQuery::var_translation ?>" value="<?php echo $this->translation->id ?>" />
						<input type="text" name="<?php echo BfoxQuery::var_search ?>" value="" />
						<input type="submit" value="<?php _e('Search Bible', BFOX_DOMAIN); ?>" class="button" />
					</form>
				</div>
			</div>
			<div id="bible_page">
			<?php
				$this->content();
				/*
				switch ($this->page)
				{
					case BfoxQuery::page_search:
						include('bible-search.php');
						break;
					case BfoxQuery::page_commentary:
						Commentaries::manage_page();
						break;
					case BfoxQuery::page_history:
					case BfoxQuery::page_passage:
					default:
						$refs = $this->refs;
						include('bible-passage.php');
				}
				*/
			?>
			</div>
		</div>
		<?php
	}
}

?>