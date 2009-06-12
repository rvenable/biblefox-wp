<?php

require_once BFOX_TRANS_DIR . '/installer.php';

/**
 * Manages all translations
 *
 */
class BfoxManageTranslations
{
	/**
	 * Called before loading the manage translations admin page
	 *
	 * Performs all the user's translation edit requests before loading the page
	 *
	 */
	public function manage_page_load()
	{
		$bfox_page_url = 'admin.php?page=' . BiblefoxSite::manage_trans_page;

		$action = $_POST['action'];
		if ( isset($_POST['deleteit']) && isset($_POST['delete']) )
			$action = 'bulk-delete';

		switch($action)
		{
		case 'addtrans':

			check_admin_referer('add-translation');

			if ( !current_user_can(BiblefoxSite::manage_trans_min_user_level))
				wp_die(__('Cheatin&#8217; uh?'));

			$trans = array();
			$trans['short_name'] = stripslashes($_POST['short_name']);
			$trans['long_name'] = stripslashes($_POST['long_name']);
			$trans['is_enabled'] = (int) $_POST['is_enabled'];
			$trans['file_name'] = stripslashes($_POST['trans_file']);
			$trans_id = BfoxTransInstaller::edit_translation((object) $trans);

			wp_redirect(add_query_arg(array('action' => 'edit', 'trans_id' => $trans_id, 'message' => 1), $bfox_page_url));

			exit;
		break;

		case 'bulk-delete':
			check_admin_referer('bulk-translations');

			if ( !current_user_can(BiblefoxSite::manage_trans_min_user_level) )
				wp_die( __('You are not allowed to delete translations.') );

			foreach ((array) $_POST['delete'] as $trans_id)
				BfoxTransInstaller::delete_translation($trans_id);

			wp_redirect(add_query_arg('message', 2, $bfox_page_url));

			exit;
		break;

		case 'editedtrans':
			$trans_id = (int) $_POST['trans_id'];
			check_admin_referer('update-translation-' . $trans_id);

			if ( !current_user_can(BiblefoxSite::manage_trans_min_user_level) )
				wp_die(__('Cheatin&#8217; uh?'));

			$trans = array();
			$trans['short_name'] = stripslashes($_POST['short_name']);
			$trans['long_name'] = stripslashes($_POST['long_name']);
			$trans['is_enabled'] = (int) $_POST['is_enabled'];
			$trans['file_name'] = stripslashes($_POST['trans_file']);
			$trans_id = BfoxTransInstaller::edit_translation((object) $trans, $trans_id);

			wp_redirect(add_query_arg(array('action' => 'edit', 'trans_id' => $trans_id, 'message' => 3), $bfox_page_url));

			exit;
		break;
		}
	}

	/**
	 * Outputs the translation management admin page
	 *
	 */
	public static function manage_page()
	{
		$messages[1] = __('Translation added.');
		$messages[2] = __('Translation deleted.');
		$messages[3] = __('Translation updated.');
		$messages[4] = __('Translation not added.');
		$messages[5] = __('Translation not updated.');

		if (isset($_GET['message']) && ($msg = (int) $_GET['message'])): ?>
			<div id="message" class="updated fade"><p><?php echo $messages[$msg]; ?></p></div>
			<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
		endif;

		switch($_GET['action'])
		{
		case 'edit':
			$trans_id = (int) $_GET['trans_id'];
			include('edit-translation-form.php');
			break;

		case 'validate':
			$file = (string) $_GET['file'];
			bfox_usfx_menu($file);
			break;

		default:
			include('manage-translations.php');
			break;
		}
	}
}

?>