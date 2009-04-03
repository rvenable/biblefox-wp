<?php

class BibleMeta
{
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
	public static function get_book_name($book, $name = '')
	{
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
	public static function get_book_id($raw_synonym, $max_level = 0)
	{
		$words = array();

		// Chop the synonym into words (numeric digits count as words)
		$raw_words = str_word_count(strtolower(trim($raw_synonym)), 1, self::digits);

		// There needs to be at least one word
		if (0 < count($raw_words))
		{
			// Create a new word array with only the words we don't want to ignore (and get rid of the old array
			foreach ($raw_words as $word) if (!isset(self::$ignore_words[$word])) $words []= $word;
			unset($raw_words);
		}

		return self::get_book_id_from_words($words, $max_level);
	}

	/**
	 * Returns an array of arrays, where the first element is a book id, and the second element is the leftover string following the book name
	 *
	 * @param string $str
	 * @param integer $max_level
	 * @return array of array(book_id, leftovers)
	 */
	public static function get_books_in_string($str, $max_level = 0)
	{
		$str = strtolower($str);

		$books = array(array(0, ''));
		$index = 0;
		$leftover_offset = 0;

		// Commas and semicolons cannot be in a book name, so we must split on them
		$sections = preg_split('/[,;]/', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

		// We have to operate on each section separately
		foreach ($sections as $section)
		{
			$section_str = $section[0];
			$section_offset = $section[1];

			// Search for books in this section
			$section_books = self::search_for_books($section_str, $max_level);
			foreach ($section_books as $book)
			{
				// Get the book id, offset, and length from $book
				$book_id = $book[0];
				$book_offset = $book[1] + $section_offset;
				$book_len = $book[2];

				// If this is a valid book we should add it to our book list
				if (!empty($book_id))
				{
					// Now we can calculate the leftovers from the previous book

					// The length of the leftovers is the book offset minus the leftover offset
					$leftover_len = $book_offset - $leftover_offset;

					// If the length of the leftovers is greater than 0, we can add the substr to our book array
					if (0 < $leftover_len) $leftovers = substr($str, $leftover_offset, $leftover_len);
					else $leftovers = '';
					$books[$index++] = array($prev_id, $leftovers);

					// Set the new previous id and new leftover offset
					$prev_id = $book_id;
					$leftover_offset = $book_offset + $book_len;
				}
			}
		}

		// Now we can calculate any final leftovers

		// The length of the leftovers is the length of str minus the leftover offset
		$leftover_len = strlen($str) - $leftover_offset;

		// If the length of the leftovers is greater than 0, we can add the substr to our book array
		if (0 < $leftover_len) $leftovers = substr($str, $leftover_offset, $leftover_len);
		else $leftovers = '';
		$books[$index++] = array($prev_id, $leftovers);

		return $books;
	}

	/**
	 * Search a string for book names. Returns an array of arrays.
	 *
	 * Each element of the return array is an array with these elements:
	 * Book ID
	 * Position in the search string at which the book's name began
	 * Length of the book name
	 *
	 * @param string $str
	 * @param integer $max_level
	 * @return array of array(book_id, position, length)
	 */
	private static function search_for_books($str, $max_level)
	{
		$books = array();
		$prefix_words = array();
		$prefix_offset = 0;

		// Get all the words (digits count as words) with their offsets
		$words = str_word_count($str, 2, self::digits);

		// Loop through each word to see if we can find a book name
		foreach ($words as $pos => $word)
		{
			// We should ignore ignore words (unless there are no prefix words)
			if (isset(self::$ignore_words[$word]))
			{
				// If no prefix words, but this ignore word can exist as the first word, add it to the prefix words
				if (empty($prefix_words) && self::$ignore_words[$word])
				{
					$prefix_words []= $word;
					$prefix_offset = $pos;
				}
			}
			else
			{
				$book = array();

				// Add the current word to the prefix list
				if (empty($prefix_words)) $prefix_offset = $pos;
				$prefix_words []= $word;
				$new_prefix_len = $pos + strlen($word) - $prefix_offset;

				// If the prefix words are a valid prefix, we should save them for later to see if we can add the next word
				// Otherwise, we need to see if we can get book name from the prefix words
				if (self::is_prefix($prefix_words)) $old_prefix_len = $new_prefix_len;
				else
				{
					// Try to get a book ID from the words
					$book_id = self::get_book_id_from_words($prefix_words, $max_level);

					// If we got a book ID, then we can add this book and clear our prefixes
					// Otherwise, this newest word doesn't belong with the prefix list
					if (!empty($book_id))
					{
						$books []= array($book_id, $prefix_offset, $new_prefix_len);
						$prefix_words = array();
					}
					else
					{
						// Pop off the newest word which we had just added to the prefix list
						array_pop($prefix_words);

						// If we still have a prefix list,
						// Then we should see if it is a book name and clear the prefix list
						if (!empty($prefix_words))
						{
							// If the prefix list is a valid book, we should add the book
							$book_id = self::get_book_id_from_words($prefix_words, $max_level);
							if (!empty($book_id)) $books []= array($book_id, $prefix_offset, $old_prefix_len);

							// Clear the prefix words
							$prefix_words = array();
						}

						// Now the prefix list should be empty, so we should check this word on its own

						// If this word is a prefix, then we should start a new prefix list with it
						// Otherwise, if it is a book name, we should add the book
						if (self::is_prefix(array($word)))
						{
							$prefix_offset = $pos;
							$prefix_words = array($word);
						}
						elseif ($book_id = self::get_book_id_from_words(array($word), $max_level))
						{
							$books[] = array($book_id, $pos, strlen($word));
						}
					}
				}
			}
		}

		// If we still have prefix words, check to see if they are a book
		if (!empty($prefix_words))
		{
			$book_id = self::get_book_id_from_words($prefix_words, $max_level);
			if (!empty($book_id)) $books []= array($book_id, $prefix_offset, $old_prefix_len);
		}

		return $books;
	}

	/**
	 * Returns whether a given sequence of words can be a valid synonym prefix
	 *
	 * @param array $prefix_words
	 * @return bool
	 */
	private static function is_prefix($prefix_words)
	{
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
	private static function get_book_id_from_words($words, $max_level)
	{
		if (0 < count($words))
		{
			// If the first word is a string representing a number, shift that number off the word list
			// That number will need to be prepended to the beginning of the first word
			// For instance: '1 sam' should become '1sam'
			if (isset(self::$num_strings[$words[0]])) $num = self::$num_strings[array_shift($words)];

			if (0 < count($words))
			{
				// Prepend the book number if set
				if (!empty($num)) $words[0] = $num . $words[0];

				$synonym = implode(' ', $words);

				if (!empty($synonym))
				{
					// Loop through each allowed level
					$level = 0;
					while (empty($book_id) && ($level <= $max_level))
					{
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
		'1kgs' => '11',
		'1kin' => '11',
		'1ki' => '11',
		'2kings' => '12',
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
		'ex' => '2',
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
		'so' => '22',
		'is' => '23',
		'jr' => '24',
		'je' => '24',
		'la' => '25',
		'dn' => '27',
		'da' => '27',
		'ho' => '28',
		'jl' => '29',
		'am' => '30',
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

} // End of BibleMeta class

global $bfox_syn_prefix;
$bfox_syn_prefix = array();

/* Functions used to generate the initial arrays:

function print_books_array_decl()
{
	global $wpdb;
	$arrays = $wpdb->get_results("SELECT * FROM " . BFOX_BOOKS_TABLE, ARRAY_A);

	$books = array();
	foreach ($arrays as $array)
	{
		$id = $array['id'];
		unset($array['id']);
		$books[$id] = $array;
	}
	echo "\n" . print_array_decl($books);
}

function print_syns_array_decl()
{
	global $wpdb;
	$arrays = $wpdb->get_results("SELECT * FROM " . BFOX_SYNONYMS_TABLE . " ORDER BY book_id ASC", ARRAY_A);

	$books1 = array();
	$books2 = array();
	foreach ($arrays as $array)
	{
		$syn = $array['synonym'];
		if (2 < strlen($syn))
			$books1[$syn] = $array['book_id'];
		else
			$books2[$syn] = $array['book_id'];
	}
	echo "\n" . print_array_decl(array($books1, $books2), -1);
//	echo "\n" . print_array_decl($books2);
}

*/

?>