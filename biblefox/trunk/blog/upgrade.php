<?php

$msgs = array();
$old_ver = get_site_option(self::option_version);
$blogs = get_blog_list(0, 'all');

if (FALSE === $old_ver) {
	BfoxPosts::create_table();
	$msgs []= "Created posts table";

	foreach ($blogs as $blog) {
		switch_to_blog($blog->blog_id);

		$msgs []= "Editing blog: $blog->blog_id";
		$msgs []= BfoxPosts::refresh_posts();

		restore_current_blog();
	}
}

// TODO3: get_site_option() is a WPMU-only function
wp_mail(get_site_option('admin_email'), "BfoxBlog Upgrade to " . BFOX_VERSION, implode("\n", $msgs));

?>