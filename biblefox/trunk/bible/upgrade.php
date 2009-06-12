<?php

$old_ver = get_site_option(self::option_version);

$blogs = get_blog_list(0, 'all');

if (FALSE === $old_ver) {
	BfoxPlans::create_tables();
	BfoxNotes::create_tables();
	BfoxHistory::create_table();
}


?>