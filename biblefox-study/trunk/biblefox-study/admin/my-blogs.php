<?php
require_once('admin.php');

global $bfox_plan, $blog_id;
$bfox_page_url = 'admin.php?page=' . BFOX_MANAGE_PLAN_SUBPAGE;

/* Not supporting any actions yet
// NOTE: I don't know why the wp_reset_vars() call isn't setting action properly (maybe its because I am in a plugin page?)
//wp_reset_vars(array('action', 'cat'));
if (isset($_POST['action'])) $action = $_POST['action'];
else if (isset($_GET['action'])) $action = $_GET['action'];

if ( isset($_GET['deleteit']) && isset($_GET['delete']) )
	$action = 'bulk-delete';

switch($action) {

case 'addplan':

	check_admin_referer('add-reading-plan');

	if ( !current_user_can(BFOX_USER_LEVEL_MANAGE_PLANS) )
		wp_die(__('Cheatin&#8217; uh?'));

	$refs = RefManager::get_from_str((string) $_POST['plan_group_passages']);
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

case 'delete':
	$cat_ID = (int) $_GET['cat_ID'];
	check_admin_referer('delete-category_' .  $cat_ID);

	if ( !current_user_can(BFOX_USER_LEVEL_MANAGE_PLANS) )
		wp_die(__('Cheatin&#8217; uh?'));

	$cat_name = get_catname($cat_ID);

	// Don't delete the default cats.
    if ( $cat_ID == get_option('default_category') )
		wp_die(sprintf(__("Can&#8217;t delete the <strong>%s</strong> category: this is the default one"), $cat_name));

	wp_delete_category($cat_ID);

	wp_redirect('categories.php?message=2');
	exit;

break;

case 'bulk-delete':
	check_admin_referer('bulk-reading-plans');

	if ( !current_user_can(BFOX_USER_LEVEL_MANAGE_PLANS) )
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

	if ( !current_user_can(BFOX_USER_LEVEL_MANAGE_PLANS) )
		wp_die(__('Cheatin&#8217; uh?'));

	$old_refs = $bfox_plan->get_plan_refs($plan_id);
	$text = trim((string) $_POST['plan_passages']);
	$sections = explode("\n", $text);

	$group_refs = RefManager::get_from_str((string) $_POST['plan_group_passages']);
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

		$refs = RefManager::get_from_str($section);
		if ($refs->is_valid()) $plan['refs_array'][] = $refs;
		$index++;
	}

	// If we didn't actually make any changes to the refs_array then there is no need to send it
	if (!$is_edited && (count($old_refs->unread) == count($plan['refs_array'])))
		unset($plan['refs_array']);

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
 */

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
	<h2><?php _e('My Bible Studies'); ?> </h2>

<br class="clear" />

<table class="widefat">
	<thead>
	<tr>
        <th scope="col"><?php _e('My Bible Study Blogs') ?></th>
		<th scope="col"><?php _e('Options') ?></th>
	</tr>
	</thead>
	<tbody id="the-list" class="list:cat">
<?php

	global $user_ID, $bfox_specials;
	$blogs = bfox_get_bible_study_blogs($user_ID);

	foreach ($blogs as $blog_id => $blog)
	{
		$class = ('alternate' == $class) ? '' : 'alternate';
		echo '<tr class="' . $class . '">';
		echo '<td>';
		echo '<a class="row-title" href="' . $blog->siteurl . '" title="' .
			attribute_escape(sprintf(__('View "%s"'), $blog->blogname)) . '">' . $blog->blogname . '</a><br/>';

		echo '<table width="100%">';
		$blog_plan = new PlanBlog($blog_id);
		$blog_plans = $blog_plan->get_plans();
		if (0 < count($blog_plans))
		{
			foreach ($blog_plans as $plan)
			{
				// HACK - lame way of getting the url
				$plan_url = $bfox_specials->get_url_reading_plans($plan->id, NULL, NULL, $blog->siteurl . '?' . BFOX_QUERY_VAR_SPECIAL . '=reading_plans');

				$td = '<td style="border:none; padding: 1px 5px 1px 5px;"';
				echo '<tr>';
				echo $td . ' width="20%"><a href="' . $plan_url . '" title="' .
				attribute_escape(sprintf(__('Edit "%s"'), $plan->name)) . '">' . $plan->name . '</a>';
				echo $td . ' width="60%">' . $plan->summary . '</td>';
//				echo $td . $plan->start_date . ' - ' . $plan->end_date . '</td>';
				$ref = $plan->refs[$plan->current_reading];
				if (isset($ref)) $str = BfoxLinks::get_ref_link($ref);
				else $str = '';
				echo $td . ' width="20%">' . $str . '</td>';
				unset($ref);
				echo '</tr>';
			}
		}
		echo '</table></td>';
		echo '<td><a href="' . $blog->siteurl . '/wp-admin/">manage</a></td>';
		echo '</tr>';
	}
?>
	</tbody>
</table>

<br class="clear" />

<p><strong>Note:</strong><br/>
Biblefox is currently in testing, and is not allowing everyone to create new bible studies.</p>

<p>As a tester, you should probably request to join one of the following bible studies. Just click below to go to one of these blogs. Then click the "Join This Blog" button.</p>

<p>
<ul>
<li><a href="http://crossroad.biblefox.com/?bfox_special=join">Crossroad</a> - A bible study blog for members of the Crossroad service at Tabernacle Baptist Church in Auckland, New Zealand</li>
<li><a href="http://liveoak.biblefox.com/?bfox_special=join">Liveoak</a> - A bible study blog for members of Liveoak Bible Church in Austin, Texas</li>
<li><a href="http://thevine.biblefox.com/?bfox_special=join">The Vine</a> - A bible study blog for members of Liveoak Bible Church's small group - The Vine</li>
</p>

</div>

<?php
/*
}
*/

include('admin-footer.php');

?>
