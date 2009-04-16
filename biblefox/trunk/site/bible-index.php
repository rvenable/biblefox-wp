<?php

// NOTE: wp-load should be the only thing that has been loaded so far
//require '../wp-load.php';

bfox_bible_page_load();
BfoxQuery::set_url(get_option('home') . '/bible/index.php?');

$url = get_option('siteurl');

?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />

		<link rel="stylesheet" href="<?php echo $url; ?>/wp-content/mu-plugins/biblefox/scripture.css" type="text/css"/>
		<link rel="stylesheet" href="<?php echo $url; ?>/wp-content/mu-plugins/biblefox/bible/bible.css" type="text/css"/>

		<script type="text/javascript" src="<?php echo $url; ?>/wp-includes/js/jquery/jquery.js"></script>
		<script type="text/javascript" src="<?php echo $url; ?>/wp-content/mu-plugins/biblefox/bible/bible.js"></script>
	</head>
	<body>
		<?php echo bfox_bible_page(); ?>
	</body>
</html>