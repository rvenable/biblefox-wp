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

function bp_bible_friends_posts($args = array()) {
	global $bp;

	$defaults = array(
		'refs' => NULL,
		'user_id' => $bp->loggedin_user->id
	);

	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if (is_null($refs)) $refs = bp_bible_the_refs();

	$friends_url = bp_core_get_user_domain($user_id) . 'friends/my-friends/all-friends';

	$total_post_count = 0;

	$friend_ids = array();
	if (class_exists(BP_Friends_Friendship)) {
		$friend_ids = BP_Friends_Friendship::get_friend_user_ids($user_id);

		$mem_dir_url = bp_core_get_root_domain() . '/members/';

		if (!empty($friend_ids)) {
			global $wpdb;

			// Add the current user to the friends so that we get his posts as well
			$friend_ids []= $user_id;

			$user_post_ids = BfoxPosts::get_post_ids_for_users($refs, $friend_ids);

			if (!empty($user_post_ids)) foreach ($user_post_ids as $blog_id => $post_ids) {
				$posts = array();

				switch_to_blog($blog_id);

				if (!empty($post_ids)) {
					BfoxBlogQueryData::set_post_ids($post_ids);
					$query = new WP_Query(1);
					$post_count = $query->post_count;
				}
				else $post_count = 0;
				$total_post_count += $post_count;

				while(!empty($post_ids) && $query->have_posts()) :?>
					<?php $query->the_post() ?>
					<div class="cbox_sub_sub">
						<div class='cbox_head'><strong><?php the_title(); ?></strong> (<?php echo bfox_the_refs(BibleMeta::name_short, FALSE) ?>) by <?php the_author() ?> (<?php the_time('F jS, Y') ?>)</div>
						<div class='cbox_body post'>
							<h4><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h4>
							<small><?php the_time('F jS, Y') ?>  by <?php the_author() ?> (<?php echo bfox_the_refs() ?>)</small>
							<div class="entry">
								<?php the_content('Read the rest of this entry &raquo;') ?>
								<p class="postmetadata"><?php the_tags('Tags: ', ', ', '<br />'); ?> Posted in <?php the_category(', ') ?> | <?php edit_post_link('Edit', '', ' | '); ?>  <?php comments_popup_link('No Comments &#187;', '1 Comment &#187;', '% Comments &#187;'); ?></p>
							</div>
						</div>
					</div>
				<?php endwhile;
				restore_current_blog();
			}

			if (empty($total_post_count)) {
				printf(__('None of your friends have written any posts about %s.
				You can write your own post. You can also find more friends using the %s.'),
				$refs->get_string(),
				"<a href='$mem_dir_url'>" . __('members directory') . "</a>");
			}
		}
		else {
			printf(__('This menu shows you any blog posts written by your friends about this passage.
			You don\'t currently have any friends. That\'s okay, because you can find some friends using our %s.'),
			"<a href='$mem_dir_url'>" . __("members directory") . "</a>");
		}
	}
}

function bp_bible_post_form($args = array()) {
	global $bp;
	$user_id = $bp->loggedin_user->id;
	if (!empty($user_id)) {
		$refs = bp_bible_the_refs();
		$ref_str = $refs->get_string();

		$blogs = get_blogs_of_user($user_id);

		$links = array();

		foreach ($blogs as $blog) {
			$role = get_blog_role_for_user($user_id, $blog->userblog_id);
			if ($role && ('Subscriber' != $role)) {
				$links []= "<li><a href='" . BfoxBlog::ref_write_url($ref_str, $blog->siteurl) . "'>$blog->blogname</a></li>";
			}
		}
		$create_url = $bp->loggedin_user->domain . $bp->blogs->slug . '/create-a-blog';
	}
	else $create_url = bp_signup_page(false);

	if (!empty($links)) {
		$content = "<p>You can write a blog post about $ref_str on these blogs:</p><ul>";
		foreach ($links as $link) $content .= $link;
		$content .= '</ul>';
		$content .= '<p><a href="' . $create_url . '">' . __('Create a new blog') . '</a></p>';
	}
	else {
		$content = '<p>You don\'t belong to any blogs that you can add a post to.
			That\'s okay because you can easily <a href="' . $create_url . '">' . __('create a new blog') . '</a>.</p>';
	}

	echo $content;
}

function bp_bible_current_readings($args = array()) {
	global $bp;

	$user_id = $bp->loggedin_user->id;
	if (!empty($user_id)) {
		$plans = BfoxPlans::get_plans_using_args(array('user_id' => $user_id, 'is_finished' => 0));
		BfoxPlans::add_history_to_plans($plans);

		$content = bp_plan_current_readings($args, $plans);
		if (empty($content)) $content = __('<p>You do not have any current readings.</p>');
		$content .= "<p><a href='" . bp_plans_user_plans_permalink() . "'>" . __('Edit Reading Plans') . "</a></p>";
	}
	else $content = "<p>" . __('With Biblefox, you can create a Bible Reading plan to organize how you read the Bible. ') . bp_bible_loginout() . __(' to see the current readings for your reading plans.</p>');

	echo $content;
}

function bp_bible_history_list($args = array()) {
	global $bp;

	$user_id = $bp->loggedin_user->id;
	if (empty($user_id)) {
		$content = "<p>" . __('Biblefox can keep track of all the Bible passages you read.
			If you\'re already a member, ') . bp_bible_loginout() . __(' to track this passage and see your recent history.
			If you\'re not a member, ') . '<a href="' . bp_signup_page(false) . '">' . __('sign up') . '</a>' . __(' for free!') . '</p>';
	}
	else {
		$history = BfoxHistory::get_history_using_args($args);

		if ('table' == $args['style']) {
			$table = new BfoxHtmlTable("class='widefat'");

			foreach ($history as $event) $table->add_row('', 5,
				$event->desc(),
				$event->ref_link(),
				BfoxUtility::nice_date($event->time),
				date('g:i a', $event->time),
				$event->toggle_link());

			$content = $table->content();
		}
		else {
			$list = new BfoxHtmlList();

			foreach ($history as $event) $list->add($event->ref_link($args['ref_name']));

			$content = $list->content();
		}
	}

	echo $content;
}

function bp_bible_options($options = array()) {
	if (empty($options)) $options = array(
		'jesus' => __('Show Jesus\' words in red'),
		'paragraphs' => __('Display verses as paragraphs'),
		'verse_nums' => __('Hide verse numbers'),
		'footnotes' => __('Hide footnote links')
	);

	?>
	<ul>
		<?php foreach ($options as $name => $label): ?>
		<li>
			<input type="checkbox" name="<?php echo $name ?>" id="option_<?php echo $name ?>" class="view_option"/>
			<label for="option_<?php echo $name ?>"><?php echo $label ?></label>
		</li>
		<?php endforeach ?>
	</ul>
	<?php
}

function bp_bible_toc() {
	$books = bp_bible_the_books();

	foreach ($books as $book) {
		$book_name = BibleMeta::get_book_name($book);
		$end_chapter = BibleMeta::end_verse_max($book);
		?>
		<div class="widget">
			<h2 class="widgettitle"><?php echo $book_name . __(' - Table of Contents') ?></h2>
			<ul class='flat_toc'>
			<?php for ($ch = BibleMeta::start_chapter; $ch <= $end_chapter; $ch++): ?>
				<li><a href='<?php echo BfoxQuery::ref_url("$book_name $ch") ?>'><?php echo $ch ?></a></li>
			<?php endfor ?>
			</ul>
		</div>
		<?php
	}
}

/*
 * Bible Discussions Templates
 */

function bp_bible_discussions_header_tabs() {
	global $bp;
?>
	<li<?php if ( !isset($bp->action_variables[0]) || 'today' == $bp->action_variables[0] ) : ?> class="current"<?php endif; ?>><a href="<?php echo $bp->displayed_user->domain . $bp->bible->slug ?>/bible-discussion"><?php _e( 'Today', 'bp-plans' ) ?></a></li>
	<li<?php if ( 'week' == $bp->action_variables[0] ) : ?> class="current"<?php endif; ?>><a href="<?php echo $bp->displayed_user->domain . $bp->bible->slug ?>/bible-discussion/week"><?php _e( 'Last 7 Days', 'bp-plans' ) ?></a></li>
	<li<?php if ( 'month' == $bp->action_variables[0] ) : ?> class="current"<?php endif; ?>><a href="<?php echo $bp->displayed_user->domain . $bp->bible->slug ?>/bible-discussion/month"><?php _e( 'Last 30 Days', 'bp-plans' ) ?></a></li>
<?php
	do_action( 'bp_plans_header_tabs' );
}

function bp_bible_discussions_filter_title() {
	global $bp;

	$current_filter = $bp->action_variables[0];

	switch ( $current_filter ) {
		case 'today': default:
			_e( 'Scriptures studied today', 'bp-plans' );
			break;
		case 'week':
			_e( 'Scriptures studied the last 7 days', 'bp-plans' );
			break;
		case 'month':
			_e( 'Scriptures studied the last 30 days', 'bp-plans' );
			break;
	}
	do_action( 'bp_plans_filter_title' );
}

function bp_bible_refs($name = '') {
	$refs = bp_get_bible_refs();
	echo $refs->get_string($name);
}

function bp_get_bible_refs() {
	global $bp;
	return $bp->bible->refs;
}

function bp_bible_add_scriptures_form() {
	?>
		<form action='' method='post' id='search-form'>
			<input type='text' id='search-terms' name='search-terms' value='' />
			<input type="checkbox" id="add-marked-read" name="mark-read" /><?php _e('Mark as read', 'bp-bible') ?>
			<input type="submit" name="add" id="add" value="<?php _e('Add', 'bp-bible') ?>" />
		</form>
	<?php
}

/*
 * Bible History Templates
 */

class BP_Bible_History_Template extends BP_Loop_Template {
	public function __construct($args = array()) {
		global $bp;

		extract($args);

		$this->set_user_id($user_id);
		$this->set_per_page($per_page);

		$args = array(
			'user_id' => $this->user_id,
			'limit' => $this->pag_num,
			'page' => $this->pag_page
		);

		$this->items = BfoxHistory::get_history_using_args($args);
		$this->total_item_count = BfoxHistory::get_total_history();

		$this->item_count = count($this->items);
		$this->set_max($max);
		$this->set_page_links();
	}
}

function bp_has_bible_history($args = array()) {
	global $bp, $bible_history_template;

	$defaults = array(
		'user_id' => false,
		'per_page' => 40,
		'max' => false,
		'type' => 'current'
	);

	$args = wp_parse_args( $args, $defaults );

/*	if ( 'my-plans' == $bp->current_action ) {
		$page = $bp->action_variables[0];
		if ( 'inactive' == $page )
			$type = 'finished';
		else if ( 'friends' == $page )
			$type = 'friends';
	}
	elseif ( $bp->plans->current_plan->slug ) {
		$type = 'single-plan';
	}
*/
	$bible_history_template = new BP_Bible_History_Template($args);

	return $bible_history_template->has_items();
}

function bp_bible_history_events() {
	global $bible_history_template;
	return $bible_history_template->items();
}

function bp_the_bible_history_event() {
	global $bible_history_template;
	return $bible_history_template->the_item();
}

/**
 * Returns the current reading bible_history if $bible_history is NULL
 *
 * @param BfoxHistoryEvent $bible_history
 * @return BfoxHistoryEvent
 */
function bp_get_bible_history_event(BfoxHistoryEvent $bible_history = NULL) {
	if (empty($bible_history)) {
		global $bp, $bible_history_template;
		if (!empty($bible_history_template->item)) $bible_history = $bible_history_template->item;
	}

	return $bible_history;
}

function bp_bible_history_event_desc() {
	echo bp_get_bible_history_event_desc();
}
	function bp_get_bible_history_event_desc(BfoxHistoryEvent $bible_history = NULL) {
		$bible_history = bp_get_bible_history_event($bible_history);
		return apply_filters( 'bp_get_bible_history_event_desc', $bible_history->desc() );
	}

function bp_bible_history_event_ref_link() {
	echo bp_get_bible_history_event_ref_link();
}
	function bp_get_bible_history_event_ref_link(BfoxHistoryEvent $bible_history = NULL) {
		$bible_history = bp_get_bible_history_event($bible_history);
		return apply_filters( 'bp_get_bible_history_event_ref_link', $bible_history->ref_link() );
	}

function bp_bible_history_event_nice_date() {
	echo bp_get_bible_history_event_nice_date();
}
	function bp_get_bible_history_event_nice_date(BfoxHistoryEvent $bible_history = NULL) {
		$bible_history = bp_get_bible_history_event($bible_history);
		return apply_filters( 'bp_get_bible_history_event_nice_date', BfoxUtility::nice_date($bible_history->time) );
	}

function bp_bible_history_event_date($format = '') {
	echo bp_get_bible_history_event_date($format);
}
	function bp_get_bible_history_event_date($format = '', BfoxHistoryEvent $bible_history = NULL) {
		if (empty($format)) $format = 'g:i a';
		$bible_history = bp_get_bible_history_event($bible_history);
		return apply_filters( 'bp_get_bible_history_event_date', date($format, $bible_history->time) );
	}

function bp_bible_history_event_toggle_link() {
	echo bp_get_bible_history_event_toggle_link();
}
	function bp_get_bible_history_event_toggle_link(BfoxHistoryEvent $bible_history = NULL) {
		$bible_history = bp_get_bible_history_event($bible_history);
		return apply_filters( 'bp_get_bible_history_event_toggle_link', $bible_history->toggle_link() );
	}

function bp_bible_history_pagination() {
	echo bp_get_bible_history_pagination();
}
	function bp_get_bible_history_pagination() {
		global $bible_history_template;
		return apply_filters( 'bp_get_bible_history_pagination', $bible_history_template->pag_links );
	}

function bp_bible_history_pagination_count() {
	global $bible_history_template;
	echo sprintf( __( 'Viewing bible history %d to %d (of %d)', 'bp-bible' ), $bible_history_template->from_num, $bible_history_template->to_num, $bible_history_template->total_item_count ); ?> &nbsp;
	<span class="ajax-loader"></span><?php
}

function bp_bible_history_pag_id() {
	echo bp_get_bible_history_pag_id();
}
	function bp_get_bible_history_pag_id() {
		return apply_filters( 'bp_get_bible_history_pag_id', 'pag' );
	}

/*
 * Bible Notes Templates
 */

class BP_Bible_Notes_Template extends BP_Loop_Template {
	public function __construct($args = array()) {
		global $bp;

		extract($args);

		$this->set_user_id($user_id);
		$this->set_per_page($per_page);

		$args = array(
			'user_id' => $this->user_id,
			'limit' => $this->pag_num,
			'page' => $this->pag_page,
			'refs' => $refs,
		);

		$this->items = BP_Bible_Note::get_notes($args);
		$this->total_item_count = BP_Bible_Note::$found_rows;

		$this->item_count = count($this->items);
		$this->set_max($max);
		$this->set_page_links();
	}
}

function bp_has_bible_notes($args = array()) {
	global $bp, $bible_notes_template;

	$defaults = array(
		'user_id' => false,
		'per_page' => 10,
		'max' => false,
		'type' => 'current'
	);

	$args = wp_parse_args( $args, $defaults );

/*	if ( 'my-plans' == $bp->current_action ) {
		$page = $bp->action_variables[0];
		if ( 'inactive' == $page )
			$type = 'finished';
		else if ( 'friends' == $page )
			$type = 'friends';
	}
	elseif ( $bp->plans->current_plan->slug ) {
		$type = 'single-plan';
	}
*/
	$bible_notes_template = new BP_Bible_Notes_Template($args);

	return $bible_notes_template->has_items();
}

function bp_bible_notes() {
	global $bible_notes_template;
	return $bible_notes_template->items();
}

function bp_the_bible_note() {
	global $bible_notes_template;
	return $bible_notes_template->the_item();
}

/**
 * Returns the current reading bible_notes if $note is NULL
 *
 * @param BP_Bible_Note $note
 * @return BP_Bible_Note
 */
function bp_get_bible_note(BP_Bible_Note $note = NULL) {
	if (empty($note)) {
		global $bp, $bible_notes_template;
		if (!empty($bible_notes_template->item)) $note = $bible_notes_template->item;
	}

	return $note;
}

function bp_bible_note_content() {
	echo bp_get_bible_note_content();
}
	function bp_get_bible_note_content(BP_Bible_Note $note = NULL) {
		$note = bp_get_bible_note($note);
		return apply_filters( 'bp_get_bible_note_content', $note->display_content );
	}

function bp_bible_note_ref_tags($ref_name = '') {
	echo bp_get_bible_note_ref_tags($ref_name = '');
}
	function bp_get_bible_note_ref_tags($ref_name = '', BP_Bible_Note $note = NULL) {
		$note = bp_get_bible_note($note);
		return apply_filters( 'bp_get_bible_note_ref_tags', $note->tag_refs->get_string($ref_name) );
	}

function bp_bible_note_modified_time($format = '') {
	echo bp_get_bible_note_modified_time($format);
}
	function bp_get_bible_note_modified_time($format = '', BP_Bible_Note $note = NULL) {
		if (empty($format)) $format = 'F n, Y, g:i a';
		$note = bp_get_bible_note($note);
		return apply_filters( 'bp_get_bible_note_modified_time', date($format, strtotime($note->modified_time)) );
	}

function bp_bible_note_created_time($format = '') {
	echo bp_get_bible_note_created_time($format);
}
	function bp_get_bible_note_created_time($format = '', BP_Bible_Note $note = NULL) {
		if (empty($format)) $format = 'F n, Y, g:i a';
		$note = bp_get_bible_note($note);
		return apply_filters( 'bp_get_bible_note_created_time', date($format, strtotime($note->created_time)) );
	}

function bp_bible_note_author_link() {
	echo bp_get_bible_note_author_link();
}
	function bp_get_bible_note_author_link(BP_Bible_Note $note = NULL) {
		$note = bp_get_bible_note($note);
		return apply_filters( 'bp_get_bible_note_author_link', bp_core_get_userlink( $note->user_id ) );
	}

function bp_bible_note_author_avatar() {
	echo bp_get_bible_note_author_avatar();
}
	function bp_get_bible_note_author_avatar(BP_Bible_Note $note = NULL) {
		$note = bp_get_bible_note($note);
		return apply_filters( 'bp_get_bible_note_author_avatar', bp_core_fetch_avatar( array( 'item_id' => $note->user_id, 'type' => 'thumb' ) ) );
	}

function bp_bible_notes_pagination() {
	echo bp_get_bible_notes_pagination();
}
	function bp_get_bible_notes_pagination() {
		global $bible_notes_template;
		return apply_filters( 'bp_get_bible_notes_pagination', $bible_notes_template->pag_links );
	}

function bp_bible_notes_pagination_count() {
	global $bible_notes_template;
	echo sprintf( __( 'Viewing bible notes %d to %d (of %d)', 'bp-bible' ), $bible_notes_template->from_num, $bible_notes_template->to_num, $bible_notes_template->total_item_count ); ?> &nbsp;
	<span class="ajax-loader"></span><?php
}

function bp_bible_notes_pag_id() {
	echo bp_get_bible_notes_pag_id();
}
	function bp_get_bible_notes_pag_id() {
		return apply_filters( 'bp_get_bible_notes_pag_id', 'pag' );
	}

function bp_bible_notes_list() {
	locate_template( array( '/bible/note-list.php' ), true );
}

function bp_bible_notes_form() {
	global $wire_posts_template;

	//if ( is_user_logged_in() && $wire_posts_template->can_post )
		locate_template( array( '/bible/note-form.php' ), true );
}

function bp_bible_note_form_action() {
	echo bp_get_bible_note_form_action();
}
	function bp_get_bible_note_form_action() {
		global $bp;
		return apply_filters( 'bp_get_bible_note_form_action', $bp->displayed_user->domain . $bp->bible->slug . '/notes/save-note' );
	}

function bp_bible_note_action_buttons() {
	echo bp_get_bible_note_action_buttons();
}
	function bp_get_bible_note_action_buttons() {
		global $wire_posts_template, $bp;

		if ( empty( $bp->current_item ) )
			$uri = $bp->current_action;
		else
			$uri = $bp->current_item;

		$notes_url = $bp->loggedin_user->domain . $bp->bible->slug . '/notes';
		return apply_filters( 'bp_get_bible_note_action_buttons', '<a class="item-button delete-post confirm" href="' . wp_nonce_url( site_url( $bp->{$bp->current_component}->slug . '/' . $uri . '/wire/delete/' . $wire_posts_template->wire_post->id ), 'bp_wire_delete_link' ) . '">' . __('Delete', 'buddypress') . '</a>' );
		return apply_filters( 'bp_get_bible_note_action_buttons', '<a class="item-button delete-post confirm" href="' . wp_nonce_url( $notes_url . '/delete/' . $note->id, 'bp_bible_note_delete_link' ) . '">' . __('Delete', 'bp-bible') . '</a>' );
	}





?>