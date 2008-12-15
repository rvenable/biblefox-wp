<?php

class QuickNote
{
	var $bfox_quicknotes;
	var $bfox_quicknote_refs;

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
	function get_quicknotes(BibleRefs $refs)
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
			ORDER BY refs.verse_start, refs.verse_end
			");
	}

	/**
	 * Returns the quicknotes in a given passage of scripture.
	 *
	 * @param BibleRefs $refs
	 * @return unknown
	 */
	function get_grouped_quicknotes(BibleRefs $refs)
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
	 * Returns an output string with a list of all the quick notes for a given bible reference
	 *
	 * @param BibleRefs $refs
	 * @return string
	 */
	function list_quicknotes(BibleRefs $refs)
	{
		$notes = $this->get_quicknotes($refs);
		foreach ($notes as $note)
		{
			$refs = new BibleRefs($note->verse_start, $note->verse_end);
			$edit = '<a class="edit_quick_note_link" onClick="bfox_edit_quick_note(' . $note->id . ')">[edit]</a>';
			$note_content = '<span id="quick_note_' . $note->id . '">' . $note->note . '</span>';
			$content .= "<tr><td>" . $refs->get_link() . "</td><td>$edit</td><td>$note_content</td></tr>";
		}

		return $content;
	}
}

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

	global $bfox_quicknote;
	$id = $bfox_quicknote->save_quicknote($refs, $note, $id);

	// Return the new list of quick notes
	// TODO2: This should return the list of quicknotes for the currently displayed passage (not the passages in the quick note)
	$content = addslashes($bfox_quicknote->list_quicknotes($refs));

	$script = "bfox_quick_note_saved('$content')";
	die($script);
}
add_action('wp_ajax_bfox_ajax_save_quick_note', 'bfox_ajax_save_quick_note');


?>
