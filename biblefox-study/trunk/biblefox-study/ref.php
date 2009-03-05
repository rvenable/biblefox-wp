<?php

define('BFOX_UNIQUE_ID_PART_SIZE', 8);
define('BFOX_UNIQUE_ID_MASK', 0xFF);
define('BFOX_UNIQUE_ID_MAX', 256);

class RefSequence
{
	protected $sequences = array();

	/**
	 * Add using a bible reference string
	 *
	 * @param string $str
	 */
	public function add_string($str)
	{
		$final_leftovers = '';

		$books = BibleMeta::get_books_in_string($str);
		foreach ($books as $book)
		{
			list($book_id, $leftovers) = $book;
			if (!empty($book_id))
			{
				preg_match('/^[\s\d-:,;]*/', $leftovers, $match);
				if (!empty($match[0]))
				{
					$leftovers = substr($leftovers, strlen($match[0]));
					$this->add_book_str($book_id, $match[0]);
				}
				else $this->add_whole_book($book_id);
			}
			$final_leftovers .= $leftovers;
		}

		return $final_leftovers;
	}

	/**
	 * Add the number part of a bible reference (ie, the 3:16 in John 3:16)
	 *
	 * @param integer $book_id
	 * @param string $str
	 */
	private function add_book_str($book_id, $str)
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
						$this->add_mixed($book_id, $ch1, $vs1, $ch2, $vs2);
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
	private function add_whole_book($book_id)
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
	private function add_mixed($book_id, $chapter1, $verse1, $chapter2, $verse2)
	{
		$this->add_seq(
			BibleVerse::calc_unique_id($book_id, $chapter1, $verse1),
			BibleVerse::calc_unique_id($book_id, $chapter2, $verse2));
	}

	/**
	 * Adds a new sequence to the the sequence list
	 *
	 * This function maintains that there are no overlapping sequences and that they are in order from lowest to highest
	 *
	 * @param integer $start
	 * @param integer $end
	 */
	private function add_seq($start, $end = 0)
	{
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
	 * Get the string for this RefSequence
	 *
	 * @return string
	 */
	public function get_string()
	{
		$books = array();
		$prev_ch = 0;

		foreach ($this->sequences as $seq)
		{
			list($book, $ch1, $vs1) = BibleVerse::calc_ref($seq->start);
			list($book, $ch2, $vs2) = BibleVerse::calc_ref($seq->end);

			if ($ch1 != $prev_ch)
			{
				if (!empty($books[$book])) $books[$book] .= '; ';
				// Whole Chapters
				if ((0 == $vs1) && (BibleVerse::max_verse_id == $vs2))
				{
					$books[$book] .= $ch1;
					if ($ch1 != $ch2) $books[$book] .= "-$ch2";
				}
				// Inner Chapters
				elseif ($ch1 == $ch2)
				{
					$books[$book] .= "$ch1:$vs1";
					if ($vs1 != $vs2) $books[$book] .= "-$vs2";
				}
				// Mixed Chapters
				else
				{
					$books[$book] .= $ch1;
					if (0 != $vs1) $books[$book] .= ":$vs1";
					$books[$book] .= "-$ch2:$vs2";
				}
			}
			else
			{
				$books[$book] .= ",$vs1";
				// Inner Chapters
				if ($ch1 == $ch2)
				{
					if ($vs1 != $vs2) $books[$book] .= "-$vs2";
				}
				// Mixed Chapters
				else
				{
					$books[$book] .= "-$ch2:$vs1";
				}
			}
			$prev_ch = $ch2;
		}

		foreach ($books as $book_id => &$str) $str = BibleMeta::get_book_name($book_id) . " $str";

		return implode('; ', $books);
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
}

class RefManager
{
	public static function get_from_str($str)
	{
		$refs = new BibleRefs();
		$refs->push_string($str);
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

abstract class BibleRefsAbstract
{
//	abstract public function is_valid();
	abstract public function get_string($format = '');
//	abstract public function get_url($context = NULL);
//	abstract public function get_link($text = NULL, $context = NULL);
//	abstract public function get_num_chapters();
//	abstract public function sql_where($col1 = 'unique_id');
//	abstract public function sql_where2($col1, $col2);
//	abstract public function increment($factor = 1);
//	abstract public function get_toc($is_full = FALSE);
//	abstract public function get_scripture($pre_format = FALSE);
}

/**
 * Creates the global synonym prefix array which is necessary for bfox_ref_replace()
 *
 */
function bfox_create_synonym_data()
{
	global $wpdb, $bfox_syn_prefix;

	if (empty($bfox_syn_prefix))
	{
		foreach (BibleMeta::$synonyms as $level => $level_syns)
		{
			foreach ($level_syns as $synonym => $book_id)
			{
				$words = preg_split('/\s/', $synonym, -1, PREG_SPLIT_NO_EMPTY);
				if (0 < count($words))
				{
					$prefix = '';
					foreach ($words as $word)
					{
						if (!empty($prefix)) $prefix .= ' ';
						$prefix .= $word;
						$bfox_syn_prefix[$prefix] = TRUE;
					}
				}
			}
		}
	}
}

function bfox_get_chapters($first_verse, $last_verse)
{
	global $wpdb, $bfox_trans;

	$query = $wpdb->prepare("SELECT chapter_id
							FROM $bfox_trans->table
							WHERE unique_id >= %d
							AND unique_id <= %d
							AND chapter_id != 0
							GROUP BY chapter_id",
							$first_verse,
							$last_verse);
	return $wpdb->get_col($query);
}

function bfox_get_passage_size($first_verse, $last_verse, $group_by = '')
{
	global $wpdb, $bfox_trans;

	if ('' != $group_by) $group_by = 'GROUP BY ' . $group_by;

	$query = $wpdb->prepare("SELECT COUNT(*)
							FROM $bfox_trans->table
							WHERE unique_id >= %d
							AND unique_id <= %d
							AND chapter_id != 0
							$group_by",
							$first_verse,
							$last_verse);
	$size = $wpdb->get_var($query);

	if (0 < $size) return $size;
	return 0;
}

function bfox_ref_replace($text)
{
	global $bfox_syn_prefix;

	// Loop throught the text, word by word (where a word is a string of non-whitespace chars)
	// Check each word to see if it is part of a synonym for a book
	// If we succesfully match a book then we can look for chapter, verse numbers
	$offset = 0;
	$prefixes = array();
	$bible_refs = array();
	while (1 == preg_match('/\S+/', $text, $matches, PREG_OFFSET_CAPTURE, $offset))
	{
		// Store the match data in more readable variables
		$pattern_start = (int) $matches[0][1];
		$pattern = (string) $matches[0][0];
		$pattern_lc = strtolower($pattern);
		$index++;

		// The word might be a portion of book name (ie, a prefix to the book name),
		// So we need to detect that using the bfox_syn_prefix array, and save those potential book 'prefixes'
		if (BibleMeta::get_book_id($pattern)) $book = $pattern;

		// For each prefix we have saved, append the current word and see if we have a synonym
		// If we have a synonym then we can use that synonym as the book
		// If we don't, we should see if the new prefix is still a valid prefix
		foreach ($prefixes as $start => &$prefix)
		{
			$prefix .= ' ' . $pattern_lc;
			if (BibleMeta::get_book_id($prefix)) $book = $prefix;

			// Unset the the prefix if it is no longer valid
			if (!isset($bfox_syn_prefix[$prefix])) unset($prefixes[$start]);
		}

		// If the current word is a prefix on its own, add it to the prefixes array
		if (isset($bfox_syn_prefix[$pattern_lc])) $prefixes[$pattern_start] = $pattern;

		$offset = $pattern_start + strlen($pattern);

		// If we have successfully found a book synonym, then we can see if there are chapter and verse references
		if (isset($book))
		{
			// If we have found a chapter/verse reference, we can see if it is valid
			if (1 == preg_match('/^\s*(\d+)(\s*-\s*\d+)?(\s*:\s*\d+)?(\s*-\s*\d+)?(\s*:\s*\d+)?/', substr($text, $offset), $matches, PREG_OFFSET_CAPTURE))
			{
				$book_name_start = $pattern_start;
				$pattern_start = (int) $matches[0][1] + $offset;
				$pattern = (string) $matches[0][0];

				$ref_str = $book . ' ' . $pattern;

				// If this is a valid bible reference, then save it to be replaced later
				$bible_ref = RefManager::get_from_str($ref_str);
				if ($bible_ref->is_valid())
					$bible_refs[$ref_str] = array('ref' => $bible_ref, 'start' => $book_name_start, 'length' => ($pattern_start - $book_name_start) + strlen($pattern));

				// Clear the prefixes
				$prefixes = array();

				$offset = $pattern_start + strlen($pattern);
			}
		}
		unset($book);
	}

	// Perform any text replacements (in reverse order since the string length might be modified)
	foreach (array_reverse($bible_refs) as $replacement => $ref)
	{
		$new_text = '<a href="' . $ref['ref']->get_url() . '">' . $replacement . '</a>';
		$text = substr_replace($text, $new_text, $ref['start'], $ref['length']);
	}

	return $text;
}

/**
 * BibleRef Class for a single bible verse
 *
 */
class BibleVerse extends BibleRefsAbstract
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

	/*
	function get_size(BibleRefVector $size_vector)
	{
		if (0 != $size_vector->values['chapter'])
			return bfox_get_passage_size($this->verse_start->unique_id, $this->verse_end->unique_id, 'chapter_id');
		if (0 != $size_vector->values['verse'])
			return bfox_get_passage_size($this->verse_start->unique_id, $this->verse_end->unique_id);
		return 0;
	}
	*/

	/**
	 * Returns the number of chapters in the bible ref (inaccurate)
	 *
	 * Results are inaccurate for the bible references that don't have a valid ending chapter.
	 * For instance, if a bible ref is set to be a whole book, the end chapter will be set to a
	 * special constant (BFOX_UNIQUE_ID_MASK) instead of the actual last chapter of the book.
	 * For more accurate results, use get_actual_chapters()
	 *
	 * @return unknown
	 */
	function get_num_chapters()
	{
		return $this->verse_end->chapter - $this->verse_start->chapter + 1;
	}

	/*
	function shift(BibleRefVector $size_vector)
	{
		// NOTE: This function was designed to replace the bfox_get_sections() function for creating a reading plan
		// It ended up being much slower however, since it is doing way too many DB queries
		// The DB queries are called by $this->get_size()

		$verse_size = 0;

		// Try to use the chapter size first
		$size = $size_vector->values['chapter'];
		if (0 != $size) $size_vector->update(array(0, $size, 0));
		else
		{
			// If the chapter size was set to 0, try to use the verse size instead
			$size = $size_vector->values['verse'];
			$size_vector->update(array(0, 0, $size));
		}

		// If the size of $this is less or equal to the target size,
		// Then we shift everything off of this,
		// Otherwise we have to partition off a portion of this bible ref
		if ($this->get_size($size_vector) <= $size)
		{
			$shifted = clone $this;
			$this->update();
		}
		else
		{
			// The new shifted value should be of size $size_vector
			$shifted = new BiblePassage(array($this->verse_start->unique_id,
												$this->verse_start->unique_id + $size_vector->value - 1));

			// This bible ref gets whatever remains
			$this->update(array($this->verse_start->unique_id + $size_vector->value,
								$this->verse_end->unique_id));
		}
		return $shifted;
	}
	*/

	/**
	 * Returns the first and last chapters of the bible reference (accurate)
	 *
	 * @return array An array with two elements: the first and last chapters
	 */
	function get_actual_chapters()
	{
		global $bfox_trans;

		$low = $this->verse_start->chapter;
		$high = $this->verse_end->chapter;

		// If the first chapter is 0, then it should really be chapter 1
		if (0 == $low) $low = 1;

		// If the last chapter is the max it can be, we should get the actual last chapter from the DB
		// TODO2: The user could also input a chapter number which is beyond the actual last chapter, but below the max
		if (BFOX_UNIQUE_ID_MASK == $high)
		{
			$high = bfox_get_num_chapters($this->verse_start->book, $bfox_trans->id);
		}

		return array($low, $high);
	}

	/**
	 * Returns an output string with a Table of Contents for the ref
	 *
	 * @param boolean $is_full Should we display the full TOC for this book or just the chapters in the ref
	 * @return string Table of Contents
	 */
	function get_toc($is_full = FALSE)
	{
		global $bfox_links, $bfox_trans;

		// TODO3: These vars are kind of hacky
		list($toc_begin, $toc_end, $ref_begin, $ref_end, $separator) = array('<center>', '</center>', '', '', ' | ');

		$book_name = BibleMeta::get_book_name($this->verse_start->book);

		// Either display the full TOC or just the chapters in the ref
		$toc = $toc_begin;
		if ($is_full)
		{
			$high = bfox_get_num_chapters($this->verse_start->book, $bfox_trans->id);
			$low = 1;
			$toc .= $book_name;
		}
		else
		{
			list($low, $high) = $this->get_actual_chapters();
			$toc .= $this->get_string();
		}
		$toc .= '<br/>';

		// Loop through the actual chapter numbers for this reference, adding links for each of them
		foreach (range($low, $high) as $chapter)
		{
			if (!empty($links)) $links .= $separator;
			$links .= $ref_begin . $bfox_links->ref_link(array('ref_str' => "$book_name $chapter", 'text' => $chapter)) . $ref_end;
		}

		$toc .= $links . $toc_end;
		return $toc;
	}

	/**
	 * Returns a string with the scripture content for these BibleRefs
	 *
	 * @param boolean $pre_format Should we pre format the scriptures?
	 * @return string
	 */
	public function get_scripture($pre_format = FALSE)
	{
		global $bfox_trans, $bfox_quicknote;

		$content = '';

		// Get the verse data from the bible translation
		$verses = $bfox_trans->get_verses($this->sql_where());
		if (!empty($verses))
		{
			if (!$pre_format)
			{
				$span_verse = TRUE;
			}

			// Try to get quick notes if we have any
			$notes = $bfox_quicknote->get_indexed_notes();

			// For each verse, do any required formatting and add it to our content
			foreach ($verses as $verse)
			{
				$verse_text = '';
				if ($verse->verse_id != 0)
					$verse_text .= '<b class="bible-verse-id">' . $verse->verse_id . '</b> ';
				if ($span_verse) $verse_text .= $bfox_quicknote->list_verse_notes($notes, $verse->unique_id);
				$verse_text .= $verse->verse;

				// Pre formatting is for when we can't use CSS (ie. in an email)
				// We just replace the tags which would have been formatted by css with tags that don't need formatting
				if ($pre_format)
				{
					$verse_text = str_replace('<span class="bible_end_p"></span>', "<br/><br/>\n", $verse_text);
					$verse_text = str_replace('<span class="bible_end_poetry"></span>', "<br/>\n", $verse_text);
					$verse_text = str_replace('<span class="bible_poetry_indent_1"></span>', '', $verse_text);
					$verse_text = str_replace('<span class="bible_poetry_indent_2"></span>', '<span style="margin-left: 20px"></span>', $verse_text);
				}

				// TODO2: We don't need the book and chapter for each verse, verses should be nested in chapter and book elements
				if ($span_verse) $verse_text = "<span class='bible_verse' book='" . BibleMeta::get_book_name($verse->book_id) . "' chapter='$verse->chapter_id' verse='$verse->verse_id'>$verse_text</span>";

				$content .= $verse_text;
			}

			// Add any remaining quick notes
			if ($span_verse) $content .= $bfox_quicknote->list_verse_notes($notes);

			$content = bfox_special_syntax($content);
		}

		return $content;
	}
}

class BibleGroupPassage extends BiblePassage
{
	private $group;

	public function __construct($group)
	{
		$this->group = $group;
		parent::__construct(
			new BibleVerse(self::get_first_book($group), 0, 0),
			new BibleVerse(self::get_last_book($group), BibleVerse::max_chapter_id, BibleVerse::max_verse_id)
			);
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

	/**
	 * Returns a string with the scripture content for these BibleRefs
	 *
	 * @param boolean $pre_format Should we pre format the scriptures?
	 * @return string
	 */
	public function get_scripture($pre_format = FALSE)
	{
		return bfox_output_bible_group($this->group);
	}
}

/*
 This class is a wrapper around BiblePassage to store it in an array
 */
class BibleRefs extends BibleRefsAbstract
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

	function is_valid()
	{
		return (0 < count($this->refs));
	}

	// Returns the internal array of BiblePassage instances converted to an
	// array of BibleRefs where each element has just one BiblePassage
	function get_refs_array()
	{
		$refs_array = array();
		foreach ($this->refs as $ref)
		{
			$new_ref = new BibleRefs;
			$new_ref->push_ref_single($ref);
			$refs_array[] = $new_ref;
		}
		return $refs_array;
	}

	function get_sets()
	{
		$unique_id_sets = array();
		foreach ($this->refs as $ref) $unique_id_sets[] = $ref->get_unique_ids();
		return $unique_id_sets;
	}

	function get_string($format = '')
	{
		$strs = array();
		foreach ($this->refs as $ref) $strs[] = $ref->get_string($format);
		return implode('; ', $strs);
	}

	function get_count()
	{
		return count($this->refs);
	}

	function get_url($context = NULL)
	{
		global $bfox_links;
		return $bfox_links->ref_url($this->get_string(), $context);
	}

	function get_link($text = NULL, $context = NULL)
	{
		global $bfox_links;
		return $bfox_links->ref_link(array('ref_str' => $this->get_string(), 'text' => $text), $context);
	}

	function get_links()
	{
		global $bfox_links;
		$links = array();
		foreach ($this->refs as $ref) $links[] = $bfox_links->ref_link($ref->get_string());
		return implode('; ', $links);
	}

	/**
	 * Returns the number of chapters (inaccurate) for the refs by accumulating the number of chapters per ref
	 *
	 * @return unknown
	 */
	function get_num_chapters()
	{
		$num_chapters = 0;
		foreach ($this->refs as $ref) $num_chapters += $ref->get_num_chapters();
		return $num_chapters;
	}

	function push_ref_single(BiblePassage $ref)
	{
		$this->refs[] = $ref;
	}

	function push_sets($unique_id_sets)
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

	function push_string($str)
	{
		if (isset(BibleMeta::$book_groups[$str]))
		{
			$this->refs []= new BibleGroupPassage($str);
		}
		else
		{
			$this->seq = new RefSequence();
			$this->seq->add_string($str);
			$this->push_sets($this->seq->get_sets());
		}
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

	function sql_where($col1 = 'unique_id')
	{
		$strs = array();
		foreach ($this->refs as $ref) $strs[] = $ref->sql_where($col1);
		return '(' . implode(' OR ', $strs) . ')';
	}

	function sql_where2($col1, $col2)
	{
		$strs = array();
		foreach ($this->refs as $ref) $strs[] = $ref->sql_where2($col1, $col2);
		return '(' . implode(' OR ', $strs) . ')';
	}

	function increment($factor = 1)
	{
		foreach ($this->refs as &$ref) $ref->increment($factor);
	}

	/*
	function shift(BibleRefVector $size_vector)
	{
		// NOTE: This function was designed to replace the bfox_get_sections() function for creating a reading plan
		// It ended up being much slower however, since it is doing way too many DB queries
		// The DB queries are called by $this->get_size()

		// Try to use the chapter size first
		$size = $size_vector->values['chapter'];
		if (0 != $size) $size_vector->update(array(0, $size, 0));
		else
		{
			// If the chapter size was set to 0, try to use the verse size instead
			$size = $size_vector->values['verse'];
			$size_vector->update(array(0, 0, $size));
		}

		// Create a new BibleRefs instance to hold all the shifted values
		$shifted = new BibleRefs;

		// Shift each ref in $this->refs until we run out of refs or our size quota is reached
		while ((0 < $size) && (0 < count($this->refs)))
		{
			// Shift the first ref in $this->refs
			$shifted_ref = $this->refs[0]->shift($size_vector);

			// If the shifted ref is valid,
			// Then push it onto our new BibleRefs and update our size quota
			if ($shifted_ref->is_valid())
			{
				$shifted->push_sets(array($shifted_ref->get_unique_ids()));
				$size -= $shifted_ref->get_size($size_vector);
			}

			// If the first ref is no longer valid, get rid of it
			if (!$this->refs[0]->is_valid()) array_shift($this->refs);
		}

		return $shifted;
	}
	*/

	function get_sections($size)
	{
		// NOTE: This function was supposed to be replaced by using the shift() functions, but they were too slow
		// It is currently being hacked to work with the new BibleRefs system

		$sections = array();
		$period = 0;
		$section = 0;
		$remainder = 0;
		$remainderStr = "";
		foreach ($this->refs as $ref)
		{
			$unique_ids = $ref->get_unique_ids();
			$chapters = bfox_get_chapters($unique_ids[0], $unique_ids[1]);
			$num_chapters = count($chapters);
			$num_sections = (int) floor(($num_chapters + $remainder) / $size);

			$tmpRef['book_id'] = $ref->get_book_id();
			$chapter1_index = 0;
			$chapter2_index = $size - $remainder - 1;
			for ($index = 0; $index < $num_sections; $index++)
			{
				$tmpRefStr = "";
				if (($index == 0) && ($remainder > 0))
				{
					$tmpRefStr .= "$remainderStr, ";
					$remainderStr = "";
					$remainder = 0;
				}

				$tmpRef['chapter1'] = $chapters[$chapter1_index];
				if ($chapter2_index > $chapter1_index)
					$tmpRef['chapter2'] = $chapters[$chapter2_index];
				else $tmpRef['chapter2'] = 0;

				$tmpRefParsed = RefManager::get_from_bcvs($tmpRef['book_id'], $tmpRef['chapter1'], $tmpRef['verse1'], $tmpRef['chapter2'], $tmpRef['verse2']);
				$tmpRefStr .= $tmpRefParsed->get_string();

				$sections[] = $tmpRefStr;

				$chapter1_index = $chapter2_index + 1;
				$chapter2_index = $chapter1_index + $size - 1;
			}

			if ($chapter1_index < $num_chapters)
			{
				$remainder += $num_chapters - $chapter1_index;
				$chapter2_index = $num_chapters - 1;

				$tmpRef['chapter1'] = $chapters[$chapter1_index];
				if ($chapter2_index > $chapter1_index)
					$tmpRef['chapter2'] = $chapters[$chapter2_index];
				else $tmpRef['chapter2'] = 0;

				if ($remainderStr != "")
					$remainderStr .= ", ";

				$tmpRefParsed = RefManager::get_from_bcvs($tmpRef['book_id'], $tmpRef['chapter1'], $tmpRef['verse1'], $tmpRef['chapter2'], $tmpRef['verse2']);
				$remainderStr .= $tmpRefParsed->get_string();
			}
		}
		if ($remainderStr != "")
			$sections[] = $remainderStr;

		$sectionRefs = array();
		foreach ($sections as $section) $sectionRefs[] = RefManager::get_from_str($section);
		return $sectionRefs;
	}

	/**
	 * Returns an output string for the Table of Contents for these refs
	 *
	 * @param boolean $is_full Should we display the full TOC for this book or just the chapters in the ref
	 * @return string Table of Contents
	 */
	function get_toc($is_full = FALSE)
	{
		$book_content = array();
		foreach ($this->refs as $ref)
		{
			$toc .= $ref->get_toc($is_full);
		}
		return $toc;
	}

	/**
	 * Returns a string with the scripture content for these BibleRefs
	 *
	 * @param boolean $pre_format Should we pre format the scriptures?
	 * @return string
	 */
	function get_scripture($pre_format = FALSE)
	{
		global $bfox_trans, $bfox_quicknote;

		$content = '';
		if ($this->is_valid())
		{
			foreach ($this->refs as $ref)
				$content .= $ref->get_scripture();

			if (empty($content)) $content = 'No verse data exists for this translation.';
		}
		else $content = 'No bible reference to display.';

		return $content;
	}

	private static function parse_ref_str($str)
	{
		// Convert all whitespace to single spaces
		$str = preg_replace('/\s+/', ' ', $str);

		// Find the last dash in the string and use it to divide the ref
		$dash_left = trim($str);
		if ($pos = strrpos($dash_left, '-'))
		{
			$dash_right = trim(substr($dash_left, $pos + 1));
			$dash_left = trim(substr($dash_left, 0, $pos));
		}

		// Parse the left side of the dash
		$left_colon_list = explode(':', $dash_left);
		$left_colon_count = count($left_colon_list);

		// We can only have one dash
		if (2 >= $left_colon_count)
		{
			// If there was a dash, then save the right side as an integer verse number
			if (1 < $left_colon_count)
				$verse_num = (int) trim($left_colon_list[1]);

			// If we didn't have any problems with the right side of the colon (verse_num)
			if ((!isset($verse_num)) || (0 < $verse_num))
			{
				if (isset($verse_num)) $verse1 = $verse_num;

				// Parse the left side of the colon to get the book name and the chapter num
				$colon_left = trim($left_colon_list[0]);
				if ($pos = strrpos($colon_left, ' '))
				{
					// Get the chapter number which must be greater than 0
					$chapter_num = (int) trim(substr($colon_left, $pos + 1));
					if (0 < $chapter_num)
					{
						$chapter1 = $chapter_num;
						$book_str = trim(substr($colon_left, 0, $pos));
					}
				}
			}
		}

		// Parse the right side of the dash if the left side worked (yielding at least a chapter id)
		if ((isset($dash_right)) && (isset($chapter1)))
		{
			$right_colon_list = explode(':', $dash_right);
			$right_colon_count = count($right_colon_list);

			// We can only have one dash
			if (2 >= $right_colon_count)
			{
				// If there was a dash, then save the right side as an integer
				if (1 < $right_colon_count)
					$num2 = (int) trim($right_colon_list[1]);

				// If we didn't have any problems with the right side of the colon (num2)
				// Then save the left side as an integer
				if ((!isset($num2)) || (0 < $num2))
					$num1 = (int) trim($right_colon_list[0]);
			}

			// If we got at least one integer and it is greater than zero,
			// then everything went fine on this side of the dash
			if ((isset($num1)) && (0 < $num1))
			{
				if (isset($num2))
				{
					// If we have 2 numbers then the first is a chapter and the second is a verse
					$chapter2 = $num1;
					$verse2 = $num2;
				}
				else
				{
					// If there is only one number on the right side of the dash,
					// then it can be either a chapter or a verse

					// If the left side of the dash yielded a verse2,
					// then we must have a verse on the right side of the dash also
					if (isset($verse1)) $verse2 = $num1;
					else $chapter2 = $num1;
				}
			}
			else
			{
				// If we didn't get any numbers on this side of the dash
				// then the string is misformatted
				unset($chapter1);
			}
		}

		// If we haven't set a book string yet, set it to the original str
		if (!isset($book_str)) $book_str = $str;

		return RefManager::get_from_bcvs(BibleMeta::get_book_id(trim($book_str), 1), $chapter1, $verse1, $chapter2, $verse2);
	}

}

?>
