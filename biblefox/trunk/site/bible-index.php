<?php

// NOTE: wp-load should be the only thing that has been loaded so far
//require '../wp-load.php';

bfox_bible_page_load();
BfoxQuery::set_url(get_option('home') . '/?');

get_header();
get_sidebar();
?>
<div id="content">
	<? bfox_bible_page(); ?>
</div>
<?php
get_footer();
?>