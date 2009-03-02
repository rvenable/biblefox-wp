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

class TxtToBlog
{
	const divider = '__________________________________________________________________';

	public static function parse_file($file)
	{
		$lines = file($file);

		$sections = array();
		foreach ($lines as $line)
		{
			$line = trim($line);
			if (self::divider == $line)
			{
				$section_num++;
			}
			else
			{
				$sections[$section_num] []= $line;
			}
		}

		$posts = array();
		foreach ($sections as $section)
		{
			list ($title, $body) = self::parse_title_body($section);
			if (preg_match('/chapter\s*(\d+)/i', $title, $match))
			{
				$chapter = $match[1];
				$posts = array_merge($posts, self::parse_chapter("$book $chapter", $body));
			}
			else if ($book_id = bfox_find_book_id($title))
			{
				//$book = RefManager::get_book_name($book_id);
				$book = $title;
				$posts []= new BlogPost($title, $body, $title);
			}
			else $posts []= new BlogPost($title, $body);
		}

		return $posts;
	}

	private static function parse_title_body($body)
	{
		while (!is_null($title = array_shift($body)) && empty($title));
		return array($title, $body);
	}

	private static function parse_num_list($str)
	{
		$nums = array();

		$commas = explode(',', $str);
		foreach ($commas as $comma)
		{
			list($low, $high) = explode('-', $comma, 2);
			if (isset($high)) array_push($nums, range($low, $high));
			else array_push($nums, $low);
		}

		return $nums;
	}

	private static function data_string($data)
	{
		$str = '';
		foreach ($data as $key => $lines)
		{
			$str .= "\n$key: " . implode("\n", $lines);
		}
		return $str;
	}

	private static function parse_chapter($chapter, $body)
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

?>