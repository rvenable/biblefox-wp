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

	private static function check_option($name, $label) {
		$id = "option_$name";

		return "<input type='checkbox' name='$name' id='$id' class='view_option'/><label for='$id'>$label</label>";
	}

	private function options() {
		$table = new BfoxHtmlList();
		$table->add($this->check_option('jesus', __('Show Jesus\' words in red')));
		$table->add($this->check_option('paragraphs', __('Display verses as paragraphs')));
		$table->add($this->check_option('verse_nums', __('Hide verse numbers')));
		$table->add($this->check_option('footnotes', __('Hide footnote links')));

		return $table->content();
	}

	private function tools_tab(BfoxRefs $refs) {
		global $user_ID;

		$tool_tabs = new BfoxHtmlTabs("id='tool_tabs' class='tabs'");

		if (!empty($user_ID)) {
			$url = BfoxQuery::page_url(BfoxQuery::page_passage);
			$cboxes = array();
			$cboxes['blogs'] = new BfoxCboxBlogs($refs, $url, 'commentaries', 'Blog Posts');
			$cboxes['notes'] = new BfoxCboxNotes($refs, $url, 'notes', 'My Bible Notes');

			ob_start();
			$cboxes['blogs']->content();
			$blog_content = ob_get_clean();

			ob_start();
			$cboxes['notes']->content();
			$note_content = ob_get_clean();

			$tool_tabs->add('blogs', __('Blogs'), $blog_content . "<a href='" . BfoxQuery::page_url(BfoxQuery::page_commentary) . "'>Manage Blog Commentaries</a>");
			$tool_tabs->add('notes', __('Notes'), $note_content);
		}
		$tool_tabs->add('options', __('Options'), $this->options());

		return $tool_tabs->content();
	}

	public function content() {
		?>
			<?php echo $this->tools_tab($this->refs) ?>
			<?php BfoxRefContent::ref_content_new($this->refs, $this->translation) ?>
			<div class="clear"></div>
		<?php
	}
}

?>