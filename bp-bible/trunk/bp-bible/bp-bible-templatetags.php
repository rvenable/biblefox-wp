<?php

/**
 * In this file you should define template tag functions that end users can add to their template files.
 * Each template tag function should echo the final data so that it will output the required information
 * just by calling the function name.
 */

/**
 * If you want to go a step further, you can create your own custom WordPress loop for your component.
 * By doing this you could output a number of items within a loop, just as you would output a number
 * of blog posts within a standard WordPress loop.
 *
 * The example template class below would allow you do the following in the template file:
 *
 * 	<?php if ( bp_get_bible_has_items() ) : ?>
 *
 *		<?php while ( bp_get_bible_items() ) : bp_get_bible_the_item(); ?>
 *
 *			<p><?php bp_get_bible_item_name() ?></p>
 *
 *		<?php endwhile; ?>
 *
 *	<?php else : ?>
 *
 *		<p class="error">No items!</p>
 *
 *	<?php endif; ?>
 *
 * Obviously, you'd want to be more specific than the word 'item'.
 *
 */

class BP_Bible_Template {
	var $current_passage = -1;
	var $passage_count;
	var $passages;

	/**
	 * @var BfoxPassage
	 */
	var $passage;

	var $in_the_loop;

	var $pag_page;
	var $pag_num;
	var $pag_links;

	/**
	 * @var BfoxRefs
	 */
	var $refs = NULL;

	var $bcvs = NULL;

	/**
	 * @var BfoxTrans
	 */
	var $translation = NULL;

	private $visible = '';
	private $footnotes = array();

	/**
	 * @var BfoxHistoryEvent
	 */
	var $event;

	function bp_bible_template( $user_id, $type, $per_page, $max, BfoxBible $bible ) {
		global $bp;

		if ( !$user_id )
			$user_id = $bp->displayed_user->id;

		/***
		 * If you want to make parameters that can be passed, then append a
		 * character or two to "page" like this: $_REQUEST['xpage']
		 * You can add more than a single letter.
		 *
		 * The "x" in "xpage" should be changed to something unique so as not to conflict with
		 * BuddyPress core components which use the unique characters "b", "g", "u", "w",
		 * "ac", "fr", "gr", "ml", "mr" with "page".
		 */

		$this->pag_page = isset( $_REQUEST['bp_page'] ) ? intval( $_REQUEST['bp_page'] ) : 1;
		$this->pag_num = isset( $_GET['num'] ) ? intval( $_GET['num'] ) : $per_page;
		$this->user_id = $user_id;

		$this->refs = $bible->refs;
		$this->translation = $bible->translation;
		$this->event = $bible->history_event;

		/***
		 * You can use the "type" variable to fetch different things to output.
		 * For bible on the groups template loop, you can fetch groups by "newest", "active", "alphabetical"
		 * and more. This would be the "type". You can then call different functions to fetch those
		 * different results.
		 */

		// switch ( $type ) {
		// 	case 'newest':
		// 		$this->passages = bp_bible_get_newest( $user_id, $this->pag_num, $this->pag_page );
		// 		break;
		//
		// 	case 'popular':
		// 		$this->passages = bp_bible_get_popular( $user_id, $this->pag_num, $this->pag_page );
		// 		break;
		//
		// 	case 'alphabetical':
		// 		$this->passages = bp_bible_get_alphabetical( $user_id, $this->pag_num, $this->pag_page );
		// 		break;
		// }

		$this->visible = $this->refs->sql_where();
		$this->bcvs = BfoxRefs::get_bcvs($this->refs->get_seqs());

		$passages = array();
		$passage_count = 0;
		foreach ($this->bcvs as $book => $cvs) {
			$passages []= new BfoxPassage($book, $cvs);
			$passage_count++;
		}

		// Passage Requests
		if ( !$max || $max >= (int)$passage_count )
			$this->total_passage_count = (int)$passage_count;
		else
			$this->total_passage_count = (int)$max;

		$this->passages = $passages;

		if ( $max ) {
			if ( $max >= count($this->passages) )
				$this->passage_count = count($this->passages);
			else
				$this->passage_count = (int)$max;
		} else {
			$this->passage_count = count($this->passages);
		}

		/* Remember to change the "x" in "bp_page" to match whatever character(s) you're using above */
		$this->pag_links = paginate_links( array(
			'base' => add_query_arg( 'bp_page', '%#%' ),
			'format' => '',
			'total' => ceil( (int) $this->total_passage_count / (int) $this->pag_num ),
			'current' => (int) $this->pag_page,
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'mid_size' => 1
		));
	}

	function has_passages() {
		if ( $this->passage_count )
			return true;

		return false;
	}

	function next_passage() {
		$this->current_passage++;
		$this->passage = $this->passages[$this->current_passage];

		return $this->passage;
	}

	function rewind_passages() {
		$this->current_passage = -1;
		if ( $this->passage_count > 0 ) {
			$this->passage = $this->passages[0];
		}
	}

	function user_passages() {
		if ( $this->current_passage + 1 < $this->passage_count ) {
			return true;
		} elseif ( $this->current_passage + 1 == $this->passage_count ) {
			do_action('loop_end');
			// Do some cleaning up after the loop
			$this->rewind_passages();
		}

		$this->in_the_loop = false;
		return false;
	}

	function the_passage() {
		$this->in_the_loop = true;
		$this->passage = $this->next_passage();

		if ( 0 == $this->current_passage ) // loop has just started
			do_action('loop_start');
	}

	function the_refs() {
		if ($this->in_the_loop) return $this->passage->refs();
		return $this->refs;
	}

	function the_ref_str($name = '') {
		if ($this->in_the_loop) return $this->passage->ref_str($name);
		return $this->refs->get_string($name);
	}

	function the_bcvs() {
		return $this->bcvs;
	}

	function ref_nav_link($type = '', $name = '', $title = '', $attrs = '') {
		if ($this->in_the_loop) $ref_str = $this->passage->nav_ref($type, $name);

		if (!empty($ref_str)) return Biblefox::ref_link($ref_str, $title, '', " class='ref_seq_$type'");
		else return '';
	}

	function the_passage_content() {
		if ($this->in_the_loop) return $this->passage->content($this->translation, $this->visible, $this->footnotes);
	}

	function the_footnotes() {
		if (!empty($this->footnotes)) {
			$footnotes = $this->footnotes;
			$this->footnotes = array();
			return $footnotes;
		}
		return false;
	}
}

function bp_bible_has_passages( $args = '' ) {
	global $bp, $passages_template, $bp_bible;

	/***
	 * This function should accept arguments passes as a string, just the same
	 * way a 'query_posts()' call accepts parameters.
	 * At a minimum you should accept 'per_page' and 'max' parameters to determine
	 * the number of passages to show per page, and the total number to return.
	 *
	 * e.g. bp_get_bible_has_passages( 'per_page=10&max=50' );
	 */

	/***
	 * Set the defaults for the parameters you are accepting via the "bp_get_bible_has_passages()"
	 * function call
	 */
	$defaults = array(
		'user_id' => false,
		'per_page' => 10,
		'max' => false,
		'type' => 'newest'
	);

	/***
	 * This function will extract all the parameters passed in the string, and turn them into
	 * proper variables you can use in the code - $per_page, $max
	 */
	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$passages_template = new BP_Bible_Template( $user_id, $type, $per_page, $max, $bp_bible );

	return $passages_template->has_passages();
}

function bp_bible_the_passage() {
	global $passages_template;
	return $passages_template->the_passage();
}

function bp_bible_passages() {
	global $passages_template;
	return $passages_template->user_passages();
}

function bp_bible_passage_name() {
	echo bp_bible_get_passage_name();
}
	/* Always provide a "get" function for each template tag, that will return, not echo. */
	function bp_bible_get_passage_name() {
		global $passages_template;
		echo apply_filters( 'bp_bible_get_passage_name', $passages_template->passage->name ); // Example: $passages_template->passage->name;
	}

function bp_bible_passage_pagination() {
	echo bp_bible_get_passage_pagination();
}
	function bp_bible_get_passage_pagination() {
		global $passages_template;
		return apply_filters( 'bp_bible_get_passage_pagination', $passages_template->pag_links );
	}

/**
 * Returns the BfoxRefs for all passages
 *
 * @return BfoxRefs
 */
function bp_bible_the_refs() {
	global $passages_template;
	return $passages_template->the_refs();
}

function bp_bible_the_ref_str($name = '') {
	global $passages_template;
	return $passages_template->the_ref_str($name);
}

function bp_bible_the_books() {
	global $passages_template;
	return array_keys($passages_template->the_bcvs());
}

function bp_bible_ref_link($type = '', $name = '', $title = '', $attrs = '') {
	global $passages_template;
	return $passages_template->ref_nav_link($type, $name, $title, $attrs);
}

function bp_bible_the_passage_content() {
	global $passages_template;
	return $passages_template->the_passage_content();
}

function bp_bible_the_footnotes() {
	global $passages_template;
	return $passages_template->the_footnotes();
}

function bp_bible_history_desc($date_str = '') {
	global $passages_template;
	if (!empty($passages_template->event)) return $passages_template->event->desc($date_str);
}

function bp_bible_mark_read_link($unread_text = '', $read_text = '') {
	global $passages_template;
	if (!empty($passages_template->event)) return $passages_template->event->toggle_link($unread_text, $read_text);
}

function bp_bible_url($ref_str = '', $search_str = '') {
	global $bp;
	$url = $bp->root_domain . '/' . $bp->bible->slug . '/';
	if (!empty($ref_str)) $url .= urlencode($ref_str) . '/';
	$url .= urlencode($search_str);

	return $url;
}

function bp_bible_bible_url(BfoxBible $bible) {
	return bp_bible_url($bible->refs->get_string(), $bible->search_str);
}

function bp_bible_translation_select($select_id = NULL, $use_short = FALSE) {
	// Get the list of enabled translations
	$translations = BfoxTrans::get_enabled();

	$select = "<select name='" . BfoxQuery::var_translation . "' id='search-which' style='width: auto'>";
	foreach ($translations as $translation) {
		$name =  ($use_short) ? $translation->short_name : $translation->long_name;
		$selected = ($translation->id == $select_id) ? ' selected ' : '';
		$select .= "<option value='$translation->id'$selected>$name</option>";
	}
	$select .= "</select>";

	return $select;
}

function bp_bible_search_form($form = '') {
	global $bp_bible;

	$form = "
		<form action='" . BfoxQuery::ref_url() . "' method='get' id='search-form'>
			" . bp_bible_translation_select(bp_bible_get_trans_id()) . "
			<input type='text' id='search-terms' name='search-terms' value='" . $bp_bible->search_query . "' />
			<input type='submit' name='search-submit' id='search-submit' value='" . __('Search Bible', 'bp-bible') . "' />
		</form>
	";
	return $form;
}
add_filter( 'bp_search_form', 'bp_bible_search_form' );

?>