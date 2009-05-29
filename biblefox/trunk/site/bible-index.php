<?php

// NOTE: wp-load should be the only thing that has been loaded so far
//require '../wp-load.php';

global $user_ID;

if ($user_ID) bfox_bible_page_load();

get_header();
if ($user_ID) bfox_bible_page();
else {
?>
	<p>The Biblefox Bible viewer is currently being tested and is therefore disabled for those without user accounts.</p>
<?php
}

get_footer();
?>