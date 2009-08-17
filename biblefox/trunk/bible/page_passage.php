<?php

require_once BFOX_BIBLE_DIR . '/ref_content.php';

class BfoxPagePassage extends BfoxPage {

	const var_page_num = 'page_num';

	/**
	 * The bible references being used
	 *
	 * @var BfoxRefs
	 */
	protected $refs;

	/**
	 * The bible translation to use for displaying scripture
	 *
	 * @var BfoxTrans
	 */
	protected $translation;

	protected $cboxes = array();

	protected $default_tab = NULL;

	public function __construct(BfoxRefs $input_refs, BfoxTrans $translation, $last_viewed) {
		// Limit the refs to 20 chapters
		list($refs) = $input_refs->get_sections(20, 1);

		// If this isn't the same scripture we last viewed, update the read history to show that we viewed these scriptures
		if (empty($last_viewed) || ($refs->get_string() != $last_viewed->refs->get_string())) {
			$this->default_tab = 0;
			BfoxHistory::view_passage($refs);
		}

		add_action('wp_head', array($this, 'wp_head'));

		$this->refs = $refs;
		$this->translation = $translation;
		BiblefoxMainBlog::set_search_str($this->refs->get_string(BibleMeta::name_short));

		parent::__construct($translation);
	}

	public function wp_head() {
		if (!is_null($this->default_tab)) $selected = "selected: $this->default_tab,";

		?>
		<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function() {
			jQuery('#tool_tabs').tabs({
				collapsible: true,
				cookie: { expires: 30, name: 'bfox_tool_tabs' }
			});
		});
		//]]>
		</script>
		<?php
	}

	public function get_title() {
		return $this->refs->get_string();
	}

	public function content() {
		global $page_passage_refs, $page_passage_trans;
		$page_passage_refs = $this->refs;
		$page_passage_trans = $this->translation;

		require BFOX_BIBLE_DIR . '/templates/passage.php';
	}
}

?>