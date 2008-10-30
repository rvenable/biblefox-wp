<?php
	/*
	 Parses a USFX bible translation file to add to our translation DB
	 */
	define('BFOX_TRANSLATIONS_DIR', dirname(__FILE__) . "/translations");
	function bfox_save_verse($table, $book, $chapter, $verse, $text)
	{
	}

	class BfoxUsfx
	{
		private $usfx_elements = array();
		private $valid_elements = array();
		private $invalid_elements = array();
		private $element_cbs = array();
		private $unsupported_elements = array();
		private $unsupported_stack = array();
		private $attr_counts = array();
		private $paragraph_tags = array();
		private $reader;
		
		private $vs = array();
		
/*		class Element
		{
			function open()
			{
			}

			function close()
			{
			}
		}
		class TagElement extends Element
		{
			function TagElement($open, $close)
			{
			}
		}

		class IdElement extends Element
		{
			function IdElement
		}
 */

		function BfoxUsfx()
		{
			$this->reader = new XMLReader();
			$this->element_cbs = array('book' => 'book',
									   'c' => 'chapter',
									   'v' => 'verse',
									   'p' => 'paragraph',
									   'q' => 'poetry'
			);

			$hidden_text = array('div', 'class="bible-hidden" style="display:none"');

			$this->tag_conv = array('wj' => array('div', 'class="bible-jesus"'),
									'f' => array('footnote'),
									'id' => $hidden_text,
									'h' => $hidden_text,
									'usfx' => array('div'), // ignore
									'ide' => hidden_text,
									'add' => array('div', 'class="bible-added-words"'),
									'd' => array('h3'),
									's' => array('h3'),
			);

			foreach ($this->tag_conv as $tag => $data) $this->element_cbs[$tag] = 'tag_conv';

			$this->tag_conv_empty = array('b' => array('br'),
										  );
			
			foreach ($this->tag_conv_empty as $tag => $data) $this->element_cbs[$tag] = 'tag_conv_empty';

			$this->load_schema();
		}

		function load_schema()
		{
			$reader = new XMLReader();
			$reader->open(BFOX_TRANSLATIONS_DIR . '/usfx-2005-09-08.xsd.xml');
			
			$elements = array();
			while ($reader->read())
			{
				if ((XMLReader::ELEMENT == $reader->nodeType) && ('element' == $reader->localName))
				{
					$element = $reader->getAttribute('name');
					$elements[$element] = '';
				}
				else if ((XMLReader::ELEMENT == $reader->nodeType) && ('documentation' == $reader->localName))
				{
					$reader->read();
					$elements[$element] = $reader->value;
				}
			}
			$reader->close();
			
			foreach ($elements as $element => $description)
				$this->add_element($element, $description);
			$this->usfx_elements = $elements;
		}

		function save_verse()
		{
			if (!empty($this->vs) && !empty($this->vs['text']))
			{
				$id = 'book'.$this->vs['book'].' '.$this->vs['chapter'].':'.$this->vs['verse'];
				if (15 > count($this->verses))
				{
					// Save the verse
					$this->verses[$id] = $this->vs['text'];
				}
				while ($element = array_pop($this->unsupported_stack))
					$this->elements[$element]['example'] = $id . ' ' . $this->vs['text'];
			}
			$this->vs['text'] = '';
		}

		function open_book()
		{
			$this->save_verse();
			
			$this->vs['book_name'] = $this->get_attribute('id');
			if ('BAK' == $this->vs['book_name']) $this->invalidate_attribute('id');

			$this->vs['book']++;
			$this->vs['chapter'] = 0;
			$this->vs['verse'] = 0;
		}

		function open_chapter()
		{
			$this->save_verse();

			// The id attribute holds the chapter ID, but we are just incrementing anyway, so we don't need it
			$this->get_attribute('id');
			
			$this->vs['chapter']++;
			$this->vs['verse'] = 0;
		}
		
		function open_verse()
		{
			$this->save_verse();
			
			// The id attribute holds the verse ID, but we are just incrementing anyway, so we don't need it
			$this->get_attribute('id');
			
			$this->vs['verse']++;
		}

		function open_paragraph()
		{
			$sfm = $this->get_attribute('sfm');
			$style = $this->get_attribute('style');

			switch ($sfm)
			{
				case 'mt':
					$is_valid = ('Book Title' == $style);
					$tag = 'h2';
					break;
				default:
					$is_valid = FALSE;
					$tag = 'p';
					break;
			}

			if (!$is_valid)
			{
				$this->invalidate_attribute('sfm');
				$this->invalidate_attribute('style');
			}

			array_push($this->paragraph_tags, $tag);
			$this->vs['text'] .= "<$tag>";
		}

		function close_paragraph()
		{
			$tag = array_pop($this->paragraph_tags);
			$this->vs['text'] .= "</$tag>";
		}
		
		function open_poetry()
		{
			$level = (int) $this->get_attribute('level');
			
			if ((1 != $level) && (2 != $level))
				$this->invalidate_attribute('level');
			
			$this->vs['text'] .= '<div class="bible-poetry-level-' . $level . '">';
		}
		
		function close_poetry()
		{
			$this->vs['text'] .= '</div>';
		}
		
		function open_tag_conv()
		{
			// Ignoring the following attributes
			switch ($this->element)
			{
				case 'id':
					$this->get_attribute('id');
					break;
				case 'usfx':
					$this->get_attribute('ns0');
					$this->get_attribute('xsi');
					$this->get_attribute('noNamespaceSchemaLocation');
					break;
				case 'ide':
					$this->get_attribute('charset');
					break;
				case 'f':
					$this->get_attribute('caller');
					break;
				case 's':
					$this->get_attribute('level');
					break;
			}
			
			$tag = $this->tag_conv[$this->element][0];
			$attr = $this->tag_conv[$this->element][1];
			if (!empty($attr)) $tag .= ' ' . $attr;
			$this->vs['text'] .= "<$tag>";
		}
		
		function close_tag_conv()
		{
			$tag = $this->tag_conv[$this->element][0];
			$this->vs['text'] .= "</$tag>";
		}
		
		function open_tag_conv_empty()
		{
			$tag = $this->tag_conv[$this->element][0];
			$attr = $this->tag_conv[$this->element][1];
			if (!empty($attr)) $tag .= ' ' . $attr;
			$this->vs['text'] .= "<$tag/>";
		}
		
		function get_callback($prefix, $element)
		{
			if (isset($this->element_cbs[$element]))
				return array($this, $prefix . '_' . $this->element_cbs[$element]);
		}

		function get_attribute($attr)
		{
			$value = $this->attributes[$attr];
			$this->used_attributes[$attr] = TRUE;
			return $value;
		}

		function invalidate_attribute($attr)
		{
			$this->used_attributes[$attr] = FALSE;
		}

		function read_attributes($element)
		{
			$this->attributes = array();
			$this->used_attributes = array();

			while ($this->reader->moveToNextAttribute()) $this->attributes[$this->reader->localName] = $this->reader->value;

			// Save the attribute counts
/*			if (!isset($this->attr_counts[$element])) $this->attr_counts[$element] = array();
			foreach ($this->attributes as $attribute => $value)
			{
				if (!isset($this->attr_counts[$element][$attribute])) $this->attr_counts[$element][$attribute] = array();
				$this->attr_counts[$element][$attribute][$value]++;
			}
			
			$this->invalid_attr_counts = $this->attr_counts;
 */
			
		}

		function clear_attributes($element)
		{
			// Save the attribute counts
			foreach ($this->attributes as $attribute => $value)
			{
				if (!$this->used_attributes[$attribute])
				{
					if (!isset($this->elements[$element]['unused_attributes'][$attribute])) $this->elements[$element]['unused_attributes'][$attribute] = array();
					$this->elements[$element]['unused_attributes'][$attribute][$value]++;
				}
			}
			unset($this->attributes);
			unset($this->used_attributes);
		}

		function add_element($element, $description = '')
		{
			$this->elements[$element] = array('description' => $description,
											  'count' => 0,
											  'is_valid' => FALSE,
											  'open_callback' => $this->get_callback('open', $element),
											  'close_callback' => $this->get_callback('close', $element),
											  'unused_attributes' => array()
											  );
		}

		function get_stat_attr_str($attr_counts)
		{
			$str = '';
			$total = 0;
			foreach ($attr_counts as $attr => $values)
			{
				$attr_str = '';
				$attr_total = 0;
				foreach ($values as $value => $count)
				{
					$attr_str .= "$value ($count), ";
					$attr_total += $count;
				}
				if (0 < $attr_total)
				{
					$str .= "<li>$attr:$attr_total\n$attr_str</li>";
					$total += $attr_total;
				}
			}
			
			return "Attributes:$total<ul>$str</ul>";
		}

		function get_stat_str()
		{
			foreach ($this->elements as $element => $stats)
			{
				$is_used = (0 < $stats['count']);
				$is_supported = !empty($stats['open_callback']);
				$has_unused_attributes = !empty($stats['unused_attributes']);

				if ($is_used && (!$is_supported || $has_unused_attributes))
				{
					$str .= "\n\n<p><strong>Element:</strong> $element<br/>";
					$str .= $stats['description'] . '<br/>';
					$str .= '<strong>Supported:</strong> ' . ($is_supported ? 'true' : 'false') . '<br/>';
					$str .= "<strong>Count:</strong> " . $stats['count'] . "<br/>";
					if ($is_supported) $str .= 'Unused ' . $this->get_stat_attr_str($stats['unused_attributes']);
//					$str .= "\n<strong>Example:</strong> " . $stats['example'] . "<br/>";
					$str .= "</p>";
				}
			}
			echo $str;
		}

		function read_file()
		{
			$this->reader->open(BFOX_TRANSLATIONS_DIR . '/web-usfx.xml');
			
			while ($this->reader->read())
			{
				if (XMLReader::ELEMENT == $this->reader->nodeType)
				{
					$this->element = $this->reader->localName;

					if (isset($this->elements[$this->element]))
						$this->elements[$this->element]['count']++;
					else $this->add_element($this->element);

					if (isset($this->usfx_elements[$this->element]))
						$this->valid_elements[$this->element]++;
					else
						$this->invalid_elements[$this->element]++;

					$this->read_attributes($this->element);

					$cb = $this->elements[$this->element]['open_callback'];
					if (is_callable($cb)) call_user_func($cb);
					else $this->unsupported_stack[] = $this->element;

					$this->clear_attributes($this->element);
				}
				else if (XMLReader::END_ELEMENT == $this->reader->nodeType)
				{
					$this->element = $this->reader->localName;
					$cb = $this->elements[$this->element]['close_callback'];
					if (is_callable($cb)) call_user_func($cb);
				}
				else if (XMLReader::TEXT == $this->reader->nodeType)
				{
					$this->vs['text'] .= $this->reader->value;
				}
				unset($this->element);
				unset($this->attributes);
			}

			$this->reader->close();
		}
		
		function echo_stats()
		{
			if (0 < count($this->verses))
			{
				echo "\nVerses:\n";
				foreach ($this->verses as $element => $count)
					echo "$element:$count\n";
			}
/*			if (0 < count($this->valid_elements))
			{
				echo "\nValid Elements:\n";
				foreach ($this->valid_elements as $element => $count)
					echo "$element:$count\n";
			}
			if (0 < count($this->invalid_elements))
			{
				echo "\nInvalid Elements:\n";
				foreach ($this->invalid_elements as $element => $count)
					echo "$element:$count\n";
			}
			if (0 < count($this->unsupported_elements))
			{
				echo "\nUnsupported Elements:\n";
				foreach ($this->unsupported_elements as $element => $count)
					echo "$element:$count\n" . $this->usfx_elements[$element] . "\n\n";
			}
			if (0 < count($this->attr_counts))
			{
			}*/
		}
	}

	echo '<div class="wrap">';
	$usfx = new BfoxUsfx();
	$usfx->read_file();
	$usfx->get_stat_str();
//	echo '<pre>';
	$usfx->echo_stats();
	echo '</div>';
?>
