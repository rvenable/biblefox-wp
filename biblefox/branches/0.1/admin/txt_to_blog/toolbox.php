<?php

class TxtToBlogToolbox extends BfoxToolBox
{
	private function output_posts(TxtToBlog $parser, $pre = FALSE, $limit = 20)
	{
		$posts = $parser->parse_file();
		//echo $parser->print_warnings();

		$url = add_query_arg('tool', $_GET['tool'], bfox_admin_page_url(BFOX_ADMIN_TOOLS_SUBPAGE));

		echo "<p>Num Posts: " . count($posts) . "</p>";

		$post = $_GET['post'];
		if (!empty($post) && isset($posts[$post]))
		{
			$post = $posts[$post];
			echo '<a href="' . add_query_arg('post', $num, $url) . '">' . $post->title . '</a><br/>';
			echo ($pre) ? '<pre>' . $post->get_string() . '</pre>' : $post->output();
		}

		$non_books = array();
		$books = array();
		$chapters = array();
		$verses = array();

		foreach ($posts as $num => $post)
		{
			$show = FALSE;
			$show = $show || (20 >= $num);

			$target_book = 'isaiah';
			$show = $show || (stristr($post->title, $target_book));
			$show = $show || (stristr($post->ref_str, $target_book));

			$show = TRUE;

			if ($show)
			{
				$anchor = '<a href="' . add_query_arg('post', $num, $url) . '">' . $post->title . '</a>';
				//echo ($pre) ? '<pre>' . $post->get_string() . '</pre>' : $post->output();

				if (preg_match('/\d$/', $post->ref_str))
				{
					list($chapter, $verse) = explode(':', $post->ref_str, 2);
					if (empty($verse))
					{
						$book = rtrim(rtrim($chapter, '0123456789'));
						$chapters[$book][$chapter] = $anchor;
					}
					else
					{
						$verses[$chapter][$verse] = $anchor;
					}
				}
				else
				{
					$book = $post->ref_str;
					if (!empty($book)) $books[$book] = $anchor;
					else $non_books []= $anchor;
				}
			}
		}

		echo "<ol style='list-style-type:decimal; padding-left:15px'>";
		foreach ($non_books as $anchor) echo "<li>$anchor</li>";
		echo "</ol><ol style='list-style-type:decimal; padding-left:15px'>";
		foreach ($books as $book => $book_anchor)
		{
			echo "<li>$book_anchor";
			if (isset($chapters[$book]))
			{
				echo '<ol style="list-style-type:decimal; padding-left:15px">';
				foreach ($chapters[$book] as $chapter => $ch_anchor)
				{
					echo "<li>$ch_anchor";
					if (isset($verses[$chapter]))
					{
						echo '<ol style="list-style-type:decimal; padding-left:15px">';
						foreach ($verses[$chapter] as $verse => $vs_anchor) echo "<li>$vs_anchor</li>";
						echo '</ol>';
					}
					echo '</li>';
				}
				echo '</ol>';
			}
			echo '</li>';
		}
		echo "</ol>";

		echo '<div><h4>Warnings</h4>' . $parser->print_warnings() . '</div>';

		self::test_whole_bible($parser->book_refs, 'Book Refs');
		self::test_whole_bible($parser->chapter_refs, 'Chapter Refs');
		self::test_whole_bible($parser->verse_refs, 'Verse Refs');
	}

	private static function test_whole_bible(BibleRefs $refs, $header = 'Refs')
	{
		$bible_start = BibleVerse::calc_unique_id(1);
		$bible_end = BibleVerse::calc_unique_id(66, BibleVerse::max_chapter_id, BibleVerse::max_verse_id);

		echo "<div><h4>$header</h4>";
		$seqs = $refs->get_seqs();
		if (($bible_start == $seqs[0]->start) && ($bible_end == $seqs[0]->end)) echo 'Complete Bible!<br/>';
		else
		{
			echo "Incomplete Bible:<br/>" . $refs->get_string();
			pre($refs->get_seqs());
		}
		echo '</div>';
	}

	function parse_mhcc()
	{
		require_once('txt_to_blog.php');
		$this->output_posts(new MhccTxtToBlog(), TRUE);
	}

	function update_mhcc()
	{
		require_once('txt_to_blog.php');
		$txt = new MhccTxtToBlog();
		$txt->update();
	}

	function parse_calcom()
	{
		require_once('txt_to_blog.php');
		$this->output_posts(new CalcomTxtToBlog());
	}

	/**
	 * A function for dumping temporary functionality to do temporary tasks
	 *
	 */
	function temp()
	{
	}

}
BfoxAdminTools::add_toolbox(new TxtToBlogToolbox());

?>
