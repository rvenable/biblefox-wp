<?php

// NOTE: wp-load should be the only thing that has been loaded so far
//require '../wp-load.php';

bfox_bible_page_load();
BfoxQuery::set_url(get_option('home') . '/?');

get_header();
get_sidebar();
bfox_bible_page();
get_footer();
?>