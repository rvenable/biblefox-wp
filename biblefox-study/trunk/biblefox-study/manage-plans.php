<?php
require_once('admin.php');

global $bfox_plan, $blog_id;
$bfox_page_url = 'admin.php?page=' . BFOX_MANAGE_PLAN_SUBPAGE;

// NOTE: I don't know why the wp_reset_vars() call isn't setting action properly (maybe its because I am in a plugin page?)
//wp_reset_vars(array('action', 'cat'));
if (isset($_POST['action'])) $action = $_POST['action'];
else if (isset($_GET['action'])) $action = $_GET['action'];

if ( isset($_GET['deleteit']) && isset($_GET['delete']) )
	$action = 'bulk-delete';

switch($action) {

case 'addplan':

	check_admin_referer('add-reading-plan');

	if ( !current_user_can('manage_categories') )
		wp_die(__('Cheatin&#8217; uh?'));

	$refs = new BibleRefs((string) $_POST['plan_group_passages']);
	$section_size = (int) $_POST['plan_chapters'];
	if ($section_size == 0) $section_size = 1;

	$plan = array();
	$plan['name'] = stripslashes($_POST['plan_name']);
	$plan['summary'] = stripslashes($_POST['plan_description']);
	$plan['refs_array'] = $refs->get_sections($section_size);
	$plan['start_date'] = bfox_format_local_date($_POST['schedule_start_date']);
	$plan['frequency'] = $bfox_plan->frequency[$_POST['schedule_frequency']];
	$plan['frequency_options'] = $_POST['schedule_frequency_options'];
	$plan_id = $bfox_plan->add_new_plan((object) $plan);
	wp_redirect($bfox_page_url . '&action=edit&plan_id=' . $plan_id . '&message=1');

	exit;
break;

/* Not supporting 'delete' yet (but 'bulk-delete' works)
case 'delete':
	$cat_ID = (int) $_GET['cat_ID'];
	check_admin_referer('delete-category_' .  $cat_ID);

	if ( !current_user_can('manage_categories') )
		wp_die(__('Cheatin&#8217; uh?'));

	$cat_name = get_catname($cat_ID);

	// Don't delete the default cats.
    if ( $cat_ID == get_option('default_category') )
		wp_die(sprintf(__("Can&#8217;t delete the <strong>%s</strong> category: this is the default one"), $cat_name));

	wp_delete_category($cat_ID);

	wp_redirect('categories.php?message=2');
	exit;

break;
 */

case 'bulk-delete':
	check_admin_referer('bulk-reading-plans');

	if ( !current_user_can('manage_categories') )
		wp_die( __('You are not allowed to delete reading plans.') );

	foreach ( (array) $_GET['delete'] as $plan_id ) {
		$bfox_plan->delete($plan_id);
	}

	$sendback = wp_get_referer();
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);

	wp_redirect($sendback);
	exit();

break;
case 'edit':

	require_once ('admin-header.php');
	$plan_id = (int) $_GET['plan_id'];
	include('edit-plan-form.php');

break;

case 'editedplan':
	$plan_id = (int) $_POST['plan_id'];
	check_admin_referer('update-reading-plan-' . $plan_id);

	if ( !current_user_can('manage_categories') )
		wp_die(__('Cheatin&#8217; uh?'));

	$old_refs = $bfox_plan->get_plan_refs($plan_id);
	$text = trim((string) $_POST['plan_passages']);
	$sections = explode("\n", $text);

	$group_refs = new BibleRefs((string) $_POST['plan_group_passages']);
	$section_size = (int) $_POST['plan_chapters'];
	if ($section_size == 0) $section_size = 1;
		
	$plan = array();
	$plan['id'] = $plan_id;
	$plan['name'] = stripslashes($_POST['plan_name']);
	$plan['summary'] = stripslashes($_POST['plan_description']);
	$plan['refs_array'] = array();
	$plan['start_date'] = bfox_format_local_date($_POST['schedule_start_date']);
	$plan['frequency'] = $bfox_plan->frequency[$_POST['schedule_frequency']];
	$plan['frequency_options'] = $_POST['schedule_frequency_options'];
		
	// Create the refs array
	$index = 0;
	$is_edited = false;
	foreach ($sections as $section)
	{
		$section = trim($section);
		
		// Determine if the text we got from input is different from the text already saved for this plan
		if (!isset($old_refs->unread[$index]) || ($old_refs->unread[$index]->get_string() != $section))
			$is_edited = true;
		
		$refs = new BibleRefs($section);
		if ($refs->is_valid()) $plan['refs_array'][] = $refs;
		$index++;
	}
	
	// If we didn't actually make any changes to the refs_array then there is no need to send it
/*	if (!$is_edited && (count($old_refs->unread) == count($plan['refs_array'])))
		unset($plan['refs_array']);*/

	// Add the group chunk refs to the refs array
	$plan['refs_array'] = array_merge($plan['refs_array'], $group_refs->get_sections($section_size));
	
	$bfox_plan->edit_plan((object) $plan);
	
	wp_redirect($bfox_page_url . '&action=edit&plan_id=' . $plan_id . '&message=3');

	exit;
break;

default:

if ( !empty($_GET['_wp_http_referer']) ) {
	 wp_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI'])));
	 exit;
}

wp_enqueue_script( 'admin-categories' );
wp_enqueue_script('admin-forms');

require_once ('admin-header.php');

$messages[2] = __('Reading Plan deleted.');
$messages[4] = __('Reading Plan not added.');
$messages[5] = __('Reading Plan not updated.');
?>

<?php if (isset($_GET['message'])) : ?>
<div id="message" class="updated fade"><p><?php echo $messages[$_GET['message']]; ?></p></div>
<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
endif; ?>

<div class="wrap">
<form id="posts-filter" action="" method="get">
<input type="hidden" name="page" value="<?php echo BFOX_MANAGE_PLAN_SUBPAGE; ?>">
<?php if ( current_user_can('manage_categories') ) : ?>
	<h2><?php printf(__('Manage Reading Plans (<a href="%s">add new</a>)'), '#addplan') ?> </h2>
<?php else : ?>
	<h2><?php _e('Manage Reading Plans') ?> </h2>
<?php endif; ?>

<br class="clear" />

<div class="tablenav">

<div class="alignleft">
<input type="submit" value="<?php _e('Delete'); ?>" name="deleteit" class="button-secondary delete" />
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
			attribute_escape(sprintf(__('Edit "%s"'), $plan->name)) . '">' . $plan->name . '</a>';
		echo '<td>' . $plan->summary . '</td>';
		echo '<td>' . $plan->start_date . ' - ' . $plan->end_date . '</td>';
		echo '</tr>';
	}
?>
	</tbody>
</table>
</form>

<br class="clear" />

<p><strong>Note:</strong><br/>
<strong>Reading Plans</strong> are an important part of most Biblefox Bible Study Blogs.<br/>
By creating a reading plan, you can structure what scriptures you read and when you read them!</p>

</div>

<?php
	if ( current_user_can('manage_categories') )
		include('edit-plan-form.php');

break;
}

include('admin-footer.php');

?>
