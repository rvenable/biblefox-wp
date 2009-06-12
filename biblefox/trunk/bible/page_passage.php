<?php

require_once BFOX_BIBLE_DIR . '/ref_content.php';

class BfoxPagePassage extends BfoxPage {

	const var_history_id = 'history_id';
	const var_page_num = 'page_num';

	/**
	 * The bible references being used
	 *
	 * @var BibleRefs
	 */
	protected $refs;

	/**
	 * The bible translation to use for displaying scripture
	 *
	 * @var Translation
	 */
	protected $translation;

	protected $history;
	protected $history_id = '';

	protected $cboxes = array();

	protected $default_tab = NULL;

	public function __construct($ref_str, Translation $translation) {
		$this->refs = new BibleRefs($ref_str);
		$this->translation = $translation;

		// Get the last viewed
		$this->history = BfoxHistory::get_history(1);
		if (!empty($this->history)) $last_viewed = current($this->history);

		if ($this->refs->is_valid()) {
			// If this isn't the same scripture we last viewed, update the read history to show that we viewed these scriptures
			if (empty($last_viewed) || ($this->refs->get_string() != $last_viewed->refs->get_string())) {
				$this->default_tab = 0;
				BfoxHistory::view_passage($this->refs);
			}
		}
		else {
			// If we don't have a valid bible ref, we should use the history
			if (!empty($last_viewed)) $this->refs = $last_viewed->refs;
			// If there is no history, show Genesis 1
			else $this->refs = new BibleRefs('Genesis 1');
		}

		// Get the passage history
		$this->history = BfoxHistory::get_history(25);
		if (!empty($this->history)) $last_viewed = current($this->history);
		if (!empty($_REQUEST[self::var_history_id])) $this->history_id = $_REQUEST[self::var_history_id];
		else $this->history_id = self::history_id($last_viewed->time);

		add_action('wp_head', array($this, 'wp_head'));

		parent::__construct($translation);
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

	private static function history_id($time) {
		return 'hist_' . strtotime($time);
	}

	private static function history_url($history_id, $page_num = 0) {
		$url = add_query_arg(self::var_history_id, $history_id, BfoxQuery::page_url(BfoxQuery::page_passage)) . "#$history_id";
		if (!empty($page_num)) $url = add_query_arg(self::var_page_num, $page_num, $url);
		return $url;
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

	public function tools_tab(BibleRefs $refs) {
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
					$row = new BfoxHtmlRow('',
						date('l, M jS', $plan->dates[$reading_id]),
						"<a href='$url'>$ref_str</a>",
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

	private function history() {
		$history_table = new BfoxHtmlTable("class='widefat'");
		//$history_table->add_header_row('', 3, 'Passage', 'Time', 'Edit');
		foreach ($this->history as $history) {
			$ref_str = $history->refs->get_string();

			if ($history->is_read) {
				$intro = __('Read on');
				$toggle = __('Mark as Unread');
			}
			else {
				$intro = __('Viewed on');
				$toggle = __('Mark as Read');
			}

			$history_id = self::history_id($history->time);

			$row = new BfoxHtmlRow('',
				Biblefox::ref_link($ref_str),
				"$intro $history->time",
				"<a href='" . BfoxQuery::toggle_read_url($history->time, BfoxQuery::page_url(BfoxQuery::page_passage)) . "'>" . $toggle . "</a>");

			$history_table->add_row($row);
		}

		return __('<p>Here are the passages you have viewed recently. You can mark them as read to keep track of your reading progress.<p>') . $history_table->content();

		$table = new BfoxHtmlTable("class='widefat'");

		foreach ($this->history as $history) if ($plan->is_current()) {
			foreach ($plan->readings as $reading_id => $reading) {
				$unread = $plan->get_unread($reading);
				$is_unread = $unread->is_valid();

				// If the passage is unread or current, add it
				if ($is_unread || ($reading_id >= $plan->current_reading_id)) {
					$ref_str = $plan->readings[$reading_id]->get_string();
					$url = Biblefox::ref_url($ref_str);
					$row = new BfoxHtmlRow('',
						date('l, M jS', $plan->dates[$reading_id]),
						"<a href='$url'>$ref_str</a>",
						"<a href='" . BfoxQuery::reading_plan_url($plan->id) . "'>$plan->name #" . ($reading_id + 1) . "</a>");
					$row->add_sort_val($plan->dates[$reading_id]);
					$table->add_row($row);
				}
			}
		}

		return $table->content(TRUE);

	}

	public function content_new() {
		$history = reset($this->history);
		$history_id = self::history_id($history->time);

		$refs = $history->refs;
		ob_start();
		?>
			<?php echo $this->tools_tab($refs) ?>
			<?php BfoxRefContent::ref_content_new($refs, $this->translation) ?>
			<div class="clear"></div>
		<?php
		$ref_content = ob_get_clean();

		$passage_tabs = new BfoxHtmlTabs("id='passage_tabs' class='tabs'");
		$passage_tabs->add('passage', $history->refs->get_string(), $ref_content);
		$passage_tabs->add('plans', __('Readings'), $this->readings());
		$passage_tabs->add('history', __('History'), $this->history());

		?>
		<?php echo $passage_tabs->content($this->default_tab) ?>
		<?php
	}

	public function content() {
		if ($this->display_full) return $this->content_new();
		else {
			$history = reset($this->history);
			$history_id = self::history_id($history->time);
			echo BfoxRefContent::ref_content_paged($history->refs, $this->translation, self::history_url($history_id), self::var_page_num, $this->page_num);
		}
	}
}

?>