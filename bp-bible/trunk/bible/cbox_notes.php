<?php

class BfoxCboxNotes extends BfoxCbox {

	public function page_load() {
		$this->url = BfoxQuery::ref_url($this->refs->get_string());
	}

	public function content() {
		$notes = BfoxNotes::get_notes();

		$notes_table = new BfoxHtmlTable("class='widefat'");
		$notes_table->add_header_row('', 3, 'Modified', 'Note', 'Scriptures Referenced');
		foreach ($notes as $note) {
			$refs = $note->get_refs();
			$ref_str = $refs->get_string();

			$notes_table->add_row('', 3,
				$note->get_modified(),
				$note->get_title() . " (<a href='" . BfoxBible::edit_note_url($note->id, $this->url) . "'>edit</a>)",
				"<a href='" . BfoxQuery::ref_url($ref_str) . "'>$ref_str</a>");
		}
		$notes_table->add_row('', 1, array("<a href='" . BfoxBible::edit_note_url(0, $this->url) . "'>Add New Note</a>", "colspan='3'"));

		echo $notes_table->content();

		// Get the current not from the user options
		$note = BfoxNotes::get_note(get_user_option(BfoxBible::user_option_note_id));
		if (empty($note->id)) $note->set_content($this->refs->get_string());

		if (empty($note->id)) $edit_header = __('Create a Note');
		else $edit_header = __('Edit Note');

		echo "<h3>$edit_header</h3>\n";
		$this->edit_note($note);
	}

	public function edit_note(BfoxNote $note) {
		$table = new BfoxHtmlOptionTable("class='form-table'", "action='$this->url' method='post'",
			BfoxUtility::hidden_input(BfoxBible::var_note_id, $note->id),
			"<p><input type='submit' name='" . BfoxBible::var_note_submit . "' value='" . __('Save') . "' class='button'/></p>");

		$content = $note->get_content();

		if (!empty($content) && !empty($note->id)) $table->add_option(__('Note'), '', $note->get_display_content(), '');

		// Note Content
		$table->add_option(__('Edit'), '', $table->option_textarea(BfoxBible::var_note_content, $content, 15, 50), '');

		echo $table->content();
	}
}

?>