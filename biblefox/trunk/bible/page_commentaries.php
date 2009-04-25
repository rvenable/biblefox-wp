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
		$action = $_POST['action'];
		if ( isset($_POST['deleteit']) && isset($_POST['delete']) )
			$action = 'bulk-delete';

		$messages = array();

		$add_url = $_REQUEST['add_url'];
		if (!empty($add_url)) $messages []= BfoxCommentaries::add_url($add_url);

		$enabled = $_POST['enabled'];
		$delete = $_POST['delete'];

		if (!empty($enabled) || !empty($delete)) $messages = array_merge($messages, BfoxCommentaries::update($enabled, $delete));

		$message = implode('<br/>', $messages);

		// If there is a message, redirect to show the message
		// Otherwise if there is no message, but this was still an update, redirect so that refreshing the page won't try to resend the update
		if (!empty($message)) wp_redirect(add_query_arg('message', urlencode($message), BfoxQuery::page_url(BfoxQuery::page_commentary)));
		elseif (!empty($_POST['update'])) wp_redirect(BfoxQuery::page_url(BfoxQuery::page_commentary));
	}

	/**
	 * Outputs the commentary management admin page
	 *
	 */
	public function content()
	{
		$bfox_page_url = BfoxQuery::page_url(BfoxQuery::page_commentary);

		if (!empty($_GET['message'])): ?>
			<div id="message" class="updated fade"><p><?php echo stripslashes(urldecode($_GET['message'])); ?></p></div>
			<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
		endif;

		// Get all the commentaries for this user
		$coms = BfoxCommentaries::get_for_user();

		// Get this user's blogs and make sure they are all in our commentary list
		// If we find any that aren't we need to add them to our list
		global $user_ID;
		$blogs = (array) get_blogs_of_user($user_ID);

		?>
		<form action="<?php echo BfoxQuery::page_post_url(BfoxQuery::page_commentary) ?>" method="post">
		<h3>My Commentaries</h3>
		<p>Biblefox transforms normal blogs into Bible commentaries by organizing and displaying blog posts using the bible references they mention.</p>
		<?php if (!empty($coms)): ?>
		<table id="commentaries">
			<tr>
				<th>Enable</th>
				<th>Name</th>
				<th>Site</th>
				<th>Delete</th>
			</tr>
		<?php foreach ($coms as $com): ?>
			<tr>
				<td><input type="checkbox" name="enabled[]" value="<?php echo $com->blog_id; ?>" <?php echo $com->is_enabled ? 'checked="checked"' : '' ?>/></td>
				<td><?php echo $com->name; ?></td>
				<td><a href="http://<?php echo $com->blog_url ?>"><?php echo $com->blog_url ?></a></td>
				<td><input type="checkbox" name="delete[]" value="<?php echo $com->blog_id; ?>"/></td>
			</tr>

		<?php endforeach ?>
		</table>
		<?php else: ?>
		<p>You currently have no commentaries.</p>
		<?php endif ?>

		<p>Add a new commentary by entering its feed url: <input name="url" id="url" type="text" value="" size="40" maxlength="127" /></p>
		<input type="submit" value="Update My Commentaries" name="update" class="button-secondary delete" />
		</form>

		<h3>Suggested Commentaries</h3>
		<p>You belong to the following blogs that you can add as commentaries:</p>
		<ul>
		<?php foreach ($blogs as $blog): ?>
			<?php if (!isset($coms[$blog->userblog_id])): ?>
			<li><a href="<?php echo add_query_arg('add_url', $blog->domain . $blog->path, $bfox_page_url) ?>"><?php echo $blog->blogname ?></a></li>
			<?php endif ?>
		<?php endforeach ?>
		</ul>
		<p>For a complete bible commentary, we recommend:</p>
		<ul>
			<li>Matthew Henry's Commentary (Coming Soon)</li>
		</ul>
		<?php
		/*include('manage-commentaries.php');*/
	}
}

?>