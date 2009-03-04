<?php

class BlogPost
{
	public $title, $content, $ref_str;
	public function __construct($title, $content, $ref_str = '')
	{
		$this->title = $title;
		if (is_array($content)) $content = implode("\n", $content);
		$this->content = $content;
		$this->ref_str = $ref_str;
	}

	public function get_string()
	{
		return "Blog Post:\nTitle: $this->title\nBible References: $this->ref_str\nContent:\n$this->content\n";
	}

	public function output()
	{
		return "<div>Title: $this->title<br/>Bible References: $this->ref_str</div><div>Content:<br/>$this->content</div>";
	}
}

abstract class TxtToBlog
{
	const dir = BFOX_TEXTS_DIR;
	const divider = '__________________________________________________________________';
	protected $file;
	private $warnings;

	public function parse_file($file = '')
	{
		if (empty($file)) $file = $this->file;
		$file = self::dir . "/$file";
		$lines = file($file);

		$sections = array();
		foreach ($lines as $line)
		{
			$line = trim($line);
			if (self::divider == $line) $section_num++;
			else $sections[$section_num] []= $line;
		}

		$posts = array();
		foreach ($sections as $section)
			$posts = array_merge($posts, $this->parse_section($section));

		return $posts;
	}

	protected abstract function parse_section($section);

	protected static function parse_title_body($body)
	{
		while (!is_null($title = array_shift($body)) && empty($title));
		return array($title, $body);
	}

	protected function warning($str)
	{
		$this->warnings []= $str;
	}

	public function print_warnings()
	{
		return implode("<br/>", $this->warnings);
	}
}

class MhccTxtToBlog extends TxtToBlog
{
	const file = 'mhcc.txt';
	private $book;

	function __construct()
	{
		$this->file = self::file;
	}

	protected function parse_section($section)
	{
		list ($title, $body) = self::parse_title_body($section);
		if (preg_match('/chapter\s*(\d+)/i', $title, $match))
		{
			$chapter = $match[1];
			$posts = self::parse_chapter("$this->book $chapter", $body);
		}
		else if (bfox_find_book_id($title))
		{
			$this->book = $title;
			$posts = array(new BlogPost($title, $body, $title));
		}
		else $posts = array(new BlogPost($title, $body));

		return $posts;
	}

	protected function parse_chapter($chapter, $body)
	{
		$verse_num_pattern = '\d[\s\d,-]*';

		// Parse the chapter body into an outline section and verse sections
		$sections = array();
		$key = '';
		foreach ($body as $line)
		{
			if (preg_match('/chapter\s*outline/i', $line)) $key = 'outline';
			elseif (preg_match('/verses?\s*(' . $verse_num_pattern . ')/i', $line, $match)) $key = $match[1];
			elseif (!empty($key)) $sections[$key] []= $line;
		}
		$outline = (array) $sections['outline'];
		unset($sections['outline']);

		// Parse the outline section to find the titles for the verse sections
		$verse_titles = array();
		$verse_title_key = '';
		$verse_title = '';
		foreach ($outline as $line)
		{
			if (preg_match('/\((' . $verse_num_pattern . ')\)/i', $line, $match))
			{
				$verse_title_key = $match[1];
				$verse_title = trim(trim($verse_title), '.');
				$verse_titles[$verse_title_key] = $verse_title;
				$verse_title = '';
			}
			else $verse_title .= " $line";
		}

		// Create a new outline page with links to verse blog posts and bible references
		$outline = '';
		foreach ($verse_titles as $verse => $verse_title)
			$outline .= "<li><h4>[bible ref='$chapter:$verse']Verses ${verse}[/bible]</h4>[link]${verse_title}[/link]</li>";

		// Create the array of blog posts, starting with the outline post, followed by all the verse posts
		$posts = array(new BlogPost($chapter, "<ol>$outline</ol>", $chapter));
		foreach ($sections as $key => $content)
			$posts []= new BlogPost($verse_titles[$key], $content, "$chapter:$key");

		return $posts;
	}
}

class CalcomTxtToBlog extends TxtToBlog
{
	const file = 'calcom/calcom01.txt';
	private $footnotes;

	function __construct()
	{
		$this->file = self::file;
		$this->footnotes = array();
	}

	private function insert_footnote($match)
	{
		$footnote = $match[0];
		if (!isset($this->footnotes[$match[1]])) $this->warning("Missing footnote: $match[0]");
		else
		{
			$footnote = "[footnote]{$this->footnotes[$match[1]]}[/footnote]";
			unset($this->footnotes[$match[1]]);
		}
		return $footnote;
	}

	public function parse_file($file = '')
	{
		$posts = parent::parse_file($file);

		foreach ($this->footnotes as &$footnote) $footnote = trim($footnote);

		// Replace all the footnotes
		foreach ($posts as &$post)
		{
			$count++;
			$post->content = preg_replace_callback('/\[(\d+)\]/', array($this, 'insert_footnote'), $post->content);
		}

		return $posts;
	}

	protected function parse_section($section)
	{
		list ($title, $body) = self::parse_title_body($section);
		$refs = RefManager::get_from_str($title);

		$posts = array();
		if ($refs->is_valid()) $posts = $this->parse_bible_refs($refs, $title, $body);
		elseif (preg_match('/^\[\d+\]/', $title)) $this->parse_footnotes($section);
		else $posts = array(new BlogPost($title, $body));

		return $posts;
	}

	protected function parse_bible_refs(BibleRefs $refs, $title, $body)
	{
		$verses = array();
		foreach ($body as $line)
		{
			if (preg_match('/^(\d+)\.?(.*)$/', $line, $match))
			{
				$verse_key = $match[1];
				$verse_count = count($verses[$verse_key]);
				$verses[$verse_key][$verse_count] = $match[2];
			}
			else $verses[$verse_key][$verse_count] .= " $line";
		}

		list(list($verse_start)) = $refs->get_sets();
		$verse_ref = new BibleVerse($verse_start);

		$posts = array();
		foreach ($verses as $verse_num => $verse)
		{
			$content = "<blockquote>{$verse[0]}</blockquote>";
			$content .= "<blockquote>{$verse[1]}</blockquote>";
			$content .= "<p>{$verse[2]}</p>";
			$verse_ref->set_ref($verse_ref->book, $verse_ref->chapter, $verse_num);
			$posts []= new BlogPost($verse_ref->get_string(), $content, $verse_ref->get_string());
		}
		return $posts;
	}

	protected function parse_footnotes($section)
	{
		$key = '';
		foreach ($section as $line)
		{
			if (preg_match('/^\[(\d+)\](.*)$/', $line, $match))
			{
				$key = $match[1];
				$this->footnotes[$key] = $match[2];
			}
			else $this->footnotes[$key] .= " $line";
		}
	}

}

?>