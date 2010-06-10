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

	private static $book_regexes = array(
	0 => array(0 => 'genesis|gen|exod|exo|exodus|leviticus|lev|num|numbers|deuteronomy|deut|deu|jsh|jos|josh|joshua|judges|judg|jdg|jdgs|rut|rth|ruth|1samuel|1sam|1sm|1sa|2samuel|2sam|2sm|2sa|1kings|1king|1kgs|1kin|1ki|2kings|2king|2kgs|2kin|2ki|1chronicles|1chron|1chr|1ch|2chronicles|2chron|2chr|2ch|ezra|ezr|neh|nehemiah|esther|esth|est|job|pss|psm|psa|psalms|pslm|psalm|pro|prv|prov|proverbs|eccl|ecc|qoheleth|qoh|eccles|ecclesiastes|sng|sos|song\s+songs|canticles|canticle\s+canticles|song|song\s+solomon|isaiah|isa|jer|jeremiah|lamentations|lam|ezk|eze|ezek|ezekiel|dan|daniel|hosea|hos|jol|joe|joel|amos|amo|oba|obad|obadiah|jonah|jnh|jon|micah|mic|nam|nah|nahum|habakkuk|hab|zephaniah|zeph|zep|hag|haggai|zechariah|zech|zec|mal|malachi|matthew|matt|mat|mrk|mark|luke|luk|jhn|john|acts|act|rom|romans|1corinthians|1cor|1co|2corinthians|2cor|2co|gal|galatians|ephesians|ephes|eph|philippians|phil|php|col|colossians|1thessalonians|1thess|1thes|1th|2thessalonians|2thess|2thes|2th|1timothy|1tim|1ti|2timothy|2tim|2ti|tit|titus|philemon|philem|phm|hebrews|heb|jas|james|1peter|1pet|1pt|1pe|2peter|2pet|2pt|2pe|1john|1jhn|1joh|1jn|1jo|2john|2jhn|2joh|2jn|2jo|3john|3jhn|3joh|3jn|3jo|jud|jude|revelation|revelations|rev|tob|tobit|judith|jdth|jdt|jth|esg|addesth|aes|rest\s+esther|add\s+es|add\s+esth|additions\s+to\s+esther|wis|wisd\s+sol|wisdom\s+solomon|wisdom|ecclus|sir|sirach|ecclesiasticus|baruch|bar|ltr\s+jer|lje|let\s+jer|letter\s+jeremiah|song\s+three\s+holy\s+children|song\s+three\s+children|song\s+three\s+youths|song\s+three\s+jews|song\s+three|song\s+thr|prayer\s+azariah|azariah|pr\s+az|susanna|sus|bel\s+and\s+dragon|bel\s+dragon|bel|1maccabees|1macc|1mac|1ma|2maccabees|2macc|2mac|2ma|1esdras|1esdr|1esd|1es|prayer\s+manasses|prayer\s+manasseh|pr\s+man|pma|2esdras|2esdr|2esd|2es', 1 => 'samuel|sam|sm|sa|kings|king|kgs|kin|ki|chronicles|chron|chr|ch|corinthians|cor|co|thessalonians|thess|thes|th|timothy|tim|ti|peter|pet|pt|pe|john|jhn|joh|jn|jo|maccabees|macc|mac|ma|esdras|esdr|esd|es', 2 => 'samuel|sam|sm|sa|kings|king|kgs|kin|ki|chronicles|chron|chr|ch|corinthians|cor|co|thessalonians|thess|thes|th|timothy|tim|ti|peter|pet|pt|pe|john|jhn|joh|jn|jo|maccabees|macc|mac|ma|esdras|esdr|esd|es', 3 => 'john|jhn|joh|jn|jo'),
	1 => array(0 => 'ge|gn|le|lv|nb|nm|nu|dt|jg|ru|1s|2s|1k|2k|ne|es|jb|ps|pr|ec|jr|je|la|dn|da|ho|jl|ob|na|zp|hg|zc|ml|mt|mr|mk|lk|jn|ac|rm|ro|ga|jm|re|tb|ws|1m|2m', 1 => 's|k|m', 2 => 's|k|m'),
	2 => array(0 => 'ex|so|is|am')
	);

	private static $prefixes = array(
	1 => '(1|one|i|1st|first)(\s+book)?(\s+of)?\s',
	2 => '(2|two|ii|2nd|second)(\s+book)?(\s+of)?\s',
	3 => '(3|three|iii|3rd|third)(\s+book)?(\s+of)?\s'
	);

	private $_regex = '';
	private function regex() {
		if (empty($this->_regex)) {
			$books = array();
			for ($level = 0; $level <= $this->max_level; $level++) {
				foreach (self::$book_regexes[$level] as $index => $regexes) {
					if (!empty($books[$index])) $books[$index] .= '|';
					$books[$index] .= $regexes;
				}
			}

			$book_regex = '(' . $books[0] . ')';
			for ($index = 1; $index <= 3; $index++) $book_regex .= '|(' . self::$prefixes[$index] . '(' . $books[$index] . '))';

			if ($this->add_whole_books) $cv_question = '?';
			else $cv_question = '';

			if ($this->require_space_before_cv) $space_star = '+';
			else $space_star = '*';

			// Regex = word boundary, book regex, word boundary, CV regex
			// CV regex = optional period, optional whitespace, number, optional [\s-:,;] ending with number
			$this->_regex = "/\b($book_regex)\b(\.?\s$space_star\d([\s-:,;]*\d)*)$cv_question/i";
		}

		return $this->_regex;
	}

	public function parse_string($str) {
		return preg_replace_callback($this->regex(), array($this, 'replace_cb'), $str);
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