<?php

	function bfox_widget_recent_readings($args) {
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
				$url = BfoxBlog::reading_plan_url($plan->id);
				$content .= '<li><a href="' . $url . '">' . $plan->name . '</a><ul>';

				$oldest = $plan->current_reading_id - $limit + 1;
				if ($oldest < 0) $oldest = 0;
				for ($index = $plan->current_reading_id; $index >= $oldest; $index--) {
					$scripture_link = '<a href="' . BfoxBlog::reading_plan_url($plan->id, $index) . '">' . $plan->readings[$index]->get_string() . '</a>';
					$content .= '<li>' . $scripture_link . ' on ' . $plan->date($index, 'M d') . '</li>';
				}
				$content .= '</ul></li>';
			}
		}

		echo $before_widget . $before_title . $title . $after_title . '<ul>' . $content . '</ul>' . $after_widget;
	}

	function bfox_widgets_init() {
		register_sidebar_widget('Recent Readings', 'bfox_widget_recent_readings');
	}

?>
