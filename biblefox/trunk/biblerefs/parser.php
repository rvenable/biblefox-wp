<?php

/**
 * Class for specifying parsing options and storing parsing results
 *
 */
class BfoxRefParserNew {
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

	/**
	 * Returns a BfoxRefs for all the bible references in a string
	 *
	 * @param string $str
	 * @return BfoxRefs
	 */
	public static function simple($str) {
		$parser = new BfoxRefParser;
		$parser->total_refs = new BfoxRefs; // Save total_refs
		$parser->max_level = 2; // Include all book abbreviations

		$parser->parse_string($str);

		return $parser->total_refs;
	}

	/**
	 * Returns the total bible references if and only if there were no leftovers
	 *
	 * @param $str
	 * @return BfoxRefs or false
	 */
	public static function no_leftovers($str) {
		$parser = new BfoxRefParser;
		$parser->total_refs = new BfoxRefs; // Save total_refs
		$parser->leftovers = true; // Save the leftovers
		$parser->max_level = 2; // Include all book abbreviations

		$leftovers = $parser->parse_string($str);

		if (empty($leftovers)) return $parser->total_refs;
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
		$parser = new BfoxRefParser;
		$parser->total_refs = $total_refs; // Save total_refs
		$parser->replace_func = $replace_func; // Modify string with the replace_func callback
		$parser->max_level = 1; // Don't include 2 letter book abbreviations
		$parser->add_whole_books = true; // Don't allow whole book references

		return $parser->parse_string_html($html);
	}

	static $_num_strings = array(
	1 => '1|one|i|1st|first',
	2 => '2|two|ii|2nd|second',
	3 => '3|three|iii|3rd|third',
	);

	public function parse_string($str) {
		$books = array();
		for ($level = 0; $level <= $this->max_level; $level++) $books = array_merge($books, BibleMeta::$synonyms[$level]);
		$books = array_keys($books);
		foreach ($books as $book) {
			$book = str_replace(' ', '\s+', $book);
			if (isset(self::$_num_strings[$book[0]])) $num_books[$book[0]] []= substr($book, 1);
			$normal_books []= $book;
		}
		$regex []= '(' . implode('|', $normal_books) . ')';

		foreach ($num_books as $num => $books) {
			$regex []= '((' . self::$_num_strings[$num] . ')(\s+book)?(\s+of)?\s(' . implode('|', $books) . '))';
		}

		if ($this->add_whole_books) $cv_question = '?';
		else $cv_question = '';

		if ($this->require_space_before_cv) $space_star = '+';
		else $space_star = '*';

		// Regex = word boundary, book regex, word boundary, CV regex
		// CV regex = optional period, optional whitespace, number, optional [\s-:,;] ending with number
		$regex = '/\b(' . implode('|', $regex) . ")\b(\.?\s$space_star\d([\s-:,;]*\d)*)$cv_question/i";

		return preg_replace_callback($regex, array($this, 'replace_cb'), $str);
	}

	/**
	 * Replaces bible references with bible links in a given html string
	 * @param string $content
	 * @return string
	 */
	public function parse_string_html($html) {
		return bfox_process_html_text($html, array($this, 'parse_string'));
	}

	public function replace_cb($matches) {
		$text = $matches[0];

		if (!empty($matches[2])) $synonym = $matches[2];
		else if (!empty($matches[3])) $synonym = '1' . $matches[7];
		else if (!empty($matches[8])) $synonym = '2' . $matches[12];
		else if (!empty($matches[13])) $synonym = '3' . $matches[17];

		$synonym = strtolower($synonym);

		$level = 0;
		while (empty($book_id) && ($level <= $this->max_level)) {
			$book_id = BibleMeta::$synonyms[$level][$synonym];
			$level++;
		}

		if ($book_id) {
			$refs = new BfoxRefs;
			$cv_str = ltrim(trim($matches[18]), '.');
			if (!empty($cv_str)) self::parse_book_str($refs, $book_id, $cv_str);
			else $refs->add_whole_book($book_id);

			if ($refs->is_valid()) {
				if (isset($this->refs_array)) $this->refs_array []= $refs;
				if (isset($this->total_refs)) $this->total_refs->add_refs($refs);
				if (isset($this->leftovers)) return '';
				if (isset($this->replace_func)) return call_user_func_array($this->replace_func, array($text, $refs));
			}
		}

		return $text;
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

class BfoxRefParser extends BfoxRefParserNew {
}

?>