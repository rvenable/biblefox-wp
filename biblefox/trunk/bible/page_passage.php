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

	protected $history = array();

	protected $cboxes = array();

	protected $default_tab = NULL;

	public function __construct($ref_str, BfoxTrans $translation) {
		$input_refs = new BfoxRefs($ref_str);

		// Get the last viewed passage
		$history = BfoxHistory::get_history(1);
		$last_viewed = reset($history);

		if ($input_refs->is_valid()) {
			// Limit the refs to 20 chapters
			list($refs) = $input_refs->get_sections(20, 1);

			// If this isn't the same scripture we last viewed, update the read history to show that we viewed these scriptures
			if (empty($last_viewed) || ($refs->get_string() != $last_viewed->refs->get_string())) {
				$this->default_tab = 0;
				BfoxHistory::view_passage($refs);
			}

			add_action('wp_head', array($this, 'wp_head'));

			$this->refs = $refs;
			$this->history = BfoxHistory::get_history(25);
			$this->translation = $translation;
			parent::__construct($translation);
		}
		else {
			// If we don't have a valid bible ref, we should use the history
			if (!empty($last_viewed)) $refs = $last_viewed->refs;

			if ($refs->is_valid()) wp_redirect(BfoxQuery::ref_url($refs->get_string()));
			else wp_redirect(BfoxQuery::ref_url('Genesis 1'));
			exit;
		}
	}

	public function wp_head() {
		if (!is_null($this->default_tab)) $selected = "selected: $this->default_tab,";

		?>
		<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function() {
			jQuery('#passage_tabs').tabs({
				<?php echo $selected ?>
				cookie: { expires: 30, name: 'passage_tabs' }
			});
			jQuery('#tool_tabs').tabs({
				collapsible: true,
				cookie: { expires: 30, name: 'tool_tabs' }
			});
		});
		//]]>
		</script>
		<?php
	}

	public function get_title() {
		return $this->refs->get_string();
	}

	public function get_search_str() {
		return $this->refs->get_string(BibleMeta::name_short);
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

		$tool_tabs = new BfoxHtmlTabs("id='tool_tabs' class='tabs'");
		$tool_tabs->add('blogs', __('Blogs'), $blog_content . "<a href='" . BfoxQuery::page_url(BfoxQuery::page_commentary) . "'>Manage Blog Commentaries</a>");
		$tool_tabs->add('notes', __('Notes'), $note_content);
		$tool_tabs->add('options', __('Options'), $this->options());

		return $tool_tabs->content();
	}

	private function readings() {
		$plans = BfoxRefContent::get_plans();

		$table = new BfoxHtmlTable("class='widefat'");

		foreach ($plans as $plan) if ($plan->is_current()) {
			foreach ($plan->readings as $reading_id => $reading) {
				$unread = $plan->get_unread($reading);
				$is_unread = $unread->is_valid();

				// If the passage is unread or current, add it
				if ($is_unread || ($reading_id >= $plan->current_reading_id)) {
					$ref_str = $plan->readings[$reading_id]->get_string();
					$url = Biblefox::ref_url($ref_str);

					if (!$is_unread) $finished = " class='finished'";
					else $finished = '';

					$row = new BfoxHtmlRow('',
						BfoxUtility::nice_date($plan->dates[$reading_id], 'l, M j'),
						"<a href='$url'$finished>$ref_str</a>",
						"<a href='" . BfoxQuery::reading_plan_url($plan->id) . "'>$plan->name #" . ($reading_id + 1) . "</a>");
					$row->add_sort_val($plan->dates[$reading_id]);
					$table->add_row($row);
				}
			}
		}

		"<a href='" . BfoxQuery::page_url(BfoxQuery::page_plans) . "'>Manage my reading plans</a>";


		if (empty($plans)) {
			$manage = __('manage reading plans');
			$header = __('<p>You are not subscribed to any reading plans. Biblefox has many reading plans you can subscribe to, or you can create your own. Visit the ') .
				"<a href='" . BfoxQuery::page_url(BfoxQuery::page_plans) . "'>$manage</a>" . __(' page to edit your plans</p>');
		}
		else {
			$manage = __('Manage your reading plans');
			$header = __('<p>Here are upcoming readings for your reading plans:</p>') . "<a href='" . BfoxQuery::page_url(BfoxQuery::page_plans) . "'>$manage</a>";
		}

		return $header . $table->content(TRUE);
	}

	private function ref_content() {
		ob_start();
		?>
			<?php echo $this->tools_tab($this->refs) ?>
			<?php BfoxRefContent::ref_content_new($this->refs, $this->translation) ?>
			<div class="clear"></div>
		<?php

		return ob_get_clean();
	}

	private function content_new() {

		$passage_tabs = new BfoxHtmlTabs("id='passage_tabs' class='tabs'");
		$passage_tabs->add('passage', $this->refs->get_string(), $this->ref_content());
		$passage_tabs->add('plans', __('Readings'), $this->readings());
		$passage_tabs->add('history', __('History'),
			__('<p>Here are the passages you have viewed recently. You can mark them as read to keep track of your reading progress.<p>') .
			BfoxRefContent::history_table($this->history));

		?>
		<?php echo $passage_tabs->content($this->default_tab) ?>
		<?php
	}

	public function content() {
		if ($this->display_full) return $this->content_new();
		else return $this->ref_content();
	}
}

?>