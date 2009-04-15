<?php

/**
 * Base class for all pages in the bible viewer
 *
 */
abstract class BfoxPage
{
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
						<li><a href="<?php echo Bible::page_url(Bible::page_passage) ?>">Passage</a></li>
						<li><a href="<?php echo Bible::page_url(Bible::page_commentary) ?>">Commentaries</a></li>
					</ul>
					<form id="bible_search_form" action="admin.php" method="get">
						<input type="hidden" name="page" value="<?php echo BFOX_BIBLE_SUBPAGE; ?>" />
						<input type="hidden" name="<?php echo Bible::var_page ?>" value="<?php echo Bible::page_search ?>" />
						<input type="hidden" name="<?php echo Bible::var_translation ?>" value="<?php echo $this->translation->id ?>" />
						<input type="text" name="<?php echo Bible::var_search ?>" value="" />
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
					case Bible::page_search:
						include('bible-search.php');
						break;
					case Bible::page_commentary:
						Commentaries::manage_page();
						break;
					case Bible::page_history:
					case Bible::page_passage:
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