<?php

class BfoxPassage {

	private $book;
	private $chapter1;
	private $chapter2;

	public function __construct($book, $cvs) {
		foreach ($cvs as $cv) {
			if (!isset($ch1)) list($ch1, $vs1) = $cv->start;
			list($ch2, $vs2) = $cv->end;
		}

		$ch1 = max($ch1, BibleMeta::start_chapter);
		if ($ch2 >= BibleMeta::end_verse_min($book)) $ch2 = BibleMeta::end_verse_max($book);

		$this->book = $book;
		$this->chapter1 = $ch1;
		$this->chapter2 = $ch2;
	}

	public function content(BfoxTrans $translation, $visible, &$footnotes = NULL) {

		// Get the verse data from the bible translation
		$formatter = new BfoxVerseFormatter(TRUE);
		if (!is_null($footnotes)) $formatter->use_footnotes($footnotes);

		$content = $translation->get_chapter_verses($this->book, $this->chapter1, $this->chapter2, $visible, $formatter);

		if (!is_null($footnotes)) $footnotes = $formatter->get_footnotes();

		return $content;
	}

	public function nav_ref($type, $name = '') {
		if ('prev' == $type) {
			$ch = $this->chapter1 - 1;
			if (BibleMeta::start_chapter <= $ch) return BibleMeta::get_book_name($this->book, $name) . ' ' . $ch;
		}
		elseif ('next' == $type) {
			$ch = $this->chapter2 + 1;
			if (BibleMeta::end_verse_max($this->book) >= $ch) return BibleMeta::get_book_name($this->book, $name) . ' ' . $ch;
		}
	}

	public function book_name($name = '') {
		return BibleMeta::get_book_name($this->book, $name);
	}

	public function ref_str($name = '') {
		$ref_str = $this->book_name($name) . ' ' . $this->chapter1;
		if ($this->chapter2 > $this->chapter1) $ref_str .= '-' . $this->chapter2;
		return $ref_str;
	}

	public function refs() {
		return new BfoxRefs($this->ref_str());
	}
}

?>