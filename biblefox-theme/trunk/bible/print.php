<?php

$refs = new BfoxRefs($_REQUEST['bible_print_ref']);
// TODO: use translation from REQUEST
//$trans = new BfoxTrans($_REQUEST['trans_id']);
$show_options = !!$_REQUEST['show_options'];
$allow_tooltips = !!$_REQUEST['allow_tooltips'];

// All we need are the scripture styles and script
// TODO: Buddypress is adding a few other syles which we don't need to load here
wp_enqueue_style('bfox_scripture');
//wp_enqueue_script('bfox-scripture');

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>

	<head profile="http://gmpg.org/xfn/11">
		<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
		<title><?php echo $refs->get_string() ?></title>
		<meta name="generator" content="WordPress <?php bloginfo('version'); ?>" /> <!-- leave this for stats -->
		<?php wp_head(); ?>
	</head>

	<body>
		<?php if ($refs->is_valid()) {
			list($content, $footnotes) = BfoxBlog::get_verse_content_foot($refs);
			echo $content . $footnotes;
		} ?>
	</body>
</html>
