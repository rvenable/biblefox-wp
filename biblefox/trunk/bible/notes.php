<?php

define(BFOX_TABLE_NOTES, BFOX_BASE_TABLE_PREFIX . 'notes');
define(BFOX_TABLE_NOTE_REFS, BFOX_BASE_TABLE_PREFIX . 'note_refs');

class BfoxNote {

	public $id = 0;
	private $user_id = 0;
	private $content = '';
	private $created_time = 0;
	private $modified_time = 0;

	/**
	 * The bible references
	 *
	 * @var BfoxRefs
	 */
	private $refs;

	public function __construct($values = NULL) {
		if (is_object($values)) $this->set_from_db($values);
		else $this->set_content('');
	}

	public function set_from_db(stdClass $db_data) {
		$this->id = $db_data->id;
		$this->user_id = $db_data->user_id;
		$this->created_time = $db_data->created_time;
		$this->modified_time = $db_data->modified_time;
		$this->set_content($db_data->content);
	}

	public function set_user_id($user_id) {
		$this->user_id = $user_id;
	}

	public function get_user_id() {
		return $this->user_id;
	}

	public function set_content($content) {
		$this->content = $content;
		$this->refs = new BfoxRefs;
		$this->display_content = wpautop(BfoxRefParser::simple_html($this->content, $this->refs));
	}

	public function get_content() {
		return $this->content;
	}

	public function get_display_content() {
		return $this->display_content;
	}

	public function get_title() {
		list($title, $body) = explode("\n", $this->content, 2);
		return $title;
	}

	/**
	 * Returns the bible references calculated for this note
	 *
	 * @return BfoxRefs
	 */
	public function get_refs() {
		return $this->refs;
	}

	private static function get_time($time_str, $format = '') {
		if (empty($format)) return $time_str;
		else return date($format, strtotime($time_str));
	}

	public function get_modified($format = '') {
		return self::get_time($this->modified_time, $format);
	}

	public function get_created($format = '') {
		return self::get_time($this->created_time, $format);
	}
}

class BfoxNotes {

	const table_notes = BFOX_TABLE_NOTES;
	const table_refs = BFOX_TABLE_NOTE_REFS;

	public static function create_tables() {
		// Note: for user_id (aka. owner) see WP's implementation in wp-admin/includes/schema.php

		BfoxUtility::create_table(self::table_notes, "
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			modified_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			created_time TIMESTAMP NOT NULL,
			content LONGTEXT NOT NULL,
			PRIMARY KEY  (id)");

		BfoxUtility::create_table(self::table_refs, "
			note_id BIGINT UNSIGNED NOT NULL,
			verse_begin MEDIUMINT UNSIGNED NOT NULL,
			verse_end MEDIUMINT UNSIGNED NOT NULL");
	}

	public static function save_note(BfoxNote &$note, $user_id = 0) {
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		// We can only save this note if it is a new note, or we are using the appropriate user
		if (!empty($user_id) && (empty($note->id) || ($note->get_user_id() == $user_id))) {
			global $wpdb;

			if (empty($note->id)) {
				$wpdb->query($wpdb->prepare("INSERT INTO " . self::table_notes . "
					SET user_id = %d, content = %s, created_time = CURRENT_TIMESTAMP",
					$user_id, $note->get_content()));
				$note->id = $wpdb->insert_id;
				$note->set_user_id($user_id);
			}
			else {
				$wpdb->query($wpdb->prepare("UPDATE " . self::table_notes . " SET content = %s WHERE id = %d", $note->get_content(), $note->id));

				// Delete any previous note references because we will be adding the new ones
				$wpdb->query($wpdb->prepare('DELETE FROM ' . self::table_refs . ' WHERE note_id = %d', $note->id));
			}

			// Save the note references
			$values = array();

			$refs = $note->get_refs();
			foreach ($refs->get_seqs() as $seq) $values []= $wpdb->prepare('(%d, %d, %d)', $note->id, $seq->start, $seq->end);

			if (!empty($values)) $wpdb->query("INSERT INTO " . self::table_refs . " (note_id, verse_begin, verse_end) VALUES " . implode(',', $values));
		}
	}

	public static function get_notes($note_ids = array(), $user_id = 0) {
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		$notes = array();

		if (!empty($user_id)) {
			global $wpdb;

			if (!empty($note_ids)) {
				$ids = array();
				foreach ($note_ids as $note_id) if (!empty($note_id)) $ids []= $wpdb->prepare('%d', $note_id);
				if (!empty($ids)) $id_where = 'AND id IN (' . implode(',', $ids) . ')';
			}

			$results = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table_notes . " WHERE user_id = %d $id_where", $user_id));
			foreach ($results as $result) $notes[$result->id] = new BfoxNote($result);

			// Note: we don't need to retrieve the bible references because we can generate them from the note content
		}

		return $notes;
	}

	public static function get_note($note_id) {
		global $wpdb;
		if (!empty($note_id)) $db_data = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table_notes . ' WHERE id = %d', $note_id));
		return new BfoxNote($db_data);
	}
}

?>