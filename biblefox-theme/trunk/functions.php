<?php

/* Load the AJAX functions for the theme */
require_once( STYLESHEETPATH . '/_inc/ajax.php' );

/* Load the javascript for the theme */
wp_enqueue_script( 'bfox-theme-ajax-js', get_stylesheet_directory_uri() . '/_inc/js/ajax.js', array( 'dtheme-ajax-js' ) );

?>