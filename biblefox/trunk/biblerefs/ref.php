<?php

require_once('bible-meta.php');

define('BFOX_UNIQUE_ID_PART_SIZE', 8);
define('BFOX_UNIQUE_ID_MASK', 0xFF);
define('BFOX_UNIQUE_ID_MAX', 256);

class RefSequence
{
	protected $sequences = array();

	function is_valid()
	{
		return (!empty($this->sequences));
	}

	public function add_seqs($seqs)
	{
		foreach ($seqs as $seq) $this->add_seq($seq);
	}

	/**
	 * Add using a bible reference string
	 *
	 * @param string $str
	 */
	public function add_string($str, $max_level = 0)
	{
		// Get all the bible reference substrings in this string
		$substrs = BibleMeta::get_bcv_substrs($str, $max_level);

		// Add each substring to our sequences
		foreach ($substrs as $substr)
		{
			// If there is a chapter, verse string use it
			if ($substr->cv_offset) $this->add_book_str($substr->book, substr($str, $substr->cv_offset, $substr->length - ($substr->cv_offset - $substr->offset)));
			else $this->add_whole_book($substr->book);
		}
	}

	/**
	 * Add the number part of a bible reference (ie, the 3:16 in John 3:16)
	 *
	 * @param integer $book_id
	 * @param string $str
	 */
	public function add_book_str($book_id, $str)
	{
		// Spaces between numbers count as semicolons
		preg_replace('/(\d)\s+(\d)/', '$1;$2', $str);

		$semis = explode(';', $str);
		foreach ($semis as $semi)
		{
			$commas = explode(',', $semi);

			$verse_chapter = 0;
			foreach ($commas as $comma)
			{
				$dash = explode('-', $comma, 2);

				$left = explode(':', $dash[0], 2);
				$ch1 = intval($left[0]);
				$vs1 = intval($left[1]);

				$right = explode(':', $dash[1], 2);
				$ch2 = intval($right[0]);
				$vs2 = intval($right[1]);

				// We must have a chapter1
				if (0 != $ch1)
				{
					// If verse0 is not 0, but verse1 is 0, we should use chapter1 as verse1, and chapter1 should be 0
					// This fixes the following type of case: 1:2-3 (1:2-3:0 becomes 1:2-0:3)
					if ((0 != $vs1) && (0 == $vs2))
					{
						$vs2 = $ch2;
						$ch2 = 0;
					}

					// Whole Chapters (or whole verses)
					if ((0 == $vs1) && (0 == $vs2)) $this->add_whole($book_id, $ch1, $ch2, $verse_chapter);
					// Inner Chapters
					elseif ((0 == $ch2) || ($ch1 == $ch2))
					{
						$verse_chapter = $ch1;
						$this->add_inner($book_id, $verse_chapter, $vs1, $vs2);
					}
					// Mixed Chapters
					else
					{
						$this->add_mixed($book_id, $ch1, $vs1, $ch2, $vs2, $verse_chapter);
						$verse_chapter = $ch2;
					}
				}
			}
		}
	}

	/**
	 * Add a sequence corresponding to a whole book
	 *
	 * @param integer $book_id
	 */
	public function add_whole_book($book_id)
	{
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
	private function add_whole($book_id, $chapter1, $chapter2 = 0, $verse_chapter = 0)
	{
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
	private function add_inner($book_id, $chapter1, $verse1, $verse2 = 0)
	{
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
	private function add_mixed($book_id, $chapter1, $verse1, $chapter2, $verse2, $verse_chapter = 0)
	{
		// Handle verse chapters, in case this is following a verse and comma
		if (!empty($verse_chapter) && empty($verse1))
		{
			$verse1 = $chapter1;
			$chapter1 = $verse_chapter;
		}

		$this->add_verse_seq($book_id, $chapter1, $verse1, $chapter2, $verse2);
	}

	public function add_bcv($book, $cv)
	{
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
	private function add_verse_seq($book, $chapter1, $verse1, $chapter2, $verse2)
	{
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

	/**
	 * Adds a new sequence to the sequence list
	 *
	 * This function maintains that there are no overlapping sequences and that they are in order from lowest to highest
	 *
	 * @param integer $start
	 * @param integer $end
	 */
	public function add_seq($start, $end = 0)
	{
		if (is_array($start)) list($start, $end) = $start;
		elseif (is_object($start))
		{
			$end = $start->end;
			$start = $start->start;
		}

		// If the end is not set, it should equal the start
		if (empty($end)) $end = $start;

		$new_seq = (object) array('start' => $start, 'end' => $end);

		// If the end is less than the start, just switch them around
		if ($end < $start)
		{
			$new_seq->end = $start;
			$new_seq->start = $end;
		}

		$new_seqs = array();
		foreach ($this->sequences as $seq)
		{
			if (isset($new_seq))
			{
				// If the new seq starts before seq
				if ($new_seq->start < $seq->start)
				{
					// If the new seq also ends before, then we've found the spot to place it
					// Otherwise, it intersects, so modify the new seq to include seq
					if (($new_seq->end + 1) < $seq->start)
					{
						$new_seqs []= $new_seq;
						$new_seqs []= $seq;
						unset($new_seq);
					}
					else
					{
						if ($new_seq->end < $seq->end) $new_seq->end = $seq->end;
					}
				}
				else
				{
					// The new seq starts with or after seq
					// If the new seq starts before seq ends, we have an intersection
					// Otherwise, we passed seq without intersecting it, so add it to the array
					if (($new_seq->start - 1) <= $seq->end)
					{
						$new_seq->start = $seq->start;
						if ($new_seq->end < $seq->end) $new_seq->end = $seq->end;
					}
					else
					{
						$new_seqs []= $seq;
					}
				}
			}
			else $new_seqs []= $seq;
		}
		if (isset($new_seq)) $new_seqs []= $new_seq;

		$this->sequences = $new_seqs;
	}

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
	 * Get the string for this RefSequence
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
	 * Return the sequences
	 *
	 * @return array of objects
	 */
	public function get_seqs()
	{
		return $this->sequences;
	}

	/**
	 * Return unique id sets
	 *
	 * @return array of arrays
	 */
	public function get_sets()
	{
		// TODO3: get rid of this function and always use get_seqs instead
		$sets = array();
		foreach ($this->sequences as $seq) $sets []= array($seq->start, $seq->end);
		return $sets;
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
	public function sql_where2($col1, $col2)
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
			"($col1 BETWEEN %d AND %d) OR ($col2 BETWEEN %d AND %d)",
			$seq->start, $seq->end, $seq->start, $seq->end);

		return '(' . implode(' OR ', $wheres) . ')';
	}
}

class RefManager
{
	/**
	 * Returns a BibleRefs
	 *
	 * @param string $str
	 * @return BibleRefs
	 */
	public static function get_from_str($str, $max_level = 1)
	{
		if (isset(BibleMeta::$book_groups[$str])) $refs = new BibleGroupPassage();
		else $refs = new BibleRefs();

		$refs->push_string($str, $max_level);
		return $refs;
	}

	public static function get_from_sets($sets)
	{
		$refs = new BibleRefs();
		$refs->push_sets($sets);
		return $refs;
	}

	public static function get_from_concat_values($begin_str, $end_str)
	{
		$refs = new BibleRefs();
		$refs->push_concatenated($begin_str, $end_str);
		return $refs;
	}

	public static function get_from_set($unique_ids)
	{
		return self::get_from_unique_ids($unique_ids[0], $unique_ids[1]);
	}

	public static function get_from_unique_ids($start_id, $end_id)
	{
		$verse_start = new BibleVerse($start_id);
		$verse_end = new BibleVerse($end_id);
		return new BiblePassage($verse_start, $verse_end);
	}

	public static function get_from_bcvs($book, $chapter1, $verse1, $chapter2, $verse2)
	{
		$verse_start = new BibleVerse($book, $chapter1, $verse1);
		$verse_end = self::fix_end_verse($verse_start, new BibleVerse($book, $chapter2, $verse2));
		return new BiblePassage($verse_start, $verse_end);
	}

	public static function fix_end_verse(BibleVerse $verse_start, BibleVerse $verse_end)
	{
		/*
		 Conversion methods:
		 john			0:0-max:max		max:max
		 john 1			1:0-1:max		ch1:max
		 john 1-2		1:0-2:max		ch2:max
		 john 1:1		1:1-1:1			ch1:vs1
		 john 1:1-5		1:1-5:max		ch2:max
		 john 1:1-0:2	1:1-1:2			ch1:vs2
		 john 1:1-5:2	1:1-5:2			ch2:vs2
		 john 1-5:2		1:0-5:2			ch2:vs2

		 When chapter2 is not set (== 0): chapter2 equals chapter1 unless chapter1 is not set
		 When verse2 is not set (== 0): verse2 equals max unless chapter2 is not set and verse1 is set
		 */

		$verse2 = $verse_end->verse;
		$chapter2 = $verse_end->chapter;

		// When verse 2 is empty:
		//  If chapter 2 is also empty and verse 1 is not empty
		//	 Then use verse 1
		//   Otherwise use the max verse
		if (empty($verse_end->verse))
			$verse2 = ((empty($verse_end->chapter)) && (!empty($verse_start->verse))) ? $verse_start->verse : BibleVerse::max_verse_id;

		// When chapter 2 is empty:
		//  If chapter 1 is also empty
		//   Then use the max chapter
		//   Otherwise use chapter 1
		if (empty($verse_end->chapter))
			$chapter2 = (empty($verse_start->chapter)) ? BibleVerse::max_chapter_id : $verse_start->chapter;

		// Update the end verse with the new values
		$verse_end->set_ref($verse_end->book, $chapter2, $verse2);

		return $verse_end;
	}
}

/**
 * BibleRef Class for a single bible verse
 *
 */
class BibleVerse
{
	const max_num_books = 256;
	const max_num_chapters = self::max_num_books;
	const max_num_verses = self::max_num_books;

	const max_book_id = 255;
	const max_chapter_id = self::max_book_id;
	const max_verse_id = self::max_book_id;

	// Reference numbers
	public $unique_id, $book, $chapter, $verse;

	// The number portion of the reference string. Append this onto the book name to create the reference string
	public $num_str;

	public function __construct($book, $chapter = 0, $verse = 0)
	{
		// If the chapter and verse are not set, and the book is not a valid book, it must be a unique id
		if (empty($chapter) && empty($verse) && (self::max_book_id < $book)) $this->set_unique_id($book);
		else $this->set_ref($book, $chapter, $verse);
	}

	public function is_valid()
	{
		return (!empty($this->book));
	}

	public static function calc_unique_id($book, $chapter = 0, $verse = 0)
	{
		return ($book * self::max_num_chapters * self::max_num_verses) +
			($chapter * self::max_num_verses) +
			$verse;
	}

	public static function calc_ref($unique_id)
	{
		$verse = $unique_id % self::max_num_verses;
		$unique_id = (int) $unique_id / self::max_num_verses;
		$chapter = $unique_id % self::max_num_chapters;
		$unique_id = (int) $unique_id / self::max_num_chapters;
		$book = $unique_id % self::max_num_books;

		return array($book, $chapter, $verse);
	}

	public function set_ref($book, $chapter = 0, $verse = 0)
	{
		$this->book = min($book, self::max_book_id);
		$this->chapter = min($chapter, self::max_chapter_id);
		$this->verse = min($verse, self::max_verse_id);
		$this->update_unique_id();
		$this->update_num_str();
	}

	public function set_unique_id($unique_id)
	{
		$this->unique_id = $unique_id;
		$this->update_ref();
		$this->update_num_str();
	}

	private function update_ref()
	{
		list ($this->book, $this->chapter, $this->verse) = self::calc_ref($this->unique_id);
	}

	private function update_unique_id()
	{
		$this->unique_id = self::calc_unique_id($this->book, $this->chapter, $this->verse);
	}

	private function update_num_str()
	{
		$num_str = '';
		if ((0 < $this->chapter) && (self::max_chapter_id != $this->chapter))
		{
			$num_str = (string) $this->chapter;
			if ((0 < $this->verse) && (self::max_verse_id != $this->verse)) $num_str .= ":$this->verse";
		}

		$this->num_str = $num_str;
	}

	public function get_string($name = '')
	{
		$str = BibleMeta::get_book_name($this->book, $name);

		if (!empty($this->num_str)) $str .= ' ' . $this->num_str;

		return $str;
	}
}

/*
 This class is used to represent a bible reference as two unique IDs
 */
class BiblePassage
{
	public $verse_start;
	public $verse_end;
	public $num_str;

	public function __construct(BibleVerse $verse_start, BibleVerse $verse_end)
	{
		$this->verse_start = $verse_start;
		$this->verse_end = $verse_end;
		$this->update_num_str();
	}

	public function is_valid()
	{
		if ((0 < $this->verse_start->unique_id) && ($this->verse_start->unique_id <= $this->verse_end->unique_id))
			return true;
		return false;
	}

	public function get_unique_ids()
	{
		return array($this->verse_start->unique_id, $this->verse_end->unique_id);
	}

	private function update_num_str()
	{
		$num_str = '';
		if (!empty($this->verse_start->num_str))
		{
			// First get the end verse num string
			$num_str = $this->verse_end->num_str;

			// If the two chapters are equal, we need to fix the end verse num string
			if ($this->verse_start->chapter == $this->verse_end->chapter)
			{
				$num_str = '';
				if (($this->verse_end->verse != $this->verse_start->verse) && (BibleVerse::max_verse_id != $this->verse_end->verse))
					$num_str = $this->verse_end->verse;
			}
			if (!empty($num_str)) $num_str = $this->verse_start->num_str . '-' . $num_str;
			else $num_str = $this->verse_start->num_str;
		}

		$this->num_str = $num_str;
	}

	public function get_string($name = '')
	{
		$str = BibleMeta::get_book_name($this->verse_start->book, $name);

		if (!empty($this->num_str)) $str .= ' ' . $this->num_str;

		return $str;
	}

	function get_book_id()
	{
		return $this->verse_start->book;
	}

	// Returns an SQL expression for comparing this bible reference against one unique id column
	function sql_where($col1 = 'unique_id')
	{
		global $wpdb;
		return $wpdb->prepare("($col1 >= %d AND $col1 <= %d)", $this->verse_start->unique_id, $this->verse_end->unique_id);
	}

	// Returns an SQL expression for comparing this bible reference against two unique id columns
	function sql_where2($col1, $col2)
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
		return $wpdb->prepare("((($col1 <= %d) AND ((%d <= $col1) OR (%d <= $col2))) OR
							    ((%d <= $col2) AND (($col1 <= %d) OR ($col2 <= %d))))",
							  $this->verse_end->unique_id, $this->verse_start->unique_id, $this->verse_end->unique_id,
							  $this->verse_start->unique_id, $this->verse_start->unique_id, $this->verse_end->unique_id);
	}

	// Increments the bible reference by a given factor
	function increment($factor = 1)
	{
		// Only increment if we are not looking at an entire book
		if (0 != $this->verse_start->chapter)
		{
			// Get the difference between chapters
			$diff = $this->verse_end->chapter - $this->verse_start->chapter;

			// If the chapter difference is 0, and there is no specified verse
			// Then we must be viewing one single chapter, so our chapter difference should be 1
			if ((0 == $diff) && (0 == $this->verse_start->verse))
				$diff = 1;

			$chapter_inc = 0;
			$verse_inc = 0;

			// If we have a chapter difference then set the chapter increment,
			// otherwise try to set a verse increment
			if (0 < $diff) $chapter_inc = $diff * $factor;
			else
			{
				$diff = $this->verse_end->verse - $this->verse_start->verse;
				$verse_inc = (1 + $diff) * $factor;
			}

			// If we have a chapter or verse increment,
			// Then update our BibleRef with incremented bible verses
			if ((0 != $chapter_inc) || (0 != $verse_inc))
			{
				// TODO3: Fix this increment (and don't use calc_unique_id())
				$inc = BibleVerse::calc_unique_id(0, $chapter_inc, $verse_inc);
				$verse1 = new BibleVerse($inc + $this->verse_start->unique_id);
				$verse2 = new BibleVerse($inc + $this->verse_end->unique_id);
				$this->__construct($verse1, $verse2);
			}
		}
	}
}

class BibleGroupPassage extends BibleRefs
{
	private $group;

	public function __construct($group = '')
	{
		if (!empty($group)) $this->push_string($group);
	}

	public function push_string($group, $max_level = 1)
	{
		$this->group = $group;
		$start = self::get_first_book($group);
		$end = self::get_last_book($group);
		for ($i = $start; $i <= $end; $i++) $this->add_whole_book($i);
	}

	public static function get_first_book($group)
	{
		$book = 0;
		if (isset(BibleMeta::$book_groups[$group][0]))
		{
			$book = BibleMeta::$book_groups[$group][0];
			if (isset(BibleMeta::$book_groups[$book])) $book = self::get_first_book($book);
		}

		return $book;
	}

	public static function get_last_book($group)
	{
		$last_index = count(BibleMeta::$book_groups[$group]) - 1;
		$book = 0;
		if ((0 < $last_index) && isset(BibleMeta::$book_groups[$group][$last_index]))
		{
			$book = BibleMeta::$book_groups[$group][$last_index];
			if (isset(BibleMeta::$book_groups[$book])) $book = self::get_last_book($book);
		}

		return $book;
	}

	public function get_string($name = '')
	{
		return BibleMeta::get_book_name($this->group, $name);
	}
}

/*
 This class is a wrapper around BiblePassage to store it in an array
 */
class BibleRefs extends RefSequence
{
	private $refs;

	function BibleRefs($value = NULL, $value2 = NULL)
	{
		$this->refs = array();
		if (is_string($value))
		{
			if (is_null($value2)) $this->push_string($value);
			else $this->push_concatenated($value, $value2);
		}
		else if (is_array($value)) $this->push_sets($value);
	}

	function push_ref_single(BiblePassage $ref)
	{
		$ids = $ref->get_unique_ids();
		parent::add_seq($ids);
		$this->push_sets_to_refs(array($ids));
	}

	function push_sets($unique_id_sets)
	{
		parent::add_seqs((array) $unique_id_sets);
		$this->push_sets_to_refs($unique_id_sets);
	}

	// TODO3: Get rid of this function, it is just here as a temporary way to keep old $refs
	private function push_sets_to_refs($unique_id_sets)
	{
		$count = 0;
		if (is_array($unique_id_sets))
		{
			foreach ($unique_id_sets as $unique_ids)
			{
				$ref = RefManager::get_from_set($unique_ids);
				if ($ref->is_valid())
				{
					$this->refs[] = $ref;
					$count++;
				}
			}
		}
		return $count;
	}

	function push_string($str, $max_level = 1)
	{
		parent::add_string($str, $max_level);
		$this->push_sets_to_refs(parent::get_sets());
	}

	/**
	 * Push bible references represented by two strings, each containing concatenated unique ids.
	 *
	 * The first string represents all the starting unique ids, and the second string represents all the ending unique ids.
	 * This function is primarily useful for extracting bible references from SQL table entries, when the start and end
	 * unique ids are concatenated using the GROUP_CONCAT() SQL function.
	 *
	 * @param unknown_type $begin_str
	 * @param unknown_type $end_str
	 * @param unknown_type $delim
	 */
	function push_concatenated($begin_str, $end_str, $delim = ',')
	{
		$begins = explode($delim, $begin_str);
		$ends = explode($delim, $end_str);
		$sets = array();
		$index = 0;
		foreach ($begins as $begin)
		{
			$end = $ends[$index++];
			$sets[] = array((int) $begin, (int) $end);
		}
		return $this->push_sets($sets);
	}

	function increment($factor = 1)
	{
		// TODO3: fix this function for the new RefSequence
	}

	/**
	 * Converts a sequence in bcv form to an array of sequences, with each element being a separate chapter
	 *
	 * @param integer $book
	 * @param object $cv
	 * @return array
	 */
	private static function bcv_to_chapter_seqs($book, $cv)
	{
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
	 * @return array of BibleRefs
	 */
	public function get_sections($chapter_size)
	{
		$sections = array(new BibleRefs());
		$index = 0;
		$ch_count = 0;

		$bcvs = self::get_bcvs($this->sequences);

		foreach ($bcvs as $book => $cvs)
		{
			$prev_ch = 0;
			foreach ($cvs as $cv)
			{
				$ch_seqs = self::bcv_to_chapter_seqs($book, $cv);
				foreach ($ch_seqs as $chapter => $seq)
				{
					if ($prev_ch != $chapter) $ch_count++;
					if ($ch_count > $chapter_size)
					{
						$index++;
						$ch_count = 1;
						$sections[$index] = new BibleRefs();
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

?>