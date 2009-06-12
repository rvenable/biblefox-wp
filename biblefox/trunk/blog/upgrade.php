<?php

$old_ver = get_site_option(self::option_version);

$blogs = get_blog_list(0, 'all');

if (FALSE === $old_ver) {
	BfoxPosts::create_table();

	foreach ($blogs as $blog) {
		switch_to_blog($blog->blog_id);

		BfoxPosts::refresh_posts();

		restore_current_blog();
	}
}


?>