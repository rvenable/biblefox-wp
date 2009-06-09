<?php

class BfoxUtility
{
	public static function create_table($table, $column_list)
	{
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta("CREATE TABLE $table (
				$column_list
			);"
		);
	}

	public static function register_script($handle, $src_file, $deps = array(), $version = BFOX_VERSION) {
		wp_register_script($handle, BFOX_URL . '/' . $src_file, $deps, $version);
	}

	public static function register_style($handle, $src_file, $deps = array(), $version = BFOX_VERSION) {
		wp_register_style($handle, BFOX_URL . '/' . $src_file, $deps, $version);
	}

	public static function enqueue_script($handle, $src_file = '', $deps = array(), $version = BFOX_VERSION) {
		if (empty($src_file)) wp_enqueue_script($handle);
		else wp_enqueue_script($handle, BFOX_URL . '/' . $src_file, $deps, $version);
	}

	public static function enqueue_style($handle, $src_file = '', $deps = array(), $version = BFOX_VERSION) {
		if (empty($src_file)) wp_enqueue_style($handle);
		else wp_enqueue_style($handle, BFOX_URL . '/' . $src_file, $deps, $version);
	}

	public static function divide_into_cols($array, $max_cols, $height_threshold = 0)
	{
		$count = count($array);
		if (0 < $count)
		{

			// The height_threshold is so that we don't divide into too many columns for small arrays
			// So, for instance, if we have 3 max columns and 5 array elements, and a threshold of 5, we shouldn't
			// divide that into 3 short columns, but one column of 5
			if (0 == $height_threshold)
				$cols = $max_cols;
			else
				$cols = ceil($count / $height_threshold);

			if ($cols > $max_cols) $cols = $max_cols;

			$array = array_chunk($array, ceil($count / $cols), TRUE);
		}
		return $array;
	}

	/*
	 This function converts a date string to the specified format, using the local timezone
	 Parameters:
	 date_str - should be a datetime string acceptable by strtotime()
		If date_str is not acceptable, 'today' will be used instead
	 format - should be a format string acceptable by date()

	 The function implements workarounds for some shortcomings of the strtotime() function:
	 Essentially, strtotime() accepts many useful strings such as 'today', 'next tuesday', '10/14/2008', etc.
	 These strings are calculated using the default timezone (date_default_timezone_get()), which isn't necessarily
	 the timezone set for the blog. In order to have full support for all those useful strings and still get results in our
	 desired timezone, we have to temporarily change the timezone, get the timestamp from strtotime(), format it using date(),
	 then finally reset the timezone back to its original state.
	 */
	public static function format_local_date($date_str, $format = 'm/d/Y')
	{
		// Get the current default timezone because we need to set it back when we are done
		$tz = date_default_timezone_get();

		// Get this blog's GMT offset (as an integer because date_default_timezone_set() doesn't support minute increments)
		$gmt_offset = (int)(get_option('gmt_offset'));

		// Invert the offset for use in date_default_timezone_set()
		$gmt_offset *= -1;

		// If the offset is positive (or 0), add the + to the beginning
		if ($gmt_offset >= 0) $gmt_offset = '+' . $gmt_offset;

		// Temporarily set the timezone to the blog's timezone
		date_default_timezone_set('Etc/GMT' . $gmt_offset);

		// Get the date string
		if (($time = strtotime($date_str)) === FALSE) $time = strtotime('today');
		$date_str = date($format, $time);

		// Set the timezone back to its previous setting
		date_default_timezone_set($tz);

		return $date_str;
	}

	/**
	 * Returns whether a table exists or not
	 *
	 * @param string $table_name
	 * @return boolean
	 */
	public static function does_table_exist($table_name)
	{
		global $wpdb;
		return (bool) ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);
	}

	/**
	 * Finds the footnotes in a string and returns their offset, length, and content
	 *
	 * @param string $content
	 * @return array of array(offset, length, content)
	 */
	public static function find_footnotes($str)
	{
		$footnotes = array();
		if (preg_match_all('/<footnote>(.*?)<\/footnote>/', $str, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
		{
			// Add the matches as an array(offset, length, content)
			foreach ($matches as $match) $footnotes []= array(
				$match[0][1], // offset
				strlen($match[0][0]), // length
				$match[1][0] // footnote content
			);
		}

		return $footnotes;
	}

	public static function hidden_input($name, $value = '') {
		return "<input type='hidden' name='$name' value='$value'/>";
	}

	/**
	 * Parses a URL to prepare it for use in a POST form
	 *
	 * Parses any variables out of the URL and adds them as hidden inputs. Returns an array with the trimmed URL and the hidden inputs.
	 *
	 * @param string $url
	 * @return array (string URL, string hidden inputs)
	 */
	public static function get_post_url($url) {
		list($path, $query) = explode('?', $url);
		$hiddens = '';
		parse_str($query, $vars);
		foreach ($vars as $var => $value) $hiddens .= self::hidden_input($var, $value);
		return array($path, $hiddens);
	}

	/**
	 * Returns a LIMIT string for SQL.
	 *
	 * @param mixed $limit Can be an integer, or an array with the offset and count
	 * @return string
	 */
	public static function limit_str($limit) {
		$limit_str = '';
		if (!empty($limit)) {
			global $wpdb;

			if (is_int($limit)) $limit_str = $wpdb->prepare("LIMIT %d", $limit);
			elseif (is_array($limit)) {
				list($offset, $count) = $limit;
				$limit_str = $wpdb->prepare("LIMIT %d, %d", $offset, $count);
			}
		}
		return $limit_str;
	}
}

class BfoxHtmlElement {
	protected $attrs = '';

	public function __construct($attrs = '') {
		$this->attrs = $attrs;
	}

	public function set_attrs($attrs) {
		$this->attrs = $attrs;
	}
}

class BfoxHtmlRow extends BfoxHtmlElement {
	private $cols = array();
	private $sub_row = '';
	public $sort_val = 0;

	public function __construct() {
		$args = func_get_args();
		$attrs = array_shift($args);
		$this->add_cols($args);
		parent::__construct($attrs);
	}

	public function add_col($col, $attrs = '', $type = 'td') {
		if (is_array($col)) list($new_col, $attrs) = $col;
		else $new_col = $col;

		$this->cols []= "<$type $attrs>$new_col</$type>";
	}

	public function add_header_col($col, $attrs = '') {
		$this->add_col($col, $attrs, 'th');
	}

	public function add_cols($cols, $attrs = '') {
		foreach ((array) $cols as $col) $this->add_col($col, $attrs);
	}

	public function add_header_cols($cols, $attrs = '') {
		foreach ((array) $cols as $col) $this->add_header_col($col, $attrs);
	}

	public function add_sub_row($text, $col_span = 0) {
		if (empty($col_span)) $col_span = count($this->cols);
		$this->sub_row = "	<tr class='sub_row'><td colspan='$col_span'>$text</td></tr>\n";
	}

	public function add_sort_val($val) {
		$this->sort_val = $val;
	}

	public function content($extra_class = '') {
		// TODO3: This extra_class param isn't safe if $this->attrs already has a class
		$attrs = $this->attrs;
		if (!empty($extra_class)) $attrs .= " class='$extra_class'";

		$content = "	<tr $attrs>\n";
		foreach ($this->cols as $col) $content .= "		$col\n";
		$content .= "	</tr>\n";
		if (!empty($this->sub_row)) $content .= $this->sub_row;
		return $content;
	}

	public static function cmp(BfoxHtmlRow $a, BfoxHtmlRow $b) {
		if ($a->sort_val == $b->sort_val) return 0;
		return ($a->sort_val < $b->sort_val) ? -1 : 1;
	}
}

class BfoxHtmlTable extends BfoxHtmlElement {
	private $header_rows = array();
	private $rows = array();
	private $footer_rows = array();
	private $caption = '';
	private $alternates = array();

	public function __construct($attrs = '', $caption = '', $alternates = array('odd_row', 'even_row')) {
		$this->caption = $caption;
		$this->alternates = $alternates;
		parent::__construct($attrs);
	}

	private static function prepare_row($func, $args) {
		$attrs = array_shift($args);

		if (is_a($attrs, BfoxHtmlRow)) $row = $attrs;
		else $row = new BfoxHtmlRow($attrs);

		$num_cols = array_shift($args);
		foreach (array_pad($args, $num_cols, '') as $col) $row->$func($col);

		return $row;
	}

	public function add_row($row = '', $num_cols = 0) {
		$args = func_get_args();
		$this->rows []= self::prepare_row(add_col, $args);
	}

	public function add_header_row($row = '', $num_cols = 0) {
		$args = func_get_args();
		$this->header_rows []= self::prepare_row(add_header_col, $args);
	}

	public function add_footer_row($row = '', $num_cols = 0) {
		$args = func_get_args();
		$this->footer_rows []= self::prepare_row(add_header_col, $args);
	}

	public function row_count() {
		return count($this->rows);
	}

	private static function row_section($section, $rows, $sort = FALSE, $alternates = array()) {
		$content = '';

		$num_alt = count($alternates);

		if ($sort) usort($rows, 'BfoxHtmlRow::cmp');

		if (!empty($rows)) {
			$content = "<$section>\n";
			foreach ($rows as $index => $row) {
				if (0 < $num_alt) $class = $alternates[$index % $num_alt];
				$content .= $row->content($class);
			}
			$content .= "</$section>\n";
		}
		return $content;
	}

	public function content($sort = FALSE) {
		if (!empty($this->caption)) $caption = "<caption>$this->caption</caption>";
		return "<table $this->attrs>\n" .
			$caption .
			self::row_section('thead', $this->header_rows) .
			self::row_section('tbody', $this->rows, $sort, $this->alternates) .
			self::row_section('tfoot', $this->footer_rows) .
			"</table>\n";
	}

	public function get_split_row($max_cols, $height_threshold = 0) {
		$row = new BfoxHtmlRow("valign='top'");
		$columns = BfoxUtility::divide_into_cols($this->rows, $max_cols, $height_threshold);
		foreach ($columns as $rows) {
			$row->add_col("<table $this->attrs>\n" .
				self::row_section('thead', $this->header_rows) .
				self::row_section('tbody', $rows) .
				self::row_section('tfoot', $this->footer_rows) .
				"</table>");
		}
		return $row;
	}
}

class BfoxHtmlOptionTable extends BfoxHtmlTable {
	private $form_attrs = '';
	private $pre = '';
	private $post = '';

	public function __construct($attrs = '', $form_attrs = '', $pre = '', $post = '') {
		parent::__construct($attrs);
		$this->form_attrs = $form_attrs;
		$this->pre = $pre;
		$this->post = $post;
	}

	public function add_option($title, $pre, $option, $post) {
		if (is_array($option)) list($id, $new_option) = $option;
		else $new_option = $option;

		$row = new BfoxHtmlRow();
		$row->add_header_col("<label for='$id'>$title</label>", "scope='row' valign='top'");
		$row->add_col($pre . $new_option . $post);
		$this->add_row($row);
	}

	public static function option_text($id, $value = '', $extra_attrs = '') {
		return array($id, "<input name='$id' id='$id' type='text' value='$value' $extra_attrs/>");
	}

	public static function option_textarea($id, $value = '', $rows = 0, $cols = 0, $extra_attrs = '') {
		return array($id, "<textarea name='$id' id='$id' rows='$rows' cols='$cols' $extra_attrs/>$value</textarea>");
	}

	public static function option_array($id, $labels = array(), $checks = '', $extra_attrs = '') {
		$inputs = array();

		$name = $id;
		if (is_array($checks)) {
			$type = 'checkbox';
			if (1 < count($checks)) $name .= '[]';
		}
		else {
			$type = 'radio';
			$checks = array($checks => TRUE);
		}

		foreach ($labels as $value => $label) {
			if ($checks[$value]) $checked = "checked='checked'";
			else $checked = '';

			$inputs []= "<input type='$type' name='$name' id='$id' value='$value' $checked $extra_attrs />$label";
		}

		return array('', implode("<br/>\n", $inputs));
	}

	public static function option_check($id, $label, $check = '', $extra_attrs = '') {
		return self::option_array($id, array(1 => $label), array(1 => $check), $extra_attrs);
	}

	public function content() {
		return "<form $this->form_attrs>\n$this->pre\n" . parent::content() . "$this->post\n</form>\n";
	}
}

class BfoxHtmlList extends BfoxHtmlElement {
	private $lis = array();
	private $sort_vals = array();

	public function add($li, $sort_val = 0) {
		$this->lis []= $li;
		$this->sort_vals []= $sort_val;
	}

	public function content($sort = FALSE, $page_num = 0, $page_size = 20) {
		if ($sort) array_multisort($this->sort_vals, $this->lis);

		$content = "<ul $this->attrs>\n";
		$count = count($this->lis);
		for ($i = $page_num * $page_size; ($i < $count) && ($i < (($page_num + 1) * $page_size)); $i++) $content .= "<li>{$this->lis[$i]}</li>\n";
		$content .= "</ul>\n";

		return $content;
	}
}

class BfoxHtmlTabs extends BfoxHtmlElement {
	private $lis = array();
	private $sort_vals = array();

	public function add($id, $name, $content) {
		$this->tabs []= (object) array('id' => $id, 'name' => $name, 'content' => $content);
	}

	public function content() {
		if ($sort) array_multisort($this->sort_vals, $this->lis);

		$content = "<div $this->attrs>\n";
		$content .= "<ul>";
		foreach ($this->tabs as $tab) $content .= "<li><a href='#tab_$tab->id'>$tab->name</a></li>";
		$content .= "</ul>";
		foreach ($this->tabs as $tab) $content .= "<div id='tab_$tab->id'>$tab->content</div>";
		$content .= "</div>\n";

		return $content;
	}
}

// TODO3: The remaining functions may be obsolete

	function bfox_admin_page_url($page_name)
	{
		return get_option('siteurl') . '/wp-admin/admin.php?page=' . $page_name;
	}

	/*
	 This function takes some html input ($html) and processes its text using the $func callback.
	 It will skip all html tags and call $func for each chunk of text.
	 The $func function should take the text as its parameter and return the modified text.
	 */
	function bfox_process_html_text($html, $func, $params = array()) {
		if (!is_callable($func)) return $html;

		$text_start = 0;
		while (1 == preg_match('/<[^<>]*[^<>\s][^<>]*>/', $html, $matches, PREG_OFFSET_CAPTURE, $text_start)) {
			// Store the match data in more readable variables
			$text_end = (int) $matches[0][1];
			$pattern = (string) $matches[0][0];

			$text_len = $text_end - $text_start;
			if (0 < $text_len) {
				// Modify the data with the replacement text
				$replacement = call_user_func_array($func, array_merge(array(substr($html, $text_start, $text_len)), $params));
				$html = substr_replace($html, $replacement, $text_start, $text_len);

				// Skip the rest of the replacement string
				$text_end = $text_start + strlen($replacement);
			}
			$text_start = $text_end + strlen($pattern);
		}

		$text_len = strlen($html) - $text_start;
		if (0 < $text_len) {
			// Modify the data with the replacement text
			$replacement = call_user_func_array($func, array_merge(array(substr($html, $text_start, $text_len)), $params));
			$html = substr_replace($html, $replacement, $text_start, $text_len);
		}

		return $html;
	}

?>