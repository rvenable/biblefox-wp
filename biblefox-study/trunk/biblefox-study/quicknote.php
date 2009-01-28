<?php

class QuickNote
{
	var $bfox_quicknotes;
	var $notes = array();

	function QuickNote()
	{
		$this->bfox_quicknotes = BFOX_BASE_TABLE_PREFIX . 'quicknotes';
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
			verse_start int NOT NULL,
			verse_end int NOT NULL
			note text NOT NULL,
			PRIMARY KEY  (id)
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

		list(list($verse_start, $verse_end)) = $refs->get_sets();

		// If a note id was specified, then we are modifying a previously created note
		if (!empty($id))
		{
			// Make sure that we are modifying a note for the current user
			// If we aren't, we can just set the id to null and continue to insert a new note
			$note_user = $wpdb->get_var($wpdb->prepare("SELECT user FROM $this->bfox_quicknotes WHERE id = %d", $id));
			if ($note_user == $user_ID)
			{
				// Update the note data
				$wpdb->query($wpdb->prepare("
					UPDATE $this->bfox_quicknotes
					SET note = %s, verse_start = %d, verse_end = %d, date_modified = NOW()
					WHERE id = %d",
					$note, $verse_start, $verse_end,
					$id));
			}
			else $id = NULL;
		}

		// If there is no note id, just insert a new note
		if (empty($id))
		{
			$wpdb->query($wpdb->prepare("
				INSERT INTO $this->bfox_quicknotes
				SET user = %d, note = %s, verse_start = %d, verse_end = %d, date_created = NOW(), date_modified = NOW()",
				$user_ID, $note, $verse_start, $verse_end));
			$id = $wpdb->insert_id;
		}

		return $id;
	}

	/**
	 * Deletes the quick note specified by the given ID
	 *
	 * The current user must be the owner of the note for it to be deleted
	 *
	 * @param integer $id
	 */
	function delete_quicknote($id)
	{
		global $user_ID, $wpdb;

		// Make sure that we are deleting a note for the current user
		$note_user = $wpdb->get_var($wpdb->prepare("SELECT user FROM $this->bfox_quicknotes WHERE id = %d", $id));
		if ($note_user == $user_ID)
		{
			$wpdb->query($wpdb->prepare("DELETE FROM $this->bfox_quicknotes WHERE id = %d", $id));
		}
	}

	/**
	 * Returns the quicknotes in a given passage of scripture.
	 *
	 * @param BibleRefs $refs
	 * @return unknown
	 */
	function get_quicknotes(BibleRefs $refs)
	{
		global $wpdb;

		// TODO3: Allow viewing friends quick notes

		global $user_ID;

		$user_where = $wpdb->prepare('user = %d', $user_ID);
		$ref_where = $refs->sql_where2("verse_start", "verse_end");

		return (array) $wpdb->get_results("
			SELECT *
			FROM $this->bfox_quicknotes
			WHERE $user_where AND $ref_where
			ORDER BY verse_start, verse_end, date_created
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
		$ref_str_short = $refs->get_string('short');
		return "<a href='#none' id='quick_note_link_$id' onclick=\"bfox_edit_quick_note('$id', '$note', '$ref_str')\" onmouseover=\"bfox_note_popup_show(this, '<b>$ref_str_short:</b> $note')\" onmouseout=\"bfox_note_popup_hide()\">[note]</a>";
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
	global $bfox_quicknote;

	$id = $_POST['note_id'];
	$note = $_POST['note'];
	$ref_str = $_POST['ref_str'];

	$verse_id = "#quick_note_link_$id";

	// If we have a note or bible reference, we can modify the note
	// Otherwise we should delete the note
	if ('' != $note || '' != $ref_str)
	{
		$refs = new BibleRefs($ref_str);
		list($unique_ids) = $refs->get_sets();
		$section_id = "#quick_notes_$unique_ids[0]";

		// If the original id was blank, then we are creating a new note
		if (0 == $id) $verse_id = '';

		// Modify the note
		$id = $bfox_quicknote->save_quicknote($refs, $note, $id);

//		$bfox_quicknote->set_biblerefs($refs);
		$link = addslashes($bfox_quicknote->get_note_link($id, $note, $refs));

		$message = __('Saved!');
	}
	else
	{
		// Delete the note
		$bfox_quicknote->delete_quicknote($id);
		$section_id = '';
		$link = '';
		$message = __('Deleted!');
	}

	$script = "bfox_quick_note_modified('$message', '$section_id', '$link', '$verse_id')";
	die($script);
}
add_action('wp_ajax_bfox_ajax_save_quick_note', 'bfox_ajax_save_quick_note');


?>
