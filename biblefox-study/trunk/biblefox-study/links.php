<?php

	class BfoxLinks
	{
		private $ref_context;
		private $home;

		function BfoxLinks()
		{
			$this->home = get_option('home');
			$this->admin = $this->home . '/wp-admin';
			$this->bible = $this->admin . '/?page=' . BFOX_BIBLE_SUBPAGE;
			$this->special = $this->home . '/?' . BFOX_QUERY_VAR_SPECIAL . '=';

			if (is_admin()) $this->ref_context = 'bible';
			else $this->ref_context = 'blog';
		}

		/**
		 * Returns a link to an admin page
		 *
		 * @param string $page The admin page (and any parameters)
		 * @param string $text The text to use in the link
		 * @param string $attrs Any additional attributes for the link
		 * @return string
		 */
		function admin_link($page, $text = '', $attrs = '')
		{
			$url = "$this->admin/$page";
			if (empty($text)) $text = $url;
			return "<a href='$url' $attrs>$text</a>";
		}

		function reading_plan_url($plan_id = NULL, $action = NULL, $reading_id = NULL)
		{
			$url = $this->special . 'current_readings';
			if (!empty($plan_id)) $url .= '&' . BFOX_QUERY_VAR_PLAN_ID . '=' . $plan_id;
			if (!empty($action)) $url .= '&' . BFOX_QUERY_VAR_ACTION . '=' . $action;
			if (!empty($reading_id)) $url .= '&' . BFOX_QUERY_VAR_READING_ID . '=' . ($reading_id + 1);
			return $url;
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

			if ('quick' == $context) $url = '#bible-text-progress';
			else
			{
				$prefix = array('blog' => $this->home . '/?',
								'bible' => $this->bible_page_url(Bible::page_passage) . '&amp;',
								'write' => $this->admin . '/post-new.php?');
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

		private function ref_format_attrs($attrs, $context = NULL)
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
			if (!isset($attrs['text'])) $attrs['text'] = $ref_str;
			if (isset($attrs['no_href']) || ('quick' == $context))
			{
				if (!isset($attrs['class'])) $attrs['class'] = 'no_href';
				unset($attrs['href']);
			}

			if ('quick' == $context) $attrs['onClick'] = "bible_text_request(\"$ref_str\")";
			return $attrs;
		}

		public function ref_link($attrs, $context = NULL)
		{
			return $this->link($this->ref_format_attrs($attrs, $context));
		}

		public function search_link($search, $display_ref_str = '', $actual_ref_str = '')
		{
			global $bfox_trans;

			if (empty($actual_ref_str)) $actual_ref_str = $display_ref_str;
			if (!empty($display_ref_str)) $ref_title = " in $display_ref_str";

			$url = add_query_arg(Bible::var_search, $search, $this->bible_page_url(Bible::page_search));
			if (!empty($actual_ref_str)) $url = add_query_arg(Bible::var_reference, $actual_ref_str, $url);
			$url = add_query_arg(Bible::var_translation, $bfox_trans->id, $url);

			return "<a href='$url' title='Search for \"$search\"$ref_title'>$display_ref_str</a>";
		}

		public function bible_page_url($page)
		{
			global $bfox_trans;
			return add_query_arg(array(Bible::var_page => $page, Bible::var_translation => $bfox_trans->id), $this->bible);
		}
	}

	global $bfox_links;
	$bfox_links = new BfoxLinks();

?>
