<?php

/**
 * Class for representing a single bible verse
 *
 */
class BibleVerse {
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

	public function __construct($book, $chapter = 0, $verse = 0) {
		// If the chapter and verse are not set, and the book is not a valid book, it must be a unique id
		if (empty($chapter) && empty($verse) && (self::max_book_id < $book)) $this->set_unique_id($book);
		else $this->set_ref($book, $chapter, $verse);
	}

	public function is_valid() {
		return (!empty($this->book));
	}

	public static function calc_unique_id($book, $chapter = 0, $verse = 0) {
		return ($book * self::max_num_chapters * self::max_num_verses) +
			($chapter * self::max_num_verses) +
			$verse;
	}

	public static function calc_ref($unique_id) {
		$verse = $unique_id % self::max_num_verses;
		$unique_id = (int) $unique_id / self::max_num_verses;
		$chapter = $unique_id % self::max_num_chapters;
		$unique_id = (int) $unique_id / self::max_num_chapters;
		$book = $unique_id % self::max_num_books;

		return array($book, $chapter, $verse);
	}

	public function set_ref($book, $chapter = 0, $verse = 0) {
		$this->book = min($book, self::max_book_id);
		$this->chapter = min($chapter, self::max_chapter_id);
		$this->verse = min($verse, self::max_verse_id);
		$this->update_unique_id();
		$this->update_num_str();
	}

	public function set_unique_id($unique_id) {
		$this->unique_id = $unique_id;
		$this->update_ref();
		$this->update_num_str();
	}

	private function update_ref() {
		list ($this->book, $this->chapter, $this->verse) = self::calc_ref($this->unique_id);
	}

	private function update_unique_id() {
		$this->unique_id = self::calc_unique_id($this->book, $this->chapter, $this->verse);
	}

	private function update_num_str() {
		$num_str = '';
		if ((0 < $this->chapter) && (self::max_chapter_id != $this->chapter)) {
			$num_str = (string) $this->chapter;
			if ((0 < $this->verse) && (self::max_verse_id != $this->verse)) $num_str .= ":$this->verse";
		}

		$this->num_str = $num_str;
	}

	public function get_string($name = '') {
		$str = BibleMeta::get_book_name($this->book, $name);

		if (!empty($this->num_str)) $str .= ' ' . $this->num_str;

		return $str;
	}
}

?>