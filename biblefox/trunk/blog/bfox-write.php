<?php

	/**
	 * Returns a BibleRefs created from $_GET[BfoxBlog::var_bible_ref] or from the saved bible refs for the current post
	 *
	 * @return BibleRefs
	 */
	function bfox_get_post_refs_from_input()
	{
		// TODO3: This function should probably be somewhere else
		$refs = new BibleRefs($_GET[BfoxBlog::var_bible_ref]);

		if (!$refs->is_valid())
		{
			global $post_ID;
			$refs = BfoxPosts::get_post_refs($post_ID);
	 	}

	 	return $refs;
	}

	/**
	 * Function for creating the form field to edit a post's bible references
	 *
	 */
	function bfox_form_edit_scripture_tags()
	{
		// TODO3: This function should probably be somewhere else
		$refs = bfox_get_post_refs_from_input();
		$ref_str = $refs->get_string();

		// Create the form
	?>
<p>Adding scripture tags is a simple way to organize your posts around Bible passages.<br/>
For instance, if this post is about Genesis 1, add Genesis 1 as a scripture tag. Once it is tagged, whenever you read Genesis 1 you will see this post!</p>

<p><strong>Current Scripture Tags:</strong></p>
<input type="text" name="<?php echo BfoxBlog::var_bible_ref ?>" id="bible-ref-list" class="hide-if-js" size="50" value="<?php echo attribute_escape($ref_str); ?>" />
<p><span id="bible-ref-checklist"></span></p>

	<?php

	}

	/**
	 * Function for creating the form displaying the scripture quick view
	 *
	 */
	function bfox_form_scripture_quick_view()
	{
		global $post_ID;
		$refs = BfoxPosts::get_post_refs($post_ID);

		if (!empty($_REQUEST[BfoxBlog::var_bible_ref]))
		{
			$hidden_refs = new BibleRefs($_REQUEST[BfoxBlog::var_bible_ref]);
			if ($hidden_refs->is_valid())
			{
				echo "<input id='hidden_refs' type='hidden' name='" . BfoxBlog::var_bible_ref . "' value='" . $hidden_refs->get_string(BibleMeta::name_short) . "'/>";
				$refs->add_seqs($hidden_refs->get_seqs());
			}
		}
		$is_valid = $refs->is_valid();
		if ($is_valid) $ref_str = $refs->get_string();

		// Create the form
		?>
		<?php if (empty($ref_str)): ?>
			<p>This post currently has no bible references.</p>
		<?php else: ?>
			<p>This post is currently referencing: <?php echo BfoxBlog::ref_link_ajax($ref_str) ?></p>
		<?php endif ?>
			<p>Add more bible references by typing them into the post, or adding them to the post tags.</p>
			<div class="hide-if-no-js">
				<h4>Quick Scripture Viewer</h4>
				<input type="text" name="new-bible-ref" id="new-bible-ref" size="16" value="" />
				<input type="button" class="button" id="view-bible-ref" value="View Scripture" tabindex="3" />
				<span class="howto"><?php _e('Type a bible reference (ie. "gen 1")'); ?></span>
				<br/>
			</div>

			<h4 id="bible-text-progress"><span id='bible_progress'><?php if ($is_valid) echo 'Viewing'?></span> <span id='bible_view_ref'><?php if ($is_valid) echo $refs->get_string(BibleMeta::name_short) ?></span></h4>
			<input type="hidden" name="bible-request-url" id="bible-request-url" value="<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" />
			<div id="bible-text"><?php if ($is_valid) echo bfox_get_ref_content_quick($refs) ?></div>
		<?php
	}

?>