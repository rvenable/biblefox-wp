<?php

class BibleBcvSubstr {
	public $book, $offset, $length, $cv_offset;

	public function __construct($book, $offset, $length, $cv_offset = 0) {
		$this->book = $book;
		$this->offset = $offset;
		$this->length = $length;
		$this->cv_offset = $cv_offset;
	}

	public function substr($str) {
		return substr($str, $this->offset, $this->length);
	}

	public function cv_substr($str) {
		return substr($str, $this->cv_offset, $this->length - ($this->cv_offset - $this->offset));
	}

	public function replace($str, $replace) {
		return substr_replace($str, $replace, $this->offset, $this->length);
	}
}

class BibleMeta {
	const name_normal = 'name';
	const name_short = 'short_name';
	const digits = '0123456789';

	/**
	 * Returns the book name for the given book ID
	 *
	 * @param integer $book
	 * @param string $name Selects a particular book name (ie. name, short_name, ...)
	 * @return unknown
	 */
	public static function get_book_name($book, $name = '') {
		if (empty($name)) $name = self::name_normal;
		if (isset(self::$books[$book][$name])) return self::$books[$book][$name];
		return 'Unknown';
	}

	/**
	 * Returns the book id for a book name synonym.
	 *
	 * Synonyms have levels for how specific the synonyms. For instance, Isaiah could be abbreviated as 'is', which could be confused with
	 * the word 'is'. So 'is' is not in the default level, and thus is not used unless its level is specified.
	 *
	 * @param string $synonym
	 * @param integer $max_level
	 * @return integer
	 */
	public static function get_book_id($raw_synonym, $max_level = 0) {
		$words = array();

		// Chop the synonym into words (numeric digits count as words)
		$raw_words = str_word_count(strtolower(trim($raw_synonym)), 1, self::digits);

		// There needs to be at least one word
		if (0 < count($raw_words)) {
			// Create a new word array with only the words we don't want to ignore (and get rid of the old array
			foreach ($raw_words as $word) if (!isset(self::$ignore_words[$word])) $words []= $word;
			unset($raw_words);
		}

		return self::get_book_id_from_words($words, $max_level);
	}

	/**
	 * Returns an array of bcv (book, chapter, verse) reference strings found in the given string
	 *
	 * @param string $str
	 * @param integer $max_level
	 * @return array of BibleBcvSubstr
	 */
	public static function get_bcv_substrs($str, $max_level = 0) {
		// Get all the book substrings in this string
		$substrs = self::get_book_substrs($str, $max_level);

		// For each book substring, check the characters immediately following it to see if there are chapter, verse references
		foreach ($substrs as $index => &$substr) {
			$cv_offset = $substr->offset + $substr->length;
			if (isset($substrs[$index + 1])) $next_offset = $substrs[$index + 1]->offset;
			else $next_offset = strlen($str);

			$leftovers = substr($str, $cv_offset, $next_offset - $cv_offset);
			if (preg_match('/^\s*\d[\s\d-:,;]*/', $leftovers, $match)) {
				$substr->cv_offset = $cv_offset;
				$substr->length += strlen(rtrim($match[0]));
			}
		}

		return $substrs;
	}

	/**
	 * Returns an array of books whose names were found in the given string
	 *
	 * @param string $str
	 * @param integer $max_level
	 * @return array of BibleBcvSubstr
	 */
	private static function get_book_substrs($str, $max_level = 0) {
		$str = strtolower($str);

		$substrs = array();

		// Commas and semicolons cannot be in a book name, so we must split on them
		$sections = preg_split('/[,;]/', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

		// We have to operate on each section separately
		foreach ($sections as $section) {
			$section_str = $section[0];
			$section_offset = $section[1];

			// Search for books in this section
			$section_substrs = self::search_for_books($section_str, $max_level);
			foreach ($section_substrs as $substr) {
				$substr->offset += $section_offset;
				$substrs []= $substr;
			}
		}

		return $substrs;
	}

	/**
	 * Search a string for book names. Returns an array of arrays.
	 *
	 * @param string $str
	 * @param integer $max_level
	 * @return array of BibleBcvSubstr
	 */
	private static function search_for_books($str, $max_level) {
		$books = array();
		$prefix_words = array();
		$prefix_offset = 0;

		// Get all the words (digits count as words) with their offsets
		$words = str_word_count($str, 2, self::digits);

		// Loop through each word to see if we can find a book name
		foreach ($words as $pos => $word) {
			// We should ignore ignore words (unless there are no prefix words)
			if (isset(self::$ignore_words[$word])) {
				// If no prefix words, but this ignore word can exist as the first word, add it to the prefix words
				if (empty($prefix_words) && self::$ignore_words[$word]) {
					$prefix_words []= $word;
					$prefix_offset = $pos;
				}
			}
			else {
				$book = array();

				// Add the current word to the prefix list
				if (empty($prefix_words)) $prefix_offset = $pos;
				$prefix_words []= $word;
				$new_prefix_len = $pos + strlen($word) - $prefix_offset;

				// If the prefix words are a valid prefix, we should save them for later to see if we can add the next word
				// Otherwise, we need to see if we can get book name from the prefix words
				if (self::is_prefix($prefix_words)) $old_prefix_len = $new_prefix_len;
				else {
					// Try to get a book ID from the words
					$book_id = self::get_book_id_from_words($prefix_words, $max_level);

					// If we got a book ID, then we can add this book and clear our prefixes
					// Otherwise, this newest word doesn't belong with the prefix list
					if (!empty($book_id)) {
						$books []= new BibleBcvSubstr($book_id, $prefix_offset, $new_prefix_len);
						$prefix_words = array();
					}
					else {
						// Pop off the newest word which we had just added to the prefix list
						array_pop($prefix_words);

						// If we still have a prefix list,
						// Then we should see if it is a book name and clear the prefix list
						if (!empty($prefix_words)) {
							// If the prefix list is a valid book, we should add the book
							$book_id = self::get_book_id_from_words($prefix_words, $max_level);
							if (!empty($book_id)) $books []= new BibleBcvSubstr($book_id, $prefix_offset, $old_prefix_len);

							// Clear the prefix words
							$prefix_words = array();
						}

						// Now the prefix list should be empty, so we should check this word on its own

						// If this word is a prefix, then we should start a new prefix list with it
						// Otherwise, if it is a book name, we should add the book
						if (self::is_prefix(array($word))) {
							$prefix_offset = $pos;
							$prefix_words = array($word);
						}
						elseif ($book_id = self::get_book_id_from_words(array($word), $max_level)) {
							$books[] = new BibleBcvSubstr($book_id, $pos, strlen($word));
						}
					}
				}
			}
		}

		// If we still have prefix words, check to see if they are a book
		if (!empty($prefix_words)) {
			$book_id = self::get_book_id_from_words($prefix_words, $max_level);
			if (!empty($book_id)) $books []= new BibleBcvSubstr($book_id, $prefix_offset, $old_prefix_len);
		}

		// Check to see if a period is at the end of each book string, and include it if so
		foreach ($books as &$book) if ('.' == $str[$book->offset + $book->length]) $book->length++;

		return $books;
	}

	/**
	 * Returns whether a given sequence of words can be a valid synonym prefix
	 *
	 * @param array $prefix_words
	 * @return bool
	 */
	private static function is_prefix($prefix_words) {
		$prefix = implode(' ', $prefix_words);
		return (isset(self::$prefixes[$prefix]) || isset(self::$num_strings[$prefix]));
	}

	/**
	 * Get a book id from a list of words
	 *
	 * Note: Ignore words should already have been taken out
	 *
	 * @param array $words
	 * @param integer $max_level
	 * @return integer Book ID or FALSE
	 */
	private static function get_book_id_from_words($words, $max_level) {
		if (0 < count($words)) {
			// If the first word is a string representing a number, shift that number off the word list
			// That number will need to be prepended to the beginning of the first word
			// For instance: '1 sam' should become '1sam'
			if (isset(self::$num_strings[$words[0]])) $num = self::$num_strings[array_shift($words)];

			if (0 < count($words)) {
				// Prepend the book number if set
				if (!empty($num)) $words[0] = $num . $words[0];

				$synonym = implode(' ', $words);

				if (!empty($synonym)) {
					// Loop through each allowed level
					$level = 0;
					while (empty($book_id) && ($level <= $max_level)) {
						$book_id = self::$synonyms[$level][$synonym];
						$level++;
					}
				}
			}
		}

		if (empty($book_id)) return FALSE;
		return $book_id;
	}

	/**
	 * Array for defining book groups (sets of books)
	 *
	 * @var array
	 */
	static $book_groups = array(
	'bible' => array('old', 'new', 'apoc'),
	'protest' => array('old', 'new'),
	'old' => array('moses', 'history', 'wisdom', 'prophets'),
	'moses' => array(1, 2, 3, 4, 5),
	'history' => array(6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17),
	'wisdom' => array(18, 19, 20, 21, 22),
	'prophets' => array('major_prophets', 'minor_prophets'),
	'major_prophets' => array(23, 24, 25, 26, 27),
	'minor_prophets' => array(28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39),
	'new' => array('gospels', 44, 'paul', 'epistles', 66),
	'gospels' => array(40, 41, 42, 43),
	'paul' => array(45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57),
	'epistles' => array(58, 59, 60, 61, 62, 63, 64, 65),
	'apoc' => array(67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81)
	);

	/**
	 * Array for defining book information
	 *
	 * The numbered ones are books, the ones with string names are book groups
	 *
	 * @var array
	 */
	static $books = array(
	'bible' => array('name' => 'Bible', 'short_name' => 'Bible'),
	'protest' => array('name' => 'Bible', 'short_name' => 'Bible'),
	'old' => array('name' => 'Old Testament', 'short_name' => 'Old'),
	'moses' => array('name' => 'Books of Moses', 'short_name' => 'Moses'),
	'history' => array('name' => 'Books of History', 'short_name' => 'History'),
	'wisdom' => array('name' => 'Books of Wisdom', 'short_name' => 'Wisdom'),
	'prophets' => array('name' => 'Prophets', 'short_name' => 'Prophets'),
	'major_prophets' => array('name' => 'Major Prophets', 'short_name' => 'Maj Prophets'),
	'minor_prophets' => array('name' => 'Minor Prophets', 'short_name' => 'Min Prophets'),
	'new' => array('name' => 'New Testament', 'short_name' => 'New'),
	'gospels' => array('name' => 'Gospels', 'short_name' => 'Gospels'),
	'paul' => array('name' => 'Pauline Epistles', 'short_name' => 'Paul'),
	'epistles' => array('name' => 'Epistles', 'short_name' => 'Epistles'),
	'apoc' => array('name' => 'Apocrypha', 'short_name' => 'Apocrypha'),
	'1' => array('name' => 'Genesis', 'wiki_name' => 'Book_of_Genesis', 'short_name' => 'Gen'),
	'2' => array('name' => 'Exodus', 'wiki_name' => 'Exodus', 'short_name' => 'Exo'),
	'3' => array('name' => 'Leviticus', 'wiki_name' => 'Leviticus', 'short_name' => 'Lev'),
	'4' => array('name' => 'Numbers', 'wiki_name' => 'Book_of_Numbers', 'short_name' => 'Num'),
	'5' => array('name' => 'Deuteronomy', 'wiki_name' => 'Deuteronomy', 'short_name' => 'Deut'),
	'6' => array('name' => 'Joshua', 'wiki_name' => 'Book_of_Joshua', 'short_name' => 'Josh'),
	'7' => array('name' => 'Judges', 'wiki_name' => 'Book_of_Judges', 'short_name' => 'Judg'),
	'8' => array('name' => 'Ruth', 'wiki_name' => 'Book_of_Ruth', 'short_name' => 'Ruth'),
	'9' => array('name' => '1 Samuel', 'wiki_name' => 'Books_of_Samuel', 'short_name' => '1Sam'),
	'10' => array('name' => '2 Samuel', 'wiki_name' => 'Books_of_Samuel', 'short_name' => '2Sam'),
	'11' => array('name' => '1 Kings', 'wiki_name' => 'Books_of_Kings', 'short_name' => '1Ki'),
	'12' => array('name' => '2 Kings', 'wiki_name' => 'Books_of_Kings', 'short_name' => '2Ki'),
	'13' => array('name' => '1 Chronicles', 'wiki_name' => 'Books_of_Chronicles', 'short_name' => '1Chr'),
	'14' => array('name' => '2 Chronicles', 'wiki_name' => 'Books_of_Chronicles', 'short_name' => '2Chr'),
	'15' => array('name' => 'Ezra', 'wiki_name' => 'Book_of_Ezra', 'short_name' => 'Ezra'),
	'16' => array('name' => 'Nehemiah', 'wiki_name' => 'Book_of_Nehemiah', 'short_name' => 'Neh'),
	'17' => array('name' => 'Esther', 'wiki_name' => 'Book_of_Esther', 'short_name' => 'Esth'),
	'18' => array('name' => 'Job', 'wiki_name' => 'Book_of_Job', 'short_name' => 'Job'),
	'19' => array('name' => 'Psalm', 'wiki_name' => 'Psalms', 'short_name' => 'Ps'),
	'20' => array('name' => 'Proverbs', 'wiki_name' => 'Book_of_Proverbs', 'short_name' => 'Prov'),
	'21' => array('name' => 'Ecclesiastes', 'wiki_name' => 'Ecclesiastes', 'short_name' => 'Ecc'),
	'22' => array('name' => 'Song of Solomon', 'wiki_name' => 'Song_of_Songs', 'short_name' => 'Song'),
	'23' => array('name' => 'Isaiah', 'wiki_name' => 'Book_of_Isaiah', 'short_name' => 'Isa'),
	'24' => array('name' => 'Jeremiah', 'wiki_name' => 'Book_of_Jeremiah', 'short_name' => 'Jer'),
	'25' => array('name' => 'Lamentations', 'wiki_name' => 'Book_of_Lamentations', 'short_name' => 'Lam'),
	'26' => array('name' => 'Ezekiel', 'wiki_name' => 'Book_of_Ezekiel', 'short_name' => 'Ezek'),
	'27' => array('name' => 'Daniel', 'wiki_name' => 'Book_of_Daniel', 'short_name' => 'Dan'),
	'28' => array('name' => 'Hosea', 'wiki_name' => 'Book_of_Hosea', 'short_name' => 'Hos'),
	'29' => array('name' => 'Joel', 'wiki_name' => 'Book_of_Joel', 'short_name' => 'Joel'),
	'30' => array('name' => 'Amos', 'wiki_name' => 'Book_of_Amos', 'short_name' => 'Amos'),
	'31' => array('name' => 'Obadiah', 'wiki_name' => 'Book_of_Obadiah', 'short_name' => 'Obad'),
	'32' => array('name' => 'Jonah', 'wiki_name' => 'Book_of_Jonah', 'short_name' => 'Jnh'),
	'33' => array('name' => 'Micah', 'wiki_name' => 'Book_of_Micah', 'short_name' => 'Mic'),
	'34' => array('name' => 'Nahum', 'wiki_name' => 'Book_of_Nahum', 'short_name' => 'Nah'),
	'35' => array('name' => 'Habakkuk', 'wiki_name' => 'Book_of_Habakkuk', 'short_name' => 'Hab'),
	'36' => array('name' => 'Zephaniah', 'wiki_name' => 'Book_of_Zephaniah', 'short_name' => 'Zeph'),
	'37' => array('name' => 'Haggai', 'wiki_name' => 'Book_of_Haggai', 'short_name' => 'Hag'),
	'38' => array('name' => 'Zechariah', 'wiki_name' => 'Book_of_Zechariah', 'short_name' => 'Zech'),
	'39' => array('name' => 'Malachi', 'wiki_name' => 'Book_of_Malachi', 'short_name' => 'Mal'),
	'40' => array('name' => 'Matthew', 'wiki_name' => 'Gospel_of_Matthew', 'short_name' => 'Matt'),
	'41' => array('name' => 'Mark', 'wiki_name' => 'Gospel_of_Mark', 'short_name' => 'Mark'),
	'42' => array('name' => 'Luke', 'wiki_name' => 'Gospel_of_Luke', 'short_name' => 'Luke'),
	'43' => array('name' => 'John', 'wiki_name' => 'Gospel_of_John', 'short_name' => 'John'),
	'44' => array('name' => 'Acts', 'wiki_name' => 'Acts_of_the_Apostles', 'short_name' => 'Acts'),
	'45' => array('name' => 'Romans', 'wiki_name' => 'Epistle_to_the_Romans', 'short_name' => 'Rom'),
	'46' => array('name' => '1 Corinthians', 'wiki_name' => 'First_Epistle_to_the_Corinthians', 'short_name' => '1Cor'),
	'47' => array('name' => '2 Corinthians', 'wiki_name' => 'Second_Epistle_to_the_Corinthians', 'short_name' => '2Cor'),
	'48' => array('name' => 'Galatians', 'wiki_name' => 'Epistle_to_the_Galatians', 'short_name' => 'Gal'),
	'49' => array('name' => 'Ephesians', 'wiki_name' => 'Epistle_to_the_Ephesians', 'short_name' => 'Eph'),
	'50' => array('name' => 'Philippians', 'wiki_name' => 'Epistle_to_the_Philippians', 'short_name' => 'Phil'),
	'51' => array('name' => 'Colossians', 'wiki_name' => 'Epistle_to_the_Colossians', 'short_name' => 'Col'),
	'52' => array('name' => '1 Thessalonians', 'wiki_name' => 'First_Epistle_to_the_Thessalonians', 'short_name' => '1Th'),
	'53' => array('name' => '2 Thessalonians', 'wiki_name' => 'Second_Epistle_to_the_Thessalonians', 'short_name' => '2Th'),
	'54' => array('name' => '1 Timothy', 'wiki_name' => 'First_Epistle_to_Timothy', 'short_name' => '1Tim'),
	'55' => array('name' => '2 Timothy', 'wiki_name' => 'Second_Epistle_to_Timothy', 'short_name' => '2Tim'),
	'56' => array('name' => 'Titus', 'wiki_name' => 'Epistle_to_Titus', 'short_name' => 'Tit'),
	'57' => array('name' => 'Philemon', 'wiki_name' => 'Epistle_to_Philemon', 'short_name' => 'Phm'),
	'58' => array('name' => 'Hebrews', 'wiki_name' => 'Epistle_to_the_Hebrews', 'short_name' => 'Heb'),
	'59' => array('name' => 'James', 'wiki_name' => 'Epistle_of_James', 'short_name' => 'Jm'),
	'60' => array('name' => '1 Peter', 'wiki_name' => 'First_Epistle_of_Peter', 'short_name' => '1Pet'),
	'61' => array('name' => '2 Peter', 'wiki_name' => 'Second_Epistle_of_Peter', 'short_name' => '2Pet'),
	'62' => array('name' => '1 John', 'wiki_name' => 'First_Epistle_of_John', 'short_name' => '1Jn'),
	'63' => array('name' => '2 John', 'wiki_name' => 'Second_Epistle_of_John', 'short_name' => '2Jn'),
	'64' => array('name' => '3 John', 'wiki_name' => 'Third_Epistle_of_John', 'short_name' => '3Jn'),
	'65' => array('name' => 'Jude', 'wiki_name' => 'Epistle_of_Jude', 'short_name' => 'Jude'),
	'66' => array('name' => 'Revelation', 'wiki_name' => 'Book_of_Revelation', 'short_name' => 'Rev'),
	'67' => array('name' => 'Tobit', 'wiki_name' => 'Book_of_Tobit', 'short_name' => 'Tob'),
	'68' => array('name' => 'Judith', 'wiki_name' => 'Book_of_Judith', 'short_name' => 'Jdth'),
	'69' => array('name' => 'Rest of Esther', 'wiki_name' => 'Rest_of_Esther', 'short_name' => 'REst'),
	'70' => array('name' => 'Wisdom', 'wiki_name' => 'Book_of_Wisdom', 'short_name' => 'Wis'),
	'71' => array('name' => 'Ecclesiasticus', 'wiki_name' => 'Sirach', 'short_name' => 'Sir'),
	'72' => array('name' => 'Baruch', 'wiki_name' => 'Book_of_Baruch', 'short_name' => 'Bar'),
	'73' => array('name' => 'Letter of Jeremiah', 'wiki_name' => 'Letter_of_Jeremiah', 'short_name' => 'LJe'),
	'74' => array('name' => 'Prayer of Azariah', 'wiki_name' => 'The_Prayer_of_Azariah_and_Song_of_the_Three_Holy_Children', 'short_name' => 'PrAz'),
	'75' => array('name' => 'Susanna', 'wiki_name' => 'Book_of_Susanna', 'short_name' => 'Sus'),
	'76' => array('name' => 'Bel and the Dragon', 'wiki_name' => 'Bel_and_the_Dragon', 'short_name' => 'Bel'),
	'77' => array('name' => '1 Maccabees', 'wiki_name' => '1_Maccabees', 'short_name' => '1Mac'),
	'78' => array('name' => '2 Maccabees', 'wiki_name' => '2_Maccabees', 'short_name' => '2Mac'),
	'79' => array('name' => '1 Esdras', 'wiki_name' => '1_Esdras', 'short_name' => '1Esd'),
	'80' => array('name' => 'Prayer of Manasseh', 'wiki_name' => 'Prayer_of_Manasseh', 'short_name' => 'PMan'),
	'81' => array('name' => '2 Esdras', 'wiki_name' => '2_Esdras', 'short_name' => '2Esd')
	);

	/**
	 * Array for defining synonyms for bible book names
	 *
	 * This array has levels for how specific the synonyms are allowed to be
	 *
	 * @var array
	 */
	static $synonyms = array(
	'0' => array(
		'genesis' => '1',
		'gen' => '1',
		'exod' => '2',
		'exo' => '2',
		'exodus' => '2',
		'leviticus' => '3',
		'lev' => '3',
		'num' => '4',
		'numbers' => '4',
		'deuteronomy' => '5',
		'deut' => '5',
		'deu' => '5',
		'jsh' => '6',
		'jos' => '6',
		'josh' => '6',
		'joshua' => '6',
		'judges' => '7',
		'judg' => '7',
		'jdg' => '7',
		'jdgs' => '7',
		'rut' => '8',
		'rth' => '8',
		'ruth' => '8',
		'1samuel' => '9',
		'1sam' => '9',
		'1sm' => '9',
		'1sa' => '9',
		'2samuel' => '10',
		'2sam' => '10',
		'2sm' => '10',
		'2sa' => '10',
		'1kings' => '11',
		'1king' => '11',
		'1kgs' => '11',
		'1kin' => '11',
		'1ki' => '11',
		'2kings' => '12',
		'2king' => '12',
		'2kgs' => '12',
		'2kin' => '12',
		'2ki' => '12',
		'1chronicles' => '13',
		'1chron' => '13',
		'1chr' => '13',
		'1ch' => '13',
		'2chronicles' => '14',
		'2chron' => '14',
		'2chr' => '14',
		'2ch' => '14',
		'ezra' => '15',
		'ezr' => '15',
		'neh' => '16',
		'nehemiah' => '16',
		'esther' => '17',
		'esth' => '17',
		'est' => '17',
		'job' => '18',
		'pss' => '19',
		'psm' => '19',
		'psa' => '19',
		'psalms' => '19',
		'pslm' => '19',
		'psalm' => '19',
		'pro' => '20',
		'prv' => '20',
		'prov' => '20',
		'proverbs' => '20',
		'eccl' => '21',
		'ecc' => '21',
		'qoheleth' => '21',
		'qoh' => '21',
		'eccles' => '21',
		'ecclesiastes' => '21',
		'sng' => '22',
		'sos' => '22',
		'song songs' => '22',
		'canticles' => '22',
		'canticle canticles' => '22',
		'song' => '22',
		'song solomon' => '22',
		'isaiah' => '23',
		'isa' => '23',
		'jer' => '24',
		'jeremiah' => '24',
		'lamentations' => '25',
		'lam' => '25',
		'ezk' => '26',
		'eze' => '26',
		'ezek' => '26',
		'ezekiel' => '26',
		'dan' => '27',
		'daniel' => '27',
		'hosea' => '28',
		'hos' => '28',
		'jol' => '29',
		'joe' => '29',
		'joel' => '29',
		'amos' => '30',
		'amo' => '30',
		'oba' => '31',
		'obad' => '31',
		'obadiah' => '31',
		'jonah' => '32',
		'jnh' => '32',
		'jon' => '32',
		'micah' => '33',
		'mic' => '33',
		'nam' => '34',
		'nah' => '34',
		'nahum' => '34',
		'habakkuk' => '35',
		'hab' => '35',
		'zephaniah' => '36',
		'zeph' => '36',
		'zep' => '36',
		'hag' => '37',
		'haggai' => '37',
		'zechariah' => '38',
		'zech' => '38',
		'zec' => '38',
		'mal' => '39',
		'malachi' => '39',
		'matthew' => '40',
		'matt' => '40',
		'mat' => '40',
		'mrk' => '41',
		'mark' => '41',
		'luke' => '42',
		'luk' => '42',
		'jhn' => '43',
		'john' => '43',
		'acts' => '44',
		'act' => '44',
		'rom' => '45',
		'romans' => '45',
		'1corinthians' => '46',
		'1cor' => '46',
		'1co' => '46',
		'2corinthians' => '47',
		'2cor' => '47',
		'2co' => '47',
		'gal' => '48',
		'galatians' => '48',
		'ephesians' => '49',
		'ephes' => '49',
		'eph' => '49',
		'philippians' => '50',
		'phil' => '50',
		'php' => '50',
		'col' => '51',
		'colossians' => '51',
		'1thessalonians' => '52',
		'1thess' => '52',
		'1thes' => '52',
		'1th' => '52',
		'2thessalonians' => '53',
		'2thess' => '53',
		'2thes' => '53',
		'2th' => '53',
		'1timothy' => '54',
		'1tim' => '54',
		'1ti' => '54',
		'2timothy' => '55',
		'2tim' => '55',
		'2ti' => '55',
		'tit' => '56',
		'titus' => '56',
		'philemon' => '57',
		'philem' => '57',
		'phm' => '57',
		'hebrews' => '58',
		'heb' => '58',
		'jas' => '59',
		'james' => '59',
		'1peter' => '60',
		'1pet' => '60',
		'1pt' => '60',
		'1pe' => '60',
		'2peter' => '61',
		'2pet' => '61',
		'2pt' => '61',
		'2pe' => '61',
		'1john' => '62',
		'1jhn' => '62',
		'1joh' => '62',
		'1jn' => '62',
		'1jo' => '62',
		'2john' => '63',
		'2jhn' => '63',
		'2joh' => '63',
		'2jn' => '63',
		'2jo' => '63',
		'3john' => '64',
		'3jhn' => '64',
		'3joh' => '64',
		'3jn' => '64',
		'3jo' => '64',
		'jud' => '65',
		'jude' => '65',
		'revelation' => '66',
		'revelations' => '66',
		'rev' => '66',
		'tob' => '67',
		'tobit' => '67',
		'judith' => '68',
		'jdth' => '68',
		'jdt' => '68',
		'jth' => '68',
		'esg' => '69',
		'addesth' => '69',
		'aes' => '69',
		'rest esther' => '69',
		'add es' => '69',
		'add esth' => '69',
		'additions to esther' => '69',
		'rest esther' => '69',
		'wis' => '70',
		'wisd sol' => '70',
		'wisdom solomon' => '70',
		'wisdom' => '70',
		'ecclus' => '71',
		'sir' => '71',
		'sirach' => '71',
		'ecclesiasticus' => '71',
		'baruch' => '72',
		'bar' => '72',
		'ltr jer' => '73',
		'lje' => '73',
		'let jer' => '73',
		'letter jeremiah' => '73',
		'song three holy children' => '74',
		'song three children' => '74',
		'song three youths' => '74',
		'song three jews' => '74',
		'song three' => '74',
		'song thr' => '74',
		'prayer azariah' => '74',
		'azariah' => '74',
		'pr az' => '74',
		'susanna' => '75',
		'sus' => '75',
		'bel and dragon' => '76',
		'bel dragon' => '76',
		'bel' => '76',
		'1maccabees' => '77',
		'1macc' => '77',
		'1mac' => '77',
		'1ma' => '77',
		'2maccabees' => '78',
		'2macc' => '78',
		'2mac' => '78',
		'2ma' => '78',
		'1esdras' => '79',
		'1esdr' => '79',
		'1esd' => '79',
		'1es' => '79',
		'prayer manasses' => '80',
		'prayer manasseh' => '80',
		'pr man' => '80',
		'pma' => '80',
		'2esdras' => '81',
		'2esdr' => '81',
		'2esd' => '81',
		'2es' => '81'
	),
	'1' => array(
		'ge' => '1',
		'gn' => '1',
		'le' => '3',
		'lv' => '3',
		'nb' => '4',
		'nm' => '4',
		'nu' => '4',
		'dt' => '5',
		'jg' => '7',
		'ru' => '8',
		'1s' => '9',
		'2s' => '10',
		'1k' => '11',
		'2k' => '12',
		'ne' => '16',
		'es' => '17',
		'jb' => '18',
		'ps' => '19',
		'pr' => '20',
		'ec' => '21',
		'jr' => '24',
		'je' => '24',
		'la' => '25',
		'dn' => '27',
		'da' => '27',
		'ho' => '28',
		'jl' => '29',
		'ob' => '31',
		'na' => '34',
		'zp' => '36',
		'hg' => '37',
		'zc' => '38',
		'ml' => '39',
		'mt' => '40',
		'mr' => '41',
		'mk' => '41',
		'lk' => '42',
		'jn' => '43',
		'ac' => '44',
		'rm' => '45',
		'ro' => '45',
		'ga' => '48',
		'jm' => '59',
		're' => '66',
		'tb' => '67',
		'ws' => '70',
		'1m' => '77',
		'2m' => '78'
	),
	'2' => array(
		'ex' => '2',
		'so' => '22',
		'is' => '23',
		'am' => '30',
	));

	/**
	 * Array of strings which can be used as the beginning of a numbered book (ex. 1st Samuel)
	 *
	 * @var array
	 */
	static $num_strings = array(
	'1' => 1,
	'2' => 2,
	'3' => 3,
	'one' => 1,
	'two' => 2,
	'three' => 3,
	'i' => 1,
	'ii' => 2,
	'iii' => 3,
	'1st' => 1,
	'2nd' => 2,
	'3rd' => 3,
	'first' => 1,
	'second' => 2,
	'third' => 3
	);

	/**
	 * Array of words to ignore
	 *
	 * If a word is set to true, then the ignore word can begin at the beginning of the book name
	 *
	 * @var array
	 */
	static $ignore_words = array(
	'book' => TRUE,
	'of' => FALSE,
	'the' => TRUE
	);

	/**
	 * Array of string which are valid synonym prefixes
	 *
	 * @var array
	 */
	static $prefixes = array(
	'song' => TRUE,
	'canticle' => TRUE,
	'rest' => TRUE,
	'add' => TRUE,
	'additions to' => TRUE,
	'additions' => TRUE,
	'wisd' => TRUE,
	'wisdom' => TRUE,
	'ltr' => TRUE,
	'let' => TRUE,
	'letter' => TRUE,
	'song three holy' => TRUE,
	'song three' => TRUE,
	'prayer' => TRUE,
	'pr' => TRUE,
	'bel and' => TRUE,
	'bel' => TRUE
	);

	const start_book = 1; // Genesis
	const end_book = 66; // Revelation

	const start_chapter = 1;
	const start_verse = 1;

	static function end_verse_min($book, $chapter = 0) {
		return self::$min_verse_counts[$book][$chapter];
	}

	static function end_verse_max($book, $chapter = 0) {
		return (isset(self::$max_verse_counts[$book][$chapter])) ? self::$max_verse_counts[$book][$chapter] : self::$min_verse_counts[$book][$chapter];
	}

	private static $min_verse_counts = array(
	1 => array(50, 31, 25, 24, 26, 32, 22, 24, 22, 29, 32, 32, 20, 18, 24, 21, 16, 27, 33, 38, 18, 34, 24, 20, 67, 34, 35, 46, 22, 35, 43, 55, 32, 20, 31, 29, 43, 36, 30, 23, 23, 57, 38, 34, 34, 28, 34, 31, 22, 33, 26),
	2 => array(40, 22, 25, 22, 31, 23, 30, 25, 32, 35, 29, 10, 51, 22, 31, 27, 36, 16, 27, 25, 26, 36, 31, 33, 18, 40, 37, 21, 43, 46, 38, 18, 35, 23, 35, 35, 38, 29, 31, 43, 38),
	3 => array(27, 17, 16, 17, 35, 19, 30, 38, 36, 24, 20, 47, 8, 59, 57, 33, 34, 16, 30, 37, 27, 24, 33, 44, 23, 55, 46, 34),
	4 => array(36, 54, 34, 51, 49, 31, 27, 89, 26, 23, 36, 35, 16, 33, 45, 41, 50, 13, 32, 22, 29, 35, 41, 30, 25, 18, 65, 23, 31, 40, 16, 54, 42, 56, 29, 34, 13),
	5 => array(34, 46, 37, 29, 49, 33, 25, 26, 20, 29, 22, 32, 32, 18, 29, 23, 22, 20, 22, 21, 20, 23, 30, 25, 22, 19, 19, 26, 68, 29, 20, 30, 52, 29, 12),
	6 => array(24, 18, 24, 17, 24, 15, 27, 26, 35, 27, 43, 23, 24, 33, 15, 63, 10, 18, 28, 51, 9, 45, 34, 16, 33),
	7 => array(21, 36, 23, 31, 24, 31, 40, 25, 35, 57, 18, 40, 15, 25, 20, 20, 31, 13, 31, 30, 48, 25),
	8 => array(4, 22, 23, 18, 22),
	9 => array(31, 28, 36, 21, 22, 12, 21, 17, 22, 27, 27, 15, 25, 23, 52, 35, 23, 58, 30, 24, 42, 15, 23, 29, 22, 44, 25, 12, 25, 11, 31, 13),
	10 => array(24, 27, 32, 39, 12, 25, 23, 29, 18, 13, 19, 27, 31, 39, 33, 37, 23, 29, 33, 43, 26, 22, 51, 39, 25),
	11 => array(22, 53, 46, 28, 34, 18, 38, 51, 66, 28, 29, 43, 33, 34, 31, 34, 34, 24, 46, 21, 43, 29, 53),
	12 => array(25, 18, 25, 27, 44, 27, 33, 20, 29, 37, 36, 21, 21, 25, 29, 38, 20, 41, 37, 37, 21, 26, 20, 37, 20, 30),
	13 => array(29, 54, 55, 24, 43, 26, 81, 40, 40, 44, 14, 47, 40, 14, 17, 29, 43, 27, 17, 19, 8, 30, 19, 32, 31, 31, 32, 34, 21, 30),
	14 => array(36, 17, 18, 17, 22, 14, 42, 22, 18, 31, 19, 23, 16, 22, 15, 19, 14, 19, 34, 11, 37, 20, 12, 21, 27, 28, 23, 9, 27, 36, 27, 21, 33, 25, 33, 27, 23),
	15 => array(10, 11, 70, 13, 24, 17, 22, 28, 36, 15, 44),
	16 => array(13, 11, 20, 32, 23, 19, 19, 73, 18, 38, 39, 36, 47, 31),
	17 => array(10, 22, 23, 15, 17, 14, 14, 10, 17, 32, 3),
	18 => array(42, 22, 13, 26, 21, 27, 30, 21, 22, 35, 22, 20, 25, 28, 22, 35, 22, 16, 21, 29, 29, 34, 30, 17, 25, 6, 14, 23, 28, 25, 31, 40, 22, 33, 37, 16, 33, 24, 41, 30, 24, 34, 17),
	19 => array(150, 6, 12, 8, 8, 12, 10, 17, 9, 20, 18, 7, 8, 6, 7, 5, 11, 15, 50, 14, 9, 13, 31, 6, 10, 22, 12, 14, 9, 11, 12, 24, 11, 22, 22, 28, 12, 40, 22, 13, 17, 13, 11, 5, 26, 17, 11, 9, 14, 20, 23, 19, 9, 6, 7, 23, 13, 11, 11, 17, 12, 8, 12, 11, 10, 13, 20, 7, 35, 36, 5, 24, 20, 28, 23, 10, 12, 20, 72, 13, 19, 16, 8, 18, 12, 13, 17, 7, 18, 52, 17, 16, 15, 5, 23, 11, 13, 12, 9, 9, 5, 8, 28, 22, 35, 45, 48, 43, 13, 31, 7, 10, 10, 9, 8, 18, 19, 2, 29, 176, 7, 8, 9, 4, 8, 5, 6, 5, 6, 8, 8, 3, 18, 3, 3, 21, 26, 9, 8, 24, 13, 10, 7, 12, 15, 21, 10, 20, 14, 9, 6),
	20 => array(31, 33, 22, 35, 27, 23, 35, 27, 36, 18, 32, 31, 28, 25, 35, 33, 33, 28, 24, 29, 30, 31, 29, 35, 34, 28, 28, 27, 28, 27, 33, 31),
	21 => array(12, 18, 26, 22, 16, 20, 12, 29, 17, 18, 20, 10, 14),
	22 => array(8, 17, 17, 11, 16, 16, 13, 13, 14),
	23 => array(66, 31, 22, 26, 6, 30, 13, 25, 22, 21, 34, 16, 6, 22, 32, 9, 14, 14, 7, 25, 6, 17, 25, 18, 23, 12, 21, 13, 29, 24, 33, 9, 20, 24, 17, 10, 22, 38, 22, 8, 31, 29, 25, 28, 28, 25, 13, 15, 22, 26, 11, 23, 15, 12, 17, 13, 12, 21, 14, 21, 22, 11, 12, 19, 12, 25, 24),
	24 => array(52, 19, 37, 25, 31, 31, 30, 34, 22, 26, 25, 23, 17, 27, 22, 21, 21, 27, 23, 15, 18, 14, 30, 40, 10, 38, 24, 22, 17, 32, 24, 40, 44, 26, 22, 19, 32, 21, 28, 18, 16, 18, 22, 13, 30, 5, 28, 7, 47, 39, 46, 64, 34),
	25 => array(5, 22, 22, 66, 22, 22),
	26 => array(48, 28, 10, 27, 17, 17, 14, 27, 18, 11, 22, 25, 28, 23, 23, 8, 63, 24, 32, 14, 49, 32, 31, 49, 27, 17, 21, 36, 26, 21, 26, 18, 32, 33, 31, 15, 38, 28, 23, 29, 49, 26, 20, 27, 31, 25, 24, 23, 35),
	27 => array(12, 21, 49, 30, 37, 31, 28, 28, 27, 27, 21, 45, 13),
	28 => array(14, 11, 23, 5, 19, 15, 11, 16, 14, 17, 15, 12, 14, 16, 9),
	29 => array(3, 20, 32, 21),
	30 => array(9, 15, 16, 15, 13, 27, 14, 17, 14, 15),
	31 => array(1, 21),
	32 => array(4, 17, 10, 10, 11),
	33 => array(7, 16, 13, 12, 13, 15, 16, 20),
	34 => array(3, 15, 13, 19),
	35 => array(3, 17, 20, 19),
	36 => array(3, 18, 15, 20),
	37 => array(2, 15, 23),
	38 => array(14, 21, 13, 10, 14, 11, 15, 14, 23, 17, 12, 17, 14, 9, 21),
	39 => array(4, 14, 17, 18, 6),
	40 => array(28, 25, 23, 17, 25, 48, 34, 29, 34, 38, 42, 30, 50, 58, 36, 39, 28, 27, 35, 30, 34, 46, 46, 39, 51, 46, 75, 66, 20),
	41 => array(16, 45, 28, 35, 41, 43, 56, 37, 38, 50, 52, 33, 44, 37, 72, 47, 20),
	42 => array(24, 80, 52, 38, 44, 39, 49, 50, 56, 62, 42, 54, 59, 35, 35, 32, 31, 37, 43, 48, 47, 38, 71, 56, 53),
	43 => array(21, 51, 25, 36, 54, 47, 71, 53, 59, 41, 42, 57, 50, 38, 31, 27, 33, 26, 40, 42, 31, 25),
	44 => array(28, 26, 47, 26, 37, 42, 15, 60, 40, 43, 48, 30, 25, 52, 28, 41, 40, 34, 28, 41, 38, 40, 30, 35, 27, 27, 32, 44, 31),
	45 => array(16, 32, 29, 31, 25, 21, 23, 25, 39, 33, 21, 36, 21, 14, 23, 33, 24),
	46 => array(16, 31, 16, 23, 21, 13, 20, 40, 13, 27, 33, 34, 31, 13, 40, 58, 24),
	47 => array(13, 24, 17, 18, 18, 21, 18, 16, 24, 15, 18, 33, 21, 14),
	48 => array(6, 24, 21, 29, 31, 26, 18),
	49 => array(6, 23, 22, 21, 32, 33, 24),
	50 => array(4, 30, 30, 21, 23),
	51 => array(4, 29, 23, 25, 18),
	52 => array(5, 10, 20, 13, 18, 28),
	53 => array(3, 12, 17, 18),
	54 => array(6, 20, 15, 16, 16, 25, 21),
	55 => array(4, 18, 26, 17, 22),
	56 => array(3, 16, 15, 15),
	57 => array(1, 25),
	58 => array(13, 14, 18, 19, 16, 14, 20, 28, 13, 28, 39, 40, 29, 25),
	59 => array(5, 27, 26, 18, 17, 20),
	60 => array(5, 25, 25, 22, 19, 14),
	61 => array(3, 21, 22, 18),
	62 => array(5, 10, 29, 24, 21, 21),
	63 => array(1, 13),
	64 => array(1, 14),
	65 => array(1, 25),
	66 => array(22, 20, 29, 22, 11, 14, 17, 17, 13, 21, 11, 19, 17, 18, 20, 8, 21, 18, 24, 21, 15, 27, 20),
	67 => array(14, 22, 14, 17, 21, 22, 17, 18, 21, 6, 12, 19, 22, 18, 15),
	68 => array(16, 16, 28, 10, 15, 24, 21, 32, 36, 14, 23, 23, 20, 20, 19, 13, 25),
	69 => array(7, 10, 6, 6, 18, 13, 9, 24, 19, 30, 14, 10, 12, 29, 32, 14),
	70 => array(5, 16, 24, 19, 20, 23, 25, 30, 21, 18, 21, 26, 27, 19, 31, 19, 29, 21, 25, 22),
	71 => array(1, 30, 18, 31, 31, 15, 37, 36, 19, 18, 31, 34, 18, 26, 27, 20, 30, 32, 33, 30, 32, 28, 27, 28, 34, 26, 29, 30, 26, 28, 25, 31, 24, 31, 26, 20, 26, 31, 34, 35, 30, 24, 25, 33, 22, 26, 20, 25, 25, 16, 29, 30),
	72 => array(5, 22, 35, 37, 37, 9),
	73 => array(1, 73),
	74 => array(1),
	75 => array(1, 64),
	76 => array(16, 42),
	77 => array(15, 64, 70, 60, 61, 68, 63, 50, 32, 73, 89, 74, 53, 53, 49, 41, 24),
	78 => array(9, 36, 32, 40, 50, 27, 31, 42, 36, 29, 38, 38, 45, 26, 46, 39),
	79 => array(16, 58, 30, 24, 63, 73, 34, 15, 96, 55),
	81 => array(40, 48, 36, 52, 56, 59, 70, 63, 47, 59, 46, 51, 58, 48, 63, 78)
	);

	private static $max_verse_counts = array (
	45 => array(14 => 26, 16 => 27),
	66 => array(22 => 21),
	69 => array(1 => 11, 2 => 6, 3 => 22, 4 => 23, 5 => 13, 6 => 9, 7 => 30),
	);

} // End of BibleMeta class

?>