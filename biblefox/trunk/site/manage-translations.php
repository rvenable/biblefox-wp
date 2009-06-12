<?php

$bfox_page_url = 'admin.php?page=' . BiblefoxSite::manage_trans_page;

$header = __('Manage Translations');
if (current_user_can(BiblefoxSite::manage_trans_min_user_level))
	$header .= ' (<a href="#addtrans">' . __('add new') . '</a>)';

?>

<div class="wrap">
<form action="" method="post">
<input type="hidden" name="page" value="<?php echo BiblefoxSite::manage_trans_page; ?>">
<h2><?php echo $header ?></h2>

<br class="clear" />

<div class="tablenav">

<div class="alignleft">
<input type="submit" value="<?php _e('Delete Selected'); ?>" name="deleteit" class="button-secondary delete" />
<?php wp_nonce_field('bulk-translations'); ?>
</div>

<br class="clear" />
</div>

<br class="clear" />

<table class="widefat">
	<thead>
	<tr>
		<th scope="col" class="check-column"><input type="checkbox" /></th>
        <th scope="col"><?php _e('Short Name') ?></th>
        <th scope="col"><?php _e('Long Name') ?></th>
        <th scope="col"><?php _e('Is Default') ?></th>
        <th scope="col"><?php _e('Is Enabled') ?></th>
        <th scope="col"></th>
	</tr>
	</thead>
	<tbody id="the-list" class="list:cat">
<?php
	// Get all the bible translations, even the disabled ones
	$translations = Translation::get_installed();

	foreach ($translations as $trans)
	{
		$edit_link = '<a class="row-title" href="' . $bfox_page_url . '&amp;action=edit&amp;trans_id=' . $trans->id . '">' . __('edit') . '</a></td>';
		?>
		<tr id="translation_<?php echo $trans->id ?>" class="alternate">
			<th scope="row" class="check-column"><input type="checkbox" name="delete[]" value="<?php echo $trans->id; ?>" /></th>
			<td><?php echo $trans->short_name; ?></td>
			<td><?php echo $trans->long_name; ?></td>
			<td><?php echo $trans->is_default; ?></td>
			<td><?php echo $trans->is_enabled; ?></td>
			<td><?php echo $edit_link; ?></td>
		</tr>

		<?php
	}
?>
	</tbody>
</table>
</form>

<br class="clear" />

<p><strong>Note:</strong><br/>
<strong>Translations</strong> are needed to display scripture.</p>

</div>

<?php
	if ( current_user_can(BiblefoxSite::manage_trans_min_user_level) )
		include('edit-translation-form.php');

?>
