<?php

class BP_Bible_FriendsPosts_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(false, 'Bible Friends\' Posts Widget');
	}

	public function widget($args, $instance) {
		extract($args);

		$refs = bp_bible_the_refs();

		if (empty($instance['title'])) $instance['title'] = __('My Friends\' Blog Posts');
		echo $before_widget . $before_title . $instance['title'] . $after_title;

		// If no user, use the current user
		if (empty($user_id)) $user_id = $GLOBALS['user_ID'];

		$friends_url = bp_core_get_user_domain($user_id) . 'friends/my-friends/all-friends';

		?>
		<div class="cbox_sub">
			<div class='cbox_body'>
		<?php

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
							<div class='cbox_head'><strong><?php the_title(); ?></strong> (<?php echo bfox_the_refs(BibleMeta::name_short) ?>) by <?php the_author() ?> (<?php the_time('F jS, Y') ?>)</div>
							<div class='cbox_body box_inside'>
								<h3><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h3>
								<small><?php the_time('F jS, Y') ?>  by <?php the_author() ?></small>
								<div class="post_content">
									<?php the_content('Read the rest of this entry &raquo;') ?>
									<p class="postmetadata"><?php the_tags('Tags: ', ', ', '<br />'); ?> Posted in <?php the_category(', ') ?> | <?php edit_post_link('Edit', '', ' | '); ?>  <?php comments_popup_link('No Comments &#187;', '1 Comment &#187;', '% Comments &#187;'); ?></p>
								</div>
							</div>
						</div>
					<?php endwhile;
					restore_current_blog();
				}
				else {
					printf(__('None of your friends have written any posts about %s.
					You can %s.
					You can also find more friends using the %s.'),
					$refs->get_string(),
					"<a href='$write_url'>" . __('write your own post') . "</a>",
					"<a href='$mem_dir_url'>" . __('members directory') . "</a>");
				}
			}
			else {
				printf(__('This menu shows you any blog posts written by your friends about this passage.
				You don\'t currently have any friends. That\'s okay, because you can find some friends using our %s.'),
				"<a href='$mem_dir_url'>" . __("members directory") . "</a>");
			}
		}
		else {
			_e('This widget requires BuddyPress.');
		}

		?>
			</div>
		</div>
		<?php
		echo $after_widget;
	}
}

class BP_Bible_Notes_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(false, __('User Bible Notes'));
	}

	public function widget($args, $instance) {
		extract($args);

		if ('passage' == $instance['type']) {
			$refs = bp_bible_the_refs();
			if (empty($instance['title'])) $instance['title'] = __('My Notes for %s');
			$instance['title'] = sprintf($instance['title'], $refs->get_string());
		}
		else {
			$refs = NULL;
			if (empty($instance['title'])) $instance['title'] = __('My Bible Notes');
		}

		if (1 > $max) $max = 10;

		echo $before_widget . $before_title . $instance['title'] . $after_title;

		global $user_ID;

		if (empty($user_ID)) $content = "<p>" . BiblefoxSite::loginout() . __(' to track the Bible passages you read.</p>');
		else {
			$notes = BfoxNotes::get_notes();

			if ('table' == $instance['style']) {
				$notes_table = new BfoxHtmlTable("class='widefat'");
				$notes_table->add_header_row('', 3, 'Modified', 'Note', 'Scriptures Referenced');
				foreach ($notes as $note) {
					$note_refs = $note->get_refs();
					$note_ref_str = $note_refs->get_string();

					$notes_table->add_row('', 3,
						$note->get_modified(),
						$note->get_title() . " (<a href='" . BfoxBible::edit_note_url($note->id, $this->url) . "'>edit</a>)",
						"<a href='" . BfoxQuery::ref_url($note_ref_str) . "'>$note_ref_str</a>");
				}
				$notes_table->add_row('', 1, array("<a href='" . BfoxBible::edit_note_url(0, $this->url) . "'>Add New Note</a>", "colspan='3'"));

				$content = $notes_table->content();
			}
			else {
				$list = new BfoxHtmlList();

				foreach ($notes as $note) $list->add("<a href='" . BfoxBible::edit_note_url($note->id, $this->url) . "'>" . $note->get_title() . "</a>");

				$content = $list->content();
			}

			// Get the current not from the user options
			$note = BfoxNotes::get_note(get_user_option(BfoxBible::user_option_note_id));
			if (empty($note->id)) $note->set_content($this->refs->get_string());

			if (empty($note->id)) $edit_header = __('Create a Note');
			else $edit_header = __('Edit Note');

			echo "<h3>$edit_header</h3>\n";
			//$this->edit_note($note);
		}

		echo $content . $after_widget;
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['ref_name'] = $new_instance['ref_name'];
		$instance['style'] = $new_instance['style'];
		$instance['type'] = $new_instance['type'];

		return $instance;
	}

	public function form($instance) {
		$title = esc_attr($instance['title']);
		if ( !$number = (int) $instance['number'] )
			$number = 10;
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number to show:'); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

		<p>
			<label for="<?php echo $this->get_field_id('ref_name'); ?>"><?php _e( 'Bible References:' ); ?></label>
			<select name="<?php echo $this->get_field_name('ref_name'); ?>" id="<?php echo $this->get_field_id('ref_name'); ?>" class="widefat">
				<option value="<?php echo BibleMeta::name_normal ?>"<?php selected( $instance['ref_name'], BibleMeta::name_normal ); ?>><?php _e('Normal'); ?></option>
				<option value="<?php echo BibleMeta::name_short ?>"<?php selected( $instance['ref_name'], BibleMeta::name_short ); ?>><?php _e('Short'); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('style'); ?>"><?php _e( 'Display Style:' ); ?></label>
			<select name="<?php echo $this->get_field_name('style'); ?>" id="<?php echo $this->get_field_id('style'); ?>" class="widefat">
				<option value="list"<?php selected( $instance['style'], 'list' ); ?>><?php _e('List'); ?></option>
				<option value="table"<?php selected( $instance['style'], 'table' ); ?>><?php _e('Table'); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('type'); ?>"><?php _e( 'History Type:' ); ?></label>
			<select name="<?php echo $this->get_field_name('type'); ?>" id="<?php echo $this->get_field_id('type'); ?>" class="widefat">
				<option value="all"<?php selected( $instance['type'], 'all' ); ?>><?php _e('All History'); ?></option>
				<option value="passage"<?php selected( $instance['type'], 'passage' ); ?>><?php _e('Passage History'); ?></option>
			</select>
		</p>
		<?php
    }
}

class BP_Bible_Options_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(false, __('Bible Options'));
	}

	public function widget($args, $instance) {
		extract($args);

		if (empty($instance['title'])) $instance['title'] = __('Bible Options');

		echo $before_widget . $before_title . $instance['title'] . $after_title;

		$table = new BfoxHtmlList();
		$table->add(bfox_reader_check_option('jesus', __('Show Jesus\' words in red')));
		$table->add(bfox_reader_check_option('paragraphs', __('Display verses as paragraphs')));
		$table->add(bfox_reader_check_option('verse_nums', __('Hide verse numbers')));
		$table->add(bfox_reader_check_option('footnotes', __('Hide footnote links')));

		echo $table->content() . $after_widget;
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['ref_name'] = $new_instance['ref_name'];
		$instance['style'] = $new_instance['style'];
		$instance['type'] = $new_instance['type'];

		return $instance;
	}

	public function form($instance) {
		$title = esc_attr($instance['title']);
		if ( !$number = (int) $instance['number'] )
			$number = 10;
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number to show:'); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

		<p>
			<label for="<?php echo $this->get_field_id('ref_name'); ?>"><?php _e( 'Bible References:' ); ?></label>
			<select name="<?php echo $this->get_field_name('ref_name'); ?>" id="<?php echo $this->get_field_id('ref_name'); ?>" class="widefat">
				<option value="<?php echo BibleMeta::name_normal ?>"<?php selected( $instance['ref_name'], BibleMeta::name_normal ); ?>><?php _e('Normal'); ?></option>
				<option value="<?php echo BibleMeta::name_short ?>"<?php selected( $instance['ref_name'], BibleMeta::name_short ); ?>><?php _e('Short'); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('style'); ?>"><?php _e( 'Display Style:' ); ?></label>
			<select name="<?php echo $this->get_field_name('style'); ?>" id="<?php echo $this->get_field_id('style'); ?>" class="widefat">
				<option value="list"<?php selected( $instance['style'], 'list' ); ?>><?php _e('List'); ?></option>
				<option value="table"<?php selected( $instance['style'], 'table' ); ?>><?php _e('Table'); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('type'); ?>"><?php _e( 'History Type:' ); ?></label>
			<select name="<?php echo $this->get_field_name('type'); ?>" id="<?php echo $this->get_field_id('type'); ?>" class="widefat">
				<option value="all"<?php selected( $instance['type'], 'all' ); ?>><?php _e('All History'); ?></option>
				<option value="passage"<?php selected( $instance['type'], 'passage' ); ?>><?php _e('Passage History'); ?></option>
			</select>
		</p>
		<?php
    }
}

class BP_Bible_History_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(false, __('User Bible History'));
	}

	public function widget($args, $instance) {
		extract($args);

		if ('passage' == $instance['type']) {
			$refs = bp_bible_the_refs();
			if (empty($instance['title'])) $instance['title'] = __('My History for %s');
			$instance['title'] = sprintf($instance['title'], $refs->get_string());
		}
		else {
			$refs = NULL;
			if (empty($instance['title'])) $instance['title'] = __('My Bible History');
		}

		if (1 > $max) $max = 10;

		echo $before_widget . $before_title . $instance['title'] . $after_title;

		global $user_ID;

		if (empty($user_ID)) $content = "<p>" . BiblefoxSite::loginout() . __(' to track the Bible passages you read.</p>');
		else {
			$history = BfoxHistory::get_history($instance['number'], 0, $refs);

			if ('table' == $instance['style']) {
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

				foreach ($history as $event) $list->add($event->ref_link($instance['ref_name']));

				$content = $list->content();
			}
		}

		echo $content . $after_widget;
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['ref_name'] = $new_instance['ref_name'];
		$instance['style'] = $new_instance['style'];
		$instance['type'] = $new_instance['type'];

		return $instance;
	}

	public function form($instance) {
		$title = esc_attr($instance['title']);
		if ( !$number = (int) $instance['number'] )
			$number = 10;
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number to show:'); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

		<p>
			<label for="<?php echo $this->get_field_id('ref_name'); ?>"><?php _e( 'Bible References:' ); ?></label>
			<select name="<?php echo $this->get_field_name('ref_name'); ?>" id="<?php echo $this->get_field_id('ref_name'); ?>" class="widefat">
				<option value="<?php echo BibleMeta::name_normal ?>"<?php selected( $instance['ref_name'], BibleMeta::name_normal ); ?>><?php _e('Normal'); ?></option>
				<option value="<?php echo BibleMeta::name_short ?>"<?php selected( $instance['ref_name'], BibleMeta::name_short ); ?>><?php _e('Short'); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('style'); ?>"><?php _e( 'Display Style:' ); ?></label>
			<select name="<?php echo $this->get_field_name('style'); ?>" id="<?php echo $this->get_field_id('style'); ?>" class="widefat">
				<option value="list"<?php selected( $instance['style'], 'list' ); ?>><?php _e('List'); ?></option>
				<option value="table"<?php selected( $instance['style'], 'table' ); ?>><?php _e('Table'); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('type'); ?>"><?php _e( 'History Type:' ); ?></label>
			<select name="<?php echo $this->get_field_name('type'); ?>" id="<?php echo $this->get_field_id('type'); ?>" class="widefat">
				<option value="all"<?php selected( $instance['type'], 'all' ); ?>><?php _e('All History'); ?></option>
				<option value="passage"<?php selected( $instance['type'], 'passage' ); ?>><?php _e('Passage History'); ?></option>
			</select>
		</p>
		<?php
    }
}

class BP_Bible_Toc_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(false, __('Bible Table of Contents'));
	}

	public function widget($args, $instance) {
		extract($args);

		if (empty($instance['title'])) $instance['title'] = __('%s - Table of Contents');
		$books = bp_bible_the_books();

		foreach ($books as $book) {
			$book_name = BibleMeta::get_book_name($book);
			$end_chapter = BibleMeta::end_verse_max($book);

			$title = sprintf($instance['title'], $book_name);
			echo $before_widget . $before_title . $title . $after_title;
			?>
			<ul class='flat_toc'>
			<?php for ($ch = BibleMeta::start_chapter; $ch <= $end_chapter; $ch++): ?>
				<li><a href='<?php echo BfoxQuery::ref_url("$book_name $ch") ?>'><?php echo $ch ?></a></li>
			<?php endfor ?>
			</ul>
			<?php
			echo $after_widget;
		}

	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);

		return $instance;
	}

	public function form($instance) {
		$title = esc_attr($instance['title']);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>
		<?php
    }
}

class BP_Bible_Passage_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(false, __('Bible Passage'));
	}

	public function widget($args, $instance) {
		extract($args);

		$refs = BfoxRefs('John 3');

		if (empty($instance['title'])) $instance['title'] = __('My Bible History');
		if (1 > $max) $max = 10;

		echo $before_widget . $before_title . $instance['title'] . $after_title;

		global $user_ID;

		if (empty($user_ID)) $content = "<p>" . BiblefoxSite::loginout() . __(' to track the Bible passages you read.</p>');
		else {
			$history = BfoxHistory::get_history($instance['number']);
			$list = new BfoxHtmlList();

			foreach ($history as $event) $list->add($event->ref_link($instance['ref_name']));

			$content = $list->content();
		}

		echo $content . $after_widget;
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['ref_name'] = $new_instance['ref_name'];

		return $instance;
	}

	public function form($instance) {
		$title = esc_attr($instance['title']);
		if ( !$number = (int) $instance['number'] )
			$number = 10;
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number to show:'); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

		<p>
			<label for="<?php echo $this->get_field_id('ref_name'); ?>"><?php _e( 'Bible References:' ); ?></label>
			<select name="<?php echo $this->get_field_name('ref_name'); ?>" id="<?php echo $this->get_field_id('ref_name'); ?>" class="widefat">
				<option value="<?php echo BibleMeta::name_normal ?>"<?php selected( $instance['ref_name'], BibleMeta::name_normal ); ?>><?php _e('Normal'); ?></option>
				<option value="<?php echo BibleMeta::name_short ?>"<?php selected( $instance['ref_name'], BibleMeta::name_short ); ?>><?php _e('Short'); ?></option>
			</select>
		</p>
		<?php
    }
}

class BP_Bible_CurrentReadings_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(false, __('Current Bible Readings'));
	}

	public static function get_plans() {
		global $user_ID;

		$plans = array();

		$plans = BfoxPlans::get_plans(array(), $user_ID, BfoxPlans::user_type_user, 'is_finished=0');

		$earliest = '';
		foreach($plans as $plan) {
			$start_time = $plan->start_date();
			if (empty($earliest) || ($start_time < $earliest)) $earliest = $start_time;
		}

		if (!empty($earliest)) {
			$history_array = BfoxHistory::get_history(0, $earliest, NULL, TRUE);
			foreach ($plans as &$plan) $plan->set_history($history_array);
		}

		return $plans;
	}

	public function widget($args, $instance) {
		extract($args);

		if (empty($instance['title'])) $instance['title'] = __('My Current Readings');

		echo $before_widget . $before_title . $instance['title'] . $after_title;
		global $user_ID;

		$plans = array();
		if (!empty($user_ID)) {
			$plans = self::get_plans();

			if (empty($plans)) $content = __('<p>You are not subscribed to any reading plans.</p>');
			else {
				$list = new BfoxHtmlList();

				foreach ($plans as $plan) if ($plan->is_current()) {
					// Show any unread readings before the current reading
					// And any readings between the current reading and the first unread reading after it
					foreach ($plan->readings as $reading_id => $reading) {
						$unread = $plan->get_unread($reading);
						$is_unread = $unread->is_valid();

						// If the passage is unread or current, add it
						if ($is_unread || ($reading_id >= $plan->current_reading_id)) {
							$ref_str = $plan->readings[$reading_id]->get_string($instance['ref_name']);
							$url = Biblefox::ref_url($ref_str);

							if (!$is_unread) $finished = " class='finished'";
							else $finished = '';

							$list->add(BfoxUtility::nice_date($plan->time($reading_id)) . ": <a href='$url'$finished>$ref_str</a>", '', $plan->date($reading_id));
						}
						// Break after the first unread reading > current_reading
						if ($is_unread && ($reading_id > $plan->current_reading_id)) break;
					}
				}

				$content = $list->content(TRUE, 0, $instance['number']);
			}

			$content .= "<p><a href='" . BfoxQuery::page_url(BfoxQuery::page_plans) . "'>" . __('Edit Reading Plans') . "</a></p>";
		}
		else $content = "<p>" . BiblefoxSite::loginout() . __(' to see the current readings for your reading plans.</p>');

		echo $content . $after_widget;
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['ref_name'] = $new_instance['ref_name'];

		return $instance;
	}

	public function form($instance) {
		$title = esc_attr($instance['title']);
		if ( !$number = (int) $instance['number'] )
			$number = 0;
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of readings to show:'); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

		<p>
			<label for="<?php echo $this->get_field_id('ref_name'); ?>"><?php _e( 'Bible References:' ); ?></label>
			<select name="<?php echo $this->get_field_name('ref_name'); ?>" id="<?php echo $this->get_field_id('ref_name'); ?>" class="widefat">
				<option value="<?php echo BibleMeta::name_normal ?>"<?php selected( $instance['ref_name'], BibleMeta::name_normal ); ?>><?php _e('Normal'); ?></option>
				<option value="<?php echo BibleMeta::name_short ?>"<?php selected( $instance['ref_name'], BibleMeta::name_short ); ?>><?php _e('Short'); ?></option>
			</select>
		</p>
		<?php
    }
}

class BP_Bible_Tools_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(false, __('Personal Bible Tools'));
	}

	public function widget($args, $instance) {
		extract($args);

		if ('passage' == $instance['type']) {
			$refs = bp_bible_the_refs();
			if (empty($instance['title'])) $instance['title'] = __('My Bible Tools');
			$instance['title'] = sprintf($instance['title'], $refs->get_string());
		}
		else {
			$refs = NULL;
			if (empty($instance['title'])) $instance['title'] = __('My Bible History');
		}

		if (1 > $max) $max = 10;

		echo $before_widget . $before_title . $instance['title'] . $after_title;

		global $user_ID;

		if (empty($user_ID)) $content = "<p>" . BiblefoxSite::loginout() . __(' to track the Bible passages you read.</p>');
		else {
			$history = BfoxHistory::get_history($instance['number'], 0, $refs);

			if ('table' == $instance['style']) {
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

				foreach ($history as $event) $list->add($event->ref_link($instance['ref_name']));

				$content = $list->content();
			}
		}

		echo $content . $after_widget;
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['ref_name'] = $new_instance['ref_name'];
		$instance['style'] = $new_instance['style'];
		$instance['type'] = $new_instance['type'];

		return $instance;
	}

	public function form($instance) {
		$title = esc_attr($instance['title']);
		if ( !$number = (int) $instance['number'] )
			$number = 10;
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number to show:'); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

		<p>
			<label for="<?php echo $this->get_field_id('ref_name'); ?>"><?php _e( 'Bible References:' ); ?></label>
			<select name="<?php echo $this->get_field_name('ref_name'); ?>" id="<?php echo $this->get_field_id('ref_name'); ?>" class="widefat">
				<option value="<?php echo BibleMeta::name_normal ?>"<?php selected( $instance['ref_name'], BibleMeta::name_normal ); ?>><?php _e('Normal'); ?></option>
				<option value="<?php echo BibleMeta::name_short ?>"<?php selected( $instance['ref_name'], BibleMeta::name_short ); ?>><?php _e('Short'); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('style'); ?>"><?php _e( 'Display Style:' ); ?></label>
			<select name="<?php echo $this->get_field_name('style'); ?>" id="<?php echo $this->get_field_id('style'); ?>" class="widefat">
				<option value="list"<?php selected( $instance['style'], 'list' ); ?>><?php _e('List'); ?></option>
				<option value="table"<?php selected( $instance['style'], 'table' ); ?>><?php _e('Table'); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('type'); ?>"><?php _e( 'History Type:' ); ?></label>
			<select name="<?php echo $this->get_field_name('type'); ?>" id="<?php echo $this->get_field_id('type'); ?>" class="widefat">
				<option value="all"<?php selected( $instance['type'], 'all' ); ?>><?php _e('All History'); ?></option>
				<option value="passage"<?php selected( $instance['type'], 'passage' ); ?>><?php _e('Passage History'); ?></option>
			</select>
		</p>
		<?php
    }
}

function bp_bible_widgets_init() {
	register_widget('BP_Bible_History_Widget');
	register_widget('BP_Bible_Toc_Widget');
	register_widget('BP_Bible_CurrentReadings_Widget');
	register_widget('BP_Bible_FriendsPosts_Widget');
	register_widget('BP_Bible_Notes_Widget');
	register_widget('BP_Bible_Options_Widget');
}

function bp_bible_register_sidebars() {
	register_sidebars(1,
		array(
			'name' => 'bible-passage-side',
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
	        'after_widget' => '</div>',
	        'before_title' => '<h2 class="widgettitle">',
	        'after_title' => '</h2>'
		)
	);
	register_sidebars(1,
		array(
			'name' => 'bible-passage-bottom',
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
	        'after_widget' => '</div>',
	        'before_title' => '<h2 class="widgettitle">',
	        'after_title' => '</h2>'
		)
	);
}

add_action('widgets_init', 'bp_bible_widgets_init');
add_action('init', 'bp_bible_register_sidebars');


?>