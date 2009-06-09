<?php

require_once BFOX_DIR . '/bible/bible.php';

global $user_ID;

if ($user_ID) $bible = new BfoxBible();

if (isset($bible)) {
	$bible->page();
}
else {
	get_header();
	?>
	<p>The Biblefox Bible viewer is currently being tested and is therefore disabled for those without user accounts.</p>
	<?php
	get_footer();
}

?>