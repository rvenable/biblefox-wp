<?php

function bfox_ref_link($ref_str, $options) {
	if (empty($ref_str)) return false;

	$defaults = array(
		'href' => '',
		'class' => array(),
		'text' => $ref_str,
		'tooltip' => true,
	);
	extract(wp_parse_args($options, $defaults));

	// href
	if (empty($href)) $href = bfox_ref_url($ref_str);
	$attrs['href'] = $href;

	// class
	$class = (array) $class;

	if (($tooltip)) {
		$class []= 'bible-tip';
		$class []= 'bible-tip-' . urlencode(str_replace(' ', '_', strtolower($ref_str)));
	}

	if (!empty($class)) $attrs['class'] = implode(' ', $class);

	// Attribute string
	$attr_str = '';
	foreach ($attrs as $attr => $value) $attr_str .= " $attr='$value'";

	return "<a$attr_str>$text</a>";
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
		return bfox_ref_link($ref->get_string(), array('text' => $text));
	}

?>