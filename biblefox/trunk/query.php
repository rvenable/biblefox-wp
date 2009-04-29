<?php

/**
 * For generating HTTP Bible queries
 *
 */
class BfoxQuery
{
	const page_passage = 'passage';
	const page_commentary = 'commentary';
	const page_search = 'search';
	const page_history = 'history';

	const var_page = 'bible_page';
	const var_translation = 'bible_trans';
	const var_reference = 'bible_ref';
	const var_search = 'bible_search';
	const var_message = 'bible_message';

	private static $url = '';
	private static $post_url = '';

	public static function set_url($url)
	{
		self::$url = $url;
		list(self::$post_url, $args) = explode('?', $url);
	}

	public static function post_url()
	{
		return self::$post_url;
	}

	public static function page_post_url($page)
	{
		return add_query_arg(self::var_page, $page, self::$post_url);
	}

	public static function page_url($page)
	{
		return add_query_arg(self::var_page, $page, self::$url);
	}

	public static function search_page_url($search_text, $ref_str = '', Translation $display_translation = NULL)
	{
		$url = add_query_arg(self::var_search, urlencode($search_text), self::page_url(self::page_search));
		if (!empty($ref_str)) $url = add_query_arg(self::var_reference, urlencode($ref_str), $url);
		if (!is_null($display_translation)) $url = add_query_arg(self::var_translation, $display_translation->id, $url);

		return $url;
	}

	public static function passage_page_url($ref_str, Translation $translation = NULL)
	{
		$url = add_query_arg(self::var_reference, urlencode($ref_str), self::page_url(self::page_passage));
		if (!is_null($translation)) $url = add_query_arg(self::var_translation, $translation->id, $url);

		return $url;
	}

	public static function sidebar_list()
	{
		?>
		<ul>
			<li><a href="<?php echo self::page_url(self::page_passage) ?>">Passage</a></li>
			<li><a href="<?php echo self::page_url(self::page_commentary) ?>">Commentaries</a></li>
		</ul>
		<?php
	}
}

?>