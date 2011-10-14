<?php
/**
 * The template for displaying single Bible Tool pages.
 *
 * Usually just displays the same page as the Bible Tool Archive,
 * but also supports the 'src' parameter which just displays raw source content for
 * Bible Tools that are stored on a local database.
 *
 */
?>

<div class="bfox-tool-admin">
	<?php load_bfox_template('iframe-bfox_tool'); ?>

	<div class='chapter-list'>
		<?php echo list_bfox_ref_chapters(bfox_book_ref(), array('before' => '', 'after' => '', 'between' => ' | ', 'first_format' => BibleMeta::name_short)); ?>
	</div>
</div>
