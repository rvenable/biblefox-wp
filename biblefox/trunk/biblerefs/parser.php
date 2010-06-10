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

	// Note: Books have to be reverse sorted to give priority to the longer book names
	private static $book_regexes = array(
	0 => array(0 => 'zephaniah|zeph|zep|zechariah|zech|zec|wisdom\s+of\s+solomon|wisdom|wisd\s+of\s+sol|wis|tobit|tob|titus|tit|susanna|sus|sos|song\s+thr|song\s+ot\s+the\s+three\s+children|song\s+of\s+the\s+three\s+youths|song\s+of\s+the\s+three\s+jews|song\s+of\s+the\s+three\s+holy\s+children|song\s+of\s+the\s+three|song\s+of\s+songs|song\s+of\s+solomon|song|sng|sirach|sir|ruth|rut|rth|romans|rom|revelations|revelation|rev|rest\s+of\s+esther|rest\s+esther|qoheleth|qoh|pss|psm|pslm|psalms|psalm|psa|prv|proverbs|prov|pro|prayer\s+of\s+manasses|prayer\s+of\s+manasseh|prayer\s+of\s+azariah|pr\s+man|pr\s+az|pma|php|phm|philippians|philemon|philem|phil|obadiah|obad|oba|numbers|num|nehemiah|neh|nam|nahum|nah|mrk|micah|mic|matthew|matt|mat|mark|malachi|mal|luke|luk|ltr\s+jer|lje|leviticus|lev|letter\s+of\s+jeremiah|let\s+of\s+jer|lamentations|lam|judith|judges|judg|jude|jud|jth|jsh|joshua|josh|jos|jonah|jon|jol|john|joel|joe|job|jnh|jhn|jeremiah|jer|jdth|jdt|jdgs|jdg|jas|james|isaiah|isa|hosea|hos|hebrews|heb|haggai|hag|habakkuk|hab|genesis|gen|galatians|gal|ezra|ezr|ezk|ezekiel|ezek|eze|exodus|exod|exo|esther|esth|est|esg|ephesians|ephes|eph|ecclus|ecclesiasticus|ecclesiastes|eccles|eccl|ecc|deuteronomy|deut|deu|daniel|dan|colossians|col|canticles|canticle\s+of\s+canticles|bel\s+dragon|bel\s+and\s+the\s+dragon|bel|baruch|bar|azariah|amos|amo|aes|additions\s+to\s+esther|addesth|add\s+to\s+esth|add\s+to\s+es|acts|act|3john|3joh|3jo|3jn|3jhn|2timothy|2tim|2ti|2thessalonians|2thess|2thes|2th|2sm|2samuel|2sam|2sa|2pt|2peter|2pet|2pe|2maccabees|2macc|2mac|2ma|2kings|2king|2kin|2ki|2kgs|2john|2joh|2jo|2jn|2jhn|2esdras|2esdr|2esd|2es|2corinthians|2cor|2co|2chronicles|2chron|2chr|2ch|1timothy|1tim|1ti|1thessalonians|1thess|1thes|1th|1sm|1samuel|1sam|1sa|1pt|1peter|1pet|1pe|1maccabees|1macc|1mac|1ma|1kings|1king|1kin|1ki|1kgs|1john|1joh|1jo|1jn|1jhn|1esdras|1esdr|1esd|1es|1corinthians|1cor|1co|1chronicles|1chron|1chr|1ch', 1 => 'timothy|tim|ti|thessalonians|thess|thes|th|sm|samuel|sam|sa|pt|peter|pet|pe|maccabees|macc|mac|ma|kings|king|kin|ki|kgs|john|joh|jo|jn|jhn|esdras|esdr|esd|es|corinthians|cor|co|chronicles|chron|chr|ch', 2 => 'timothy|tim|ti|thessalonians|thess|thes|th|sm|samuel|sam|sa|pt|peter|pet|pe|maccabees|macc|mac|ma|kings|king|kin|ki|kgs|john|joh|jo|jn|jhn|esdras|esdr|esd|es|corinthians|cor|co|chronicles|chron|chr|ch', 3 => 'john|joh|jo|jn|jhn'),
	1 => array(0 => 'zp|zc|ws|tb|ru|ro|rm|re|ps|pr|ob|nu|nm|ne|nb|na|mt|mr|ml|mk|lv|lk|le|la|jr|jn|jm|jl|jg|je|jb|ho|hg|gn|ge|ga|es|ec|dt|dn|da|ac|2s|2m|2k|1s|1m|1k', 1 => 's|m|k', 2 => 's|m|k'),
	2 => array(0 => 'so|is|ex|am')
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

		$synonym = strtolower(preg_replace('/\s+/', ' ', $synonym));

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