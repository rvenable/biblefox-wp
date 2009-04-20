<?php

// NOTE: wp-load should be the only thing that has been loaded so far
//require '../wp-load.php';

global $user_ID;

bfox_bible_page_load();
BfoxQuery::set_url(get_option('home') . '/?');

get_header();
get_sidebar();
?>
<div id="content">
	<?php if ($user_ID): ?>
		<?php bfox_bible_page(); ?>
	<?php else: ?>
		<p>The Biblefox Bible viewer is currently being tested and is therefore disabled for those without user accounts.</p>
	<?php endif; ?>
</div>
<?php
get_footer();
?>