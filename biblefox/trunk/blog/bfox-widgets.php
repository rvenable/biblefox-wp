<?php

	function bfox_widget_recent_readings($args)
	{
		global $bfox_plan;

		extract($args);
		$title = 'Recent Readings';
		$content = '';

		if (empty($limit)) $limit = 4;

		$content = '';
		$blog_plans = $bfox_plan->get_plans();
		if (0 < count($blog_plans))
		{
			foreach ($blog_plans as $plan)
			{
				if (isset($plan->current_reading))
				{

					$url = BfoxBlog::reading_plan_url($plan->id);
					$content .= '<li><a href="' . $url . '">' . $plan->name . '</a><ul>';

					$oldest = $plan->current_reading - $limit + 1;
					if ($oldest < 0) $oldest = 0;
					for ($index = $plan->current_reading; $index >= $oldest; $index--)
					{
						$scripture_link = '<a href="' . BfoxBlog::reading_plan_url($plan->id, $index) . '">' . $plan->refs[$index]->get_string() . '</a>';
						$content .= '<li>' . $scripture_link . ' on ' . date('M d', $plan->dates[$index]) . '</li>';
					}
					$content .= '</ul></li>';
				}
			}
		}

		echo $before_widget . $before_title . $title . $after_title . '<ul>' . $content . '</ul>' . $after_widget;
	}

	function bfox_widgets_init()
	{
		register_sidebar_widget('Recent Readings', 'bfox_widget_recent_readings');
	}

?>
