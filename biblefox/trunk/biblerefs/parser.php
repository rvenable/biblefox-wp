<?php

class BfoxRefParser {

	public static function simple($str, $max_level = 0) {
		$refs = new BibleRefs;
		self::add_string($refs, $str, $max_level);
		return $refs;
	}

	public static function bible_search($str) {
		return self::simple($str);
	}

	public static function text_flat($text) {
		return self::simple($text);
	}

	/**
	 * Add using a bible reference string
	 *
	 * @param BibleRefs $refs
	 * @param string $str
	 */
	public static function add_string(BibleRefs &$refs, $str, $max_level = 0) {
		// Get all the bible reference substrings in this string
		$substrs = BibleMeta::get_bcv_substrs($str, $max_level);

		// Add each substring to our sequences
		foreach ($substrs as $substr) {
			// If there is a chapter, verse string use it
			if ($substr->cv_offset) self::add_book_str($refs, $substr->book, substr($str, $substr->cv_offset, $substr->length - ($substr->cv_offset - $substr->offset)));
			else $refs->add_whole_book($substr->book);
		}
	}

	/**
	 * Add the number part of a bible reference (ie, the 3:16 in John 3:16)
	 *
	 * @param BibleRefs $refs
	 * @param integer $book_id
	 * @param string $str
	 */
	public static function add_book_str(BibleRefs &$refs, $book_id, $str) {
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

?>