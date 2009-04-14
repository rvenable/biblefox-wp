<?php
// TODO2: This define probably needs to go somewhere else
define('BFOX_SETUP_DIR', dirname(__FILE__) . "/setup");

global $bfox_toolboxes;
$bfox_toolboxes = array();

class BfoxAdminTools
{
	public static function init()
	{
		if (is_site_admin())
		{
			include_once('admin_toolbox.php');

			add_action('admin_menu', array('BfoxAdminTools', 'add_menu'));
		}
	}

	public static function add_toolbox(BfoxToolbox $toolbox)
	{
		global $bfox_toolboxes;
		$bfox_toolboxes []= $toolbox;
	}

	public static function add_menu()
	{
		global $bfox_toolboxes;
		foreach ($bfox_toolboxes as $toolbox)
		{
			$class = get_class($toolbox);
			add_submenu_page('wpmu-admin.php', $class, $class, 10, "bfox_$class", array($toolbox, 'menu'));
		}
	}
}
add_action('init', array('BfoxAdminTools', 'init'));

class BfoxToolbox
{
	/**
	 * Displays the admin tools menu
	 *
	 */
	public function menu()
	{
		$class = get_class($this);
		echo "<div class='wrap'><h2>$class</h2>";

		$tools = get_class_methods($class);
		foreach ($tools as $tool)
		{
			// Don't show tools after menu, because they are all of the BfoxToolbox class instead of its child
			if ('menu' == $tool) break;

			echo '<a href="' . bfox_admin_page_url('bfox_' . $class) . '&amp;tool=' . $tool . '">' . $tool . '</a><br/>';
		}

		$tool = $_GET['tool'];
		if (isset($tool))
		{
			global $wpdb;
			$wpdb->show_errors(TRUE);

			echo '<h2>' . $tool . '</h2>';
			$admin_tools = new BfoxAdminTools();
			$func = array($admin_tools, $tool);
			if (is_callable($func)) call_user_func($func);

			$wpdb->show_errors(FALSE);
		}
		echo '</div>';
	}

	/**
	 * Private function for echoing the results of a DB table query
	 *
	 * @param unknown_type $results
	 * @param unknown_type $cols
	 */
	protected static function echo_table_results($results, $cols = NULL)
	{
		$rows = '';
		if (is_array($results))
		{
			foreach ($results as $row)
			{
				if (!is_array($cols))
					if (is_array($row)) $cols = array_keys($row);
					else $cols = array_keys(get_object_vars($row));
				$rows .= '<tr>';
				foreach ($row as $col) $rows .= '<td>' . $col . '</td>';
				$rows .= '</tr>';
			}
			echo '<table><tr>';
			foreach ($cols as $col) echo '<th>' . $col . '</th>';
			echo '</tr>' . $rows . '</table>';
		}
	}

	/**
	 * Private function for echoing DB tables
	 *
	 * @param unknown_type $table_name
	 * @param unknown_type $cols
	 */
	protected static function echo_table($table_name, $cols = NULL)
	{
		global $wpdb;
		$results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
		$this->echo_table_results($results, $cols);
	}

	/**
	 * Private function for echoing the description of a DB table
	 *
	 * @param unknown_type $table_name
	 * @param unknown_type $cols
	 */
	protected static function echo_table_describe($table_name, $cols = NULL)
	{
		global $wpdb;
		$results = $wpdb->get_results("DESCRIBE $table_name", ARRAY_A);
		$this->echo_table_results($results, $cols);
	}

}

?>
