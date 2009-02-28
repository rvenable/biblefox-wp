<?php

class Bible
{
	const page_passage = 'passage';
	const page_commentary = 'commentary';
	const page_search = 'search';
	const page_history = 'history';

	const var_page = 'bible_page';
	const var_translation = 'trans_id';
	const var_reference = 'bible_ref';
	const var_search = 'bible_search';

	public static function output_quick_press()
	{
		global $user_ID;
		// This is an imitation of the QuickPress code from wp_dashboard_quick_press()
		$user_blogs = get_blogs_of_user($user_ID);
		pre($blogs);

		$blogs = array();
		foreach ($user_blogs as $blog_id => $blog)
		{
			switch_to_blog($blog_id);
			if (current_user_can('edit_posts'))
			{
				if (current_user_can('publish_posts'))
					$blog->publish_string = __('Publish');
				else
					$blog->publish_string = __('Submit for Review');

				$blog->quick_press_url = clean_url(admin_url('post.php'));

				$blogs[] = $blog;
			}
			restore_current_blog();
		}

		?>
		<div class="biblebox">
			<div class="box_head">Write a commentary post</div>
			<?php if (isset($blogs[0])): ?>
			<form name="post" action="<?php echo $blogs[0]->quick_press_url ?>" method="post" id="quick_press">
				<div class="box_inside">
					<div class="quick_write_input">
						<h4 id="quick-post-blog"><label for="blog"><?php _e('Blog') ?></label></h4>
						<select name="blog_id" id="blog" tabindex="1" onchange="eval(this.value)">
						<?php foreach ($blogs as $index => $blog): ?>
							<option value="<?php echo "bfox_quick_write_set_blog('$blog->quick_press_url', '$blog->publish_string')" ?>" <?php if (0 == $index) echo 'selected' ?>><?php echo $blog->blogname ?></option>
						<?php endforeach; ?>
						</select>
					</div>
					<div class="quick_write_input">
						<h4 id="quick-post-title"><label for="title"><?php _e('Title') ?></label></h4>
						<input type="text" name="post_title" id="title" tabindex="1" value="" />
					</div>
					<div class="quick_write_input">
						<h4 id="content-label"><label for="content"><?php _e('Content') ?></label></h4>
						<textarea name="content" id="content" class="mceEditor" rows="3" cols="15" tabindex="2"></textarea>
					</div>
					<div class="quick_write_input">
						<h4><label for="tags-input"><?php _e('Tags') ?></label></h4>
						<input type="text" name="tags_input" id="tags-input" tabindex="3" value="" />
					</div>
				</div>
				<div class="box_menu">
					<input type="hidden" name="action" id="quickpost-action" value="post-quickpress-save" />
					<input type="hidden" name="quickpress_post_ID" value="0" />
					<?php wp_nonce_field('add-post'); ?>
					<input type="submit" name="save" id="save-post" class="button" tabindex="4" value="<?php _e('Save Draft'); ?>" />
					<input type="reset" value="<?php _e( 'Cancel' ); ?>" class="button" />
					<span class="box_right">
					<input type="submit" name="publish" id="publish" accesskey="p" tabindex="5" class="button-primary" value="<?php echo $blogs[0]->publish_string ?>" />
					</span>
					<br class="clear" />
				</div>
			</form>
			<?php else: ?>
			<div class="box_inside">
				You have no blogs to post to.
			</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

	// TODO3: get rid of this function
	function bfox_get_ref_content(BibleRefs $refs, $version_id = -1, $id_text_begin = '<em class="bible-verse-id">', $id_text_end = '</em> ')
	{
		return $refs->get_scripture();
	}

	/**
	 * Returns an output string with scripture text for the Scripture Quick View
	 *
	 * Includes a Table of Contents at the bottom.
	 *
	 * @param BibleRefs $refs
	 * @param unknown_type $limit The limit of how many chapters can be displayed in full
	 * @return string Scripture Text Output (with TOC)
	 */
	function bfox_get_ref_content_quick(BibleRefs $refs, $limit = 5)
	{
		$is_full = FALSE;

		// Only get the scripture text output if we haven't exceeded the chapter limit
		$num_chapters = $refs->get_num_chapters();
		if ($limit >= $num_chapters)
		{
			$content = bfox_get_ref_content($refs);
			$content .= '<hr/>';
			$is_full = TRUE;
		}

		// Add the Table of Contents to the end of the output
		$content .= $refs->get_toc($is_full);

		return $content;
	}

	// Function for echoing scripture
	function bfox_echo_scripture($version_id, BibleRefs $ref)
	{
		$content = bfox_get_ref_content($ref, $version_id);
		echo $content;
	}

	function bfox_get_posts_equation_for_refs(BibleRefs $refs, $table_name = '', $verse_begin = 'verse_begin', $verse_end = 'verse_end')
	{
		if (empty($table_name)) $table_name = $GLOBALS['wpdb']->bfox_bible_ref;
		$begin = $table_name . '.' . $verse_begin;
		$end = $table_name . '.' . $verse_end;
		return $refs->sql_where2($begin, $end);
	}

	function bfox_get_posts_for_refs(BibleRefs $refs)
	{
		global $wpdb, $blog_id;
		$table_name = $wpdb->bfox_bible_ref;
		$posts_table = $wpdb->posts;

		// TODO3: This check shouldn't be here permanently
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			return array();

		$posts = array();
		$equation = bfox_get_posts_equation_for_refs($refs, $table_name);
		if ('' != $equation)
			$posts = $wpdb->get_results("
				SELECT $posts_table.*
				FROM $table_name
				INNER JOIN $posts_table
				ON $table_name.post_id = $posts_table.ID
				WHERE $posts_table.post_type = 'post'
				AND $equation");

		return $posts;
	}

	function bfox_get_post_bible_refs($post_id = 0)
	{
		global $wpdb;
		$table_name = $wpdb->bfox_bible_ref;

		// If the table does not exist then there are obviously no bible references
		if ((0 != $post_id) && ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name))
		{
			$select = $wpdb->prepare("SELECT verse_begin, verse_end FROM $table_name WHERE post_id = %d ORDER BY ref_order ASC", $post_id);
			$sets = $wpdb->get_results($select, ARRAY_N);
		}

		return (RefManager::get_from_sets($sets));
	}

	function bfox_get_ref_menu(BibleRefs $refs, $header = true, $scripture_links = NULL)
	{
		$home_dir = get_option('home');
		$admin_dir = $home_dir . '/wp-admin';
		$refStr = $refs->get_string();

		// Use the current_url param if it is passed
		if (isset($scripture_links['current_url'])) $page_url = $scripture_links['current_url'];
		else $page_url = $refs->get_url();

		$menu = '';

		// Add bible tracking data
		global $user_ID;
		get_currentuserinfo();
		if (0 < $user_ID)
		{
			global $bfox_history;
			$menu .= '<small>';
			if ($header) $menu .= $bfox_history->get_dates_str($refs, false) . '<br/>';
			$menu .= $bfox_history->get_dates_str($refs, true);
			$menu .= ' (<a href="' . $page_url . '&bfox_action=mark_read">Mark as read</a>)';
			if ($header) $menu .= '<br/><a href="http://www.biblegateway.com/passage/?search=' . $refStr . '&version=31" target="_blank">Read on BibleGateway</a>';
			$menu .= '</small>';

			$write_link = $refs->get_link('Write about this passage', 'write');
		}
		else $menu .= '<small><a href="' . $home_dir . '/wp-login.php">Login</a> to track your bible reading</small>';

		// Scripture navigation links
		if (is_null($scripture_links))
		{
			$next_refs = RefManager::get_from_sets($refs->get_sets());
			$previous_refs = RefManager::get_from_sets($refs->get_sets());
			$next_refs->increment(1);
			$previous_refs->increment(-1);

			$scripture_links = array();
			$scripture_links['next'] = $next_refs->get_link($next_refs->get_string() . ' >');
			$scripture_links['previous'] = $previous_refs->get_link('< ' . $previous_refs->get_string());
		}

		$menu .= '<table width="100%"><tr>';
		$menu .= '<td align="left" width="33%">' . $scripture_links['previous'] . '</td>';
		$menu .= '<td align="center" width="33%">' . $write_link . '</td>';
		$menu .= '<td align="right" width="33%">' . $scripture_links['next'] . '</a></td>';
		$menu .= '</tr>';
		$menu .= '</table>';

		return $menu;
	}

	function bfox_get_next_refs(BibleRefs $refs, $action)
	{
		// Determine if we need to modify the refs using a next/previous action
		$next_factor = 0;
		if ('next' == $action) $next_factor = 1;
		else if ('previous' == $action) $next_factor = -1;
		else if ('mark_read' == $action)
		{
			$next_factor = 0;
			global $bfox_history;
			$bfox_history->update($refs, true);
		}

		// Modify the refs for the next factor
		if (0 != $next_factor) $refs->increment($next_factor);

		return $refs;
	}

	function bfox_ref_quick_view_menu(BibleRefs $ref)
	{
		$next_refs = RefManager::get_from_sets($ref->get_sets());
		$previous_refs = RefManager::get_from_sets($ref->get_sets());
		$next_refs->increment(1);
		$previous_refs->increment(-1);

		$scripture_links = array();
		$next_link = '<input type="button" class="button" onclick="bible_text_request(\'' . $next_refs->get_string() . '\')" value="' . $next_refs->get_string() . ' >">';
		$previous_link = '<input type="button" class="button" onclick="bible_text_request(\'' . $previous_refs->get_string() . '\')" value="< ' . $previous_refs->get_string() . '">';
		$tag_link = '<input type="button" class="button" id="add-bible-ref" onclick="bible_ref_flush_to_text()" bible_ref="' . $ref->get_string() . '" value="Tag ' . $ref->get_string() . '">';

		$menu = '<table width="100%"><tr>';
		$menu .= '<td align="left" width="33%">' . $previous_link . '</td>';
		$menu .= '<td align="center" width="33%">' . $tag_link . '</td>';
		$menu .= '<td align="right" width="33%">' . $next_link . '</a></td>';
		$menu .= '</tr>';
		$menu .= '</table>';
		return $menu;
	}

	/*
	 AJAX function for sending the bible text
	 */
	function bfox_ajax_send_bible_text()
	{
		global $bfox_quicknote, $bfox_links;

		// All the links on the quick view should link to the quick view by default
		$bfox_links->set_ref_context('quick');

		$ref_str = $_POST['ref_str'];
		$ref = RefManager::get_from_str($ref_str);
		$bfox_quicknote->set_biblerefs($ref);
		sleep(1);

		// If it is not valid, give the user an error message
		// Otherwise give the user the content they were looking for
		if (!$ref->is_valid())
		{
			$content = 'Invalid bible reference: ' . $ref_str;
		}
		else
		{
			$ref_str = $ref->get_string();
			$menu = addslashes(bfox_ref_quick_view_menu($ref));
			$content = addslashes(bfox_get_ref_content_quick($ref));
		}

		$script = "bfox_quick_view_loaded('$ref_str', '$content', '$menu');";
		die($script);
	}
	add_action('wp_ajax_bfox_ajax_send_bible_text', 'bfox_ajax_send_bible_text');

?>
