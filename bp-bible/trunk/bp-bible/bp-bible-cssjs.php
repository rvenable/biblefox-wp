<?php

/**
 * NOTE: You should always use the wp_enqueue_script() and wp_enqueue_style() functions to include
 * javascript and css files.
 */

/**
 * bp_bible_add_js()
 *
 * This function will enqueue the components javascript file, so that you can make
 * use of any javascript you bundle with your component within your interface screens.
 */
function bp_bible_add_js() {
	global $bp;

	if ( $bp->current_component == $bp->bible->slug ) {
		//wp_enqueue_script( 'bp-bible-jquery', BP_BIBLE_URL . '/bible/jquery/js/jquery-1.3.2.min.js', 'bp-general-js' );
		wp_enqueue_script( 'bp-bible-jquery-cookie', BP_BIBLE_URL . '/bible/jquery.cookie.js' );
		//wp_enqueue_script( 'bp-bible-jquery-ui', BP_BIBLE_URL . '/bible/jquery/js/jquery-ui-1.7.2.custom.min.js' );
		wp_enqueue_script( 'bp-bible-js', BP_BIBLE_URL . '/bp-bible/js/bible.js' );
	}
}
add_action( 'template_redirect', 'bp_bible_add_js', 1 );

/**
 * bp_bible_add_structure_css()
 *
 * This function will enqueue structural CSS so that your component will retain interface
 * structure regardless of the theme currently in use. See the notes in the CSS file for more info.
 */
function bp_bible_add_structure_css() {
	/* Enqueue the structure CSS file to give basic positional formatting for your component reglardless of the theme. */
	wp_enqueue_style( 'bp-bible-structure', BP_BIBLE_URL . '/bp-bible/css/structure.css' );

	global $bp;

	if ( $bp->current_component == $bp->bible->slug ) {
		wp_enqueue_style( 'bp-bible-scripture', BP_BIBLE_URL . '/blog/scripture.css' );
		wp_enqueue_style( 'bp-bible-jquery-ui', BP_BIBLE_URL . '/bible/jquery/css/overcast/jquery-ui-1.7.2.custom.css' );
		wp_enqueue_style( 'bp-bible-search', BP_BIBLE_URL . '/bp-bible/css/search.css' );
	}
}
add_action( 'bp_styles', 'bp_bible_add_structure_css' );

?>