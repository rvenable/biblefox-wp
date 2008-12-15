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

		// If a note id was specified, then we are modifying a previously created note
		if (!is_null($id))
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
		if (is_null($id))
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

	function list_quicknotes(BibleRefs $refs)
	{
		$notes = $this->get_quicknotes($refs);
		foreach ($notes as $note)
		{
			$refs = new BibleRefs($note->verse_start, $note->verse_end);
			$content .= $refs->get_link() . ': ' . $note->note . '<br/>';
		}

		echo $content;
	}
}

global $bfox_quicknote;
$bfox_quicknote = new QuickNote();

?>
