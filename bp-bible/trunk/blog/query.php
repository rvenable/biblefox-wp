<?php

/**
 * For generating HTTP Bible queries
 *
 */
class BfoxQuery {

	const default_base = 'bible';

	const page_reader = 'reader';
	const page_passage = 'passage';
	const page_commentary = 'commentary';
	const page_search = 'search';
	const page_history = 'history';
	const page_plans = 'plans';

	const var_page = 'bible_page';
	const var_translation = 'bible_trans';
	const var_reference = 'bible_ref';
	const var_search = 'bible_search';
	const var_message = 'bible_message';
	const var_toggle_read = 'bible_read';
	const var_display = 'bible_display';

	const var_pretty_query = 'bfox_pquery';

	const display_ajax = 'ajax';

	const var_plan_id = 'plan_id';

	private static $url = '';
	private static $use_pretty_urls = FALSE;

	public static function set_url($url, $use_pretty_urls = FALSE, $base = self::default_base) {
		$url = rtrim($url, '/') . '/';
		self::$url = $url;
		self::$use_pretty_urls = $use_pretty_urls;
		if ($use_pretty_urls) {
			$base = trim($base, '/');
			self::$url .= $base . '/';
		}
	}

	public static function url($args = array()) {
		$url = self::$url;

		if (self::$use_pretty_urls) {
			$ref_str = '';
			if (!empty($args[self::var_reference])) $ref_str = trim(trim($args[self::var_reference]), '/');
			if (!empty($args[self::var_translation])) $ref_str = trim(trim($args[self::var_translation]), '/') . '/' . $ref_str;

			// Don't send a page var if it is the passage page
			if (self::page_passage == $args[self::var_page]) unset($args[self::var_page]);

			unset($args[self::var_reference]);
			unset($args[self::var_translation]);

			if (!empty($ref_str)) $url .= urlencode($ref_str) . '/';
		}
		elseif (empty($args[self::var_page])) $args[self::var_page] = self::page_passage;

		return add_query_arg(array_map('urlencode', array_filter($args)), $url);
	}

	public static function page_url($page, $args = array()) {
		$args[self::var_page] = $page;
		return self::url($args);
	}

	public static function search_url($search_text, $ref_str = '', $args = array()) {
		$args[self::var_page] = self::page_search;
		$args[self::var_search] = $search_text;
		if (!empty($ref_str)) $args[self::var_reference] = $ref_str;

		return self::url($args);
	}

	public static function ref_url($ref_str = '', $trans_str = '', $args = array()) {
		global $bp;
		$url = $bp->root_domain . '/' . $bp->bible->slug . '/' . urlencode($ref_str);
		if (!empty($trans_str)) $url = add_query_arg('trans', urlencode($trans_str), $url);

		return $url;
	}

	public static function reading_plan_url($plan_id, $editor_url = '') {
		global $bp;
		//return $bp->root_domain . '/' . BfoxBpPlans::slug . '/' . $plan_id . '/';
		return $bp->loggedin_user->domain . BfoxBpPlans::slug . '/' . $plan_id . '/';
	}

	public static function add_display_type($type, $url) {
		return add_query_arg(self::var_display, $type, $url);
	}
}

?>