<?php

/**
 * Class for specifying parsing options and storing parsing results
 *
 */
class BfoxRefParserData {
	/**
	 * If not null, stores all bible references together in one BfoxRefs
	 * @var BfoxRefs
	 */
	var $total_refs;

	/**
	 * If not null, stores an array of all the individual BfoxRefs found
	 * @var array of BfoxRefs
	 */
	var $refs_array;

	/**
	 * If not null, stores a string containing all the leftover characters not considered part of a Bible reference
	 * @var string
	 */
	var $leftovers;

	/**
	 * The max level of book abbreviations to allow
	 * @var integer
	 */
	var $max_level = 0;

	/**
	 * Whether to allow references to whole books
	 * @var boolean
	 */
	var $add_whole_books = true;
}

/**
 * Class for parsing strings, looking for bible references
 *
 */
class BfoxRefParser {

	/**
	 * Returns a BfoxRefs for all the bible references in a string
	 *
	 * @param string $str
	 * @return BfoxRefs
	 */
	public static function simple($str) {
		$data = new BfoxRefParserData;
		$data->total_refs = new BfoxRefs; // Save total_refs
		$data->max_level = 2; // Include all book abbreviations

		self::parse_string($str, $data);

		return $data->total_refs;
	}

	/**
	 * Returns the total bible references if and only if there were no leftovers
	 *
	 * @param $str
	 * @return BfoxRefs or false
	 */
	public static function no_leftovers($str) {
		$data = new BfoxRefParserData;
		$data->total_refs = new BfoxRefs; // Save total_refs
		$data->leftovers = true; // Save the leftovers
		$data->max_level = 2; // Include all book abbreviations

		self::parse_string($str, $data);

		if (empty($data->leftovers)) return $data->total_refs;
		else return false;
	}

	/**
	 * Returns a BfoxRefs for the string, with some support for BibleGroupPassage
	 *
	 * @param $str
	 * @return BfoxRefs
	 */
	public static function with_groups($str) {
		if (isset(BibleMeta::$book_groups[$str])) return new BibleGroupPassage($str);
		else return self::simple($str);
	}

	/**
	 * Parses HTML content for Bible references. Returns content modified by the optional $replace_func callback
	 *
	 * Optionally stores the total BfoxRefs in $total_refs parameter
	 * Optionally modifies the content using the $replace_func parameter
	 *
	 * @param string $html
	 * @param BfoxRefs $total_refs
	 * @param function $replace_func
	 * @return string HTML content modified by the optional $replace_func callback
	 */
	public static function simple_html($html, BfoxRefs $total_refs = null, $replace_func = null) {
		$data = new BfoxRefParserData;
		$data->total_refs = $total_refs; // Save total_refs
		$data->replace_func = $replace_func; // Modify string with the replace_func callback
		$data->max_level = 1; // Don't include 2 letter book abbreviations
		$data->add_whole_books = false; // Don't allow whole book references

		return self::parse_html($html, $data);
	}

	/**
	 * Replaces bible references with bible links in a given html string
	 * @param string $content
	 * @return string
	 */
	private static function parse_html($html, BfoxRefParserData &$data) {
		return bfox_process_html_text($html, 'BfoxRefParser::parse_string', array($data));
	}

	/**
	 * Add using a bible reference string
	 *
	 * @param BfoxRefs $refs
	 * @param string $str
	 */
	public static function parse_string($str, BfoxRefParserData &$data) {

		// Get all the bible reference substrings in this string
		$substrs = BibleMeta::get_bcv_substrs($str, $data->max_level);

		// Add each substring to our sequences
		$refs_array = array();

		// Loop through each bcv substr to create a BfoxRefs
		foreach ($substrs as $substr) {
			// $substr = new BibleBcvSubstr;
			$key = $substr->substr($str);
			if (!isset($refs_array[$key])) {
				$refs = new BfoxRefs;

				// If there is a chapter, verse string use it
				if ($substr->cv_offset) self::parse_book_str($refs, $substr->book, $substr->cv_substr($str));
				elseif ($data->add_whole_books) $refs->add_whole_book($substr->book);

				if ($refs->is_valid()) {
					$refs_array[$key]= $refs;
					if (!is_null($data->total_refs)) $data->total_refs->add_refs($refs);
				}
			}
		}

		// Save the refs_array if we want
		if (isset($data->refs_array)) $data->refs_array = $refs_array;

		// Save leftovers if we want
		if (isset($data->leftovers)) $data->leftovers = str_replace(array_keys($refs_array), '', $str);

		// If we are replacing, we should replace in reverse
		if (isset($data->replace_func)) foreach (array_reverse($substrs) as $substr) {
			// $substr = new BibleBcvSubstr;
			$key = $substr->substr($str);
			if (isset($refs_array[$key])) $str = $substr->replace($str, call_user_func_array($data->replace_func, array($key, $refs_array[$key])));
		}

		return $str;
	}

	/**
	 * Add the number part of a bible reference (ie, the 3:16 in John 3:16)
	 *
	 * @param BfoxRefs $refs
	 * @param integer $book_id
	 * @param string $str
	 */
	private static function parse_book_str(BfoxRefs &$refs, $book_id, $str) {
		// Spaces between numbers count as semicolons
		preg_replace('/(\d)\s+(\d)/', '$1;$2', $str);

		$semis = explode(';', $str);
		foreach ($semis as $semi) {
			$commas = explode(',', $semi);

			$verse_chapter = 0;
			foreach ($commas as $comma) {
				$dash = explode('-', $comma, 2);

				$left = explode(':', $dash[0], 2);
				$ch1 = intval($left[0]);
				$vs1 = intval($left[1]);

				$right = explode(':', $dash[1], 2);
				$ch2 = intval($right[0]);
				$vs2 = intval($right[1]);

				// We must have a chapter1
				if (0 != $ch1) {
					// If verse0 is not 0, but verse1 is 0, we should use chapter1 as verse1, and chapter1 should be 0
					// This fixes the following type of case: 1:2-3 (1:2-3:0 becomes 1:2-0:3)
					if ((0 != $vs1) && (0 == $vs2)) {
						$vs2 = $ch2;
						$ch2 = 0;
					}

					// Whole Chapters (or whole verses)
					if ((0 == $vs1) && (0 == $vs2)) $refs->add_whole($book_id, $ch1, $ch2, $verse_chapter);
					// Inner Chapters
					elseif ((0 == $ch2) || ($ch1 == $ch2)) {
						$verse_chapter = $ch1;
						$refs->add_inner($book_id, $verse_chapter, $vs1, $vs2);
					}
					// Mixed Chapters
					else {
						$refs->add_mixed($book_id, $ch1, $vs1, $ch2, $vs2, $verse_chapter);
						$verse_chapter = $ch2;
					}
				}
			}
		}
	}
}

/**
 * This function takes some html input ($html) and processes its text using the $func callback.
 *
 * It will skip all html tags and call $func for each chunk of text.
 * The $func function should take the text as its parameter and return the modified text.
 *
 * @param string $html
 * @param function $func
 * @param array $params
 * @return unknown_type
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