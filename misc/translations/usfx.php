<?php
	/*
	 Parses a USFX bible translation file to add to our translation DB
	 */

	class BfoxXml
	{
		private $reader;

		private $elements;

		private $element_cbs = array();
		private $element_stack = array();

		protected $open_element_cb;
		protected $close_element_cb;
		protected $text_element_cb;


		function __construct($schema_file = NULL)
		{
			if (!empty($schema_file))
				$this->load_schema($schema_file);

			$this->reader = new XMLReader();
		}

		/*
		 * PUBLIC FUNCTIONS
		 */

		/**
		 * Sets the open and close callbacks for an element
		 *
		 * @param string $element
		 * @param callback $open_cb
		 * @param callback $close_cb
		 */
		public function set_element_cbs($element, $open_cb, $close_cb = NULL)
		{
			$array = array('is_supported' => TRUE);
			if (is_callable($open_cb)) $array['open_callback'] = $open_cb;
			if (is_callable($close_cb)) $array['close_callback'] = $close_cb;
			$this->modify_element($element, $array);
		}

		/**
		 * Sets the open and close tags for an element
		 *
		 * @param string $element
		 * @param string $new_tag The tag to use for open and close
		 * @param string $new_attributes The attributes to use in the open tag
		 */
		public function set_tag_conv($element, $new_tag, $new_attributes = '')
		{
			$open_tag = $new_tag;
			if (!empty($new_attributes)) $open_tag .= ' ' . $new_attributes;

			$this->modify_element($element, array('is_supported' => TRUE, 'open_tag' => "<$open_tag>", 'close_tag' => "</$new_tag>"));
		}

		/**
		 * Sets the open and close tag for an element that should only have an open tag
		 *
		 * @param string $element
		 * @param string $open_tag
		 */
		public function set_tag_conv_empty($element, $open_tag)
		{
			$this->modify_element($element, array('is_supported' => TRUE, 'open_tag' => "<$open_tag />", 'close_tag' => ''));
		}

		/**
		 * Set the value for a given element and key
		 *
		 * @param string $element
		 * @param string $key
		 * @param unknown_type $value
		 */
		public function set_element_value($element, $key, $value)
		{
			$this->modify_element($element, array($key => $value));
		}

		/**
		 * Return the value for a given element and key
		 *
		 * @param string $element
		 * @param string $key
		 * @return unknown
		 */
		public function get_element_value($element, $key)
		{
			return $this->elements[$element][$key];
		}

		/**
		 * Loads a schema file to store information for elements of the schema
		 *
		 * @param string $schema_file The schema file to read
		 */
		public function load_schema($schema_file)
		{
			$reader = new XMLReader();
			$reader->open($schema_file);

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
				$this->add_schema_element($element, $description);
		}

		/**
		 * Reads an XML file
		 *
		 * @param string $file The file name
		 */
		public function read_file($file)
		{
			$this->reader->open($file);

			// Read the file, one xml node at a time
			while ($this->reader->read())
			{
				// Check the XML node type
				if (XMLReader::ELEMENT == $this->reader->nodeType)
				{
					$is_empty = $this->reader->isEmptyElement;
					$element = $this->reader->localName;
					$this->open_element($element);

					if ($is_empty) $this->close_element($element);
				}
				else if (XMLReader::END_ELEMENT == $this->reader->nodeType)
				{
					$this->close_element($this->reader->localName);
				}
				else if (XMLReader::TEXT == $this->reader->nodeType)
				{
					if (is_callable($this->text_element_cb)) call_user_func($this->text_element_cb, preg_replace('/\s+/', ' ', $this->reader->value));
				}
				unset($this->element);
				unset($this->attributes);
			}

			$this->reader->close();
		}

		/**
		 * Returns the value for an attribute for the element that is currently opened during a file read.
		 *
		 * @param unknown_type $attr
		 * @return unknown
		 */
		public function get_attribute($attr)
		{
			$value = $this->attributes[$attr];
			$this->used_attributes[$attr] = TRUE;
			return $value;
		}

		/**
		 * Sets an attribute to be unused
		 *
		 * @param unknown_type $attr
		 */
		public function invalidate_attribute($attr)
		{
			$this->used_attributes[$attr] = FALSE;
		}

		/*
		 * PRIVATE FUNCTIONS
		 */

		/**
		 * Adds an element from the schema to the element list
		 *
		 * @param string $element
		 * @param string $description
		 */
		private function add_schema_element($element, $description)
		{
			$array = array(
				'description' => $description,
				'schema' => TRUE,
			);
			$this->modify_element($element, $array);
		}

		/**
		 * Adds an element which was not found in the schema to the element list
		 *
		 * @param string $element
		 */
		private function add_nonschema_element($element)
		{
			$this->modify_element($element, array('schema' => FALSE));
		}

		/**
		 * Modifies an element's entry in the element list
		 *
		 * @param string $element
		 * @param array $mod_array The modifications for the element
		 */
		private function modify_element($element, $mod_array)
		{
			if (!isset($this->elements[$element]))
			{
				$this->elements[$element] = array(
					'unused_attributes' => array()
				);
			}

			$this->elements[$element] = array_merge($this->elements[$element], $mod_array);
		}

		/**
		 * Called when an open element is read
		 *
		 * @param string $element
		 */
		private function open_element($element)
		{
			// If we don't already have info for this element, we should add it
			if (!isset($this->elements[$element])) $this->add_nonschema_element($element);

			// Count how many times we open this element
			$this->elements[$element]['open_count']++;

			$data = array(
				'element' => $element,
				'open_tag' => $this->elements[$element]['open_tag'],
				'close_tag' => $this->elements[$element]['close_tag']
			);

			// Read any attributes for this element
			$this->read_attributes($element);

			// Call an open callback if set
			$cb = $this->elements[$element]['open_callback'];
			if (is_callable($cb)) $data = call_user_func($cb, $data);

			// We're done with the attributes
			$this->clear_attributes($element);

			array_push($this->element_stack, $data);

			// Call the open tag callback
			if (is_callable($this->open_element_cb)) call_user_func($this->open_element_cb, $data);
		}

		/**
		 * Called when a close element is read
		 *
		 * @param string $element
		 */
		private function close_element($element)
		{
			if (!isset($this->elements[$element])) $this->add_nonschema_element($element);

			$this->elements[$element]['close_count']++;

			$data = array_pop($this->element_stack);

			// Call an open callback if set
			$cb = $this->elements[$element]['close_callback'];
			if (is_callable($cb)) call_user_func($cb);

			// Call the close tag callback
			if (is_callable($this->close_element_cb)) call_user_func($this->close_element_cb, $data);
		}

		/**
		 * Reads all the attributes for an element
		 *
		 * @param string $element
		 */
		private function read_attributes($element)
		{
			$this->attributes = array();
			$this->used_attributes = array();

			while ($this->reader->moveToNextAttribute()) $this->attributes[$this->reader->localName] = $this->reader->value;
		}

		/**
		 * Clears all the attributes for an element
		 *
		 * @param unknown_type $element
		 */
		private function clear_attributes($element)
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

		/*
		 * STATISTIC FUNCTIONS
		 */

		function get_all_elements()
		{
			return array_keys($this->elements);
		}

		function get_key_value_elements($key, $value)
		{
			$list = array();
			foreach ($this->elements as $element => $data)
				if ($value == $data[$key]) $list[] = $element;
			return $list;
		}

		function get_unsupported()
		{
			$list = array();
			foreach ($this->elements as $element => $data)
				if (!$data['is_supported']) $list[] = $element;
			return $list;
		}

		function get_supported()
		{
			$list = array();
			foreach ($this->elements as $element => $data)
				if ($data['is_supported']) $list[] = $element;
			return $list;
		}

		function list_elements($list, $header = '')
		{
			foreach ($list as $element)
			{
				if (!empty($content)) $content .= ', ';
				$content .= "<a href='#$element'>$element</a>";
			}
			if (!empty($header)) $content = "<h3>$header</h3>" . $content;
			return $content;
		}

		function get_all_element_content()
		{
			foreach ($this->elements as $element => $data)
			{
				$content .= "<p id='$element'><strong>$element</strong><br/>";
				foreach ($data as $key => $value)
				{
					$content .= "$key: ";
					if (is_string($value)) $content .= htmlspecialchars($value);
					else if (is_bool($value)) $content .= ($value) ? 'TRUE' : 'FALSE';
					else if (is_int($value)) $content .= $value;
					else $content .= 'unknown';
					$content .= '<br/>';
				}
				$content .= '</p>';
			}
			return $content;
		}

		function echo_stats()
		{
			if (0 < count($this->verses))
			{
				echo "\nVerses:\n";
				foreach ($this->verses as $element => $count)
					echo "$element:$count\n";
			}
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

	}

	class BfoxBibleText extends BfoxXml
	{
		private $table_name = '';
		private $vs = array();
		private $verse_tag_stack = array();
		public $verse_samples = array();
		public $verse_xml_errors = array();
		public $valid_verses = 0;
		public $invalid_verses = 0;

		function __construct($schema_file = NULL)
		{
			parent::__construct($schema_file);

			// Sets the callbacks
			$this->open_element_cb = array($this, 'add_open_tag');
			$this->close_element_cb = array($this, 'add_close_tag');
			$this->text_element_cb = array($this, 'add_text');
		}

		function set_table_name($table_name)
		{
			$this->table_name = $table_name;
		}

		/**
		 * Sets the elements delimiting books, chapters, and verses
		 *
		 * @param unknown_type $book
		 * @param unknown_type $chapter
		 * @param unknown_type $verse
		 */
		function set_bcv($book, $chapter, $verse)
		{
			$this->set_element_cbs($book, array($this, 'open_book'));
			$this->set_element_cbs($chapter, array($this, 'open_chapter'));
			$this->set_element_cbs($verse, array($this, 'open_verse'));
		}

		/**
		 * Sets hidden tags
		 *
		 * @param array $tags
		 */
		function set_hidden_tags($tags)
		{
			foreach ((array) $tags as $tag) $this->set_tag_conv($tag, 'div', 'class="bible-hidden" style="display:none"');
		}

		/**
		 * Sets whether an element should be added to the tag stack
		 *
		 * @param string $element
		 */
		function set_element_unstackable($element)
		{
			$this->set_element_value($element, 'unstackable', TRUE);
		}

		/**
		 * Returns whether an element should be added to the tag stack
		 *
		 * @param string $element
		 * @return bool
		 */
		function is_element_stackable($element)
		{
			$unstackable = $this->get_element_value($element, 'unstackable');
			return empty($unstackable);
		}

		function add_open_tag($data)
		{
			if (isset($this->vs['verse']))
			{
				$this->vs['text'] .= $data['open_tag'];

				// If this is a stackable element then we should add it to our verse tag stack
				if ($this->is_element_stackable($data['element']))
					array_push($this->verse_tag_stack, $data);
			}
		}

		function add_close_tag($data = array())
		{
			if (isset($this->vs['verse']))
			{
				// Pop off the data from the verse tag stack
				$pop_data = array_pop($this->verse_tag_stack);

				// If the popped data isn't the same as the passed in data, then we should put it back on the stack
				if (isset($data['element']) && ($data['element'] != $pop_data['element'])) array_push($this->verse_tag_stack, $pop_data);
				else $data = $pop_data;

				$this->vs['text'] .= $data['close_tag'];
			}
		}

		function add_text($text)
		{
			$this->vs['text'] .= $text;
		}

		/**
		 * Saves verse text to the DB and resets the verse data for the next verse
		 *
		 */
		private function save_verse()
		{
			$open_tags = array();
			if (!empty($this->vs) && !empty($this->vs['text']))
			{
				// Store the currently open tags, so we can close them at the end of this verse,
				// and reopen them at the beginning of the next verse
				$open_tags = $this->verse_tag_stack;

				// Close any open tags
				$count = count($open_tags);
				for ($i = 0; $i < $count; $i++) $this->add_close_tag();

				$bible_verse = new BibleVerse($this->vs['book'], $this->vs['chapter'], $this->vs['verse']);

				// Save some stat data
				$id = $bible_verse->get_string();
				$verse_filter = TRUE; //$this->vs['book'] == 43 && $this->vs['chapter'] == 3; // John 3
				if ($verse_filter && (15 > count($this->verse_samples)))
				{
					// Save the verse
					$this->verse_samples[$id] = htmlspecialchars($this->vs['text']);
				}

				// Validate the XML
				libxml_use_internal_errors(true);
				$doc = '<verse_text>' . $this->vs['text'] . '</verse_text>';
				$xml = simplexml_load_string($doc, NULL, LIBXML_NOWARNING);

				if (!$xml)
				{
					$this->invalid_verses++;
					if (count($this->verse_xml_errors) < 10)
					{
						$errors = libxml_get_errors();
						foreach ($errors as &$error) $error->xml = $doc;
						$this->verse_xml_errors[$id] = $errors;
						libxml_clear_errors();
					}
				}
				else $this->valid_verses++;

				// Save the verse data to the DB
				if (!empty($this->table_name) && isset($this->vs['book']))
				{
					BfoxTransInstaller::update_verse_text($this->table_name, $bible_verse, $this->vs['text']);
				}
			}

			// Reset the verse text
			$this->vs['text'] = '';
			$this->verse_tag_stack = array();

			// Reopen any tags that we had to close
			foreach ($open_tags as $tag) $this->add_open_tag($tag);
		}

		// TODO: We shouldn't need to duplicate $book_id_lookup here since it is in the BfoxRefParser
		private static $book_id_lookup = array('genesis'=>1,'gen'=>1,'exod'=>2,'exo'=>2,'exodus'=>2,'leviticus'=>3,'lev'=>3,'num'=>4,'numbers'=>4,'deuteronomy'=>5,'deut'=>5,'deu'=>5,'jsh'=>6,'jos'=>6,'josh'=>6,'joshua'=>6,'judges'=>7,'judg'=>7,'jdg'=>7,'jdgs'=>7,'rut'=>8,'rth'=>8,'ruth'=>8,'1samuel'=>9,'1sam'=>9,'1sm'=>9,'1sa'=>9,'2samuel'=>10,'2sam'=>10,'2sm'=>10,'2sa'=>10,'1kings'=>11,'1king'=>11,'1kgs'=>11,'1kin'=>11,'1ki'=>11,'2kings'=>12,'2king'=>12,'2kgs'=>12,'2kin'=>12,'2ki'=>12,'1chronicles'=>13,'1chron'=>13,'1chr'=>13,'1ch'=>13,'2chronicles'=>14,'2chron'=>14,'2chr'=>14,'2ch'=>14,'ezra'=>15,'ezr'=>15,'neh'=>16,'nehemiah'=>16,'esther'=>17,'esth'=>17,'est'=>17,'job'=>18,'pss'=>19,'psm'=>19,'psa'=>19,'psalms'=>19,'pslm'=>19,'psalm'=>19,'pro'=>20,'prv'=>20,'prov'=>20,'proverbs'=>20,'eccl'=>21,'ecc'=>21,'qoheleth'=>21,'qoh'=>21,'eccles'=>21,'ecclesiastes'=>21,'sng'=>22,'sos'=>22,'song of songs'=>22,'canticles'=>22,'canticle of canticles'=>22,'song'=>22,'song of solomon'=>22,'isaiah'=>23,'isa'=>23,'jer'=>24,'jeremiah'=>24,'lamentations'=>25,'lam'=>25,'ezk'=>26,'eze'=>26,'ezek'=>26,'ezekiel'=>26,'dan'=>27,'daniel'=>27,'hosea'=>28,'hos'=>28,'jol'=>29,'joe'=>29,'joel'=>29,'amos'=>30,'amo'=>30,'oba'=>31,'obad'=>31,'obadiah'=>31,'jonah'=>32,'jnh'=>32,'jon'=>32,'micah'=>33,'mic'=>33,'nam'=>34,'nah'=>34,'nahum'=>34,'habakkuk'=>35,'hab'=>35,'zephaniah'=>36,'zeph'=>36,'zep'=>36,'hag'=>37,'haggai'=>37,'zechariah'=>38,'zech'=>38,'zec'=>38,'mal'=>39,'malachi'=>39,'matthew'=>40,'matt'=>40,'mat'=>40,'mrk'=>41,'mark'=>41,'luke'=>42,'luk'=>42,'jhn'=>43,'john'=>43,'acts'=>44,'act'=>44,'rom'=>45,'romans'=>45,'1corinthians'=>46,'1cor'=>46,'1co'=>46,'2corinthians'=>47,'2cor'=>47,'2co'=>47,'gal'=>48,'galatians'=>48,'ephesians'=>49,'ephes'=>49,'eph'=>49,'philippians'=>50,'phil'=>50,'php'=>50,'col'=>51,'colossians'=>51,'1thessalonians'=>52,'1thess'=>52,'1thes'=>52,'1th'=>52,'2thessalonians'=>53,'2thess'=>53,'2thes'=>53,'2th'=>53,'1timothy'=>54,'1tim'=>54,'1ti'=>54,'2timothy'=>55,'2tim'=>55,'2ti'=>55,'tit'=>56,'titus'=>56,'philemon'=>57,'philem'=>57,'phm'=>57,'hebrews'=>58,'heb'=>58,'jas'=>59,'james'=>59,'1peter'=>60,'1pet'=>60,'1pt'=>60,'1pe'=>60,'2peter'=>61,'2pet'=>61,'2pt'=>61,'2pe'=>61,'1john'=>62,'1jhn'=>62,'1joh'=>62,'1jn'=>62,'1jo'=>62,'2john'=>63,'2jhn'=>63,'2joh'=>63,'2jn'=>63,'2jo'=>63,'3john'=>64,'3jhn'=>64,'3joh'=>64,'3jn'=>64,'3jo'=>64,'jud'=>65,'jude'=>65,'revelation'=>66,'revelations'=>66,'rev'=>66,'tob'=>67,'tobit'=>67,'judith'=>68,'jdth'=>68,'jdt'=>68,'jth'=>68,'esg'=>69,'addesth'=>69,'aes'=>69,'rest of esther'=>69,'add to es'=>69,'add to esth'=>69,'additions to esther'=>69,'rest esther'=>69,'wis'=>70,'wisd of sol'=>70,'wisdom of solomon'=>70,'wisdom'=>70,'ecclus'=>71,'sir'=>71,'sirach'=>71,'ecclesiasticus'=>71,'baruch'=>72,'bar'=>72,'ltr jer'=>73,'lje'=>73,'let of jer'=>73,'letter of jeremiah'=>73,'song of the three holy children'=>74,'song ot the three children'=>74,'song of the three youths'=>74,'song of the three jews'=>74,'song of the three'=>74,'song thr'=>74,'prayer of azariah'=>74,'azariah'=>74,'pr az'=>74,'susanna'=>75,'sus'=>75,'bel and the dragon'=>76,'bel dragon'=>76,'bel'=>76,'1maccabees'=>77,'1macc'=>77,'1mac'=>77,'1ma'=>77,'2maccabees'=>78,'2macc'=>78,'2mac'=>78,'2ma'=>78,'1esdras'=>79,'1esdr'=>79,'1esd'=>79,'1es'=>79,'prayer of manasses'=>80,'prayer of manasseh'=>80,'pr man'=>80,'pma'=>80,'2esdras'=>81,'2esdr'=>81,'2esd'=>81,'2es'=>81,'ge'=>1,'gn'=>1,'le'=>3,'lv'=>3,'nb'=>4,'nm'=>4,'nu'=>4,'dt'=>5,'jg'=>7,'ru'=>8,'1s'=>9,'2s'=>10,'1k'=>11,'2k'=>12,'ne'=>16,'es'=>17,'jb'=>18,'ps'=>19,'pr'=>20,'ec'=>21,'jr'=>24,'je'=>24,'la'=>25,'dn'=>27,'da'=>27,'ho'=>28,'jl'=>29,'ob'=>31,'na'=>34,'zp'=>36,'hg'=>37,'zc'=>38,'ml'=>39,'mt'=>40,'mr'=>41,'mk'=>41,'lk'=>42,'jn'=>43,'ac'=>44,'rm'=>45,'ro'=>45,'ga'=>48,'jm'=>59,'re'=>66,'tb'=>67,'ws'=>70,'1m'=>77,'2m'=>78,'ex'=>2,'so'=>22,'is'=>23,'am'=>30);
		function get_book_id($synonym) {
			return self::$book_id_lookup[strtolower($synonym)];
		}

		function open_book($data)
		{
			$this->save_verse();
			unset($this->vs['book']);

			// Try to get the book's name from the 'id' attribute
			// If we can't the ID attribute is invalid
			if ($book_id = self::get_book_id($this->get_attribute('id')))
			{
				$this->vs['book'] = $book_id;
				$this->vs['book_name'] = BibleMeta::get_book_name($book_id);
			}
			else
			{
				$this->invalidate_attribute('id');
				$this->vs['book_name'] = 'Chapter';
			}

			$this->vs['chapter'] = 0;
			$this->vs['verse'] = 0;

			return $data;
		}

		function open_chapter($data)
		{
			$this->save_verse();

			// The id attribute holds the chapter ID, but we are just incrementing anyway, so we don't need it
			$this->get_attribute('id');

			$this->vs['chapter']++;
			$this->vs['verse'] = 0;
			$this->vs['text'] .= '<h4 class="bible_chapter_id">' . $this->vs['book_name'] . ' ' . $this->vs['chapter'] . '</h4>';

			return $data;
		}

		function open_verse($data)
		{
			$this->save_verse();
			$this->verse_tag_stack = array();

			// The id attribute holds the verse ID, but we are just incrementing anyway, so we don't need it
			$this->get_attribute('id');

			$this->vs['verse']++;

			return $data;
		}

		function list_xml_errors($errors)
		{
			$content = '';
			foreach ($errors as $error)
			{
				if (empty($content)) $content .= htmlspecialchars($error->xml) . '<br/>';
				$content .= "Error $error->code ($error->line:$error->column): $error->message<br/>";
			}
			return $content;
		}

	}

	class BfoxUsfx extends BfoxBibleText
	{
		function __construct()
		{
			parent::__construct(BFOX_TRANS_DIR . '/usfx-2005-09-08.xsd.xml');

			$this->set_bcv('book', 'c', 'v');
			$this->set_element_cbs('p', array($this, 'open_paragraph'));
			$this->set_element_cbs('q', array($this, 'open_poetry'));
			$this->set_tag_conv('wj', 'span', 'class="bible_jesus"');
			$this->set_tag_conv('f', 'footnote');
			$this->set_tag_conv('add', 'em', 'class="bible_added_words"');
			$this->set_tag_conv('d', 'span', 'class="bible_psalm_desc"');
			$this->set_tag_conv('s', 'h3');
			$this->set_tag_conv_empty('b', 'br class="bible_poetry"');
			$this->set_hidden_tags(array('id', 'h', 'ide'));
			$this->set_tag_conv('usfx', 'div');

			// 'p' and 'q' don't need to be closed at the end of each verse anymore, so we won't stack them
			$this->set_element_unstackable('p');
			$this->set_element_unstackable('q');
		}

		function open_paragraph($data)
		{
			$sfm = $this->get_attribute('sfm');
			$style = $this->get_attribute('style');

			switch ($sfm)
			{
				case 'mt':
					$is_valid = ('Book Title' == $style);
					$data['open_tag'] = '<h2>';
					$data['close_tag'] = '</h2>';
					break;
				default:
					$is_valid = FALSE;
					$data['open_tag'] = '';
					$data['close_tag'] = '<span class="bible_end_p"></span>';
					break;
			}

			if (!$is_valid)
			{
				$this->invalidate_attribute('sfm');
				$this->invalidate_attribute('style');
			}

			return $data;
		}

		function open_poetry($data)
		{
			$level = (int) $this->get_attribute('level');

			if ((1 != $level) && (2 != $level))
				$this->invalidate_attribute('level');

			$data['open_tag'] = '<span class="bible_poetry_indent_' . $level . '"></span>';
			$data['close_tag'] = '<span class="bible_end_poetry"></span>';

			return $data;
		}

		function open_tag_conv()
		{
			// Ignoring the following attributes
			switch ($element)
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


	}

	function bfox_usfx_menu($file = 'web-usfx.xml')
	{
		$usfx = new BfoxUsfx();
		$usfx->read_file(BfoxTransInstaller::dir . '/' . $file);
		$all = $usfx->get_all_elements();

		$schema = $usfx->get_key_value_elements('schema', TRUE);
		$nonschema = array_diff($all, $schema);

		$supported = $usfx->get_key_value_elements('is_supported', TRUE);
		$unsupported = array_diff($all, $supported);

		?>
		<div class="wrap">
			<h2>XML Validation</h2>
			<p>
			Valid XML Verses: <?php echo $usfx->valid_verses ?><br />
			Invalid XML Verses: <?php echo $usfx->invalid_verses ?>
			</p>
			<p>XML Errors:</p>
			<?php foreach ($usfx->verse_xml_errors as $id => $errors) echo "$id:<br/>" . $usfx->list_xml_errors($errors); ?>
			<h2>Verse Samples</h2>
			<?php foreach ($usfx->verse_samples as $id => $sample) echo "$id: $sample<br/>"; ?>
			<h2>Element Overviews</h2>
			<?php echo $usfx->list_elements($all, 'All') ?>
			<?php echo $usfx->list_elements($schema, 'Schema') ?>
			<?php echo $usfx->list_elements($nonschema, 'Non-Schema') ?>
			<?php echo $usfx->list_elements($supported, 'Supported') ?>
			<?php echo $usfx->list_elements($unsupported, 'Unsupported') ?>
			<br/><br/>
			<h2>Element Details</h2>
			<?php echo $usfx->get_all_element_content() ?>
		</div>
		<?php
	}

?>
