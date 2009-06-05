<?php

require_once BFOX_BIBLE_DIR . '/ref_content.php';
require_once BFOX_BIBLE_DIR . '/cbox_notes.php';
require_once BFOX_BIBLE_DIR . '/cbox_plans.php';

class BfoxPagePassage extends BfoxPage {
	/**
	 * The bible references being used
	 *
	 * @var BibleRefs
	 */
	protected $refs;

	protected $history;

	protected $cboxes = array();

	public function __construct($ref_str, $trans_str = '') {
		$this->refs = new BibleRefs($ref_str);

		$url = BfoxQuery::page_url(BfoxQuery::page_passage);
		$this->cboxes['plans'] = new BfoxCboxPlans($url, 'plans', 'My Reading Plans');
		$this->cboxes['notes'] = new BfoxCboxNotes($url, 'notes', 'My Bible Notes');

		// Get the passage history
		$earliest = $this->cboxes['plans']->get_earliest_time();
		if (empty($earliest)) $limit = 5;
		else $limit = 0;
		$this->history = BfoxHistory::get_history($limit, $earliest);
		$this->cboxes['plans']->set_history($this->history);
		if (!empty($this->history)) $last_viewed = current($this->history);

		if ($this->refs->is_valid()) {
			// If this isn't the same scripture we last viewed, update the read history to show that we viewed these scriptures
			if (empty($last_viewed) || ($this->refs->get_string() != $last_viewed->refs->get_string())) BfoxHistory::view_passage($this->refs);
		}
		else {
			// If we don't have a valid bible ref, we should use the history
			if (!empty($last_viewed)) $this->refs = $last_viewed->refs;
			// If there is no history, show Genesis 1
			else $this->refs = new BibleRefs('Genesis 1');
		}

		parent::__construct($trans_str);
	}

	public function page_load() {
		foreach ($this->cboxes as $cbox) $cbox->page_load();
	}

	public function get_title()
	{
		return $this->refs->get_string();
	}

	public function get_search_str()
	{
		return $this->refs->get_string(BibleMeta::name_short);
	}

	public function content()
	{
		$history_table = new BfoxHtmlTable("class='widefat'");
		$history_table->add_header_row('', 3, 'Passage', 'Time', 'Edit');
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

			$history_table->add_row('', 3,
				"<a href='" . BfoxQuery::passage_page_url($ref_str, $this->translation) . "'>$ref_str</a>",
				"$intro $history->time",
				"<a href='" . BfoxQuery::toggle_read_url($history->time, BfoxQuery::page_url(BfoxQuery::page_passage)) . "'>" . $toggle . "</a>");
		}

		$ref_str = $this->refs->get_string();

		$footnotes = array();

		?>

		<div id="bible_passage">
			<div id="bible_note_popup"></div>
			<div class="roundbox">
				<div class="box_head">
					<?php echo $ref_str ?>
					<a id="verse_layout_toggle" class="button">Switch to Verse View</a>
				</div>
				<?php BfoxRefContent::ref_content($this->refs, $this->translation, $footnotes); ?>
			</div>
		</div>

		<div id='history' class='cbox'>
			<div class='cbox_head'>Passage History</div>
			<div class='cbox_body box_inside'>
			<?php echo $history_table->content() ?>
			</div>
		</div>
		<?php
			foreach ($this->cboxes as $cbox) echo $cbox->cbox();
		?>
		<div id='commentaries' class='cbox'>
			<div class='cbox_head'>Blog Posts</div>
			<div class='cbox_body'>
				<?php BfoxRefContent::output_posts($this->refs); ?>
			</div>
		</div>
		<?php
	}
}

?>