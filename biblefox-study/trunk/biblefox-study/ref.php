<?php

class RefManager
{
	public static function get_book_name($book, $name = 'name')
	{
		global $bfox_books;

		if (isset($bfox_books[$book][$name])) return $bfox_books[$book][$name];
		return 'Unknown';
	}

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

define('BFOX_UNIQUE_ID_PART_SIZE', 8);
define('BFOX_UNIQUE_ID_MASK', 0xFF);
define('BFOX_UNIQUE_ID_MAX', 256);

define(BFOX_REF_FORMAT_NORMAL, 'normal');
define(BFOX_REF_FORMAT_SHORT, 'short');

/**
 * Returns the book name for a given book ID
 *
 * @param integer $book_id
 * @param string $format
 * @return string
 */
function bfox_get_book_name($book_id, $format = '')
{
	global $bfox_books;

	if (BFOX_REF_FORMAT_SHORT == $format) $col = 'short_name';
	else $col = 'name';

	return $bfox_books[$book_id][$col];
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
function bfox_find_book_id($synonym, $max_level = 0)
{
	global $bfox_synonyms;
	$synonym = strtolower(trim($synonym));

	// TODO2: Books with First and Second should be handled algorithmically

	$level = 0;
	while (empty($book_id) && ($level <= $max_level))
	{
		$book_id = $bfox_synonyms[$level][$synonym];
		$level++;
	}

	if (empty($book_id)) return FALSE;
	return $book_id;
}

/**
 * Creates the global synonym prefix array which is necessary for bfox_ref_replace()
 *
 */
function bfox_create_synonym_data()
{
	global $wpdb, $bfox_synonyms, $bfox_syn_prefix;

	if (empty($bfox_syn_prefix))
	{
		foreach ($bfox_synonyms as $level => $level_syns)
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
		if (bfox_find_book_id($pattern)) $book = $pattern;

		// For each prefix we have saved, append the current word and see if we have a synonym
		// If we have a synonym then we can use that synonym as the book
		// If we don't, we should see if the new prefix is still a valid prefix
		foreach ($prefixes as $start => &$prefix)
		{
			$prefix .= ' ' . $pattern_lc;
			if (bfox_find_book_id($prefix)) $book = $prefix;

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

	public $unique_id, $book, $chapter, $verse;

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
	}

	public function set_unique_id($unique_id)
	{
		$this->unique_id = $unique_id;
		$this->update_ref();
	}

	public function update_ref()
	{
		list ($this->book, $this->chapter, $this->verse) = self::calc_ref($this->unique_id);
	}

	public function update_unique_id()
	{
		$this->unique_id = self::calc_unique_id($this->book, $this->chapter, $this->verse);
	}

	public function get_string($name = 'name')
	{
		$str = RefManager::get_book_name($this->book, $name);
		if ((0 < $this->chapter) && (self::max_chapter_id != $this->chapter))
		{
			$str .= " $this->chapter";
			if ((0 < $this->verse) && (self::max_verse_id != $this->verse)) $str .= ":$this->verse";
		}

		return $str;
	}
}

/*
 This class is used to represent a bible reference using the following integer values:
 book_id, chapter1, verse1, chapter2, verse2

 This form allows easy conversion between the unique ID form (BibleRefSingle) and string input/output
 */
class BibleRefParsed
{
	function set($book_id = 0, $chapter1 = 0, $verse1 = 0, $chapter2 = 0, $verse2 = 0)
	{
		$book_id  &= BFOX_UNIQUE_ID_MASK;
		$chapter1 &= BFOX_UNIQUE_ID_MASK;
		$verse1   &= BFOX_UNIQUE_ID_MASK;
		$chapter2 &= BFOX_UNIQUE_ID_MASK;
		$verse2   &= BFOX_UNIQUE_ID_MASK;

		if (0 < $book_id) $this->book_id = $book_id;
		if (0 < $chapter1) $this->chapter1 = $chapter1;
		if (0 < $verse1) $this->verse1 = $verse1;
		if (0 < $chapter2) $this->chapter2 = $chapter2;
		if (0 < $verse2) $this->verse2 = $verse2;
	}

	function set_by_unique_ids($unique_ids)
	{
		$book_id  = (int) (($unique_ids[0] >> (BFOX_UNIQUE_ID_PART_SIZE * 2)) & BFOX_UNIQUE_ID_MASK);
		$chapter1 = (int) (($unique_ids[0] >> BFOX_UNIQUE_ID_PART_SIZE) & BFOX_UNIQUE_ID_MASK);
		$verse1   = (int)  ($unique_ids[0] & BFOX_UNIQUE_ID_MASK);
		$chapter2 = (int) (($unique_ids[1] >> BFOX_UNIQUE_ID_PART_SIZE) & BFOX_UNIQUE_ID_MASK);
		$verse2   = (int)  ($unique_ids[1] & BFOX_UNIQUE_ID_MASK);

		// Clear verse2 if it is set to the max, or if chapter2 and verse2 equal chapter1 and verse1
		if ((BFOX_UNIQUE_ID_MASK == $verse2) || (($chapter1 == $chapter2) && ($verse1 == $verse2)))
			$verse2 = 0;
		// If chapter two is set to max, we should not use it
		if ((BFOX_UNIQUE_ID_MASK == $chapter2) || ($chapter1 == $chapter2))
			$chapter2 = 0;

		$this->set($book_id, $chapter1, $verse1, $chapter2, $verse2);
	}

	public static function parse_ref_str($str)
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

		$book_id = bfox_find_book_id(trim($book_str));
		$verse_start = new BibleVerse($book_id, $chapter1, $verse1);
		$verse_end = self::fix_end_verse($verse_start, new BibleVerse($book_id, $chapter2, $verse2));

		return array($verse_start, $verse_end);
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

	function get_string($format = '')
	{
		if (empty($format)) $format = BFOX_REF_FORMAT_NORMAL;

		if (isset($this->str[$format]))
			return $this->str[$format];

		// Create the reference string
		$str = bfox_get_book_name($this->book_id, $format);
		if (isset($this->chapter1))
		{
			$str .= " {$this->chapter1}";
			if (isset($this->verse1))
				$str .= ":{$this->verse1}";
			if (isset($this->chapter2))
			{
				$str .= "-{$this->chapter2}";
				if (isset($this->verse2))
					$str .= ":{$this->verse2}";
			}
			else if (isset($this->verse2))
				$str .= "-{$this->verse2}";
		}

		$this->str[$format] = $str;
		return $this->str[$format];
	}
}

/*
 This class is used to represent a bible reference as two unique IDs
 */
class BibleRefSingle
{
	public $verse_start;
	public $verse_end;

	public function __construct($value = array(0, 0))
	{
		$this->update($value);
	}

	public function update($value = array(0, 0))
	{
		unset($this->cache);

		if (is_string($value))
		{
			list($this->verse_start, $this->verse_end) = BibleRefParsed::parse_ref_str($value);
		}
		else if (is_array($value))
		{
			$unique_ids = $value;
			$this->verse_start = new BibleVerse($unique_ids[0]);
			$this->verse_end = new BibleVerse($unique_ids[1]);
		}
	}

	function is_valid()
	{
		if ((0 < $this->verse_start->unique_id) && ($this->verse_start->unique_id <= $this->verse_end->unique_id))
			return true;
		return false;
	}

	function get_unique_ids()
	{
		return array($this->verse_start->unique_id, $this->verse_end->unique_id);
	}

	function get_string($format = '')
	{
		return $this->get_parsed()->get_string($format);
	}

	// Returns the parsed array form of the bible reference
	function get_parsed()
	{
		if (!isset($this->cache['parsed']))
		{
			$this->cache['parsed'] = new BibleRefParsed;
			$this->cache['parsed']->set_by_unique_ids($this->get_unique_ids());
		}

		return $this->cache['parsed'];
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
				$this->update(array($verse1->unique_id, $verse2->unique_id));
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
			$shifted = new BibleRefSingle(array($this->verse_start->unique_id,
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

		$book_name = bfox_get_book_name($this->verse_start->book);

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

}

/*
 This class is a wrapper around BibleRefSingle to store it in an array
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

	// Returns the internal array of BibleRefSingles converted to an
	// array of BibleRefs where each element has just one BibleRefSingle
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

	function push_ref_single(BibleRefSingle $ref)
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
				$ref = new BibleRefSingle($unique_ids);
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
		$count = 0;
		$refstrs = preg_split("/[\n,;]/", trim($str));
		foreach ($refstrs as $refstr)
		{
			$ref = new BibleRefSingle($refstr);
			if ($ref->is_valid())
			{
				$this->refs[] = $ref;
				$count++;
			}
		}
		return $count;
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

				// HACK: This is a hacky way of getting the string, because I didn't want to rewrite this whole function to
				// work well with the new BibleRefs system
				$tmpRefParsed = new BibleRefParsed;
				$tmpRefParsed->set($tmpRef['book_id'], $tmpRef['chapter1'], $tmpRef['verse1'], $tmpRef['chapter2'], $tmpRef['verse2']);
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

				// HACK: This is a hacky way of getting the string, because I didn't want to rewrite this whole function to
				// work well with the new BibleRefs system
				$tmpRefParsed = new BibleRefParsed;
				$tmpRefParsed->set($tmpRef['book_id'], $tmpRef['chapter1'], $tmpRef['verse1'], $tmpRef['chapter2'], $tmpRef['verse2']);
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
			$content = '';
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
				if ($span_verse) $verse_text = "<span class='bible_verse' book='" . bfox_get_book_name($verse->book_id) . "' chapter='$verse->chapter_id' verse='$verse->verse_id'>$verse_text</span>";

				$content .= $verse_text;
			}

			// Add any remaining quick notes
			if ($span_verse) $content .= $bfox_quicknote->list_verse_notes($notes);

			$content = bfox_special_syntax($content);
		}
		else $content = 'No verse data exists for this translation.';

		return $content;
	}

}

?>
