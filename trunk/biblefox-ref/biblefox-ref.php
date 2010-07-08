<?php


if (!defined('BFOX_REF_DIR')) define('BFOX_REF_DIR', dirname(__FILE__));

require_once BFOX_REF_DIR . '/bible-meta.php';
require_once BFOX_REF_DIR . '/verse.php';
require_once BFOX_REF_DIR . '/sequences.php';
require_once BFOX_REF_DIR . '/parser.php';
require_once BFOX_REF_DIR . '/db-table.php';

class BfoxRefSequence extends BfoxSequence {

	public function __construct($start = 0, $end = 0) {
		$this->set_start($start);
		$this->set_end($end);
	}

	public function set_start($start) {
		if (is_array($start)) {
			list($book1, $chapter1, $verse1) = $start;
			$start = BibleVerse::calc_unique_id($book1, $chapter1, $verse1);
		}
		else {
			list($book1, $chapter1, $verse1) = BibleVerse::calc_ref($start);
		}

		/*
		 * Maximize bible reference before we actually add them to the sequence list
		 */

		$edit1 = FALSE;

		// Adjust verse1 to be zero if it is one
		if (1 == $verse1) {
			$verse1 = 0;
			$edit1 = TRUE;
		}

		// Adjust chapter1 to zero if this is the first verse of the first chapter
		if ((1 == $chapter1) && (0 == $verse1)) {
			$chapter1 = 0;
			$edit1 = TRUE;
		}

		// If the start verse is greater than the last verse of the chapter, try to start with the next chapter
		// So, 'Haggai 1:100-2:4' should become 'Haggai 2:1-4'
		if ($verse1 > BibleMeta::passage_end($book1, $chapter1)) {
			$verse1 = 0;
			if (BibleVerse::max_chapter_id > $chapter1) $chapter1++;
			$edit1 = TRUE;
		}

		// If the start chapter is greater than the last chapter of the book, this isn't a valid sequence
		if ($chapter1 > BibleMeta::passage_end($book1)) {
			$this->start = null;
		}
		else {
			// We have a valid sequence, so calculate the unique id and set it
			if ($edit1) $start = BibleVerse::calc_unique_id($book1, $chapter1, $verse1);

			$this->start = $start;
		}

		return $this->start;
	}

	public function set_end($end) {
		if (is_array($end)) {
			list($book2, $chapter2, $verse2) = $end;
			$end = BibleVerse::calc_unique_id($book2, $chapter2, $verse2);
		}
		else {
			list($book2, $chapter2, $verse2) = BibleVerse::calc_ref($end);
		}

		/*
		 * Maximize bible reference before we actually add them to the sequence list
		 */

		$edit2 = FALSE;

		// Adjust verse2 to be max_verse_id if it is greater than equal to the earliest possible end verse for chapter2,
		// or if chapter2 is greater than the end chapter for this verse
		if (($verse2 >= BibleMeta::earliest_end($book2, $chapter2)) || ($chapter2 > BibleMeta::passage_end($book2))) {
			$verse2 = BibleVerse::max_verse_id;
			$edit2 = TRUE;
		}

		// Adjust chapter2 to be max_chapter_id if it is greater than or equal to the last chapter of this book
		if ((BibleVerse::max_verse_id == $verse2) && ($chapter2 >= BibleMeta::passage_end($book2))) {
			$chapter2 = BibleVerse::max_chapter_id;
			$edit2 = TRUE;
		}

		if ($edit2) $end = BibleVerse::calc_unique_id($book2, $chapter2, $verse2);

		$this->end = $end;

		return $this->end;
	}
}

class BfoxRef extends BfoxSequenceList {

	/*
	 * Creation and Modification Functions
	 *
	 *
	 */

	public function __construct($value = NULL) {
		if (is_string($value)) $this->add_string($value);
		elseif ($value instanceof BfoxRef) $this->add_ref($value);
	}

	public function __toString() {
		// TODO3: Should we actually return the right string or an error?
		// We only added this function because we are adding BfoxRef to post data (see bfox_posts_results()),
		// and WP sometimes tries to add_magic_quotes() to the BfoxRef  (see wp_update_post()) which requires them to be
		// able to convert to a string, but of course we aren't actually using the value of the ref in that instance
		return 'BfoxRef Error: Use get_string() instead';
	}

	/**
	 * Add bible references
	 *
	 * @param BfoxRef $ref
	 */
	public function add_ref(BfoxRef $ref) {
		return $this->add_seqs($ref->get_seqs());
	}

	/**
	 * Add bible references from a string
	 *
	 * @param string $ref_str
	 */
	public function add_string($ref_str) {
		$this->add_ref(BfoxRefParser::simple($ref_str));
	}

	public function add_concat($begin_str, $end_str, $delim = ',') {
		$ends = explode($delim, $end_str);
		foreach (explode($delim, $begin_str) as $idx => $begin) if (isset($ends[$idx])) $this->add_seq(new BfoxRefSequence($begin, $ends[$idx]));
	}

	/**
	 * Subtract bible references
	 *
	 * @param BfoxRef $ref
	 */
	public function sub_ref(BfoxRef $ref) {
		return $this->sub_seqs($ref->get_seqs());
	}

	/**
	 * Subtract bible references specified by a string
	 *
	 * @param string $ref_str
	 */
	public function sub_string($ref_str) {
		$this->sub_ref(BfoxRefParser::simple($ref_str));
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
		return $this->add_seq(new BfoxRefSequence(
			array($book, $chapter1, $verse1),
			array($book, $chapter2, $verse2)));
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
	public static function get_bcvs($sequences) {
		$bcvs = array();
		foreach ($sequences as $seq) {
			list($book1, $ch1, $vs1) = BibleVerse::calc_ref($seq->start);
			list($book2, $ch2, $vs2) = BibleVerse::calc_ref($seq->end);

			$books = array();
			$books[$book1]->start = array($ch1, $vs1);
			if ($book2 > $book1) {
				$start = array(0, 0);
				$end = array(BibleVerse::max_chapter_id, BibleVerse::max_verse_id);

				$books[$book1]->end = $end;

				$middle_books = $book2 - $book1;
				for ($i = 1; $i < $middle_books; $i++) {
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
	public function get_string($name = '') {
		return self::bcv_string(self::get_bcvs($this->sequences), $name);
	}

	public static function bcv_string($bcvs, $name = '') {
		$books = array();
		foreach ($bcvs as $book => $cvs) $books []= self::create_book_string($book, $cvs, $name);
		return implode('; ', $books);
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
	public static function create_book_string($book, $cvs, $name = '') {
		$str = '';
		$prev_ch = 0;

		foreach ((array) $cvs as $cv) {
			list($ch1, $vs1) = $cv->start;
			list($ch2, $vs2) = $cv->end;

			$is_whole_book = FALSE;

			// If chapter1 is 0, then this is either a whole book, or needs to begin at chapter 1
			if (0 == $ch1) {
				if (BibleVerse::max_chapter_id == $ch2) $is_whole_book = TRUE;
				else $ch1 = BibleMeta::start_chapter;
			}

			if (!$is_whole_book) {
				$is_whole_chapters = FALSE;

				// If verse1 is 0, then this is either a whole chapter(s), or needs to begin at verse 1
				if (0 == $vs1) {
					if (BibleVerse::max_verse_id == $vs2) $is_whole_chapters = TRUE;
					else $vs1 = BibleMeta::start_verse;
				}

				// Adjust the end chapter and verse to be the actual maximum chapter/verse we can display
				$ch2 = min($ch2, BibleMeta::passage_end($book));
				$vs2 = min($vs2, BibleMeta::passage_end($book, $ch2));

				if ($ch1 != $prev_ch) {
					if (!empty($str)) $str .= '; ';
					// Whole Chapters
					if ($is_whole_chapters) {
						$str .= $ch1;
						if ($ch1 != $ch2) $str .= "-$ch2";
					}
					// Inner Chapters
					elseif ($ch1 == $ch2) {
						$str .= "$ch1:$vs1";
						if ($vs1 != $vs2) $str .= "-$vs2";
					}
					// Mixed Chapters
					else {
						$str .= $ch1;
						if (BibleMeta::start_verse != $vs1) $str .= ":$vs1";
						$str .= "-$ch2:$vs2";
					}
				}
				else {
					$str .= ",$vs1";
					// Inner Chapters
					if ($ch1 == $ch2) {
						if ($vs1 != $vs2) $str .= "-$vs2";
					}
					// Mixed Chapters
					else {
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
		if (BibleVerse::max_chapter_id == $ch2) $ch2 = BibleMeta::passage_end($book);

		$seqs = array();
		$seqs[$ch1] = new BfoxRefSequence;
		$seqs[$ch1]->set_start(array($book, $ch1, $vs1));
		if ($ch2 > $ch1) {
			$seqs[$ch1]->set_end(array($book, $ch1, BibleVerse::max_verse_id));

			$middle_chapters = $ch2 - $ch1;
			for ($i = 0; $i < $middle_chapters; $i++) {
				$ch = $ch1 + $i;
				$seqs[$ch] = new BfoxRefSequence(array($book, $ch, 0), array($book, $ch, BibleVerse::max_verse_id));
			}

			$seqs[$ch2] = new BfoxRefSequence;
			$seqs[$ch2]->set_start(array($book, $ch2, 0));
		}
		$seqs[$ch2]->set_end(array($book, $ch2, $vs2));

		return $seqs;
	}

	/**
	 * Divides a bible reference into smaller references of chapter size $chapter_size
	 *
	 * @param integer $chapter_size
	 * @return array of BfoxRef
	 */
	public function get_sections($chapter_size, $limit = 0) {
		$sections = array(new BfoxRef);
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
						$sections[$index] = new BfoxRef;
					}

					$sections[$index]->add_seq($seq);

					$prev_ch = $chapter;
				}
			}

			$prev_book = $book;
		}

		return $sections;
	}

	public function first_verse() {
		return BibleVerse::calc_ref($this->start());
	}

	public function last_verse() {
		return BibleVerse::calc_ref($this->end());
	}

	public function prev_chapter_string($name = '') {
		list($book, $ch, $vs) = $this->first_verse();

		$ch--;
		if (BibleMeta::start_chapter > $ch) {
			if ($book > BibleMeta::start_book) {
				$book--;
				$ch = BibleMeta::passage_end($book);
			}
			else return '';
		}

		return BibleMeta::get_book_name($book, $name) . ' ' . $ch;
	}

	public function next_chapter_string($name = '') {
		list($book, $ch, $vs) = $this->last_verse();

		$ch++;
		if (BibleMeta::passage_end($book) < $ch) {
			if ($book < BibleMeta::passage_end()) {
				$book++;
				$ch = BibleMeta::start_chapter;
			}
			else return '';
		}

		return BibleMeta::get_book_name($book, $name) . ' ' . $ch;
	}
}

class BibleGroupPassage extends BfoxRef {
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