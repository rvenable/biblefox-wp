<?php

function add_bfox_tool(BfoxBibleToolApi $api, $shortName, $longName = '') {
	$bfoxTools = BfoxBibleToolController::sharedInstance();
	$bfoxTools->addTool(new BfoxBibleTool($api, $shortName, $longName));
}

function active_bfox_tool() {
	$bfoxTools = BfoxBibleToolController::sharedInstance();
	return $bfoxTools->activeTool();
}

function has_bfox_tool() {
	return (!is_null(active_bfox_tool()));
}

/**
 * Loops through Bible Tool links and adds a Bible Tool for each (loaded in Iframe)
 */
function load_blog_bfox_tools() {
	$query = bfox_tool_query();

	while ($query->have_posts()) {
		$query->the_post();

		$url = bfox_tool_source_url();
		$title = get_the_title();
		$post = &get_post($id);

		add_bfox_tool(new BfoxWPBibleToolIframeApi($url), $post->post_name, $title);
	}
}

/*
 * Reading Tool Meta Data functions
 */

function bfox_tool_meta($key, $post_id = 0) {
	if (empty($post_id)) $post_id = $GLOBALS['post']->ID;
	$value = get_post_meta($post_id, '_bfox_tool_' . $key, true);
	return $value;
}

function bfox_tool_update_meta($key, $value, $post_id = 0) {
	if (empty($post_id)) $post_id = $GLOBALS['post']->ID;
	return update_post_meta($post_id, '_bfox_tool_' . $key, $value);
}

/*
Bible Tool Source URL handling
*/

function bfox_tool_source_linker(BfoxRef $ref = null) {
	global $_bfox_tool_source_linker;
	if (is_null($_bfox_tool_source_linker)) {
		$_bfox_tool_source_linker = new BfoxBibleToolLink();
		if (is_null($ref)) $ref = bfox_ref();
	}

	if (!is_null($ref)) $_bfox_tool_source_linker->setRef($ref);
	return $_bfox_tool_source_linker;
}

function bfox_tool_source_url($post_id = 0, BfoxRef $ref = null) {
	if (empty($post_id)) $post_id = $GLOBALS['post']->ID;
	$template = bfox_tool_meta('url', $post_id);

	if (empty($template)) {
		if (is_null($ref)) $ref = bfox_ref();
		return add_query_arg('src', true, bfox_ref_url($ref->get_string(), $post_id));
	}

	$linker = bfox_tool_source_linker($ref);
	return $linker->urlForTemplate($template);
}

function is_bfox_tool_link() {
	$url = bfox_tool_meta('url');
	return !empty($url);
}

/*
Bible Tool Queries
*/

function bfox_tool_query($args = array()) {
	$args['post_type'] = 'bfox_tool';
	return new WP_Query($args);
}

/*
Functions for remembering the most recent used Bible Reference
*/

function bfox_tool_last_viewed_ref_str() {
	global $user_ID;
	if ($user_ID) return get_user_option('bfox_tool_last_viewed_ref_str');
	return $_COOKIE['bfox_tool_last_viewed_ref_str'];
}

function bfox_tool_set_last_viewed_ref_str($ref_str) {
	global $user_ID;
	if ($user_ID) update_user_option($user_ID, 'bfox_tool_last_viewed_ref_str', $ref_str, true);
	else setcookie('bfox_tool_last_viewed_ref_str', $ref_str, /* 30 days from now: */ time() + 60 * 60 * 24 * 30, '/');
}

/*
Functions for remembering the most recent used Bible Tool
*/

function selected_bfox_tool_post_id() {
	global $_selected_bfox_tool_post_id;

	if (!$_selected_bfox_tool_post_id) {
		// First try to get the selected BfoxTool from the cookies
		$post_id = $_COOKIE['selected_bfox_tool'];

		// Make sure that the cookied post id is actually a BfoxTool
		if ($post_id) {
			$post = &get_post($post_id);
			if ('bfox_tool' != $post->post_type) $post_id = 0;
		}

		// If we didn't get a BfoxTool from the cookies, just get the first one from a query
		if (!$post_id) {
			$tools = bfox_tool_query();
			if ($tools->have_posts()) {
				$post = $tools->next_post();
				$post_id = $post->ID;
			}
		}

		$_selected_bfox_tool_post_id = $post_id;
	}

	return $_selected_bfox_tool_post_id;
}

function the_selected_bfox_tool_post() {
	global $post;
	$post = get_post(selected_bfox_tool_post_id());
	setup_postdata($post);
}

?>