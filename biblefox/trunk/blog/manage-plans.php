<?php

global $bfox_plan;
$bfox_page_url = 'admin.php?page=' . BFOX_MANAGE_PLAN_SUBPAGE;

?>

<div class="wrap">
<form action="" method="post">
<input type="hidden" name="page" value="<?php echo BFOX_MANAGE_PLAN_SUBPAGE; ?>">
<?php if ( current_user_can(BFOX_USER_LEVEL_MANAGE_PLANS) ) : ?>
	<h2><?php printf(__('Manage Reading Plans (<a href="%s">add new</a>)'), '#addplan') ?> </h2>
<?php else : ?>
	<h2><?php _e('Manage Reading Plans') ?> </h2>
<?php endif; ?>

<br class="clear" />

<div class="tablenav">

<div class="alignleft">
<input type="submit" value="<?php _e('Delete Selected'); ?>" name="deleteit" class="button-secondary delete" />
<?php wp_nonce_field('bulk-reading-plans'); ?>
</div>

<br class="clear" />
</div>

<br class="clear" />

<table class="widefat">
	<thead>
	<tr>
		<th scope="col" class="check-column"><input type="checkbox" /></th>
        <th scope="col"><?php _e('Name') ?></th>
        <th scope="col"><?php _e('Description') ?></th>
        <th scope="col"><?php _e('Schedules') ?></th>
	</tr>
	</thead>
	<tbody id="the-list" class="list:cat">
<?php
	$plans = $bfox_plan->get_plans();
	foreach ($plans as $plan)
	{
		echo '<tr id="reading-plan-' . $plan->id . '" class="alternate">';
		echo '<th scope="row" class="check-column"> <input type="checkbox" name="delete[]" value="' . $plan->id . '" /></th>';
		echo '<td><a class="row-title" href="' . $bfox_page_url . '&amp;action=edit&amp;plan_id=' . $plan->id . '" title="' .
			attribute_escape(sprintf(__('Edit "%s"'), $plan->name)) . '">' . $plan->name . '</a></td>';
		echo '<td>' . $plan->summary . '</td>';
		echo '<td>' . $plan->start_date . ' - ' . $plan->end_date . '</td>';
		echo '</tr>';
	}
?>
	</tbody>
</table>
</form>

<table id="reading_plans" class="widefat">
	<thead>
	<tr>
		<th>Plan</th>
		<th>Reading List</th>
		<th>Schedule</th>
	</tr>
	</thead>
<?php foreach (BfoxBlog::get_reading_plans() as $plan): ?>
	<tr>
		<td><?php echo $plan->name ?> by <?php echo $plan->owner_link() ?><br/><?php echo $plan->description ?></td>
		<td><?php echo $plan->list->name ?></td>
		<td><?php echo $plan->start_str() ?> - <?php echo $plan->end_str() ?><?php if ($plan->is_recurring) echo ' (recurring)'?><br/><?php echo $plan->frequency_desc() ?></td>
	</tr>
<?php endforeach ?>
</table>

<br class="clear" />

<p><strong>Note:</strong><br/>
<strong>Reading Plans</strong> are an important part of most Biblefox Bible Study Blogs.<br/>
By creating a reading plan, you can structure what scriptures you read and when you read them!</p>

</div>

<?php
	if ( current_user_can(BFOX_USER_LEVEL_MANAGE_PLANS) )
		include('edit-plan-form.php');

?>
