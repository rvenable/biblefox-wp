<?php

class BP_Bible_FriendsPosts_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(false, 'Bible Friends\' Posts Widget');
	}

	public function widget($args, $instance) {
		extract($args);

		if (empty($instance['title'])) $instance['title'] = __('My Friends\' Blog Posts');
		echo $before_widget . $before_title . $instance['title'] . $after_title;

		bp_bible_friends_posts();

		echo $after_widget;
	}
}

class BP_Bible_WritePost_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(false, __('Bible: Write a Post'));
	}

	public function widget($args, $instance) {
		extract($args);

		if (empty($instance['title'])) $instance['title'] = __('Share your thoughts about %s');

		$refs = bp_get_bible_refs();
		$ref_str = $refs->get_string();
		$instance['title'] = sprintf($instance['title'], $ref_str);

		echo $before_widget . $before_title . $instance['title'] . $after_title;

		bp_bible_post_form();

		echo $after_widget;
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

		bp_bible_options();

		echo $after_widget;
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
			$refs = bp_get_bible_refs();
			if (empty($instance['title'])) $instance['title'] = __('My History for %s');
			$instance['title'] = sprintf($instance['title'], $refs->get_string());
		}
		else {
			$refs = NULL;
			if (empty($instance['title'])) $instance['title'] = __('My Bible History');
		}

		echo $before_widget . $before_title . $instance['title'] . $after_title;

		$instance['limit'] = $instance['number'];
		bp_bible_history_list($instance);

		echo $after_widget;
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

// TODO: Delete
class BP_Bible_Toc_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(false, __('Bible Table of Contents'));
	}

	public function widget($args, $instance) {
		extract($args);

		if (empty($instance['title'])) $instance['title'] = __('%s - Table of Contents');

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

		if (empty($user_ID)) $content = "<p>" . bp_bible_loginout() . __(' to track the Bible passages you read.</p>');
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

	public function widget($args, $instance) {
		extract($args);

		if (empty($instance['title'])) $instance['title'] = __('My Recent Readings');

		echo $before_widget . $before_title . $instance['title'] . $after_title;

		$instance['max'] = $instance['number'];
		bp_bible_current_readings($instance);

		echo $after_widget;
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
			$refs = bp_get_bible_refs();
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

		if (empty($user_ID)) $content = "<p>" . bp_bible_loginout() . __(' to track the Bible passages you read.</p>');
		else {
			$history = BfoxHistory::get_history($instance['number'], 0, $refs);

			if ('table' == $instance['style']) {
				$table = new BfoxHtmlTable("class='widefat'");

				foreach ($history as $event) $table->add_row('', 4,
					$event->desc(),
					$event->ref_link(),
					BfoxUtility::nice_date($event->time),
					date('g:i a', $event->time));

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
	register_widget('BP_Bible_Options_Widget');
	register_widget('BP_Bible_WritePost_Widget');
}

// TODO: delete these
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