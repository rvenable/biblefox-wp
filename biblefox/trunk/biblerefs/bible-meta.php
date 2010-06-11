<?php

class BibleMeta {
	const name_normal = 'name';
	const name_short = 'short_name';

	const start_book = 1; // Genesis
	const start_chapter = 1;
	const start_verse = 1;


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
	 * Array for defining book groups (sets of books)
	 *
	 * @var array
	 */
	static $book_groups = array(
	'bible' => array('old', 'new'),
	'bible_apoc' => array('old', 'new', 'apoc'),
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
	'bible_apoc' => array('name' => 'Bible', 'short_name' => 'Bible'),
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
	 * Returns the end of a passage (last chapter of book, or last verse of chapter)
	 *
	 * Because some translations have more verses in some chapters than others, this returns the largest possible number.
	 * Use earliest_end() instead if you want the smallest possible number.
	 *
	 * @param integer $book
	 * @param integer $chapter
	 * @return integer
	 */
	static function passage_end($book = 0, $chapter = 0) {
		if (!$book) return 66; // Revelation (TODO: support apocrypha as an option)
		return (isset(self::$max_verse_counts[$book][$chapter])) ? self::$max_verse_counts[$book][$chapter] : self::$min_verse_counts[$book][$chapter];
	}

	/**
	 * Returns the end of a passage (last chapter of book, or last verse of chapter)
	 *
	 * Because some translations have more verses in some chapters than others, this returns the smallest possible number.
	 * Use passage_end() instead if you want the largest possible number.
	 *
	 * @param integer $book
	 * @param integer $chapter
	 * @return integer
	 */
	static function earliest_end($book, $chapter) {
		return self::$min_verse_counts[$book][$chapter];
	}

	/**
	 * Stores the verse counts of each chapter in the bible
	 *
	 * @var array of array of integer
	 */
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

	/**
	 * Stores the maximum verse counts of each chapter in the bible
	 *
	 * @var array of array of integer
	 */
	private static $max_verse_counts = array (
	45 => array(14 => 26, 16 => 27),
	66 => array(22 => 21),
	69 => array(1 => 11, 2 => 6, 3 => 22, 4 => 23, 5 => 13, 6 => 9, 7 => 30),
	);

} // End of BibleMeta class

?>