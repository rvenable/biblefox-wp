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
			add_rewrite_rule("$base\/?(.*)$", 'index.php?' . self::var_pretty_query . '=/$matches[1]');
		}
	}

	public static function page_url($page) {
		return add_query_arg(self::var_page, $page, self::$url);
	}

	public static function search_page_url($search_text, $ref_str = '', BfoxTrans $display_translation = NULL) {
		$url = add_query_arg(self::var_search, urlencode($search_text), self::page_url(self::page_search));
		if (!empty($ref_str)) $url = add_query_arg(self::var_reference, urlencode($ref_str), $url);
		if (!is_null($display_translation)) $url = add_query_arg(self::var_translation, $display_translation->id, $url);

		return $url;
	}

	public static function ref_url($ref_str = '', $trans_str = '') {
		if (self::$use_pretty_urls) {
			if (!empty($trans_str)) $ref_str = "$trans_str/$ref_str";
			return self::$url . urlencode($ref_str) . '/';
		}
		else {
			$url = self::page_url(self::page_passage);
			if (!empty($ref_str)) $url = add_query_arg(self::var_reference, urlencode($ref_str), $url);
			if (!empty($trans_str)) $url = add_query_arg(self::var_translation, $trans_str, $url);
			return $url;
		}
	}

	public static function passage_page_url($ref_str = '', BfoxTrans $translation = NULL) {
		if (!is_null($translation)) $trans_str = $translation->id;
		return self::ref_url($ref_str, $trans_str);
	}

	public static function reading_plan_url($plan_id, $editor_url = '') {
		if (empty($editor_url)) $editor_url = self::page_url(self::page_plans);
		return add_query_arg(self::var_plan_id, $plan_id, $editor_url);
	}

	public static function toggle_read_url($time, $url = '') {
		if (empty($url)) $url = self::page_url(self::page_history);

		return add_query_arg(self::var_toggle_read, urlencode($time), $url);
	}

	public static function add_display_type($type, $url) {
		return add_query_arg(self::var_display, $type, $url);
	}

	// TODO3: Are we still using this?
	public static function sidebar_list() {
		?>
		<ul>
			<li><a href="<?php echo self::page_url(self::page_passage) ?>">Bible Reader</a></li>
		</ul>
		<?php
	}
}

?>