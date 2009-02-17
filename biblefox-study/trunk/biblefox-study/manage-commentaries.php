<?php

$blog_id = $_GET['blog_id'];

if (!empty($blog_id)) $header_url = $bfox_page_url;
$header = __('Manage Commentaries') . ' (<a href="' . $header_url . '#editcom">' . __('add new') . '</a>)';

?>

<div class="wrap">
<form action="" method="post">
<input type="hidden" name="page" value="<?php echo Commentaries::page; ?>">
<h2><?php echo $header ?></h2>

<br class="clear" />

<div class="tablenav">

<div class="alignleft">
<input type="submit" value="<?php _e('Delete Selected'); ?>" name="deleteit" class="button-secondary delete" />
<?php wp_nonce_field('bulk-commentaries'); ?>
</div>

<br class="clear" />
</div>

<br class="clear" />

<table class="widefat">
	<thead>
	<tr>
		<th scope="col" class="check-column"><input type="checkbox" /></th>
        <th scope="col"><?php _e('Name') ?></th>
        <th scope="col"></th>
        <th scope="col"></th>
	</tr>
	</thead>
	<tbody id="the-list" class="list:cat">
<?php
	// Get all the commentaries for this user
	$coms = Commentaries::get_for_user();

	foreach ($coms as $com)
	{
		$edit_link = '<a class="row-title" href="' . $bfox_page_url . '&amp;blog_id=' . $com->blog_id . '#editcom">' . __('edit') . '</a></td>';
		?>
		<tr id="commentary_<?php echo $com->blog_id ?>" class="alternate">
			<th scope="row" class="check-column"><input type="checkbox" name="delete[]" value="<?php echo $com->blog_id; ?>" /></th>
			<td><?php echo $com->name; ?> (<a href="http://<?php echo $com->blog_url ?>">visit</a>)</td>
			<td><?php echo $com->is_enabled ? 'Enabled' : 'Disabled'; ?></td>
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
<strong>Commentaries</strong> are just blogs that you can read alongside scripture.</p>

</div>

<?php
	include('edit-commentary-form.php');

?>
