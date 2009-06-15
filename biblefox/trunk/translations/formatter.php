<?php

/**
 * A class for formatting verses from the translation into text for the user
 *
 */
class BfoxVerseFormatter {

	const trans_begin_poetry_1 = 'bible_poetry_indent_1';
	const trans_begin_poetry_2 = 'bible_poetry_indent_2';
	const trans_end_poetry = 'bible_end_poetry';
	const trans_end_p = 'bible_end_p';

	private $use_span, $p_text, $p_poetry;

	public $only_visible = FALSE;
	public $use_verse0 = FALSE;

	private $ps = array();
	private $p_count = 0;
	private $cur_text = '';
	private $first = '';

	private $do_footnotes = FALSE;
	private $footnote_index = 0;
	private $footnotes = array();

	public function __construct($use_span = FALSE, $p_text = 'bible_text', $p_poetry = 'bible_poetry') {
		$this->use_span = $use_span;
		$this->p_text = $p_text;
		$this->p_poetry = $p_poetry;
	}

	public function use_footnotes($footnotes) {
		$this->do_footnotes = TRUE;
		$this->footnotes = $footnotes;
		$this->footnote_index = count($this->footnotes);
	}

	public function get_footnotes() {
		return $this->footnotes;
	}

	public function format_cv($chapters) {
		$content = '';
		foreach ((array) $chapters as $chapter_id => $verses) {
			$verse_content = $this->format($verses);
			if (!empty($verse_content)) {
				if ($this->use_span) $content .= "<div class='chapter'>\n<span class='chapter_head'>$chapter_id</span>\n$verse_content</div>\n";
				else $content .= $verse_content;
			}
		}
		return $content;
	}

	public function format($verses) {
		$this->ps = array();
		$this->p_count = 0;
		$this->cur_text = '';
		if ($this->use_span) $this->first = 'first_p';

		$is_poetry_sub_line = FALSE;
		$cur_p = '';

		foreach ($verses as $verse) if ($this->use_verse($verse)) {
			$verse_span = "<span class='bible_verse' verse='$verse->verse_id'>";

			// HACK: Removing bible poetry breakpoints (we should remove these from the actual translation data instead)
			$verse->verse = str_ireplace('<br class="bible_poetry" />', '', $verse->verse);

			$parts = preg_split('/<span class="([^"]*)"><\/span>/i', " <b class='verse_num'>$verse->verse_id</b> $verse->verse", -1, PREG_SPLIT_DELIM_CAPTURE);
			foreach ($parts as $index => $part) {
				$trim = trim($part);
				if (!empty($trim)) {
					if (0 == ($index % 2)) $this->add_text($part, $verse_span);
					else {
						if (self::trans_begin_poetry_1 == $part) $cur_p = $this->p_poetry;
						elseif (self::trans_begin_poetry_2 == $part) {
							$cur_p = $this->p_poetry;
							$is_poetry_sub_line = TRUE;
						}
						elseif (self::trans_end_poetry == $part) {
							if ($is_poetry_sub_line && !empty($this->p_count)) $this->add_poetry_line();
							else $this->add_p($this->p_poetry);
							$is_poetry_sub_line = FALSE;
							$cur_p = '';
						}
						elseif (self::trans_end_p == $part) $this->add_p($this->p_text);
					}
				}
			}
		}

		// Flush out any remaining text
		if (!empty($this->cur_text)) {
			if (empty($cur_p)) $cur_p = $this->p_text;
			$this->add_p($cur_p);
		}

		$total_text = '';
		foreach ($this->ps as $p) $total_text .= "<p class='{$p[0]}'>{$p[1]}</p>\n";
		$this->ps = array();

		if ($this->do_footnotes) $total_text = preg_replace_callback('/<footnote>(.*?)<\/footnote>/', array($this, 'footnote_replace'), $total_text);
		//else $total_text = preg_replace('/<footnote>(.*?)<\/footnote>/', '', $total_text);

		return $total_text;
	}

	private function footnote_replace($match) {
		$this->footnote_index++;
		$note = "<a name='footnote_$this->footnote_index' href='#footnote_ref_$this->footnote_index'>[$this->footnote_index]</a> " . BfoxRefParser::simple_html($match[1]);
		$link = " <a name='footnote_ref_$this->footnote_index' href='#footnote_$this->footnote_index' title='" . strip_tags($match[1]) . "' class='ref_foot_link'>[$this->footnote_index]</a> ";
		$this->footnotes []= $note;
		return $link;
	}

	private function use_verse($verse) {
		$use_verse = (!empty($verse->verse_id) || $this->use_verse0);
		$use_verse = $use_verse && (!$this->only_visible || $verse->visible);
		return $use_verse;
	}

	private function add_text($text, $verse_span) {
		if ($this->use_span) $this->cur_text .= "$verse_span$text</span>";
		else $this->cur_text .= $text;
	}

	private function add_poetry_line() {
		// Add the poetry line to the last p
		$this->ps[$this->p_count - 1][1] .= "<br/>\n$this->cur_text";
		$this->cur_text = '';
		$this->first = '';
	}

	private function add_p($class) {
		if (!empty($this->first)) $class .= ' ' . $this->first;
		$this->ps[$this->p_count++] = array($class, $this->cur_text);
		$this->cur_text = '';
		$this->first = '';
	}
}

?>