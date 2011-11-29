<?php
/**
 * Use this file to customize the Bible Tools used on your site
 */

// Official ESV API
add_bfox_tool(new BfoxESVApi(), 'ESV', 'English Standard Version');

// Official NET Bible API
add_bfox_tool(new BfoxNETBibleApi(), 'NET', 'New English Translation');

// Official ESV API via Javascript
add_bfox_tool(new BfoxESVJavaScriptApi(), 'ESVJS', 'English Standard VersionJS');

// NET Bible via Javascript
add_bfox_tool(new BfoxNETBibleTaggerApi(), 'NETJS', 'New English TranslationJS');

// RefTagger API via Javascript
add_bfox_tool(new BfoxRefTaggerApi('NIV'), 'NIV', 'New International Version');

// Biblia API
// Requires API Key, visit: http://api.biblia.com/docs/API_Keys
if (defined('BFOX_BIBLIA_API_KEY')) {
	add_bfox_tool(new BfoxBibliaApi('LEB', BFOX_BIBLIA_API_KEY), 'LEB', 'Lexham English Bible');
}

/*
 * You can also load Bible Tools your own locally stored Database
 *
 * An example of a Bible stored in a local database
 * In this example,
 *  'wp_bfox_trans_KJV_verses' is the name of the local database table
 *  'verse' is a column in the table that has the text for an individual verse
 *  'unique_id' is a column that has a unique ID for verse
 *    Verse unique IDs need to be formatted as such, unique_id = bookNum * 256 * 256 + chapterNum * 256 + verseNum
 *    bookNum begins with Genesis = 1, Exodus = 2
 */

/*
 * Use this as an example of loading scripture from a local database
 */
// add_bfox_tool(new BfoxLocalWPBibleToolApi('wp_bfox_trans_KJV_verses', 'verse', 'unique_id'), 'KJV', 'King James Version');


// Loads all Bible Tools links for the blog, displayed in iframes
load_blog_bfox_tools();

?>