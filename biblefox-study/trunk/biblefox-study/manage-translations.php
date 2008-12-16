<?php
require_once('admin.php');

global $bfox_translations, $blog_id;
$bfox_page_url = 'admin.php?page=' . BFOX_TRANSLATION_SUBPAGE;

// NOTE: I don't know why the wp_reset_vars() call isn't setting action properly (maybe its because I am in a plugin page?)
//wp_reset_vars(array('action', 'cat'));
if (isset($_POST['action'])) $action = $_POST['action'];
else if (isset($_GET['action'])) $action = $_GET['action'];

if ( isset($_GET['deleteit']) && isset($_GET['delete']) )
	$action = 'bulk-delete';

switch($action) {

case 'addtrans':

	check_admin_referer('add-translation');

	if ( !current_user_can(BFOX_USER_LEVEL_MANAGE_TRANSLATIONS))
		wp_die(__('Cheatin&#8217; uh?'));

	$trans = array();
	$trans['short_name'] = stripslashes($_POST['short_name']);
	$trans['long_name'] = stripslashes($_POST['long_name']);
	$trans['is_enabled'] = (int) $_POST['is_enabled'];
	$trans['file_name'] = stripslashes($_POST['trans_file']);
	$trans_id = $bfox_translations->edit_translation((object) $trans);

	wp_redirect($bfox_page_url . '&message=1');

	exit;
break;

case 'bulk-delete':
	check_admin_referer('bulk-translations');

	if ( !current_user_can(BFOX_USER_LEVEL_MANAGE_TRANSLATIONS) )
		wp_die( __('You are not allowed to delete translations.') );

	foreach ((array) $_GET['delete'] as $trans_id)
		$bfox_translations->delete_translation($trans_id);

	$sendback = wp_get_referer();
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);

	wp_redirect($sendback);
	exit();

break;

case 'edit':

	require_once ('admin-header.php');
	$trans_id = (int) $_GET['trans_id'];
	include('edit-translation-form.php');

break;

case 'editedtrans':
	$trans_id = (int) $_POST['trans_id'];
	check_admin_referer('update-translation-' . $trans_id);

	if ( !current_user_can(BFOX_USER_LEVEL_MANAGE_TRANSLATIONS) )
		wp_die(__('Cheatin&#8217; uh?'));

	$trans = array();
	$trans['short_name'] = stripslashes($_POST['short_name']);
	$trans['long_name'] = stripslashes($_POST['long_name']);
	$trans['is_enabled'] = (int) $_POST['is_enabled'];
	$trans['file_name'] = stripslashes($_POST['trans_file']);
	$trans_id = $bfox_translations->edit_translation((object) $trans, $trans_id);

	wp_redirect($bfox_page_url . '&message=3');

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

$messages[1] = __('Translation added.');
$messages[2] = __('Translation deleted.');
$messages[3] = __('Translation updated.');
$messages[4] = __('Translation not added.');
$messages[5] = __('Translation not updated.');
?>

<?php if (isset($_GET['message'])) : ?>
<div id="message" class="updated fade"><p><?php echo $messages[$_GET['message']]; ?></p></div>
<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
endif; ?>

<div class="wrap">
<form id="posts-filter" action="" method="get">
<input type="hidden" name="page" value="<?php echo BFOX_TRANSLATION_SUBPAGE; ?>">
<?php if ( current_user_can(BFOX_USER_LEVEL_MANAGE_TRANSLATIONS) ) : ?>
	<h2><?php printf(__('Manage Translations (<a href="%s">add new</a>)'), '#addtrans') ?> </h2>
<?php else : ?>
	<h2><?php _e('Manage Translations') ?> </h2>
<?php endif; ?>

<br class="clear" />

<div class="tablenav">

<div class="alignleft">
<input type="submit" value="<?php _e('Delete'); ?>" name="deleteit" class="button-secondary delete" />
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
	$translations = $bfox_translations->get_translations();
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
	if ( current_user_can(BFOX_USER_LEVEL_MANAGE_TRANSLATIONS) )
		include('edit-translation-form.php');

break;
}

include('admin-footer.php');

?>
