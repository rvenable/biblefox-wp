<?php
/**
 * The template for displaying Bible Tool Archive pages.
 *
 * Usually used to create a Bible reader with access to all of the Bible Tools.
 *
 */

get_header(); ?>

<?php
global $tooltip_ref;
$tooltip_ref = new BfoxRef('Gen 1');

load_template(get_bfox_tooltip_template());
?>

<?php get_sidebar(); ?>
<?php get_footer(); ?>