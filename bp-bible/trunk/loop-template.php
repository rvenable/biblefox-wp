<?php

/**
 * If you want to go a step further, you can create your own custom WordPress loop for your component.
 * By doing this you could output a number of items within a loop, just as you would output a number
 * of blog posts within a standard WordPress loop.
 *
 * The example template class below would allow you do the following in the template file:
 *
 * 	<?php if ( bp_get_plans_has_items() ) : ?>
 *
 *		<?php while ( bp_get_plans_items() ) : bp_get_plans_the_item(); ?>
 *
 *			<p><?php bp_get_plans_item_name() ?></p>
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

class BP_Loop_Template {
	protected $current_item = -1;
	public $item_count;
	public $items;
	public $item;

	protected $in_the_loop;

	public $pag_page;
	public $pag_num;
	public $pag_links;

	protected function set_user_id($user_id) {
		global $bp;

		if (empty($user_id)) $user_id = $bp->displayed_user->id;
		$this->user_id = $user_id;

		return $this->user_id;
	}

	protected function set_per_page($per_page) {
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
	}

	protected function set_max($max) {
		// Item Requests
		if ( $max && ($max < $this->item_count ) )
			$this->item_count = $max;
	}

	protected function set_page_links() {
		/* Remember to change the "x" in "bp_page" to match whatever character(s) you're using above */
		$this->pag_links = paginate_links( array(
			'base' => add_query_arg( 'bp_page', '%#%' ),
			'format' => '',
			'total' => ceil( (int) $this->total_item_count / (int) $this->pag_num ),
			'current' => (int) $this->pag_page,
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'mid_size' => 1
		));

		$this->from_num = intval( ( $this->pag_page - 1 ) * $this->pag_num ) + 1;
		$this->to_num = ( $this->from_num + ( $this->pag_num - 1 ) > $this->total_item_count ) ? $this->total_item_count : $this->from_num + ( $this->pag_num - 1) ;
	}

	public function has_items() {
		if ( $this->item_count )
			return true;

		return false;
	}

	public function next_item() {
		$this->current_item++;
		$this->item = $this->items[$this->current_item];

		return $this->item;
	}

	public function rewind_items() {
		$this->current_item = -1;
		if ( $this->item_count > 0 ) {
			$this->item = $this->items[0];
		}
	}

	public function items() {
		if ( $this->current_item + 1 < $this->item_count ) {
			return true;
		} elseif ( $this->current_item + 1 == $this->item_count ) {
			do_action('loop_end');
			// Do some cleaning up after the loop
			$this->rewind_items();
		}

		$this->in_the_loop = false;
		return false;
	}

	public function the_item() {
		$this->in_the_loop = true;
		$this->item = $this->next_item();

		if ( 0 == $this->current_item ) // loop has just started
			do_action('loop_start');
	}
}

?>