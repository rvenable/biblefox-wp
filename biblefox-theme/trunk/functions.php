<?php

/* Load the AJAX functions for the theme */
require_once( STYLESHEETPATH . '/_inc/ajax.php' );

/* Load the javascript for the theme */
wp_enqueue_script( 'bfox-theme-ajax-js', get_stylesheet_directory_uri() . '/_inc/js/ajax.js', array( 'dtheme-ajax-js' ) );

// HACK for displaying the bible on iphone:
// Copied from WP-Touch plugin: detectAppleMobile()
function bp_bible_hack_detectAppleMobile() {
	$container = $_SERVER['HTTP_USER_AGENT'];
	// The below prints out the user agent array. Uncomment to see it shown on the page.
	// print_r($container);
	// Add whatever user agents you want here to the array if you want to make this show on another device.
	// No guarantees it'll look pretty, though!
		$useragents = array(
		"iphone",  				 // Apple iPhone
		"ipod", 					 // Apple iPod touch
		"aspen", 				 // iPhone simulator
		"dream", 				 // Pre 1.5 Android
		"android", 			 // 1.5+ Android
		"cupcake", 			 // 1.5+ Android
		"blackberry9500",	 // Storm
		"blackberry9530",	 // Storm
		"opera mini", 		 // Experimental
		"webos",				 // Experimental
		"incognito", 			 // Other iPhone browser
		"webmate" 			 // Other iPhone browser
	);
	$applemobile = false;
	foreach ( $useragents as $useragent ) {
		if ( eregi( $useragent, $container ) ) {
			$applemobile = true;
		}
	}
	if ($applemobile) {
		?>
		<style type="text/css">
		.passagecolumn {
			font-size: 1.5em;
			width: 100%;
		}
		#bible-sidebar { display: none; }
		</style>
		<?php
	}
}
add_action( 'wp_head', 'bp_bible_hack_detectAppleMobile', 100 );

// Add the bp-bible widgets if they exist
if (function_exists('bp_bible_widgets_init')) add_action('widgets_init', 'bp_bible_widgets_init');

?>