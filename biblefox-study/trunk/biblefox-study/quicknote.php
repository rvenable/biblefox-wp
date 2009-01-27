<?php

class QuickNote
{
	var $bfox_quicknotes;
	var $bfox_quicknote_refs;
	var $notes = array();

	function QuickNote()
	{
		$this->bfox_quicknotes = BFOX_BASE_TABLE_PREFIX . 'quicknotes';
		$this->bfox_quicknote_refs = BFOX_BASE_TABLE_PREFIX . 'quicknote_refs';
	}

	function create_tables()
	{
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		$sql = "
		CREATE TABLE IF NOT EXISTS $this->bfox_quicknotes (
			id bigint(20) unsigned NOT NULL auto_increment,
			user bigint(20) unsigned NOT NULL,
			privacy int NOT NULL default '0',
			date_created datetime NOT NULL,
			date_modified datetime NOT NULL,
			note text NOT NULL,
			PRIMARY KEY  (id)
		);
		CREATE TABLE IF NOT EXISTS $this->bfox_quicknote_refs (
			id bigint(20) unsigned NOT NULL,
			verse_start int NOT NULL,
			verse_end int NOT NULL
		);
		";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Saves a quick note. Modifies an existing note if the a note id is specified, or creates a new note if no note id is specified.
	 *
	 * @param BibleRefs $refs
	 * @param string $note
	 * @param int $id
	 * @return int note id for the modified/created note
	 */
	function save_quicknote(BibleRefs $refs, $note, $id = NULL)
	{
		global $user_ID, $wpdb;

		// Convert end-lines to <br /> tags
		$note = str_replace(array("\r\n", "\r"), "\n", $note);
		$note = preg_replace("/\n+/", "<br />", $note);

		// If a note id was specified, then we are modifying a previously created note
		if (!empty($id))
		{
			// Make sure that we are modifying a note for the current user
			// If we aren't, we can just set the id to null and continue to insert a new note
			$note_user = $wpdb->get_var($wpdb->prepare("SELECT user FROM $this->bfox_quicknotes WHERE id = %d", $id));
			if ($note_user == $user_ID)
			{
				// Update the note data
				$wpdb->query($wpdb->prepare("UPDATE $this->bfox_quicknotes SET note = %s, date_modified = NOW() WHERE id = %d", $note, $id));

				// Delete any previously saved ref data because we have new refs to insert
				$wpdb->query($wpdb->prepare("DELETE FROM $this->bfox_quicknote_refs WHERE id = %d", $id));
			}
			else $id = NULL;
		}

		// If there is no note id, just insert a new note
		if (empty($id))
		{
			$wpdb->query($wpdb->prepare("INSERT INTO $this->bfox_quicknotes SET user = %d, note = %s, date_created = NOW(), date_modified = NOW()", $user_ID, $note));
			$id = $wpdb->insert_id;
		}

		// Insert all the bible refs for the note we just modified or created
		foreach ($refs->get_sets() as $unique_ids)
		{
			$wpdb->query($wpdb->prepare("INSERT INTO $this->bfox_quicknote_refs SET id = %d, verse_start = %d, verse_end = %d", $id, $unique_ids[0], $unique_ids[1]));
		}

		return $id;
	}

	/**
	 * Returns the quicknotes in a given passage of scripture.
	 *
	 * @param BibleRefs $refs
	 * @return unknown
	 */
	private function get_quicknotes(BibleRefs $refs)
	{
		global $wpdb;

		// TODO3: Allow viewing friends quick notes

		global $user_ID;

		$user_where = $wpdb->prepare('notes.user = %d', $user_ID);
		$ref_where = $refs->sql_where2("refs.verse_start", "refs.verse_end");

		return (array) $wpdb->get_results("
			SELECT notes.*, refs.verse_start AS verse_start, refs.verse_end AS verse_end
			FROM $this->bfox_quicknotes AS notes
			INNER JOIN $this->bfox_quicknote_refs AS refs
			ON notes.id = refs.id
			WHERE $user_where AND $ref_where
			ORDER BY refs.verse_start, refs.verse_end, notes.date_created
			");
	}

	/**
	 * Returns the quicknotes in a given passage of scripture.
	 *
	 * @param BibleRefs $refs
	 * @return unknown
	 */
	private function get_grouped_quicknotes(BibleRefs $refs)
	{
		global $wpdb;

		// TODO3: Allow viewing friends quick notes

		global $user_ID;

		$user_where = $wpdb->prepare('notes.user = %d', $user_ID);
		$ref_where = $refs->sql_where2("refs.verse_start", "refs.verse_end");

		return (array) $wpdb->get_results("
			SELECT notes.*, GROUP_CONCAT(refs.verse_start) AS verse_start, GROUP_CONCAT(refs.verse_end) AS verse_end
			FROM $this->bfox_quicknotes AS notes
			INNER JOIN $this->bfox_quicknote_refs AS refs
			ON notes.id = refs.id
			WHERE $user_where AND $ref_where
			GROUP BY notes.id
			ORDER BY MIN(refs.verse_start), MIN(refs.verse_end)
			");
	}

	/**
	 * Sets the BibleRefs to get quick notes for. It retrieves all the quick notes for those refs and stores them internally.
	 *
	 * @param BibleRefs $refs
	 */
	function set_biblerefs(BibleRefs $refs)
	{
		$this->notes = $this->get_quicknotes($refs);
	}

	/**
	 * Gets an array of quick note arrays, indexed by the verse_end unique_id
	 *
	 * @return unknown
	 */
	function get_indexed_notes()
	{
		foreach ($this->notes as $note)
		{
			$key = $note->verse_start;
			if (!isset($indexed_notes[$key])) $indexed_notes[$key] = array();
			array_push($indexed_notes[$key], $note);
		}
		return $indexed_notes;
	}

	function get_note_link($id, $note, BibleRefs $refs)
	{
		$ref_str = $refs->get_string();
		return "<a href='#none' id='quick_note_link_$id' title='$note' onclick=\"bfox_edit_quick_note('$id', '$ref_str')\">[note]</a>";
	}

	/**
	 * Returns a list of verse note links for the given unique_id, taking the notes from the given indexed_notes array.
	 *
	 * $indexed_notes should be of the form as returned by get_indexed_notes(). This function modifies $indexed_notes to remove notes as it uses them.
	 * If no $unique_id is set, all of the notes left in $indexed_notes are used.
	 *
	 * @param array $indexed_notes Quicknote data as returned by get_indexed_notes()
	 * @param integer $unique_id
	 * @return string
	 */
	function list_verse_notes(&$indexed_notes, $unique_id = NULL)
	{
		$note_list = array();
		if (empty($unique_id))
		{
			if (!empty($indexed_notes)) $note_list = $indexed_notes;
			$indexed_notes = array();
			$content .= "<span id='end_quick_notes'>";
		}
		else
		{
			if (isset($indexed_notes[$unique_id]))
			{
				$note_list = array($indexed_notes[$unique_id]);
				unset($indexed_notes[$unique_id]);
			}
			$content .= "<span id='quick_notes_$unique_id'>";
		}

		foreach ($note_list as $notes)
		{
			foreach ($notes as $note)
			{
				$refs = new BibleRefs($note->verse_start, $note->verse_end);
				$content .= $this->get_note_link($note->id, $note->note, $refs);;
			}
		}
		if (!empty($content)) $content .= '</span> ';
		return $content;
	}

	/**
	 * Returns an output string with a list of all the quick notes for a given bible reference
	 *
	 * @param BibleRefs $refs
	 * @return string
	 */
/* TODO2: Not currently using this function, but will be needed for displaying quicknotes without javascript
	function list_quicknotes()
	{
//		$content .= '<form action="">';
		foreach ($this->notes as $note)
		{
			$refs = new BibleRefs($note->verse_start, $note->verse_end);
			$edit = '<a class="edit_quick_note_link" onClick="bfox_edit_quick_note(' . $note->id . ')">[edit]</a>';
			$note_content = '<span id="quick_note_' . $note->id . '">' . $note->note . '</span>';
			$content .= "<tr><td>" . $refs->get_link() . "</td><td>$edit</td><td>$note_content</td></tr>";
//			$content .= '<tr><td><input type="text" value="' . $refs->get_string() . '" /></td><td></td><td><textarea rows="1" style="width: 100%; height: auto;">' . $note->note . '</textarea></td></tr>';
		}
//		$content .= '</form>';

		return $content;
	}*/
}

// TODO2: We shouldn't use a global instance of quicknote
global $bfox_quicknote;
$bfox_quicknote = new QuickNote();

/**
 * AJAX function for saving the quick note
 *
 */
function bfox_ajax_save_quick_note()
{
	$id = $_POST['note_id'];
	$note = $_POST['note'];
	$ref_str = $_POST['ref_str'];

	$refs = new BibleRefs($ref_str);
	$is_new_note = (0 == $id);

	global $bfox_quicknote;
	$id = $bfox_quicknote->save_quicknote($refs, $note, $id);

	list($unique_ids) = $refs->get_sets();
	$section_id = "#quick_notes_$unique_ids[0]";
	if ($is_new_note)
	{
		// Return the new list of quick notes
		$bfox_quicknote->set_biblerefs($refs);
		$link = addslashes($bfox_quicknote->get_note_link($id, $note, $refs));

		$script = "bfox_quick_note_created('$section_id', '$link')";
	}
	else
	{
		$script = "bfox_quick_note_edited('$section_id', '#quick_note_link_$id', '$note')";
	}

	die($script);
}
add_action('wp_ajax_bfox_ajax_save_quick_note', 'bfox_ajax_save_quick_note');


?>
