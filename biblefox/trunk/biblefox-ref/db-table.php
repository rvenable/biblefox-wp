<?php

class BfoxSequenceDbTable {
	protected $table_name;
	protected $data_table_name;
	protected $item_id_definition;

	public function __construct($data_table_name, $postfix = '_bfox_seqs') {
		$this->table_name = $data_table_name . $postfix;
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
					start MEDIUMINT UNSIGNED NOT NULL,
					end MEDIUMINT UNSIGNED NOT NULL,
					KEY item_id (item_id),
					KEY sequence (start,end)
				);"
			);
			update_site_option($this->table_name . '_version', $version);
		}
	}

	/**
	 * Returns a string with SQL for joining the Sequence Table in a FROM statement
	 * @param $short_table_name
	 * @return string
	 */
	public function from_sql($short_table_name = 'seqs') {
		return "$this->table_name $short_table_name";
	}

	/**
	 * Returns a string with SQL for joining the Sequence Table in a JOIN statement
	 * @param string $join_col
	 * @param string $short_table_name
	 * @return string
	 */
	public function join_sql($join_col, $short_table_name = 'seqs') {
		return "JOIN $this->table_name AS $short_table_name ON " . $this->join_where($join_col, $short_table_name);
	}

	/**
	 * Returns a string with SQL for a WHERE statement to specify the columns to join on
	 * @param string $join_col
	 * @param string $short_table_name
	 * @return string
	 */
	public function join_where($join_col, $short_table_name = 'seqs') {
		return "($join_col = $short_table_name.item_id)";
	}

	/**
	 * Returns a string with SQL for a WHERE statement to specify the sequence to select
	 * @param BfoxSequenceList $seqs
	 * @param string $short_table_name
	 * @return string
	 */
	public function seqs_where(BfoxSequenceList $seqs, $short_table_name = 'seqs') {
		return $seqs->sql_where2("$short_table_name.start", "$short_table_name.end");
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
	 * Save a sequence list for the given item ID
	 *
	 * @param array $item_id
	 * @param BfoxSequenceList $seqs
	 * @return boolean (TRUE if there were actually sequences to save)
	 */
	public function save_item($item_id, BfoxSequenceList $seqs) {
		$this->delete_items(array($item_id));

		if ($seqs->is_valid()) {
			global $wpdb;

			$item_id = $this->prepare_item_id($item_id);

			$item_id_value = implode(', ', $item_id);
			$item_id_col = implode(', ', array_keys($item_id));

			$values = array();
			foreach ($seqs->get_seqs() as $seq) $values []= $wpdb->prepare("($item_id_value, %d, %d)", $seq->start, $seq->end);

			if (!empty($values)) {
				$wpdb->query($wpdb->prepare("
					INSERT INTO $this->table_name
					($item_id_col, start, end)
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
}

class BfoxRefDbTable extends BfoxSequenceDbTable {

	public function __construct($data_table_name, $postfix = '_bfox_refs') {
		parent::__construct($data_table_name, $postfix);
	}

	public function refresh_select($id_col, $content_col, $limit = 0, $offset = 0) {
		return "* FROM $this->data_table_name ORDER BY $id_col ASC LIMIT $offset, $limit";
	}

	public function save_data_row($data_row, $id_col, $content_col) {
		return $this->save_item($data_row->$id_col, new BfoxRef($data_row->$content_col));
	}

	/**
	 * Refreshes the ref table with data from the data table
	 *
	 * Returns an array with counts: the number of scanned items, indexed items, and total items to scan
	 *
	 * @param BfoxRefDbTable $db_table
	 * @param string $id_col
	 * @param string $content_col
	 * @param integer $limit
	 * @param integer $offset
	 * @return array
	 */
	public static function simple_refresh(BfoxRefDbTable $db_table, $id_col, $content_col, $limit = 0, $offset = 0) {
		global $wpdb;

		$limit = (int) $limit;
		$offset = (int) $offset;
		if (0 == $limit) $limit = 100;

		$results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS " . $db_table->refresh_select($id_col, $content_col, $limit, $offset));
		$total = $wpdb->get_var('SELECT FOUND_ROWS()');

		$scanned = count($results);
		$indexed = 0;

		foreach ($results as $data_row) if ($db_table->save_data_row($data_row, $id_col, $content_col)) $indexed++;

		return compact('scanned', 'indexed', 'total');
	}
}

?>