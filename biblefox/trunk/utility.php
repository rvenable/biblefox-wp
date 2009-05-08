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

	public static function option_form_generic($id, $title, $option, $help_text = '')
	{
		?>
		<tr>
			<th scope='row' valign='top'><label for='<?php echo $id ?>'><?php echo $title ?></label></th>
			<td><?php echo $option ?><?php if (!empty($help_text)) echo "<br/>$help_text" ?></td>
		</tr>
		<?php
	}

	public static function option_form_text($id, $title, $help_text = '', $value = '', $extra_attrs = '')
	{
		self::option_form_generic($id, $title, "<input name='$id' id='$id' type='text' value='$value' $extra_attrs/>", $help_text);
	}

	public static function option_form_textarea($id, $title, $help_text = '', $rows = 0, $cols = 0, $value = '', $extra_attrs = '')
	{
		self::option_form_generic($id, $title, "<textarea name='$id' id='$id' rows='$rows' cols='$cols' $extra_attrs/>$value</textarea>", $help_text);
	}
}

class BfoxHtmlElement {
	protected $attrs;

	public function __construct($attrs = '') {
		$this->attrs = $attrs;
	}
}

class BfoxHtmlRow extends BfoxHtmlElement {
	private $cols = array();

	public function add_col($col, $attrs = '') {
		$this->cols []= "<td $attrs>$col</td>";
	}
	public function add_header_col($col, $attrs = '') {
		$this->cols []= "<th $attrs>$col</th>";
	}

	public function content() {
		$content = "	<tr $this->attrs>\n";
		foreach ($this->cols as $col) $content .= "		$col\n";
		$content .= "	</tr>\n";
		return $content;
	}
}

class BfoxHtmlTable extends BfoxHtmlElement {
	private $header_rows = array();
	private $rows = array();
	private $footer_rows = array();

	public function add_row(BfoxHtmlRow $row) {
		$this->rows []= $row;
	}

	public function add_header_row(BfoxHtmlRow $row) {
		$this->header_rows []= $row;
	}

	public function add_footer_row(BfoxHtmlRow $row) {
		$this->footer_rows []= $row;
	}

	private static function row_section($section, $rows) {
		$content = '';
		if (!empty($rows)) {
			$content = "<$section>\n";
			foreach ($rows as $row) $content .= $row->content();
			$content .= "</$section>\n";
		}
		return $content;
	}

	public function content() {
		return "<table $this->attrs>\n" .
			self::row_section('thead', $this->header_rows) .
			self::row_section('tbody', $this->rows) .
			self::row_section('tfoot', $this->footer_rows) .
			"</table>\n";
	}

	public function content_split($max_cols, $attrs = '', $height_threshold = 0) {
		$content = "<table $attrs><tr>\n";
		$columns = BfoxUtility::divide_into_cols($this->rows, $max_cols, $height_threshold);
		foreach ($columns as $rows) {
			$content .= "<td><table $this->attrs>\n" .
				self::row_section('thead', $this->header_rows) .
				self::row_section('tbody', $rows) .
				self::row_section('tfoot', $this->footer_rows) .
				"</table><td>\n";
		}
		$content .= "</tr></table>\n";
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
	function bfox_process_html_text($html, $func)
	{
		if (!is_callable($func)) return $html;

		$text_start = 0;
		while (1 == preg_match('/<[^<>]*[^<>\s][^<>]*>/', $html, $matches, PREG_OFFSET_CAPTURE, $text_start))
		{
			// Store the match data in more readable variables
			$text_end = (int) $matches[0][1];
			$pattern = (string) $matches[0][0];

			$text_len = $text_end - $text_start;
			if (0 < $text_len)
			{
				// Modify the data with the replacement text
				$replacement = call_user_func($func, substr($html, $text_start, $text_len));
				$html = substr_replace($html, $replacement, $text_start, $text_len);

				// Skip the rest of the replacement string
				$text_end = $text_start + strlen($replacement);
			}
			$text_start = $text_end + strlen($pattern);
		}

		$text_len = strlen($html) - $text_start;
		if (0 < $text_len)
		{
			// Modify the data with the replacement text
			$replacement = call_user_func($func, substr($html, $text_start, $text_len));
			$html = substr_replace($html, $replacement, $text_start, $text_len);
		}

		return $html;
	}

?>