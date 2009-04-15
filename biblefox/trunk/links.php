<?php

class BfoxLinkOptions
{
	public $ref_context;
	public $home;

	public function __construct()
	{
		$this->home = get_option('home');
		$this->admin = $this->home . '/wp-admin';
		$this->bible = $this->admin . '/?page=' . BFOX_BIBLE_SUBPAGE;
		$this->special = $this->home . '/?' . BFOX_QUERY_VAR_SPECIAL . '=';

		if (is_admin()) $this->ref_context = 'bible';
		else $this->ref_context = 'blog';
	}
}

global $bfox_link_options;
$bfox_link_options = new BfoxLinkOptions();

class BfoxLinks
{
	public static function set_ref_context($context)
	{
		global $bfox_link_options;
		$bfox_link_options->ref_context = $context;
	}

	/**
	 * Returns a link to an admin page
	 *
	 * @param string $page The admin page (and any parameters)
	 * @param string $text The text to use in the link
	 * @param string $attrs Any additional attributes for the link
	 * @return string
	 */
	public static function admin_link($page, $text = '', $attrs = '')
	{
		global $bfox_link_options;
		$url = "$bfox_link_options->admin/$page";
		if (empty($text)) $text = $url;
		return "<a href='$url' $attrs>$text</a>";
	}

	public static function reading_plan_url($plan_id = NULL, $action = NULL, $reading_id = NULL)
	{
		global $bfox_link_options;
		$url = $bfox_link_options->special . 'current_readings';
		if (!empty($plan_id)) $url .= '&' . BFOX_QUERY_VAR_PLAN_ID . '=' . $plan_id;
		if (!empty($action)) $url .= '&' . BFOX_QUERY_VAR_ACTION . '=' . $action;
		if (!empty($reading_id)) $url .= '&' . BFOX_QUERY_VAR_READING_ID . '=' . ($reading_id + 1);
		return $url;
	}

	public static function link($attrs)
	{
		$text = $attrs['text'];
		unset($attrs['text']);

		$link = '<a';
		foreach ($attrs as $key => $value) $link .= " $key='$value'";
		return $link . '>' . $text . '</a>';
	}

	public static function ref_url($ref_str, $context = NULL)
	{
		global $bfox_link_options;
		if (empty($context)) $context = $bfox_link_options->ref_context;

		if ('quick' == $context) $url = '#bible-text-progress';
		else
		{
			$prefix = array('blog' => $bfox_link_options->home . '/?',
							'bible' => Bible::page_url(Bible::page_passage) . '&amp;',
							'write' => $bfox_link_options->admin . '/post-new.php?');
			$url = $prefix[$context] . 'bible_ref=' . $ref_str;

			// For the main bible viewer, attach the current translation
			if ('bible' == $context)
			{
				global $bfox_trans;
				$url .= '&amp;trans_id=' . $bfox_trans->id;
			}
		}

		return $url;
	}

	private static function ref_format_attrs($attrs, $context = NULL)
	{
		global $bfox_link_options;
		if (empty($context)) $context = $bfox_link_options->ref_context;
		if (!is_array($attrs))
		{
			$ref_str = $attrs;
			$attrs = array();
		}
		else
		{
			$ref_str = $attrs['ref_str'];
			unset($attrs['ref_str']);
		}

		if (!isset($ref_str)) $ref_str = '';
		if (!isset($attrs['href'])) $attrs['href'] = self::ref_url($ref_str, $context);
		if (!isset($attrs['title'])) $attrs['title'] = $ref_str;
		if (!isset($attrs['text'])) $attrs['text'] = $ref_str;
		if (isset($attrs['no_href']) || ('quick' == $context))
		{
			if (!isset($attrs['class'])) $attrs['class'] = 'no_href';
			unset($attrs['href']);
		}

		if ('quick' == $context) $attrs['onClick'] = "bible_text_request(\"$ref_str\")";
		return $attrs;
	}

	public static function ref_link($attrs, $context = NULL)
	{
		return self::link(self::ref_format_attrs($attrs, $context));
	}

	public static function get_ref_url(BibleRefs $refs, $context = NULL)
	{
		return self::ref_url($refs->get_string(), $context);
	}

	public static function get_ref_link(BibleRefs $refs, $text = NULL, $context = NULL)
	{
		return self::ref_link(array('ref_str' => $refs->get_string(), 'text' => $text), $context);
	}

	public static function write_link(BibleRefs $refs, $text = '')
	{
		if (empty($text)) $text = $refs->get_string();
		return self::get_ref_link($refs, $text, 'write');
	}
}


?>
