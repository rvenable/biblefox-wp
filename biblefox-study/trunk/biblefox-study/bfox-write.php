<?php
	
	function bfox_create_bible_ref_table()
	{
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		$table_name = 
		$sql = "CREATE TABLE " . BFOX_TABLE_BIBLE_REF . " (
				post_id int,
				ref_order int,
				verse_begin int,
				verse_end int
			);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	function bfox_set_post_bible_refs($post_id, BibleRefs $refs)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;
		$id = 1;

		// If the table doesn't exist create it, otherwise remove any previous entries for this post
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			bfox_create_bible_ref_table();
		else
			$wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE post_id = %d", $post_id));

		$ref_order = 0;
		$sets = $refs->get_sets();
		foreach ($sets as $range)
		{
			$insert = $wpdb->prepare("INSERT INTO $table_name (post_id, ref_order, verse_begin, verse_end) VALUES (%d, %d, %d, %d)", $post_id, $ref_order, $range[0], $range[1]);
			$wpdb->query($insert);
			$ref_order++;
		}
	}

	/**
	 * Returns a BibleRefs created from $_GET['bible_ref'] or from the saved bible refs for the current post
	 *
	 * @return BibleRefs
	 */
	function bfox_get_post_refs_from_input()
	{
		// TODO3: This function should probably be somewhere else
		$refs = new BibleRefs;
		if (isset($_GET['bible_ref']))
			$refs->push_string($_GET['bible_ref']);
		
		if (!$refs->is_valid())
		{
			global $post_ID;
			$refs = bfox_get_post_bible_refs($post_ID);
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
		$refStr = $refs->get_string();

		// Create the form
	?>
<p>Adding scripture tags is a simple way to organize your posts around Bible passages.<br/>
For instance, if this post is about Genesis 1, add Genesis 1 as a scripture tag. Once it is tagged, whenever you read Genesis 1 you will see this post!</p>

<p><strong>Current Scripture Tags:</strong></p>
<input type="text" name="bible_ref" id="bible-ref-list" class="hide-if-js" size="50" autocomplete="off" value="<?php echo attribute_escape($refStr); ?>" />
<p><div id="bible-ref-checklist"></div></p>

	<?php

	}
	
	/**
	 * Function for creating the form displaying the scripture quick view
	 *
	 */
	function bfox_form_scripture_quick_view()
	{
		// TODO3: This function should probably be somewhere else
		$refs = bfox_get_post_refs_from_input();
		$refStr = $refs->get_string();

		// Create the form
	?>
<span class="hide-if-no-js">
<p>The Scripture Quick View is an easy way to see any bible passages while typing your post. It lets you scan passages for verses to copy and paste into your post, and also lets you tag passages to link them to your post.</p>
<input type="text" name="new-bible-ref" id="new-bible-ref" size="16" autocomplete="off" value="" />
<input type="button" class="button" id="view-bible-ref" value="View Scripture" tabindex="3" />
<span class="howto"><?php _e('Type a bible reference (ie. "gen 1")'); ?></span>
<br/>

<strong><p id="bible-text-progress"></p></strong>
<input type="hidden" name="bible-request-url" id="bible-request-url" value="<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" />
<input type="hidden" name="bible-ref-field" id="bible-ref-field" value="" />
<div id="bible-text"></div>
</span>

<div id="bible-ref-viewer" class="hide-if-js">

<?php if ($refs->is_valid()) : ?>
<p>The following scriptures are tagged for this post. You can use this to reference the scripture while writing your post.</p>
<?php echo bfox_get_ref_content($refs); ?>
<?php else : ?>
<p>This box is for viewing scriptures that are tagged for this post. If you tag some scriptures (see the Scripture Tags box) and save the post, you will see the scripture here. You can then use this to reference the scripture while writing your post.</p>
<?php endif; ?>
</div>
<?php

	}

?>
