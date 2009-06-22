<?php

/**
 * Base class for all pages in the bible viewer
 *
 */
abstract class BfoxPage {

	protected $display_full = TRUE;

	protected $display = '';

	public function __construct() {
		BfoxUtility::enqueue_script('bfox_bible');
		BfoxUtility::enqueue_style('bfox_bible');

		$this->display = $_REQUEST[BfoxQuery::var_display];
		if (BfoxQuery::display_ajax == $this->display) $this->display_full = FALSE;

		$this->page_load();
	}

	public function page_load() {}

	protected abstract function content();

	public function get_title() {
		return 'Biblefox Bible Viewer';
	}

	public function page() {
		if ($this->display_full) {
			get_header();
			?>
			<div id="bible" class="">
				<div id="bible_page">
					<?php $this->content() ?>
				</div>
			</div>
			<?php
			get_footer();
		}
		else $this->content();
	}
}

abstract class BfoxCbox {

	protected $refs;
	protected $url;
	protected $id;
	protected $title;

	public function __construct(BfoxRefs $refs, $url, $id = '', $title = '') {
		$this->refs = $refs;
		$this->url = $url;
		$this->id = $id;
		$this->title = $title;

		$this->page_load();
	}

	public function page_load() {}
	public abstract function content();

	public function cbox() {
		?>
		<div class='cbox <?php echo $this->id ?>'>
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