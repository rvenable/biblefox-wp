<?php

class BlogPost
{
	public $id, $type, $title, $full_title, $content, $ref_str, $excerpt;

	public function __construct($title, $content, $ref_str = '')
	{
		$this->id = 0;
		$this->type = 'post';
		$this->title = $title;
		$this->full_title = $title;
		if (is_array($content)) $content = implode("\n", $content);
		$this->content = $content;
		$this->ref_str = $ref_str;
		$this->excerpt = '';
	}

	public function get_string()
	{
		return "Blog $this->type:\nTitle: $this->title\nBible References: $this->ref_str\nExcerpt:$this->excerpt\nContent:\n$this->content\n";
	}

	public function output()
	{
		return "<div>Title: $this->title<br/>Bible References: $this->ref_str</div><div>Excerpt:<br/>$this->excerpt</div><div>Content:<br/>$this->content</div>";
	}

	public function get_array($globals = array())
	{
		return array_merge(array(
				'ID' => $this->id,
				'post_type' => $this->type,
				'post_content' => $this->content,
				'post_title' => $this->title,
				'post_excerpt' => $this->excerpt
			),
			$globals
		);
	}
}

class BlogPage extends BlogPost
{
	public function __construct($title, $content, $ref_str = '')
	{
		parent::__construct($title, $content, $ref_str);
		$this->type = 'page';
	}
}

abstract class TxtToBlog
{
	const dir = BFOX_TEXTS_DIR;
	const divider = '__________________________________________________________________';
	const prev_posts_option = 'bfox_txt_to_blog_prev_posts';
	const good_post_title_len = 100;
	protected $file;
	protected $posts;
	private $post_index;
	private $warnings = array();
	private $global_post_vals;
	private $used_titles;
	private $refs;

	public function __construct()
	{
		$this->refs = new BibleRefs();
	}

	public function get_post_indexing_title(BlogPost $post)
	{
		return "{$post->type}_{$this->global_post_vals['post_category']}:$post->title";
	}

	public function update()
	{
		return $this->update_posts($this->parse_file());
	}

	public function update_posts($posts)
	{
		$prev_posts = get_option(self::prev_posts_option, array());

		foreach ($posts as &$post)
		{
			$index = $this->get_post_indexing_title($post);
			if (isset($prev_posts[$index]))
			{
				$post->id = $prev_posts[$index];
				unset($prev_posts[$index]);
			}
		}

		// Remove any remaining posts
		foreach ($prev_posts as $id)
		{
			echo "Deleting: $id<br/>";
			wp_delete_post($id);
		}

		$prev_posts = array();
		foreach ($posts as $post) $prev_posts[$this->get_post_indexing_title($post)] = wp_insert_post($post->get_array($this->global_post_vals));

		update_option(self::prev_posts_option, $prev_posts);
		pre($prev_posts);

		return count($prev_posts);
	}

	public function parse_file($file = '')
	{
		if (empty($file)) $file = $this->file;
		$file = self::dir . "/$file";
		$lines = file($file);

		$sections = array();
		$section_num = 0;
		foreach ($lines as $line)
		{
			$line = trim($line);
			if (self::divider == $line) $section_num++;
			else $sections[$section_num] []= $line;
		}

		// Ignore the first section if it is empty
		if (isset($sections[0]) && self::is_empty_section($sections[0])) unset($sections[0]);

		$this->posts = array();
		$this->post_index = 0;
		foreach ($sections as $section)
			$this->parse_section($section);

		return $this->posts;
	}

	protected function shorten_title(BlogPost &$post)
	{
		$post->title = trim($post->title);
		if (self::good_post_title_len < strlen($post->title))
		{
			// Remove the parenthesis suffix, if it exists
			if (preg_match('/\s*\([^\(\)]*\)$/', $post->title, $matches, PREG_OFFSET_CAPTURE))
			{
				$suffix = ' ' . trim($matches[0][0]);
				$suffix_len = strlen($suffix);
				$post->title = substr($post->title, 0, $matches[0][1]);
				// $this->warning("Suffix found: '$post->title', '$suffix'");
			}

			// Shorten titles by dividing them on commas, semicolons, and periods
			$matches = array();
			$count = preg_match_all('/[,;\.]/', $post->title, $matches, PREG_OFFSET_CAPTURE);
			if (0 < $count)
			{
				$index = 0;
				while (($index + 1 < $count) && ($matches[$index + 1][1] + 3 + $suffix_len < self::good_post_title_len)) $index++;
				$new_title = substr($post->title, 0, $matches[0][$index][1]) . '...' . $suffix;

				// $this->warning("Shortening Title: '$post->title' becomes '$new_title'");
				$post->title = $new_title;
			}
		}
	}

	protected function check_titles($posts)
	{
		foreach ($posts as $post_id => $post)
		{
			if (empty($post->title)) $this->warning("Blank Title ($post->content)");
		}
	}

	protected function add_post(BlogPost $post, $index = 0)
	{
		if (empty($index)) $index = $this->post_index++;

		// Shorten titles
		$this->shorten_title($post);

		// Handle duplicate titles by adding the verse reference to the end of the string
		if (isset($this->used_titles[$post->title]))
		{
			if (!empty($post->ref_str))
			{
				$new_title = $post->title . " ($post->ref_str)";
				// $this->warning("Duplicate Title: '$post->title' (post $index dupes {$this->used_titles[$post->title]}), replacing with '$new_title'");
			}
			else $this->warning("Duplicate Title with no Ref: '$post->title' (post $index dupes {$this->used_titles[$post->title]})");
		}
		$this->used_titles[$post->title] = $index;

		$this->posts[$index] = $post;
		return $index;
	}

	protected function set_global_category($category)
	{
		$this->global_post_vals['post_category'] = $category;
	}

	protected function set_global_publish_status($status = 'publish')
	{
		$this->global_post_vals['post_status'] = $status;
	}

	protected abstract function parse_section($section);

	protected static function is_empty_section($section)
	{
		$not_empty = FALSE;
		foreach ($section as $line) if ('' != trim($line)) $not_empty = TRUE;

		return !$not_empty;
	}

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

	public function add_refs($ref_str)
	{
		// Keep track of which verses have been added
		if (!empty($ref_str)) $this->refs->add_string($ref_str);
	}

	public function get_refs()
	{
		return $this->refs;
	}

	public static function footnote_code($note)
	{
		return "[footnote]{$note}[/footnote]";
	}

	public function post_link_code($post_index, $title = '')
	{
		if (empty($title)) $title = $this->posts[$post_index]->full_title;
		return self::link_code($this->posts[$post_index]->title, $title);
	}

	public static function link_code($page, $title = '')
	{
		if (empty($title)) $title = $page;
		return "[link page='$page']{$title}[/link]";
	}

	public static function bible_code($ref, $title = '')
	{
		if (empty($title)) $title = $ref;
		return "[bible ref='$ref']{$title}[/bible]";
	}
}

class MhccTxtToBlog extends TxtToBlog
{
	const file = 'mhcc.txt';
	private $book_index, $book_names, $book_tocs;

	function __construct()
	{
		parent::__construct();
		$this->file = self::file;
		$this->set_global_category(0);
		$this->set_global_publish_status();
	}

	public function update()
	{
		$posts = $this->parse_file();
		return $this->update_posts(array($posts[3]));
	}

	public function parse_file($file = '')
	{
		$this->book_toc = array();
		$posts = parent::parse_file($file);

		// Make some hardcoded changes
		unset($posts[0]);
		$posts[1]->title = 'Matthew Henry\'s Commentary on the Bible';
		$posts[1]->content = 'An abridgment of the 6 volume "Matthew Henry\'s Commentary on the Bible".';

		// Use the book content as an except before we add the TOC
		foreach ($this->book_names as $book => $name) $posts[$book]->excerpt = $posts[$book]->content;

		foreach ($this->book_tocs as $index => $toc)
		{
			$posts[$index]->content .= "<h4>Chapters</h4><ul>$toc</ul>";
			$posts[1]->content .= "<h4>" . self::link_code($this->book_names[$index]) . "</h4><ul>$toc</ul>";
		}

		$this->check_titles($posts);

		return $posts;
	}

	protected function parse_section($section)
	{
		list ($title, $body) = self::parse_title_body($section);
		if (preg_match('/chapter\s*(\d+)/i', $title, $match))
		{
			$chapter = $match[1];
			$ref = $this->book_names[$this->book_index] . " $chapter";
			$this->book_tocs[$this->book_index] .= "<li style='display: inline; padding-right: 10px;'>" . self::link_code($ref, $chapter) . "</li>";

			self::parse_chapter($ref, $body);
		}
		else if (BibleMeta::get_book_id($title))
		{
			$this->book_index = $this->add_post(new BlogPost($title, $body, $title));
			$this->book_names[$this->book_index] = $title;
			$this->book_tocs[$this->book_index] = '';
		}
		else $this->add_post(new BlogPage($title, $body));
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
			else $intro_lines .= $line;
		}
		$outline = (array) $sections['outline'];
		unset($sections['outline']);

		$intro_lines = trim($intro_lines);
		if (!empty($intro_lines)) $intro_lines .= "\n";
		// if (!empty($intro_lines)) $this->warning("Intro: $intro_lines");

		// Add the chapter post
		$chapter_post_index = $this->add_post(new BlogPost($chapter, $intro_lines, $chapter));

		if (!empty($outline))
		{
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

					// Create a lookup array for finding this key from its first verse number
					if (preg_match('/^\d+/', $verse_title_key, $match)) $verse_title_keys[$match[0]] = $verse_title_key;
				}
				else $verse_title .= " $line";
			}

			// Some outlines contain verse titles that apply to more than one post section at a time
			// For these, the verse_title array will not already have the necessary titles, so we will have to expand it.
			$new_verse_titles = array();
			$new_title_counts = array();
			$low = 0;
			foreach ($sections as $key => $content)
			{
				if (isset($verse_titles[$key]))
				{
					$new_verse_titles[$key] = $verse_titles[$key];
					$low = 0;
				}
				else
				{
					if (empty($low) && preg_match('/^\d+/', $key, $match)) $low = $match[0];

					if (!empty($low))
					{
						$new_verse_titles[$key] = $verse_titles[$verse_title_keys[$low]] . " ($chapter:$key)";
						$new_title_counts[$verse_titles[$verse_title_keys[$low]]]++;
						// $this->warning("New title: " . $new_verse_titles[$key]);
					}
					else $this->warning("Unknown Section Verses: $chapter:$key");
				}
			}
			$verse_titles = $new_verse_titles;

			// Make sure any new titles were added more than once (because all new titles should be coming from verse sections with the same name)
			foreach ($new_title_counts as $title => $count) if ($count < 2) $this->warning("New Title Added, but only $count time (possible error): $title");

			// Add verse posts for each remaining section
			$section_posts = array();
			foreach ($sections as $key => $content)
			{
				$ref_str = "$chapter:$key";
				$section_posts[$key] = $this->add_post(new BlogPost($verse_titles[$key], $content, $ref_str));
				$this->add_refs($ref_str);
			}

			// Make sure the title/section counts are equal
			if (count($verse_titles) != count($section_posts)) $this->warning("Unequal title/section counts: " . count($verse_titles) . '/' . count($section_posts));

			// Create a new outline page with links to verse blog posts and bible references
			$outline = '<ol>';
			foreach ($section_posts as $verse => $section_index)
				$outline .= "<li><h4>" . self::bible_code("$chapter:$verse", "Verses {$verse}") . "</h4>" . $this->post_link_code($section_index) . "</li>";
			$outline .= '</ol>';
		}
		else
		{
			$outline = '';
			foreach ($sections as $key => $content)
			{
				$ref_str = "$chapter:$key";
				$outline .= "<h4>" . self::bible_code($ref_str, "Verses {$key}") . "</h4>\n" . implode("\n", $content);
				$this->add_refs($ref_str);
			}
		}

		// Add the outline to the end of the chapter post
		$this->posts[$chapter_post_index]->content .= $outline;
	}
}

class CalcomTxtToBlog extends TxtToBlog
{
	const file = 'calcom/calcom01.txt';
	private $footnotes;

	function __construct()
	{
		parent::__construct();
		$this->file = self::file;
		$this->footnotes = array();
	}

	private function insert_footnote($match)
	{
		$footnote = $match[0];
		if (!isset($this->footnotes[$match[1]])) $this->warning("Missing footnote: $match[0]");
		else
		{
			$footnote = self::footnote_code($this->footnotes[$match[1]]);
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
		else $this->add_post(new BlogPost($title, $body));
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

		foreach ($verses as $verse_num => $verse)
		{
			$content = "<blockquote>{$verse[0]}</blockquote>";
			$content .= "<blockquote>{$verse[1]}</blockquote>";
			$content .= "<p>{$verse[2]}</p>";
			$verse_ref->set_ref($verse_ref->book, $verse_ref->chapter, $verse_num);
			$this->add_post(new BlogPost($verse_ref->get_string(), $content, $verse_ref->get_string()));
		}
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