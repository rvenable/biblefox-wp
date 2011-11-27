<?php

/**
 * Getter for the active instance of BfoxRef
 *
 * @return BfoxRef
 */
function bfox_ref() {
	global $_bfox_ref;
	if (!isset($_bfox_ref)) $_bfox_ref = new BfoxRef;
	return $_bfox_ref;
}

/**
 * Setter for the active instance of BfoxRef
 *
 * @param BfoxRef $ref
 * @return BfoxRef
 */
function set_bfox_ref(BfoxRef $ref) {
	global $_bfox_ref;
	$_bfox_ref = $ref;
	return $_bfox_ref;
}

function bfox_ref_str($format = '') {
	$ref = bfox_ref();
	return $ref->get_string($format);
}

function bfox_ref_url($ref_str, $post_id = 0) {
	return add_query_arg('ref', urlencode(strtolower($ref_str)), bfox_tool_url());
}

function bfox_book_ref(BfoxRef $ref = null) {
	if (is_null($ref)) $ref = bfox_ref();
	return $ref->book_ref();
}

function bfox_next_chapter_ref_str($format = '') {
	$ref = bfox_ref();
	return $ref->next_chapter_string($format);
}

function bfox_previous_chapter_ref_str($format = '') {
	$ref = bfox_ref();
	return $ref->prev_chapter_string($format);
}

/**
 * Returns a BfoxRef for the given tag string
 *
 * @param string $tag
 * @return BfoxRef
 */
function bfox_ref_from_tag($tag) {
	return BfoxRefParser::simple($tag);
}

/**
 * Returns a BfoxRef for the given content string
 *
 * @param string $content
 * @return BfoxRef
 */
function bfox_ref_from_content($content) {
	$ref = new BfoxRef;
	BfoxRefParser::simple_html($content, $ref);
	return $ref;
}

?>