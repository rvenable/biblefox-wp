<?php

class BfoxPageNotes extends BfoxPage {

	const var_submit = 'submit';
	const var_note_id = 'note_id';
	const var_content = 'content';

	public function page_load() {

		if (isset($_REQUEST[self::var_submit])) {
			$note = BfoxNotes::get_note($_REQUEST[self::var_note_id]);
			$note->set_content(strip_tags($_REQUEST[self::var_content]));
			BfoxNotes::save_note($note);
		}
	}

	public function content() {
		$notes = BfoxNotes::get_notes();

		$notes_table = new BfoxHtmlTable();
		foreach ($notes as $note) {
			$refs = $note->get_refs();
			$ref_str = $refs->get_string();

			$notes_table->add_row('', 3,
				$note->get_modified(),
				$note->get_title(),
				"<a href='" . BfoxQuery::passage_page_url($ref_str, $this->translation) . "'>$ref_str</a>");
		}

		echo "<h2>My Notes</h2>\n";
		echo $notes_table->content();
		echo "<h3>Create a Note</h3>";
		self::edit_note(new BfoxNote());
	}

	public static function edit_note(BfoxNote $note) {
		$table = new BfoxHtmlOptionTable("class='form-table'", "action='" . BfoxQuery::page_url(BfoxQuery::page_notes) . "' method='post'",
			BfoxUtility::hidden_input(self::var_note_id, $note->id),
			"<p><input type='submit' name='" . self::var_submit . "' value='" . __('Save') . "' class='button'/></p>");

		// Note Content
		$table->add_option(__('Note'), '', $table->option_textarea(self::var_content, $note->get_content(), 15, 50), '');

		echo $table->content();
	}
}

?>