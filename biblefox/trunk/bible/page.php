<?php

/**
 * Base class for all pages in the bible viewer
 *
 */
abstract class BfoxPage
{
	/**
	 * The bible translation to use for displaying scripture
	 *
	 * @var Translation
	 */
	protected $translation;

	public function __construct($trans_str = '') {
		// TODO3: if (empty($trans)) $translation = get_from_history
		if (empty($trans_str)) $this->translation = $GLOBALS['bfox_trans'];
		else $this->translation = Translations::get_translation($trans_str);

		BfoxUtility::enqueue_script('bfox_bible');
		BfoxUtility::enqueue_style('bfox_bible');
	}

	public function page_load() {}

	protected abstract function content();

	public function get_title()
	{
		return 'Biblefox Bible Viewer';
	}

	public function get_search_str()
	{
		return '';
	}

	public function page()
	{
		// TODO3: Remove 'page' form input when removing from admin pages
		?>
		<div id="bible" class="">
			<div id="bible_head">
				<div id="bible_head_content">
					<h2>Biblefox Bible Viewer</h2>
					<form id="bible_search_form" action="<?php echo BfoxQuery::post_url() ?>" method="get">
						<input type="hidden" name="<?php echo BfoxQuery::var_page ?>" value="<?php echo BfoxQuery::page_search ?>" />
						<?php Translations::output_select($this->translation->id) ?>
						<input type="text" name="<?php echo BfoxQuery::var_search ?>" value="<?php echo $this->get_search_str() ?>" />
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
						BfoxPageCommentaries::manage_page();
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

abstract class BfoxCbox {

	protected $refs;
	protected $url;
	protected $id;
	protected $title;

	public function __construct(BibleRefs $refs, $url, $id = '', $title = '') {
		$this->refs = $refs;
		$this->url = $url;
		$this->id = $id;
		$this->title = $title;
	}

	public function page_load() {}
	public abstract function content();

	public function cbox() {
		?>
		<div id='<?php echo $this->id ?>' class='cbox'>
			<div class='cbox_head'><?php echo $this->title ?></div>
			<div class='cbox_body box_inside'>
				<?php echo $this->content() ?>
			</div>
		</div>
		<?php
	}

	protected function cbox_url($url) {
		if (!empty($this->id)) return "$url#$this->id";
		return $url;
	}
}

?>