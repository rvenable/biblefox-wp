<?php

	function bfox_widget_special_pages($args)
	{
		extract($args);
		$title = 'Special Pages';
		$content = '';
		global $bfox_specials;
		foreach ($bfox_specials->pages as $page)
		{
			$content .= '<li><a href="' . $page['url'] . '">' . $page['title'] . '</a></li>';
		}
		echo $before_widget . $before_title . $title . $after_title . '<ul>' . $content . '</ul>' . $after_widget;
	}
	
	function bfox_widgets_init()
	{
		register_sidebar_widget('Special Pages', 'bfox_widget_special_pages');
	}

?>
