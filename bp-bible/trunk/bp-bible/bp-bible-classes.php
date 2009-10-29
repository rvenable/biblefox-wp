<?php

/**
 * This function should include all classes and functions that access the database.
 * In most BuddyPress components the database access classes are treated like a model,
 * where each table has a class that can be used to create an object populated with a row
 * from the corresponding database table.
 *
 * By doing this you can easily save, update and delete records using the class, you're also
 * abstracting database access.
 */

class BfoxRefTable {

/*	function add_where_for_id(&$wheres, $args, $id_col = 'id') {
		global $wpdb;
		if ($id_col && $args) {
			$id_cols = $id_col . 's';
			if (is_array($args[$id_col])) $args[$id_cols] = $args[$id_col];
			if ($args[$id_cols]) $wheres []= "$id_col IN (" . implode(', ', $wpdb->escape($args[$id_cols]));
			elseif ($args[$id_col]) $wheres []= $wpdb->prepare("$id_col = %d", $args[$id_col]);
		}
	}
*/

	function get_ids($table_name, BfoxRefs $refs, $index_col) {
		$post_ids = array();

		if (!empty($user_ids)) {
			global $wpdb;

			foreach ($user_ids as &$user_id) $user_id = $wpdb->prepare('%d', $user_id);

			$results = $wpdb->get_results(
				"SELECT DISTINCT blog_id, post_id
				FROM " . self::table . "
				WHERE user_id IN (" . implode(',', $user_ids) . ") AND " . $refs->sql_where2());

			foreach ((array) $results as $result) $post_ids[$result->blog_id] []= $result->post_id;
		}

		return $post_ids;

	}

	function get_refs($table_name, $id_col_values, $index_col = '') {
		global $wpdb;

		$wheres = array();
		foreach ($id_col_values as $id_col => $value) {
			if (is_array($value)) $wheres []= "$id_col IN (" . implode(', ', $wpdb->escape($value)) . ')';
			else $wheres []= $wpdb->prepare("$id_col = %d", $value);
		}

		$results = $wpdb->get_results('SELECT * FROM ' . $table_name . ' WHERE ' . implode(' AND ', $wheres));

		if (!empty($results)) {
			if (!empty($index_col)) foreach ($results as $result) {
				if (!isset($refs[$result->$index_col])) $refs[$result->$index_col] = new BfoxRefs;
				$refs[$result->$index_col]->add_seq(new BfoxSequence($result->verse_begin, $result->verse_end));
			}
			else {
				$refs = new BfoxRefs;
				foreach ($results as $result) $refs->add_seq(new BfoxSequence($result->verse_begin, $result->verse_end));
			}
			return $refs;
		}
		return NULL;
	}

	function set_refs($table_name, $id_col_values, BfoxRefs $refs = NULL) {
		global $wpdb;

		$wheres = array();
		foreach ($id_col_values as $id_col => $value) $wheres []= $wpdb->prepare("$id_col = %d", $value);

		$wpdb->query('DELETE FROM ' . $table_name . ' WHERE ' . implode(' AND ', $wheres));

		if (!is_null($refs) && $refs->is_valid()) {
			$values = array();
			$id_col_values_str = implode(', ', $wpdb->escape($id_col_values));
			foreach ($refs->get_seqs() as $seq) $values []= $wpdb->prepare("($id_col_values_str, %d, %d)", $seq->start, $seq->end);

			if (!empty($values)) {
				$wpdb->query($wpdb->prepare("
					INSERT INTO " . $table_name . "
					(" . implode(', ', array_keys($id_col_values)) . ", verse_begin, verse_end)
					VALUES " . implode(', ', $values)));
			}
		}
	}
}

class BP_Bible_Note {
	var $id;
	var $user_id;
	var $modified_time;
	var $created_time;
	var $display_content;
	private $content;

	var $tag_refs = NULL;
	var $content_refs = NULL;

	public static $found_rows;

	const ref_type_tag = 0;
	const ref_type_content = 1;

	/**
	 * bp_bible_tablename()
	 *
	 * This is the constructor, it is auto run when the class is instantiated.
	 * It will either create a new empty object if no ID is set, or fill the object
	 * with a row from the table if an ID is provided.
	 */
	function bp_bible_note( $id = null ) {
		global $wpdb, $bp;

		if ( $id ) {
			$this->id = $id;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bp->bible->table_name_notes} WHERE id = %d", $this->id ) );
		}
		$this->populate_with_row($row);
	}

	/**
	 * populate_with_row()
	 *
	 * This method will populate the object with a row from the database, based on the
	 * ID passed to the constructor.
	 */
	function populate_with_row($row) {
		global $bp;

		$this->id = $row->id;

		if ($this->id) {
			$this->user_id = $row->user_id;
			$this->modified_time = $row->modified_time;
			$this->created_time = $row->created_time;

			// If row doesn't have the tag refs, get them from the note refs table
			if (isset($row->tag_refs)) $this->tag_refs = $row->tag_refs;
			else $this->tag_refs = BfoxRefTable::get_refs($bp->bible->table_name_note_refs, array('note_id' => $this->id, 'ref_type' => self::ref_type_tag));

			$this->set_content($row->content);
		}
		if (is_null($this->tag_refs)) $this->tag_refs = new BfoxRefs;
		if (is_null($this->content_refs)) $this->content_refs = new BfoxRefs;
	}

	function set_content($content) {
		$this->content = $content;

		// We don't need to query the DB for the content refs, because we can just parse the content
		$this->content_refs = new BfoxRefs;
		$this->display_content = BfoxRefParser::simple_html($this->content, $this->content_refs);
	}

	function get_editable_content() {
		return $this->content;
	}

	/**
	 * save()
	 *
	 * This method will save an object to the database. It will dynamically switch between
	 * INSERT and UPDATE depending on whether or not the object already exists in the database.
	 */

	function save() {
		global $wpdb, $bp;

		/***
		 * In this save() method, you should add pre-save filters to all the values you are saving to the
		 * database. This helps with two things -
		 *
		 * 1. Blanket filtering of values by plugins (for example if a plugin wanted to force a specific
		 *	  value for all saves)
		 *
		 * 2. Security - attaching a wp_filter_kses() call to all filters, so you are not saving
		 *	  potentially dangerous values to the database.
		 *
		 * It's very important that for number 2 above, you add a call like this for each filter to
		 * 'bp-bible-filters.php'
		 *
		 *   add_filter( 'bible_data_fieldname1_before_save', 'wp_filter_kses' );
		 */

		$this->set_content(apply_filters( 'bp_bible_note_content_before_save', $this->content, $this->id ));

		/* Call a before save action here */
		do_action( 'bp_bible_note_before_save', $this );

		if ( $this->id ) {
			// Update
			$result = $wpdb->query( $wpdb->prepare(
					"UPDATE {$bp->bible->table_name_notes} SET
						content = %s
					WHERE id = %d",
						$this->content,
						$this->id
					) );
		} else {
			// Save
			$result = $wpdb->query( $wpdb->prepare(
					"INSERT INTO {$bp->bible->table_name_notes} (
						user_id,
						created_time,
						content
					) VALUES (
						%d, NOW(), %s
					)",
						$this->user_id,
						$this->content
					) );
		}

		if ( false === $result )
			return false;

		if ( !$this->id ) {
			$this->id = $wpdb->insert_id;
		}

		if ($this->id) {
			// Save the tag and content refs to the DB
			BfoxRefTable::set_refs($bp->bible->table_name_note_refs, array('note_id' => $this->id, 'ref_type' => self::ref_type_tag), $this->tag_refs);
			BfoxRefTable::set_refs($bp->bible->table_name_note_refs, array('note_id' => $this->id, 'ref_type' => self::ref_type_content), $this->content_refs);
		}

		/* Add an after save action here */
		do_action( 'bp_bible_note_after_save', $this );

		return ($result !== false);
	}

	/**
	 * delete()
	 *
	 * This method will delete the corresponding row for an object from the database.
	 */
	function delete() {
		global $wpdb, $bp;

		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->bible->table_name_notes} WHERE id = %d", $this->id ) );
	}

	/* Static Functions */

	/**
	 * Static functions can be used to bulk delete items in a table, or do something that
	 * doesn't necessarily warrant the instantiation of the class.
	 *
	 * Look at bp-core-classes.php for bibles of mass delete.
	 */

	function get_notes($args = array()) {
		global $wpdb, $bp;

		extract($args);

		$join = '';
		$wheres = array();
		$wheres []= $wpdb->prepare('user_id = %d', $bp->loggedin_user->id);

		// If we are looking for refs, we should query the note_refs table first to get note_ids
		if ($refs && $refs->is_valid()) {
			//$ref_wheres = $wheres;
			$wheres []= $refs->sql_where2();
			//$note_ids = $wpdb->get_col("SELECT DISTINCT note_id FROM {$bp->bible->table_name_note_refs} WHERE " . implode(' AND ', $ref_wheres));
			$join = "INNER JOIN {$bp->bible->table_name_note_refs} ON id = note_id";
		}

		// Add a where for note_id
		if ($note_ids) $wheres []= 'id IN (' . implode(', ', $wpdb->escape($note_ids)) . ')';

		// Handle Limits
		$found_rows = '';
		if ($limit) {
			$limit = $wpdb->prepare("LIMIT %d, %d", $limit * max($page - 1, 0), $limit);
			$found_rows = 'SQL_CALC_FOUND_ROWS';
		}

		// Get the notes from the notes table
		$results = (array) $wpdb->get_results("
			SELECT $found_rows *
			FROM {$bp->bible->table_name_notes} $join
			WHERE " . implode(' AND ', $wheres) . "
			GROUP BY id
			ORDER BY modified_time DESC
			$limit
		");

		if (!empty($found_rows)) self::$found_rows = $wpdb->get_var('SELECT FOUND_ROWS()');

		// Get any tag references from the note_refs table
		$note_ids = array();
		foreach ($results as $result) $note_ids []= $result->id;
		if (!empty($note_ids)) $tag_refs_list = BfoxRefTable::get_refs($bp->bible->table_name_note_refs, array('note_id' => $note_ids, 'ref_type' => self::ref_type_tag), 'note_id');

		// Create instances of BP_Bible_Note for each result
		$notes = array();
		foreach ($results as $result) {
			if (isset($tag_refs_list[$result->id])) $result->tag_refs = $tag_refs_list[$result->id];
			else $result->tag_refs = new BfoxRefs;

			$new_note = new BP_Bible_Note;
			$new_note->populate_with_row($result);
			$notes []= $new_note;
		}

		return $notes;
	}

	function delete_all() {
		global $wpdb, $bp;

		$wpdb->query( "DELETE FROM {$bp->bible->table_name_notes}" );
		$wpdb->query( "DELETE FROM {$bp->bible->table_name_note_refs}" );
	}

	function delete_by_user_id() {
		global $wpdb, $bp;

		// TODO: delete from refs table also!
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->bible->table_name_notes} WHERE user_id = %d", $bp->loggedin_user->id ) );
	}

}

?>