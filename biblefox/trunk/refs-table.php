<?php

class BfoxRefsTable {
	private $table_name;
	private $data_table_name;
	private $item_id_definition;

	public function __construct($data_table_name) {
		$this->table_name = $data_table_name . '_bfox_refs';
		$this->data_table_name = $data_table_name;
		$this->set_item_id_definition(array('item_id' => '%d'));
	}

	/**
	 * Sets the item ID definition
	 *
	 * An item ID definition is an associative array:
	 * The keys are column names for columns that are used to ID a given item
	 * The values are strings accepted by $wpdb->prepare() ('%s' and '%d') to specify how to prepare the value for SQL statements
	 *
	 * @param array $item_id_definition
	 */
	public function set_item_id_definition($item_id_definition) {
		$this->item_id_definition = $item_id_definition;
	}

	public function check_install($version) {
		if (get_site_option($this->table_name . '_version') < $version) {
			require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );
			dbDelta("CREATE TABLE $this->table_name (
					item_id BIGINT(20) NOT NULL,
					verse_begin MEDIUMINT UNSIGNED NOT NULL,
					verse_end MEDIUMINT UNSIGNED NOT NULL,
					INDEX (item_id),
					INDEX (verse_begin, verse_end)
				);"
			);
			update_site_option($this->table_name . '_version', $version);
		}
	}

	/**
	 * Returns a string with SQL for joining the Refs Table in a FROM statement
	 * @param $short_table_name
	 * @return string
	 */
	public function from_sql($short_table_name = 'refs') {
		return "$this->table_name $short_table_name";
	}

	/**
	 * Returns a string with SQL for a WHERE statement to specify the columns to join on
	 * @param string $join_col
	 * @param string $short_table_name
	 * @return string
	 */
	public function join_where($join_col, $short_table_name = 'refs') {
		return "($join_col = $short_table_name.item_id)";
	}

	/**
	 * Returns a string with SQL for a WHERE statement to specify the Refs to select
	 * @param BfoxRefs $refs
	 * @param string $short_table_name
	 * @return string
	 */
	public function refs_where(BfoxRefs $refs, $short_table_name = 'refs') {
		return $refs->sql_where2("$short_table_name.verse_begin", "$short_table_name.verse_end");
	}

	/**
	 * Prepares an item ID for use in DB queries
	 *
	 * @param array $item_id
	 * @return array
	 */
	public function prepare_item_id($item_id) {
		if (!is_array($item_id)) $item_id = array('item_id' => $item_id);

		global $wpdb;

		$prepared_id = array();
		foreach ($this->item_id_definition as $key => $type) if (isset($item_id[$key])) $prepared_id[$key] = $wpdb->prepare($type, $item_id[$key]);
		return $prepared_id;
	}

	/**
	 * Creates an SQL where statement for this item ID
	 *
	 * @param array $item_id
	 * @return string
	 */
	private function item_id_where($item_id) {
		if (!is_array($item_id)) $item_id = array('item_id' => $item_id);

		global $wpdb;

		$wheres = array();
		foreach ($this->item_id_definition as $key => $type) if (isset($item_id[$key])) $wheres []= $wpdb->prepare("($key = $type)", $item_id[$key]);
		return implode(' AND ', $wheres);
	}

	/**
	 * Delete the items specified in the $item_ids array
	 *
	 * @param $item_ids
	 * @return unknown_type
	 */
	public function delete_items($item_ids) {
		global $wpdb;
		$wheres = array();
		foreach ($item_ids as $item_id) $wheres []= $this->item_id_where($item_id);
		$wpdb->query("DELETE FROM $this->table_name WHERE " . implode(' OR ', $wheres));
	}

	/**
	 * Delete simple items which are identified by a single item_id
	 *
	 * @param mixed $item_ids
	 */
	public function delete_simple_items($item_ids) {
		global $wpdb;
		if (!is_array($item_ids)) $item_ids = array($item_ids);
		$wpdb->query("DELETE FROM $this->table_name WHERE item_id IN (" . implode(',', $wpdb->escape($item_ids)) . ")");
	}

	/**
	 * Save Bible References for the given item ID
	 *
	 * @param array $item_id
	 * @param BfoxRefs $refs
	 * @return boolean (TRUE if there were actually Bible References to save)
	 */
	public function save_item($item_id, BfoxRefs $refs) {
		$this->delete_items(array($item_id));

		if ($refs->is_valid()) {
			global $wpdb;

			$item_id = $this->prepare_item_id($item_id);

			$item_id_value = implode(', ', $item_id);
			$item_id_col = implode(', ', array_keys($item_id));

			$values = array();
			foreach ($refs->get_seqs() as $seq) $values []= $wpdb->prepare("($item_id_value, %d, %d)", $seq->start, $seq->end);

			if (!empty($values)) {
				$wpdb->query($wpdb->prepare("
					INSERT INTO $this->table_name
					($item_id_col, verse_begin, verse_end)
					VALUES " . implode(', ', $values)));
			}
			return true;
		}
		return false;
	}

	/**
	 * Deletes all data in the SQL table
	 */
	public function delete_all() {
		global $wpdb;
		$wpdb->query("DELETE FROM $this->table_name");
	}

	/**
	 * Refreshes the refs table with data from the data table
	 *
	 * Returns the next offset to use, or 0 if all items have been refreshed
	 *
	 * @param unknown_type $id_col
	 * @param unknown_type $content_col
	 * @param unknown_type $limit
	 * @param unknown_type $offset
	 * @return number The next offset to use, or 0 if all items have been refreshed
	 */
	public function simple_refresh($id_col, $content_col, $limit = 0, $offset = 0) {
		global $wpdb;

		$limit = (int)$limit;
		$offset = (int)$offset;
		if (0 == $limit) $limit = 100;

		$results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS $id_col, $content_col FROM $this->data_table_name ORDER BY $id_col ASC LIMIT $offset, $limit");
		$total = $wpdb->get_var('SELECT FOUND_ROWS()');

		$scanned = count($results);
		$indexed = 0;

		foreach ($results as $data) if ($this->save_item($data->$id_col, new BfoxRefs($data->$content_col))) $indexed++;

		return compact('scanned', 'indexed', 'total');
	}
}

?>