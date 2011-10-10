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
	if ($post_id) $bible_url = get_post_permalink($post_id);
	else $bible_url = get_post_type_archive_link('bfox_tool');

	return add_query_arg('ref', urlencode(strtolower($ref_str)), $bible_url);
}

/**
 * Fixes a bible ref link options array so that it has a ref_str if it doesn't already
 *
 * @param $options
 */
function bfox_fix_ref_link_options(&$options) {
	// If there is no ref_str, try to get it from $ref->get_string($name)
	if (empty($options['ref_str']) && isset($options['ref']) && is_a($options['ref'], 'BfoxRef') && $options['ref']->is_valid())
		$options['ref_str'] = $options['ref']->get_string($options['name']);
}

/**
 * Creates a link from an array specifying bible ref link options
 *
 * Used to create links by bfox_ref_bible_link() and bfox_ref_blog_link()
 *
 * @param array $options
 * @return string
 */
function bfox_ref_link_from_options($options = array()) {
	extract($options);
	$link = '';

	// Only create a link if we actually have a ref_str
	if (!empty($ref_str)) {
		// If there is no text, use the ref_str
		if (empty($text)) $text = $ref_str;

		// If there is no href, get it from the bfox_ref_bible_url function
		if (!isset($attrs['href'])) bfox_ref_bible_url($ref_str);

		// Add the bible-ref class
		if (!isset($disable_tooltip)) {
			if (!empty($attrs['class'])) $attrs['class'] .= ' ';
			$attrs['class'] .= 'bible-tip bible-tip-' . urlencode(str_replace(' ', '_', strtolower($ref_str)));
		}

		$attr_str = '';
		foreach ($attrs as $attr => $value) $attr_str .= " $attr='$value'";

		$link = "<a$attr_str>$text</a>";
	}

	return $link;
}

/**
 * Returns a URL to the external Bible reader of choice for a given Bible Ref
 *
 * Should be used whenever we want to link to the Bible page, as opposed to the Bible archive
 *
 * @param string $ref_str
 * @return string
 */
function bfox_ref_bible_url($ref_str) {
	return bfox_ref_url($ref_str);
}

/**
 * Returns a link to the external Bible reader of choice for a given Bible Ref
 *
 * Should be used whenever we want to link to the Bible page, as opposed to the Bible archive
 *
 * @param array $options
 * @return string
 */
function bfox_ref_bible_link($options) {
	bfox_fix_ref_link_options($options);

	// If there is no href, get it from the bfox_ref_bible_url() function
	if (!isset($options['attrs']['href'])) $options['attrs']['href'] = bfox_ref_bible_url($options['ref_str']);

	return bfox_ref_link_from_options($options);
}

/**
 * Replaces bible references with bible links in a given html string
 * @param string $content
 * @return string
 */
function bfox_ref_replace_html($content, $callback = 'bfox_ref_replace_html_cb') {
	return BfoxRefParser::simple_html($content, null, $callback);
}
	function bfox_ref_replace_html_cb($text, $ref) {
		return bfox_ref_bible_link(array(
			'ref' => $ref,
			'text' => $text
		));
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