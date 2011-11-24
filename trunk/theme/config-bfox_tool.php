<?php
/**
 * The template for displaying Bible Tools within the admin interface (ie. on Edit Post screen)
 *
 */

/*
 * Three different ways to load Bibles:
 * Your server loads the scripture from an external API
 * Your server embeds javascript to load the scripture on the client
 * Your server loads the scripture from a local database
 */

/*
 * An example of a Bible stored in a local database
 * In this example,
 *  'wp_bfox_trans_KJV_verses' is the name of the local database table
 *  'verse' is a column in the table that has the text for an individual verse
 *  'unique_id' is a column that has a unique ID for verse
 *    Verse unique IDs need to be formatted as such, unique_id = bookNum * 256 * 256 + chapterNum * 256 + verseNum
 *    bookNum begins with Genesis = 1, Exodus = 2
 */

/* Use this as an example of loading scripture from a local database *
add_bfox_tool(new BfoxLocalWPBibleToolApi('wp_bfox_trans_KJV_verses', 'verse', 'unique_id'), 'KJV', 'King James Version');
/**/

/*
 * The official ESV API
 * Loaded via Javascript
 * No API key required
 */
add_bfox_tool(new BfoxESVJavaScriptApi(), 'ESV', 'English Standard Version');

/*
 * The official NET Bible API
 * Loaded via Javascript
 * No API key required
 *
 * We don't recommend this one because it adds copyright notices to every verse.
 * Instead, we recommend the API that is used by the official NET Bible Tagger plugin,
 * which just puts one copyright notice at the end of all the verses.
 */

/* Commented out because its not recommended *
add_bfox_tool(new BfoxNETBibleApi(), 'NET', 'New English Translation');
/**/

/*
 * The official NET Bible API, used by their NET Bible Tagger plugin
 * Loaded via Javascript
 * No API key required
 *
 * This API is better than the regular NET Bible API because it
 * just puts one copyright notice at the end of all the verses.
 */
add_bfox_tool(new BfoxNETBibleTaggerApi(), 'NET', 'New English Translation');

/*
 * The API used by the great RefTagger plugin
 * Loaded via Javascript
 * No API key required
 *
 * This API has a lot of available versions, but only returns a few (three?) verses at a time
 */
add_bfox_tool(new BfoxRefTaggerApi('NIV'), 'NIV', 'New International Version');

/*
 * The Biblia API, made by Logos
 * Loaded by server
 * API key required
 *
 * This API has a lot of available versions, but requires an API key
 * Set BFOX_BIBLIA_API_KEY to your API key in wp-config.php
 *
 */
if (defined('BFOX_BIBLIA_API_KEY')) {
	add_bfox_tool(new BfoxBibliaApi('LEB', BFOX_BIBLIA_API_KEY), 'LEB', 'Lexham English Bible');
}

/* Uncomment this if you want to load all of your bfox_tool links as Bibles in iframes */
load_blog_bfox_tools();
/**/

?>