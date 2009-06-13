<?php

$msgs = array();
$old_ver = get_site_option(self::option_version);
$blogs = get_blog_list(0, 'all');

if (FALSE === $old_ver) {
	BfoxPlans::create_tables();
	$msgs []= "Created reading plans table";
	BfoxNotes::create_tables();
	$msgs []= "Created notes table";
	BfoxHistory::create_table();
	$msgs []= "Created history table";

	$msgs []= "NOTE: You still need to install the bible translations!";
}

wp_mail(get_site_option('admin_email'), "BfoxBlog Upgrade to " . BFOX_VERSION, implode("\n", $msgs));

?>