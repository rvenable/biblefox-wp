<?php

require_once BFOX_REFS_DIR . '/bible-meta.php';
require_once BFOX_REFS_DIR . '/verse.php';
require_once BFOX_REFS_DIR . '/sequences.php';
require_once BFOX_REFS_DIR . '/parser.php';

class BfoxRefs extends BfoxSequenceList {

	/*
	 * Creation and Modification Functions
	 *
	 *
	 */

	public function __construct($value = NULL) {
		if (is_string($value)) $this->add_string($value);
		elseif ($value instanceof BfoxRefs) $this->add_refs($value);
	}

	/**
	 * Add bible references
	 *
	 * @param BfoxRefs $refs
	 */
	public function add_refs(BfoxRefs $refs) {
		$this->add_seqs($refs->get_seqs());
	}

	/**
	 * Add bible references from a string
	 *
	 * @param string $ref_str
	 */
	public function add_string($ref_str) {
		$this->add_refs(BfoxRefParser::simple($ref_str));
	}

	public function add_concat($begin_str, $end_str, $delim = ',') {
		$ends = explode($delim, $end_str);
		foreach (explode($delim, $begin_str) as $idx => $begin) if (isset($ends[$idx])) $this->add_seq($begin, $ends[$idx]);
	}

	/**
	 * Subtract bible references
	 *
	 * @param BfoxRefs $refs
	 */
	public function sub_refs(BfoxRefs $refs) {
		$this->sub_seqs($refs->get_seqs());
	}

	/**
	 * Subtract bible references specified by a string
	 *
	 * @param string $ref_str
	 */
	public function sub_string($ref_str) {
		$this->sub_refs(BfoxRefParser::simple($ref_str));
	}

	/**
	 * Add a sequence corresponding to a whole book
	 *
	 * @param integer $book_id
	 */
	public function add_whole_book($book_id) {
		$this->add_whole($book_id, 0, BibleVerse::max_chapter_id);
	}

	/**
	 * Add a sequence corresponding to whole chapters (or verses, if $verse_chapter is set)
	 *
	 * @param integer $book_id
	 * @param integer $chapter1
	 * @param integer $chapter2
	 * @param integer $verse_chapter If this is non-zero, it will be used as the chapter number and the chapter numbers will be the verse numbers
	 */
	public function add_whole($book_id, $chapter1, $chapter2 = 0, $verse_chapter = 0) {
		if (empty($chapter2)) $chapter2 = $chapter1;

		// If the verse chapter is set, then these are actually verses
		if (empty($verse_chapter)) $this->add_mixed($book_id, $chapter1, 0, $chapter2, BibleVerse::max_verse_id);
		else $this->add_inner($book_id, $verse_chapter, $chapter1, $chapter2);
	}

	/**
	 * Add a sequence corresponding to verses within a specific chapter
	 *
	 * @param integer $book_id
	 * @param integer $chapter1
	 * @param integer $verse1
	 * @param integer $verse2
	 */
	public function add_inner($book_id, $chapter1, $verse1, $verse2 = 0) {
		if (empty($verse2)) $verse2 = $verse1;
		$this->add_mixed($book_id, $chapter1, $verse1, $chapter1, $verse2);
	}

	/**
	 * Add a sequence of verses which can be in different chapters
	 *
	 * @param integer $book_id
	 * @param integer $chapter1
	 * @param integer $verse1
	 * @param integer $chapter2
	 * @param integer $verse2
	 */
	public function add_mixed($book_id, $chapter1, $verse1, $chapter2, $verse2, $verse_chapter = 0) {
		// Handle verse chapters, in case this is following a verse and comma
		if (!empty($verse_chapter) && empty($verse1)) {
			$verse1 = $chapter1;
			$chapter1 = $verse_chapter;
		}

		$this->add_verse_seq($book_id, $chapter1, $verse1, $chapter2, $verse2);
	}

	public function add_bcv($book, $cv) {
		$this->add_verse_seq($book, $cv->start[0], $cv->start[1], $cv->end[0], $cv->end[1]);
	}

	/**
	 * Add a sequence of verses. This prepares the verses before calling add_seq()
	 *
	 * @param integer $book
	 * @param integer $chapter1
	 * @param integer $verse1
	 * @param integer $chapter2
	 * @param integer $verse2
	 */
	public function add_verse_seq($book, $chapter1, $verse1, $chapter2, $verse2) {
		// Adjust verse1 to be zero if it is one
		if (1 == $verse1) $verse1 = 0;

		// Adjust chapter1 to zero if this is the first verse of the first chapter
		if ((1 == $chapter1) && (0 == $verse1)) $chapter1 = 0;

		// Adjust verse2 to be max_verse_id if it is greater than equal to the end verse (min) for chapter2,
		// or if chapter2 is greater than the end chapter for this verse
		if (($verse2 >= BibleMeta::end_verse_min($book, $chapter2)) || ($chapter2 > BibleMeta::end_verse_min($book))) $verse2 = BibleVerse::max_verse_id;

		// Adjust chapter2 to be max_chapter_id if it is greater than or equal to the last chapter of this book
		if ((BibleVerse::max_verse_id == $verse2) && ($chapter2 >= BibleMeta::end_verse_min($book))) $chapter2 = BibleVerse::max_chapter_id;

		$this->add_seq(
			BibleVerse::calc_unique_id($book, $chapter1, $verse1),
			BibleVerse::calc_unique_id($book, $chapter2, $verse2));
	}

	/*
	 * Get Functions
	 *
	 *
	 */

	/**
	 * Returns a BCV array. These are useful for situations where the sequences need to be divided by books (such as in get_string()).
	 *
	 * A BCV array has book_ids for keys and CVS arrays for elements.
	 * A CVS array is an array of CVs.
	 * A CV is an object with start and end values that are arrays of (chapter, verse).
	 *
	 * BCV = array([book_id] => CVS, ...)
	 * CVS = array(CV, ...)
	 * CV = object(start => array(chapter, verse), end => array(chapter, verse))
	 *
	 * @param unknown_type $sequences
	 * @return unknown
	 */
	public static function get_bcvs($sequences)
	{
		$bcvs = array();
		foreach ($sequences as $seq)
		{
			list($book1, $ch1, $vs1) = BibleVerse::calc_ref($seq->start);
			list($book2, $ch2, $vs2) = BibleVerse::calc_ref($seq->end);

			$books = array();
			$books[$book1]->start = array($ch1, $vs1);
			if ($book2 > $book1)
			{
				$start = array(0, 0);
				$end = array(BibleVerse::max_chapter_id, BibleVerse::max_verse_id);

				$books[$book1]->end = $end;

				$middle_books = $book2 - $book1;
				for ($i = 1; $i < $middle_books; $i++)
				{
					$book = $book1 + $i;
					$books[$book]->start = $start;
					$books[$book]->end = $end;
				}

				$books[$book2]->start = $start;
			}
			$books[$book2]->end = array($ch2, $vs2);

			foreach ($books as $book => $cv) $bcvs[$book] []= $cv;
		}

		return $bcvs;
	}

	/**
	 * Get the string
	 *
	 * @return string
	 */
	public function get_string($name = '')
	{
		$books = array();

		$bcvs = self::get_bcvs($this->sequences);
		foreach ($bcvs as $book => $cvs) $books[$book] = self::create_book_string($book, $cvs, $name);

		return implode('; ', $books);
	}

	/**
	 * Returns an SQL expression for comparing these bible references against one unique id column
	 *
	 * @param string $col1
	 * @return string
	 */
	public function sql_where($col1 = 'unique_id')
	{
		global $wpdb;

		$wheres = array();
		foreach ($this->sequences as $seq) $wheres []= $wpdb->prepare("($col1 >= %d AND $col1 <= %d)", $seq->start, $seq->end);

		return '(' . implode(' OR ', $wheres) . ')';
	}

	/**
	 * Returns an SQL expression for comparing these bible references against two unique id columns
	 *
	 * @param string $col1
	 * @param string $col2
	 * @return string
	 */
	public function sql_where2($col1 = 'verse_begin', $col2 = 'verse_end')
	{
		/*
		 Equation for determining whether one bible reference overlaps another

		 a1 <= b1 and b1 <= a2 or
		 a1 <= b2 and b2 <= a2
		 or
		 b1 <= a1 and a1 <= b2 or
		 b1 <= a2 and a2 <= b2

		 a1b1 * b1a2 + a1b2 * b2a2 + b1a1 * a1b2 + b1a2 * a2b2
		 b1a2 * (a1b1 + a2b2) + a1b2 * (b1a1 + b2a2)

		 */

		global $wpdb;

		$wheres = array();

		// Old equations using reduced <= and >= operators - these might not be as easy for MySQL to optimize as the BETWEEN operator
		/*foreach ($this->sequences as $seq) $wheres []= $wpdb->prepare(
			"((($col1 <= %d) AND ((%d <= $col1) OR (%d <= $col2))) OR
			((%d <= $col2) AND (($col1 <= %d) OR ($col2 <= %d))))",
			$seq->end, $seq->start, $seq->end,
			$seq->start, $seq->start, $seq->end);*/

		// Using the BETWEEN operator
		foreach ($this->sequences as $seq) $wheres []= $wpdb->prepare(
			"($col1 BETWEEN %d AND %d) OR ($col2 BETWEEN %d AND %d) OR (%d BETWEEN $col1 AND $col2) OR (%d BETWEEN $col1 AND $col2)",
			$seq->start, $seq->end, $seq->start, $seq->end, $seq->start, $seq->end);

		return '(' . implode(' OR ', $wheres) . ')';
	}

	/*
	 * Utility Functions
	 *
	 *
	 */

	/**
	 * Creates a string for a book with CVS values (see get_bcvs())
	 *
	 * @param integer $book
	 * @param array $cvs
	 * @param string $name
	 * @return string
	 */
	public static function create_book_string($book, $cvs, $name = '')
	{
		$str = '';
		$prev_ch = 0;

		foreach ((array) $cvs as $cv)
		{
			list($ch1, $vs1) = $cv->start;
			list($ch2, $vs2) = $cv->end;

			$is_whole_book = FALSE;

			// If chapter1 is 0, then this is either a whole book, or needs to begin at chapter 1
			if (0 == $ch1)
			{
				if (BibleVerse::max_chapter_id == $ch2) $is_whole_book = TRUE;
				else $ch1 = BibleMeta::start_chapter;
			}

			if (!$is_whole_book)
			{
				$is_whole_chapters = FALSE;

				// If verse1 is 0, then this is either a whole chapter(s), or needs to begin at verse 1
				if (0 == $vs1)
				{
					if (BibleVerse::max_verse_id == $vs2) $is_whole_chapters = TRUE;
					else $vs1 = BibleMeta::start_verse;
				}

				// Adjust the end chapter and verse to be the actual maximum chapter/verse we can display
				$ch2 = min($ch2, BibleMeta::end_verse_max($book));
				$vs2 = min($vs2, BibleMeta::end_verse_max($book, $ch2));

				if ($ch1 != $prev_ch)
				{
					if (!empty($str)) $str .= '; ';
					// Whole Chapters
					if ($is_whole_chapters)
					{
						$str .= $ch1;
						if ($ch1 != $ch2) $str .= "-$ch2";
					}
					// Inner Chapters
					elseif ($ch1 == $ch2)
					{
						$str .= "$ch1:$vs1";
						if ($vs1 != $vs2) $str .= "-$vs2";
					}
					// Mixed Chapters
					else
					{
						$str .= $ch1;
						if (BibleMeta::start_verse != $vs1) $str .= ":$vs1";
						$str .= "-$ch2:$vs2";
					}
				}
				else
				{
					$str .= ",$vs1";
					// Inner Chapters
					if ($ch1 == $ch2)
					{
						if ($vs1 != $vs2) $str .= "-$vs2";
					}
					// Mixed Chapters
					else
					{
						$str .= "-$ch2:$vs2";
					}
				}

				$prev_ch = $ch2;
			}
		}

		if (!empty($str)) $str = " $str";

		return BibleMeta::get_book_name($book, $name) . $str;
	}

	/**
	 * Converts a sequence in bcv form to an array of sequences, with each element being a separate chapter
	 *
	 * @param integer $book
	 * @param object $cv
	 * @return array
	 */
	private static function bcv_to_chapter_seqs($book, $cv) {
		list($ch1, $vs1) = $cv->start;
		list($ch2, $vs2) = $cv->end;

		if (0 == $ch1) $ch1 = 1;
		if (BibleVerse::max_chapter_id == $ch2) $ch2 = BibleMeta::end_verse_max($book);

		$seqs = array();
		$seqs[$ch1]->start = BibleVerse::calc_unique_id($book, $ch1, $vs1);
		if ($ch2 > $ch1)
		{
			$seqs[$ch1]->end = BibleVerse::calc_unique_id($book, $ch1, BibleVerse::max_verse_id);

			$middle_chapters = $ch2 - $ch1;
			for ($i = 0; $i < $middle_chapters; $i++)
			{
				$ch = $ch1 + $i;
				$seqs[$ch]->start = BibleVerse::calc_unique_id($book, $ch);
				$seqs[$ch]->end = BibleVerse::calc_unique_id($book, $ch, BibleVerse::max_verse_id);
			}

			$seqs[$ch2]->start = BibleVerse::calc_unique_id($book, $ch2);
		}
		$seqs[$ch2]->end = BibleVerse::calc_unique_id($book, $ch2, $vs2);

		return $seqs;
	}

	/**
	 * Divides a bible reference into smaller references of chapter size $chapter_size
	 *
	 * @param integer $chapter_size
	 * @return array of BfoxRefs
	 */
	public function get_sections($chapter_size, $limit = 0) {
		$sections = array(new BfoxRefs);
		$index = 0;
		$ch_count = 0;

		$bcvs = self::get_bcvs($this->sequences);

		foreach ($bcvs as $book => $cvs) {
			$prev_ch = 0;

			foreach ($cvs as $cv) {
				$ch_seqs = self::bcv_to_chapter_seqs($book, $cv);

				foreach ($ch_seqs as $chapter => $seq) {

					if ($prev_ch != $chapter) $ch_count++;
					if ($ch_count > $chapter_size) {
						$index++;

						// Break out early if we've reached the limit
						if (!empty($limit) && ($index >= $limit)) return $sections;

						$ch_count = 1;
						$sections[$index] = new BfoxRefs;
					}

					$sections[$index]->add_seq($seq);

					$prev_ch = $chapter;
				}
			}

			$prev_book = $book;
		}

		return $sections;
	}
}

class BibleGroupPassage extends BfoxRefs {
	private $group;

	public function __construct($group = '') {
		if (!empty($group)) {
			$this->group = $group;
			$start = self::get_first_book($group);
			$end = self::get_last_book($group);
			for ($i = $start; $i <= $end; $i++) $this->add_whole_book($i);
		}
	}

	public static function get_first_book($group) {
		$book = 0;

		if (isset(BibleMeta::$book_groups[$group][0])) {
			$book = BibleMeta::$book_groups[$group][0];
			if (isset(BibleMeta::$book_groups[$book])) $book = self::get_first_book($book);
		}

		return $book;
	}

	public static function get_last_book($group) {
		$last_index = count(BibleMeta::$book_groups[$group]) - 1;
		$book = 0;
		if ((0 < $last_index) && isset(BibleMeta::$book_groups[$group][$last_index])) {
			$book = BibleMeta::$book_groups[$group][$last_index];
			if (isset(BibleMeta::$book_groups[$book])) $book = self::get_last_book($book);
		}

		return $book;
	}

	public function get_string($name = '') {
		return BibleMeta::get_book_name($this->group, $name);
	}
}

?>