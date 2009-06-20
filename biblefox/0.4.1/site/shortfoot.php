<?php
/*************************************************************************

	Plugin Name: ShortFoot
	Plugin URI: http://tools.biblefox.com/
	Description: Adds simple footnote shortcodes to wordpress. Use 'foot' or 'footnote' shortcodes to add a footnote. Footnotes will be listed automatically at the end of the post content.
	Version: 0.1
	Author: Biblefox
	Author URI: http://biblefox.com

*************************************************************************/

/*************************************************************************

	Copyright 2009 biblefox.com

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.

*************************************************************************/

/**
 * @package ShortFoot
 * @link http://codex.wordpress.org/Shortcode_API
 */

/**
 * Class for managing the global footnotes data
 *
 */
class ShortFootData
{
	const default_header = 'Footnotes';

	private static $notes = array();
	private static $set_index = 1;
	private static $index = 0;

	/**
	 * Called when a new footnote is found. Remembers the footnote for displaying later in the footnotes list
	 *
	 * @param string $note
	 * @return array ($set_index, $index)
	 */
	public static function add_note($note)
	{
		self::$index++;
		self::$notes[self::$index] = $note;
		return array(self::$set_index, self::$index);
	}

	/**
	 * Called when displaying the footnote list. Clears the footnotes to prepare the next set of footnotes.
	 *
	 * @return array ($set_index, $notes)
	 */
	public static function get_notes()
	{
		$results = array(self::$set_index, self::$notes);

		if (!empty(self::$notes))
		{
			self::$notes = array();
			self::$set_index++;
			self::$index = 0;
		}

		return $results;
	}
}

/**
 * Returns the html displaying the footnote list
 *
 * @param string $header
 * @return string
 */
function shortfoot_get_list($header = ShortFootData::default_header)
{
	$content = '';

	list($set_index, $notes) = ShortFootData::get_notes();

	if (!empty($notes))
	{
		if (!empty($header)) $content = "<h3>$header</h3>\n";
		$content .= '<ul>';
		// Update the footnotes section string
		foreach ($notes as $index => $note) $content .= "<li><a name='footnote{$set_index}_$index' href='#footnoteref{$set_index}_$index'>[$index]</a> $note</li>\n";
		$content .= '</ul>';
	}

	return $content;
}

/**
 * Shortcode function for adding a footnote
 *
 * @param array $atts
 * @param string $content
 * @return string shortcode text
 */
function shortfoot_shortcode_footnote($atts, $content = '')
{
	extract(shortcode_atts(array('note' => $content), $atts));

	list($set_index, $index) = ShortFootData::add_note($note);

	// Replace the footnote with a link
	return "<a name='footnoteref{$set_index}_$index' href='#footnote{$set_index}_$index' title='" . strip_tags($note) . "'>[$index]</a>";
}
add_shortcode('foot', 'shortfoot_shortcode_footnote');
add_shortcode('footnote', 'shortfoot_shortcode_footnote');

/**
 * Shortcode function for listing all the footnotes
 *
 * @param array $atts
 * @param string $content Optional string to use as the header
 * @return string
 */
function shortfoot_shortcode_footnote_list($atts, $content = '')
{
	extract(shortcode_atts(array('header' => ShortFootData::default_header), $atts));

	if (!empty($content)) $header = $content;
	$content = '';

	return shortfoot_get_list($header);
}
add_shortcode('footnotes', 'shortfoot_shortcode_footnote_list');
add_shortcode('list_footnotes', 'shortfoot_shortcode_footnote_list');
add_shortcode('footnote_list', 'shortfoot_shortcode_footnote_list');

/**
 * Appends the footnote list to the end of the_content
 *
 * @param string $content
 * @return string
 */
function shortfoot_the_content($content)
{
	return $content . shortfoot_get_list();
}

// We need to print the shortcode list at the end of the post content
// NOTE: This MUST happen after the do_shortcode() filter on 'the_content' so that the footnote list will already be populated
add_filter('the_content', 'shortfoot_the_content', 12); // AFTER do_shortcode()

?>