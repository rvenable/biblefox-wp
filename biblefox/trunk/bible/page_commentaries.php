<?php

class BfoxPageCommentaries extends BfoxPage
{
	/**
	 * Called before loading the manage commentaries admin page
	 *
	 * Performs all the user's management edit requests before loading the page
	 *
	 */
	public function page_load()
	{
		$bfox_page_url = BfoxQuery::page_url(BfoxQuery::page_commentary);

		$action = $_POST['action'];
		if ( isset($_POST['deleteit']) && isset($_POST['delete']) )
			$action = 'bulk-delete';

		switch($action)
		{
		case 'update':

			check_admin_referer('update-commentary');

			$message = BfoxCommentaries::update_from_form($_POST['name'], $_POST['url'], isset($_POST['is_enabled']) ? TRUE : FALSE);

			wp_redirect(add_query_arg('message', urlencode($message), $bfox_page_url));

			exit;
		break;

		case 'bulk-delete':
			check_admin_referer('bulk-commentaries');

			BfoxCommentaries::delete_for_user($_POST['delete']);

			wp_redirect(add_query_arg('message', urlencode('Commentaries deleted.'), $bfox_page_url));

			exit;
		break;
		}
	}

	/**
	 * Outputs the commentary management admin page
	 *
	 */
	public function content()
	{
		$bfox_page_url = BfoxQuery::page_url(BfoxQuery::page_commentary);

		if (!empty($_GET['message'])): ?>
			<div id="message" class="updated fade"><p><?php echo urldecode($_GET['message']); ?></p></div>
			<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
		endif;

		include('manage-commentaries.php');
	}
}

?>