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
		else if (!empty($_POST['update'])) $messages = array_merge($messages, BfoxCommentaries::update($_POST['enabled'], $_POST['delete']));

		$message = implode('<br/>', $messages);

		// If there is a message, redirect to show the message
		// Otherwise if there is no message, but this was still an update, redirect so that refreshing the page won't try to resend the update
		if (!empty($message)) wp_redirect(add_query_arg(BfoxQuery::var_message, urlencode($message), BfoxQuery::page_url(BfoxQuery::page_commentary)));
		elseif (!empty($_POST['update'])) wp_redirect(BfoxQuery::page_url(BfoxQuery::page_commentary));
	}

	protected function message()
	{
		if (!empty($_GET[BfoxQuery::var_message]))
		{
			?>
			<div id="page_message"><?php echo strip_tags(stripslashes(urldecode($_GET[BfoxQuery::var_message])), '<br/>') ?></div>
			<?php
			$_SERVER['REQUEST_URI'] = remove_query_arg(array(BfoxQuery::var_message), $_SERVER['REQUEST_URI']);
		}
	}

	/**
	 * Outputs the commentary management admin page
	 *
	 */
	public function content()
	{
		$this->message();

		$bfox_page_url = BfoxQuery::page_url(BfoxQuery::page_commentary);

		// Get all the commentaries for this user
		$coms = BfoxCommentaries::get_for_user();

		// Get this user's blogs and make sure they are all in our commentary list
		// If we find any that aren't we need to add them to our list
		global $user_ID;
		$your_blogs = array();
		foreach ((array) get_blogs_of_user($user_ID) as $blog) if (!isset($coms[$blog->userblog_id])) $your_blogs []= $blog;
		unset($user_blogs);

		?>
		<?php if (!empty($coms)): ?>
		<h3>My Commentaries</h3>
		<p>These are the commentaries you currently have selected. You can disable them from being shown in the <a href="<?php echo BfoxQuery::page_url(BfoxQuery::page_passage) ?>">Biblefox Bible Viewer</a> or delete them from your commentary list entirely.</p>
		<form action="<?php echo BfoxQuery::page_post_url(BfoxQuery::page_commentary) ?>" method="post">
		<table id="commentaries">
			<tr>
				<th>Name</th>
				<th>Site</th>
				<th>Enabled?</th>
				<th>Delete?</th>
			</tr>
		<?php foreach ($coms as $com): ?>
			<tr>
				<td><?php echo $com->name; ?></td>
				<td><a href="http://<?php echo $com->blog_url ?>"><?php echo $com->blog_url ?></a></td>
				<td><input type="checkbox" name="enabled[]" value="<?php echo $com->blog_id; ?>" <?php echo $com->is_enabled ? 'checked="checked"' : '' ?>/></td>
				<td><input type="checkbox" name="delete[]" value="<?php echo $com->blog_id; ?>"/></td>
			</tr>

		<?php endforeach ?>
		</table>
		<p><input type="submit" value="Update My Commentaries" name="update" class="button-secondary delete" /></p>
		</form>
		<?php endif ?>

		<h3>Add Commentaries</h3>
		<p>Here you can add commentaries that will be displayed alongside the scripture in the <a href="<?php echo BfoxQuery::page_url(BfoxQuery::page_passage) ?>">Biblefox Bible Viewer</a>. Biblefox transforms normal blogs into Bible commentaries by organizing and displaying blog posts using the bible references they mention.</p>
		<p>Here are some blogs you might want to add as commentaries:</p>
		<?php if (!empty($your_blogs)): ?>
		<h4>Your Blogs</h4>
		<p>You belong to the following blogs that you can add as commentaries:</p>
		<ul>
			<?php foreach ($your_blogs as $blog): ?>
			<li><?php echo $blog->blogname ?>: <a href="http://<?php echo $blog->domain . $blog->path ?>">visit site</a> or <a href="<?php echo add_query_arg('add_url', $blog->domain . $blog->path, $bfox_page_url) ?>">add as a commentary</a></li>
			<?php endforeach ?>
		</ul>
		<?php endif ?>
		<h4>Suggested Blogs</h4>
		<p>For a complete bible commentary, we recommend:</p>
		<ul>
			<li>Matthew Henry's Commentary (Coming Soon)</li>
		</ul>
		<h4>Other Blogs</h4>
		<p>You can add any biblefox.com blog by entering the blog URL. For example, add blogs that your friends have created to keep updated with what they are learning from the Bible.</p>
		<form action="<?php echo BfoxQuery::page_post_url(BfoxQuery::page_commentary) ?>" method="post">
		<p>Add any biblefox.com blog by entering its url:
		<input name="add_url" id="add_url" type="text" value="" size="40" maxlength="127" />
		<input type="submit" value="Add URL" name="update" class="button-secondary delete" /></p>
		</form>
		<?php
	}
}

?>