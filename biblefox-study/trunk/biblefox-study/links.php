<?php

	class BfoxLinks
	{
		private $ref_context;
		private $home;

		function BfoxLinks()
		{
			$this->home = get_option('home');
			$this->admin = $this->home . '/wp-admin';
			$this->bible = $this->admin . '/?page=' . BFOX_READ_SUBPAGE;

			if (is_admin()) $this->ref_context = 'bible';
			else $this->ref_context = 'blog';
		}

		function link($attrs)
		{
			$text = $attrs['text'];
			unset($attrs['text']);
			
			$link = '<a';
			foreach ($attrs as $key => $value) $link .= " $key='$value'";
			return $link . '>' . $text . '</a>';
		}

		function set_ref_context($context)
		{
			$this->ref_context = $context;
		}

		function ref_url($ref_str, $context = NULL)
		{
			if (empty($context)) $context = $this->ref_context;
			$prefix = array('blog' => $this->home . '/?',
							'bible' => $this->bible . '&amp;',
							'write' => $this->admin . '/post-new.php?');
			return $prefix[$context] . 'bible_ref=' . $ref_str;
		}

		function ref_link($attrs, $context = NULL)
		{
			if (empty($context)) $context = $this->ref_context;
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
			if (!isset($attrs['href'])) $attrs['href'] = $this->ref_url($ref_str, $context);
			if (!isset($attrs['title'])) $attrs['title'] = $ref_str;
			if (!isset($attrs['class'])) $attrs['class'] = 'bible-ref-link';
			if (!isset($attrs['value'])) $attrs['value'] = $ref_str;
			if (!isset($attrs['text'])) $attrs['text'] = $ref_str;
			if (!isset($attrs['bible_ref'])) $attrs['bible_ref'] = $ref_str;
			if (isset($attrs['no_href'])) unset($attrs['href']);
			
			return $this->link($attrs);
		}
		
	}

	global $bfox_links;
	$bfox_links = new BfoxLinks();

?>
