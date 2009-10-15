<?php

// TODO: remove this file

//require_once BFOX_DIR . '/bible/bible.php';

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

global $current_site;
wp_mail(get_site_option('admin_email'), "BfoxBible Upgrade to " . BFOX_VERSION . " on $current_site->domain$current_site->path", implode("\n", $msgs));

?>