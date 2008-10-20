<?php
	define('BFOX_UNIQUE_ID_PART_SIZE', 8);
	define('BFOX_UNIQUE_ID_MASK', 0xFF);
	define('BFOX_UNIQUE_ID_MAX', 256);

	function bfox_get_book_name($book_id)
	{
		global $wpdb;
		$query = $wpdb->prepare("SELECT name FROM " . BFOX_BOOKS_TABLE . " WHERE id = %d", $book_id);
		return $wpdb->get_var($query);
	}

	function bfox_find_book_id($synonym)
	{
		global $wpdb;
		$query = $wpdb->prepare("SELECT book_id FROM " . BFOX_SYNONYMS_TABLE . " WHERE synonym LIKE %s", trim($synonym));
		if (isset($query))
			return $wpdb->get_var($query);
		return false;
	}

	function bfox_get_chapters($first_verse, $last_verse)
	{
		global $wpdb;
		
		// TODO: We need to let the user pick their own version
		// Use the default translation until we add user input for this value
		$version_id = bfox_get_default_version();
		$table_name = bfox_get_verses_table_name($version_id);
		
		$query = $wpdb->prepare("SELECT chapter_id
								FROM $table_name
								WHERE unique_id >= %d
								AND unique_id <= %d
								AND chapter_id != 0
								GROUP BY chapter_id",
								$first_verse,
								$last_verse);
		return $wpdb->get_col($query);
	}
	
	function bfox_get_passage_size($first_verse, $last_verse, $group_by = '')
	{
		global $wpdb;

		if ('' != $group_by) $group_by = 'GROUP BY ' . $group_by;
		
		// TODO: We need to let the user pick their own version
		// Use the default translation until we add user input for this value
		$version_id = bfox_get_default_version();
		$table_name = bfox_get_verses_table_name($version_id);
		
		$query = $wpdb->prepare("SELECT COUNT(*)
								FROM $table_name
								WHERE unique_id >= %d
								AND unique_id <= %d
								AND chapter_id != 0
								$group_by",
								$first_verse,
								$last_verse);
		$size = $wpdb->get_var($query);

		if (0 < $size) return $size;
		return 0;
	}

	function bfox_format_ref_url($ref_str, $path = '/')
	{
		return get_option('home') . $path . '?bible_ref=' . $ref_str;
	}
	
	function bfox_format_ref_link($ref_str)
	{
		return '<a href="' . bfox_format_ref_url($ref_str) . '" title="' . $ref_str . '">' . $ref_str . '</a>';
	}
	
	/*
	 This class is used to represent a bible reference as a 3 integer vector: book, chapter, verse
	 */
	class BibleRefVector
	{
		function BibleRefVector($vector)
		{
			$this->update($vector);
		}

		function update($vector)
		{
			if (is_array($vector))
			{
				$this->values = $vector;
				$this->value = 0;
				$count = count($vector);
				for ($index = 0; $index < $count; $index++)
					$this->value = ($this->value << BFOX_UNIQUE_ID_PART_SIZE) + ($vector[$index] % BFOX_UNIQUE_ID_MAX);
			}
			else
			{
				$this->value = $vector;
				$this->values = array(0, 0, 0);
				for ($index = count($this->values) - 1; 0 <= $index; $index--)
				{
					$this->values[$index] = $vector % BFOX_UNIQUE_ID_MAX;
					$vector = $vector >> BFOX_UNIQUE_ID_PART_SIZE;
				}
			}

			// Set easy to use keys for the book, chapter and verse
			$this->values['book'] = $this->values[0];
			$this->values['chapter'] = $this->values[1];
			$this->values['verse'] = $this->values[2];
		}
	}

	/*
	 This class is used to represent a bible reference using the following integer values:
	 book_id, chapter1, verse1, chapter2, verse2
	 
	 This form allows easy conversion between the unique ID form (BibleRefSingle) and string input/output
	 */
	class BibleRefParsed
	{
		function set($book_id = 0, $chapter1 = 0, $verse1 = 0, $chapter2 = 0, $verse2 = 0)
		{
			$book_id  &= BFOX_UNIQUE_ID_MASK;
			$chapter1 &= BFOX_UNIQUE_ID_MASK;
			$verse1   &= BFOX_UNIQUE_ID_MASK;
			$chapter2 &= BFOX_UNIQUE_ID_MASK;
			$verse2   &= BFOX_UNIQUE_ID_MASK;

			if (0 < $book_id) $this->book_id = $book_id;
			if (0 < $chapter1) $this->chapter1 = $chapter1;
			if (0 < $verse1) $this->verse1 = $verse1;
			if (0 < $chapter2) $this->chapter2 = $chapter2;
			if (0 < $verse2) $this->verse2 = $verse2;
		}

		function set_by_unique_ids($unique_ids)
		{
			$book_id  = (int) (($unique_ids[0] >> (BFOX_UNIQUE_ID_PART_SIZE * 2)) & BFOX_UNIQUE_ID_MASK);
			$chapter1 = (int) (($unique_ids[0] >> BFOX_UNIQUE_ID_PART_SIZE) & BFOX_UNIQUE_ID_MASK);
			$verse1   = (int)  ($unique_ids[0] & BFOX_UNIQUE_ID_MASK);
			$chapter2 = (int) (($unique_ids[1] >> BFOX_UNIQUE_ID_PART_SIZE) & BFOX_UNIQUE_ID_MASK);
			$verse2   = (int)  ($unique_ids[1] & BFOX_UNIQUE_ID_MASK);

			// If chapter two is set to max, we should not use it
			if ((BFOX_UNIQUE_ID_MASK == $chapter2) || ($chapter1 == $chapter2))
				$chapter2 = 0;
			if ((BFOX_UNIQUE_ID_MASK == $verse2) || ($verse1 == $verse2))
				$verse2 = 0;

			$this->set($book_id, $chapter1, $verse1, $chapter2, $verse2);
		}
		
		function set_by_string($str)
		{
			// Convert all whitespace to single spaces
			$str = preg_replace('/\s+/', ' ', $str);
			
			// Find the last dash in the string and use it to divide the ref
			$dash_left = trim($str);
			if ($pos = strrpos($dash_left, '-'))
			{
				$dash_right = trim(substr($dash_left, $pos + 1));
				$dash_left = trim(substr($dash_left, 0, $pos));
			}
			
			// Parse the left side of the dash
			$left_colon_list = explode(':', $dash_left);
			$left_colon_count = count($left_colon_list);
			
			// We can only have one dash
			if (2 >= $left_colon_count)
			{
				// If there was a dash, then save the right side as an integer verse number
				if (1 < $left_colon_count)
					$verse_num = (int) trim($left_colon_list[1]);
				
				// If we didn't have any problems with the right side of the colon (verse_num)
				if ((!isset($verse_num)) || (0 < $verse_num))
				{
					if (isset($verse_num)) $verse1 = $verse_num;
					
					// Parse the left side of the colon to get the book name and the chapter num
					$colon_left = trim($left_colon_list[0]);
					if ($pos = strrpos($colon_left, ' '))
					{
						// Get the chapter number which must be greater than 0
						$chapter_num = (int) trim(substr($colon_left, $pos + 1));
						if (0 < $chapter_num)
						{
							$chapter1 = $chapter_num;
							$book_str = trim(substr($colon_left, 0, $pos));
						}
					}
				}
			}
			
			// Parse the right side of the dash if the left side worked (yielding at least a chapter id)
			if ((isset($dash_right)) && (isset($chapter1)))
			{
				$right_colon_list = explode(':', $dash_right);
				$right_colon_count = count($right_colon_list);
				
				// We can only have one dash
				if (2 >= $right_colon_count)
				{
					// If there was a dash, then save the right side as an integer
					if (1 < $right_colon_count)
						$num2 = (int) trim($right_colon_list[1]);
					
					// If we didn't have any problems with the right side of the colon (num2)
					// Then save the left side as an integer
					if ((!isset($num2)) || (0 < $num2))
						$num1 = (int) trim($right_colon_list[0]);
				}
				
				// If we got at least one integer and it is greater than zero,
				// then everything went fine on this side of the dash
				if ((isset($num1)) && (0 < $num1))
				{
					if (isset($num2))
					{
						// If we have 2 numbers then the first is a chapter and the second is a verse
						$chapter2 = $num1;
						$verse2 = $num2;
					}
					else
					{
						// If there is only one number on the right side of the dash,
						// then it can be either a chapter or a verse
						
						// If the left side of the dash yielded a verse2,
						// then we must have a verse on the right side of the dash also
						if (isset($verse1)) $verse2 = $num1;
						else $chapter2 = $num1;
					}
				}
				else
				{
					// If we didn't get any numbers on this side of the dash
					// then the string is misformatted
					unset($chapter1);
				}
			}
			
			// If we haven't set a book string yet, set it to the original str
			if (!isset($book_str)) $book_str = $str;
			
			// Try to find a book id for the book string
			if ($book_id = bfox_find_book_id(trim($book_str)))
			{
				// If we found a book id, this must be a valid bible reference string
				$this->set($book_id, $chapter1, $verse1, $chapter2, $verse2);
			}
		}
		
		function get_unique_ids()
		{
			// Create the unique ids
			$unique_ids = array();

			if (isset($this->book_id))
			{
				/*
				 Conversion methods:
				 john			0:0-max:max		max:max
				 john 1			1:0-1:max		ch1:max
				 john 1-2		1:0-2:max		ch2:max
				 john 1:1		1:1-1:1			ch1:vs1
				 john 1:1-5		1:1-5:max		ch2:max
				 john 1:1-0:2	1:1-1:2			ch1:vs2
				 john 1:1-5:2	1:1-5:2			ch2:vs2
				 john 1-5:2		1:0-5:2			ch2:vs2
				 
				 When chapter2 is not set (== 0): chapter2 equals chapter1 unless chapter1 is not set
				 When verse2 is not set (== 0): verse2 equals max unless chapter2 is not set and verse1 is set
				 */
				
				if (isset($this->chapter1)) $chapter1 = $this->chapter1;
				else $chapter1 = 0;

				if (isset($this->verse1)) $verse1 = $this->verse1;
				else $verse1 = 0;
				
				// When verse2 is not set: verse2 equals max unless chapter2 is not set and verse1 is set
				if (isset($this->verse2)) $verse2 = $this->verse2;
				else $verse2 = ((isset($this->verse1)) && (!isset($this->chapter2))) ? $verse1 : BFOX_UNIQUE_ID_MASK;

				// When chapter2 is not set: chapter2 equals chapter1 unless chapter1 is not set
				if (isset($this->chapter2)) $chapter2 = $this->chapter2;
				else $chapter2 = (isset($this->chapter1)) ? $chapter1 : BFOX_UNIQUE_ID_MASK;
				
				// Set the unique IDs according to the book IDs
				$unique_ids[0] = $unique_ids[1] = $this->book_id << (BFOX_UNIQUE_ID_PART_SIZE * 2);

				// Add the verse and chapter information to the unique ids
				$unique_ids[0] += ($chapter1 << BFOX_UNIQUE_ID_PART_SIZE) + $verse1;
				$unique_ids[1] += ($chapter2 << BFOX_UNIQUE_ID_PART_SIZE) + $verse2;
			}

			return $unique_ids;
		}

		function get_string()
		{
			if (isset($this->str))
				return $this->str;
			
			if (!isset($this->book_name))
				$this->book_name = bfox_get_book_name($this->book_id);
			
			// Create the reference string
			$str = "$this->book_name";
			if (isset($this->chapter1))
			{
				$str .= " {$this->chapter1}";
				if (isset($this->verse1))
					$str .= ":{$this->verse1}";
				if (isset($this->chapter2))
				{
					$str .= "-{$this->chapter2}";
					if (isset($this->verse2))
						$str .= ":{$this->verse2}";
				}
				else if (isset($this->verse2))
					$str .= "-{$this->verse2}";
			}
			
			$this->str = $str;
			return $this->str;
		}
	}

	/*
	 This class is used to represent a bible reference as two unique IDs
	 */
	class BibleRefSingle
	{
		function BibleRefSingle($value = array(0, 0))
		{
			$this->update($value);
		}

		function update($value = array(0, 0))
		{
			unset($this->cache);

			if (is_string($value))
			{
				$this->cache['parsed'] = new BibleRefParsed;
				$this->cache['parsed']->set_by_string($value);
				$unique_ids = $this->cache['parsed']->get_unique_ids();
			}
			else if (is_array($value))
			{
				$unique_ids = $value;
			}
			
			if (isset($unique_ids))
			{
				$this->vectors = array(new BibleRefVector($unique_ids[0]),
									   new BibleRefVector($unique_ids[1]));
			}
		}

		function is_valid()
		{
			if ((0 < $this->vectors[0]->value) && ($this->vectors[0]->value <= $this->vectors[1]->value))
				return true;
			return false;
		}

		function get_unique_ids()
		{
			return array($this->vectors[0]->value, $this->vectors[1]->value);
		}

		function get_string()
		{
			return $this->get_parsed()->get_string();
		}

		// Returns the parsed array form of the bible reference
		function get_parsed()
		{
			if (!isset($this->cache['parsed']))
			{
				$this->cache['parsed'] = new BibleRefParsed;
				$this->cache['parsed']->set_by_unique_ids($this->get_unique_ids());
			}

			return $this->cache['parsed'];
		}

		function get_book_id()
		{
			return $this->vectors[0]->values['book'];
		}

		// Returns an SQL expression for comparing this bible reference against one unique id column
		function sql_where($col1 = 'unique_id')
		{
			global $wpdb;
			return $wpdb->prepare("($col1 >= %d AND $col1 <= %d)", $this->vectors[0]->value, $this->vectors[1]->value);
		}

		// Returns an SQL expression for comparing this bible reference against two unique id columns
		function sql_where2($col1, $col2)
		{
			/*
			 Equation for determining whether one bible reference overlaps another
			 
			 a1 <= b1 and b1 <= a2 or
			 a1 <= b2 and b2 <= a2
			 or
			 b1 <= a1 and a1 <= b2 or
			 b1 <= a2 and a2 <= b2
			 
			 a1b1 * b1a2 + a1b2 * b2a2 + b1a1 * a1b2 + b1a2 * a2b2
			 b1a2 * (a1b1 + a2b2) + a1b2 * (b1a1 + b2a2)
			 
			 */
			
			global $wpdb;
			return $wpdb->prepare("((($col1 <= %d) AND ((%d <= $col1) OR (%d <= $col2))) OR
								    ((%d <= $col2) AND (($col1 <= %d) OR ($col2 <= %d))))",
								  $this->vectors[1]->value, $this->vectors[0]->value, $this->vectors[1]->value,
								  $this->vectors[0]->value, $this->vectors[0]->value, $this->vectors[1]->value);
		}

		// Increments the bible reference by a given factor
		function increment($factor = 1)
		{
			// Only increment if we are not looking at an entire book
			if (0 != $this->vectors[0]->values['chapter'])
			{
				// Get the difference between chapters
				$diff = $this->vectors[1]->values['chapter'] - $this->vectors[0]->values['chapter'];

				// If the chapter difference is 0, and there is no specified verse
				// Then we must be viewing one single chapter, so our chapter difference should be 1
				if ((0 == $diff) && (0 == $this->vectors[0]->values['verse']))
					$diff = 1;

				$chapter_inc = 0;
				$verse_inc = 0;

				// If we have a chapter difference then set the chapter increment,
				// otherwise try to set a verse increment
				if (0 < $diff) $chapter_inc = $diff * $factor;
				else
				{
					$diff = $this->vectors[1]->values['verse'] - $this->vectors[0]->values['verse'];
					$verse_inc = (1 + $diff) * $factor;
				}

				// If we have a chapter or verse increment,
				// Then update our BibleRef with incremented vectors
				if ((0 != $chapter_inc) || (0 != $verse_inc))
				{
					$inc = new BibleRefVector(array(0, $chapter_inc, $verse_inc));
					$vector1 = new BibleRefVector($inc->value + $this->vectors[0]->value);
					$vector2 = new BibleRefVector($inc->value + $this->vectors[1]->value);
					$this->update(array($vector1->value, $vector2->value));
				}
			}
		}

		function get_size(BibleRefVector $size_vector)
		{
			if (0 != $size_vector->values['chapter'])
				return bfox_get_passage_size($this->vectors[0]->value, $this->vectors[1]->value, 'chapter_id');
			if (0 != $size_vector->values['verse'])
				return bfox_get_passage_size($this->vectors[0]->value, $this->vectors[1]->value);
			return 0;
		}

		function shift(BibleRefVector $size_vector)
		{
			// NOTE: This function was designed to replace the bfox_get_sections() function for creating a reading plan
			// It ended up being much slower however, since it is doing way too many DB queries
			// The DB queries are called by $this->get_size()

			$verse_size = 0;

			// Try to use the chapter size first
			$size = $size_vector->values['chapter'];
			if (0 != $size) $size_vector->update(array(0, $size, 0));
			else
			{
				// If the chapter size was set to 0, try to use the verse size instead
				$size = $size_vector->values['verse'];
				$size_vector->update(array(0, 0, $size));
			}

			// If the size of $this is less or equal to the target size,
			// Then we shift everything off of this,
			// Otherwise we have to partition off a portion of this bible ref
			if ($this->get_size($size_vector) <= $size)
			{
				$shifted = clone $this;
				$this->update();
			}
			else
			{
				// The new shifted value should be of size $size_vector
				$shifted = new BibleRefSingle(array($this->vectors[0]->value,
													$this->vectors[0]->value + $size_vector->value - 1));

				// This bible ref gets whatever remains
				$this->update(array($this->vectors[0]->value + $size_vector->value,
									$this->vectors[1]->value));
			}
			return $shifted;
		}
	}

	/*
	 This class is a wrapper around BibleRefSingle to store it in an array
	 */
	class BibleRefs
	{
		private $refs;

		function BibleRefs($value = 0)
		{
			$this->refs = array();
			if (is_string($value)) $this->push_string($value);
			else if (is_array($value)) $this->push_sets($value);
		}

		function is_valid()
		{
			return (0 < count($this->refs));
		}

		// Returns the internal array of BibleRefSingles converted to an
		// array of BibleRefs where each element has just one BibleRefSingle
		function get_refs_array()
		{
			$refs_array = array();
			foreach ($this->refs as $ref)
			{
				$new_ref = new BibleRefs;
				$new_ref->push_ref_single($ref);
				$refs_array[] = $new_ref;
			}
			return $refs_array;
		}
		
		function get_sets()
		{
			$unique_id_sets = array();
			foreach ($this->refs as $ref) $unique_id_sets[] = $ref->get_unique_ids();
			return $unique_id_sets;
		}

		function get_string()
		{
			$strs = array();
			foreach ($this->refs as $ref) $strs[] = $ref->get_string();
			return implode('; ', $strs);
		}

		function get_count()
		{
			return count($this->refs);
		}

		function get_url()
		{
			return bfox_format_ref_url($this->get_string());
		}

		function get_write_url()
		{
			return bfox_format_ref_url($this->get_string(), '/wp-admin/post-new.php');
		}
		
		function get_link()
		{
			return bfox_format_ref_link($this->get_string());
		}
		
		function get_links()
		{
			$links = array();
			foreach ($this->refs as $ref) $links[] = bfox_format_ref_link($ref->get_string());
			return implode('; ', $links);
		}
		
		function push_ref_single(BibleRefSingle $ref)
		{
			$this->refs[] = $ref;
		}

		function push_sets($unique_id_sets)
		{
			$count = 0;
			if (is_array($unique_id_sets))
			{
				foreach ($unique_id_sets as $unique_ids)
				{
					$ref = new BibleRefSingle($unique_ids);
					if ($ref->is_valid())
					{
						$this->refs[] = $ref;
						$count++;
					}
				}
			}
			return $count;
		}

		function push_string($str)
		{
			$count = 0;
			$refstrs = preg_split("/[\n,;]/", trim($str));
			foreach ($refstrs as $refstr)
			{
				$ref = new BibleRefSingle($refstr);
				if ($ref->is_valid())
				{
					$this->refs[] = $ref;
					$count++;
				}
			}
			return $count;
		}

		function sql_where($col1 = 'unique_id')
		{
			$strs = array();
			foreach ($this->refs as $ref) $strs[] = $ref->sql_where($col1);
			return '(' . implode(' OR ', $strs) . ')';
		}

		function sql_where2($col1, $col2)
		{
			$strs = array();
			foreach ($this->refs as $ref) $strs[] = $ref->sql_where2($col1, $col2);
			return '(' . implode(' OR ', $strs) . ')';
		}

		function increment($factor = 1)
		{
			foreach ($this->refs as &$ref) $ref->increment($factor);
		}

		function shift(BibleRefVector $size_vector)
		{
			// NOTE: This function was designed to replace the bfox_get_sections() function for creating a reading plan
			// It ended up being much slower however, since it is doing way too many DB queries
			// The DB queries are called by $this->get_size()

			// Try to use the chapter size first
			$size = $size_vector->values['chapter'];
			if (0 != $size) $size_vector->update(array(0, $size, 0));
			else
			{
				// If the chapter size was set to 0, try to use the verse size instead
				$size = $size_vector->values['verse'];
				$size_vector->update(array(0, 0, $size));
			}

			// Create a new BibleRefs instance to hold all the shifted values
			$shifted = new BibleRefs;

			// Shift each ref in $this->refs until we run out of refs or our size quota is reached
			while ((0 < $size) && (0 < count($this->refs)))
			{
				// Shift the first ref in $this->refs
				$shifted_ref = $this->refs[0]->shift($size_vector);

				// If the shifted ref is valid,
				// Then push it onto our new BibleRefs and update our size quota
				if ($shifted_ref->is_valid())
				{
					$shifted->push_sets(array($shifted_ref->get_unique_ids()));
					$size -= $shifted_ref->get_size($size_vector);
				}

				// If the first ref is no longer valid, get rid of it
				if (!$this->refs[0]->is_valid()) array_shift($this->refs);
			}

			return $shifted;
		}

		function get_sections($size)
		{
			// NOTE: This function was supposed to be replaced by using the shift() functions, but they were too slow
			// It is currently being hacked to work with the new BibleRefs system
			
			$sections = array();
			$period = 0;
			$section = 0;
			$remainder = 0;
			$remainderStr = "";
			foreach ($this->refs as $ref)
			{
				$unique_ids = $ref->get_unique_ids();
				$chapters = bfox_get_chapters($unique_ids[0], $unique_ids[1]);
				$num_chapters = count($chapters);
				$num_sections = (int) floor(($num_chapters + $remainder) / $size);
				
				$tmpRef['book_id'] = $ref->get_book_id();
				$chapter1_index = 0;
				$chapter2_index = $size - $remainder - 1;
				for ($index = 0; $index < $num_sections; $index++)
				{
					$tmpRefStr = "";
					if (($index == 0) && ($remainder > 0))
					{
						$tmpRefStr .= "$remainderStr, ";
						$remainderStr = "";
						$remainder = 0;
					}
					
					$tmpRef['chapter1'] = $chapters[$chapter1_index];
					if ($chapter2_index > $chapter1_index)
						$tmpRef['chapter2'] = $chapters[$chapter2_index];
					else $tmpRef['chapter2'] = 0;
					
					// HACK: This is a hacky way of getting the string, because I didn't want to rewrite this whole function to
					// work well with the new BibleRefs system
					$tmpRefParsed = new BibleRefParsed;
					$tmpRefParsed->set($tmpRef['book_id'], $tmpRef['chapter1'], $tmpRef['verse1'], $tmpRef['chapter2'], $tmpRef['verse2']);
					$tmpRefStr .= $tmpRefParsed->get_string();
					
					$sections[] = $tmpRefStr;
					
					$chapter1_index = $chapter2_index + 1;
					$chapter2_index = $chapter1_index + $size - 1;
				}
				
				if ($chapter1_index < $num_chapters)
				{
					$remainder += $num_chapters - $chapter1_index;
					$chapter2_index = $num_chapters - 1;
					
					$tmpRef['chapter1'] = $chapters[$chapter1_index];
					if ($chapter2_index > $chapter1_index)
						$tmpRef['chapter2'] = $chapters[$chapter2_index];
					else $tmpRef['chapter2'] = 0;
					
					if ($remainderStr != "")
						$remainderStr .= ", ";
					
					// HACK: This is a hacky way of getting the string, because I didn't want to rewrite this whole function to
					// work well with the new BibleRefs system
					$tmpRefParsed = new BibleRefParsed;
					$tmpRefParsed->set($tmpRef['book_id'], $tmpRef['chapter1'], $tmpRef['verse1'], $tmpRef['chapter2'], $tmpRef['verse2']);
					$remainderStr .= $tmpRefParsed->get_string();
				}
			}
			if ($remainderStr != "")
				$sections[] = $remainderStr;
			
			$sectionRefs = array();
			foreach ($sections as $section) $sectionRefs[] = new BibleRefs($section);
			return $sectionRefs;
		}
	}

?>
