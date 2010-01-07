<?php

require_once BFOX_PLANS_DIR . '/plans.php';

class BfoxBlogPlans {
	const var_plan_id = 'bfox_plan_id';
	const var_reading_id = 'bfox_reading_id';
	const page_manage_plan = 'bfox-manage-plan';
	const user_level_plans = 7;

	public static function init() {
		// TODO3: Blog reading plans are temporarily disabled for non-site admins
		if (is_site_admin()) add_action('admin_menu', 'BfoxBlogPlans::add_menu');

		add_filter('query_vars', 'BfoxBlogPlans::query_vars');
		add_action('parse_query', 'BfoxBlogPlans::parse_query');

		register_sidebar_widget('Recent Readings', 'BfoxBlogPlans::widget_recent_readings');
	}

	public static function add_menu() {
		// Add the reading plan page to the Post menu along with the corresponding load action
		add_submenu_page('post-new.php', 'Reading Plans', 'Reading Plans', self::user_level_plans, self::page_manage_plan, 'BfoxBlogPlans::plan_editor');
		add_action('load-' . get_plugin_page_hookname(self::page_manage_plan, 'post-new.php'), 'BfoxBlogPlans::plan_editor_load');
	}

	public static function plan_editor_load() {
		global $blog_id, $bfox_plan_editor;

		require_once BFOX_PLANS_DIR . '/edit.php';
		$bfox_plan_editor = new BfoxPlanEdit($blog_id, BfoxPlans::user_type_blog, '');
		$bfox_plan_editor->page_load();
	}

	public static function plan_editor() {
		global $bfox_plan_editor;
		$bfox_plan_editor->content();
	}

	public static function query_vars($qvars) {
		$qvars[] = self::var_plan_id;
		$qvars[] = self::var_reading_id;
		return $qvars;
	}

	public static function parse_query($wp_query) {
		$showing_refs = FALSE;

		if (isset($wp_query->query_vars[self::var_plan_id])) {
			self::set_reading_plan($wp_query->query_vars[self::var_plan_id], $wp_query->query_vars[self::var_reading_id]);
			$wp_query->is_home = FALSE;
			$showing_refs = TRUE;
		}

		if ($showing_refs) BfoxUtility::enqueue_style('bfox_scripture');
	}

	public static function widget_recent_readings($args) {
		global $blog_id;

		extract($args);
		$title = 'Recent Readings';
		$content = '';

		if (empty($limit)) $limit = 4;

		$content = '';
		list($plans, $subs) = BfoxPlans::get_user_plans($blog_id, BfoxPlans::user_type_blog);
		foreach ($subs as $sub) if (isset($plans[$sub->plan_id]) && !$sub->is_finished) {
			$plan = $plans[$sub->plan_id];
			if ($plan->is_current()) {
				$url = self::plan_url($plan->id);
				$content .= '<li><a href="' . $url . '">' . $plan->name . '</a><ul>';

				$oldest = $plan->current_reading_id - $limit + 1;
				if ($oldest < 0) $oldest = 0;
				for ($index = $plan->current_reading_id; $index >= $oldest; $index--) {
					$scripture_link = '<a href="' . self::plan_url($plan->id, $index) . '">' . $plan->readings[$index]->get_string() . '</a>';
					$content .= '<li>' . $scripture_link . ' on ' . $plan->date($index, 'M d') . '</li>';
				}
				$content .= '</ul></li>';
			}
		}

		echo $before_widget . $before_title . $title . $after_title . '<ul>' . $content . '</ul>' . $after_widget;
	}

	public static function plan_url($plan_id, $reading_id = -1) {
		$url = get_option('home') . '/?' . self::var_plan_id . '=' . $plan_id;
		if (0 <= $reading_id) $url .= '&' . self::var_reading_id . '=' . ($reading_id + 1);
		return $url;
	}

	public static function set_reading_plan($plan_id = 0, $reading_id = 0) {
		global $blog_id;

		$refs = new BfoxRefs;
		$new_posts = array();

		$plan = BfoxPlans::get_plan($plan_id);
		if ($plan->is_current()) {

			// If there is no reading set, use the current reading
			// If there is a reading set, we need to decrement it to make it zero-based
			if (empty($reading_id)) $reading_id = $plan->current_reading_id;
			else $reading_id--;

			$refs->add_refs($plan->readings[$reading_id]);
			$new_posts []= self::create_reading_post($plan, $reading_id);
		}

		if ($refs->is_valid()) BfoxBlogQueryData::set_post_ids($refs);
		if (!empty($new_posts)) BfoxBlogQueryData::add_pre_posts($new_posts);
	}

	/**
	 * Creates a post with reading content
	 *
	 * @param $plan
	 * @param $reading_id
	 * @return object new_post
	 */
	private static function create_reading_post(BfoxReadingPlan $plan, $reading_id) {
		$refs = $plan->readings[$reading_id];
		$ref_str = $refs->get_string();

		// Create the navigation bar with the prev/write/next links
		$nav_bar = "<div class='bible_post_nav'>";
		if (isset($plan->readings[$reading_id - 1])) {
			$prev_ref_str = $book_name . ' ' . ($ch1 - 1);
			$nav_bar .= '<a href="' . self::plan_url($plan->id, $reading_id - 1) . '" class="bible_post_prev">&lt; ' . $plan->readings[$reading_id - 1]->get_string() . '</a>';
		}
		$nav_bar .= BfoxBlog::ref_write_link($refs->get_string(), 'Write about this passage');
		if (isset($plan->readings[$reading_id + 1])) {
			$next_ref_str = $book_name . ' ' . ($ch2 + 1);
			$nav_bar .= '<a href="' . self::plan_url($plan->id, $reading_id + 1) . '" class="bible_post_next">' . $plan->readings[$reading_id + 1]->get_string() . ' &gt;</a>';
		}
		$nav_bar .= "</div>";

		$new_post = BfoxBlogQueryData::add_verse_post_content(array(), $refs, $nav_bar);
		$new_post['ID'] = -1;
		$new_post['post_title'] = $ref_str;
		$new_post['bible_ref_str'] = $ref_str;
		$new_post['post_type'] = BfoxBlog::post_type_bible;
		$new_post['bfox_permalink'] = self::plan_url($plan->id, $reading_id);
		$new_post['bfox_author'] = '<a href="' . self::plan_url($plan->id) . '">' . $plan->name . ' (Reading ' . ($reading_id + 1) . ')</a>';

		// Set the date according to the reading plan if possible, otherwise set it to the current date
		$new_post['post_date'] = $new_post['post_date_gmt'] = $plan->date($reading_id, 'Y-m-d H:i:s');

		// Turn off comments
		$new_post['comment_status'] = 'closed';
		$new_post['ping_status'] = 'closed';

		return (object) $new_post;
	}
}
add_action('init', 'BfoxBlogPlans::init');

?>