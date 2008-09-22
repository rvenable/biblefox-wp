<?php

	function bfox_create_plan_menu()
	{
		$text = (string) $_GET['books'];
		$period_length = (string) $_GET['frequency'];
		$section_size = (int) $_GET['num_chapters'];
		
	?>
<div class="wrap">
<h2>Create a Reading Plan</h2>
<form action="admin.php" method="get">
<input type="hidden" name="page" value="<?php echo BFOX_PLAN_SUBPAGE; ?>">
<input type="hidden" name="hidden_field" value="Y">
Which books?<br/>
<textarea rows="5" cols="20" wrap="physical" name="books"><?php echo $text; ?></textarea><br/>
How Fast?<br/>
<input type="text" size="10" maxlength="40" name="num_chapters" value="<?php echo $section_size; ?>"> chapters per
<select name="frequency" value="<?php echo $period_length; ?>">
<option>day</option>
<option>week</option>
<option>month</option>
</select>
<input type="submit" class="button" />
</form>
</div>
<?php
	}
	
	function bfox_get_sections_slow($text, $size)
	{
		// NOTE: This function was designed to replace the bfox_get_sections() function for creating a reading plan
		// It ended up being much slower however, since it is doing way too many DB queries
		// The DB queries are called by $this->get_size()
		$refs = new BibleRefs($text);
		$size_vector = new BibleRefVector(array(0, $size, 0));

		$sections = array();
		while ($refs->is_valid())
		{
			$shifted_refs = $refs->shift($size_vector);
			if ($shifted_refs->is_valid())
				$sections[] = $shifted_refs;
		}

		return $sections;
	}

	function bfox_blog_reading_plans()
	{
		global $bfox_plan;
		$plan_ids = $bfox_plan->get_plan_ids();
		foreach ($plan_ids as $plan_id)
		{
			$page = BFOX_PLAN_SUBPAGE;
			$delete_url = "admin.php?page=$page&amp;action=delete&amp;plan_id=$plan_id";
			$personal_url = "admin.php?page=$page&amp;action=use_personal&amp;plan_id=$plan_id";
			echo "<strong>Plan $plan_id</strong> [<a href=\"$delete_url\">remove</a>] [<a href=\"$personal_url\">track your progress</a>]<br/>";
			$sections = $bfox_plan->get_plan_list($plan_id);
			echo "<br/>";
		}
	}
	
	function bfox_user_reading_plans($blogs)
	{
		global $bfox_plan_progress;
		$plan_ids = $bfox_plan_progress->get_plan_ids();
		foreach ($plan_ids as $plan_id)
		{
			$page = BFOX_PLAN_SUBPAGE;
			$delete_url = $blog_url . "page=$page&amp;action=delete&amp;plan_id=$plan_id";
			echo "<strong>Plan $plan_id</strong> [<a href=\"$delete_url\">remove</a>]<br/>";
			$sections = $bfox_plan_progress->get_plan_list($plan_id);
			echo "<br/>";
		}

		foreach ($blogs as $blog_id => $blog_info)
		{
			$blog_url = $blog_info->siteurl . '/wp-admin/admin.php?';
			$plan_url = $blog_url . 'page=' . BFOX_PLAN_SUBPAGE;
			echo "<strong><a href=\"$plan_url\">$blog_info->blogname</a></strong> - ";
			//$blog_plan = new PlanSource($blog_id);
			$blog_plans = array();// $blog_plan->get_plans();
			if (0 < count($blog_plans))
			{
				foreach ($blog_plans as $plan)
				{
					echo $plan->name;
					if ($last && $next)
						echo " You last read __ and should next read __.<br/>";
					else
						echo "Not tracked. You can choose to follow this reading plan.<br/>";
				}
			}
			else
			{
				echo "This Bible Study Blog currently has no reading plans. You should create one. <br/>";
			}
			echo "<br/>";
		}
	}

	function bfox_progress_page()
	{
		global $user_ID;
		$blogs = get_blogs_of_user($user_ID);

		echo "<div class=\"wrap\">";
		echo "<h2>Bible Study Blogs</h2>";
		if (0 < count($blogs))
		{
			echo "You are a part of the following Bible Study Blogs:<br/>";
			echo "<ul>";
			foreach ($blogs as $blog_id => $blog_info)
				echo "<li>$blog_info->blogname</li>";
			echo "</ul>";
		}
		echo "You can always create a new Bible Study Blog. <br/>";
		echo "</div>";

		echo "<div class=\"wrap\">";
		echo "<h2>Reading Plans</h2><br/>";
		if (0 < count($blogs))
		{
			bfox_user_reading_plans($blogs);
		}
		else
		{
			echo "You need to be part of a Bible Study Blog to have a reading plan. Feel free to join one or create your own.<br/>";
		}
		echo "</div>";
	}

	function bfox_create_plan()
	{
		global $bfox_plan, $bfox_plan_progress;

		if (isset($_GET['plan_id']))
		{
			if ($_GET['action'] == 'delete')
				$bfox_plan->delete($_GET['plan_id']);
			else if ($_GET['action'] == 'use_personal')
				$bfox_plan_progress->copy_plan($_GET['plan_id']);
		}

		if($_GET['hidden_field'] == 'Y')
		{
			$text = (string) $_GET['books'];
			$period_length = (string) $_GET['frequency'];
			$section_size = (int) $_GET['num_chapters'];
			if ($section_size == 0) $section_size = 1;

			$refs = new BibleRefs($text);
			$plan = array();
			$plan['refs_array'] = $refs->get_sections($section_size);
			$bfox_plan->add_new_plan((object) $plan);
//			$sections = bfox_get_sections_slow($text, $section_size);
		}

		echo "<div class=\"wrap\">";
		echo "<h2>Available Reading Plans</h2><br/>";
		bfox_blog_reading_plans();
		echo "</div>";
		
		bfox_create_plan_menu();
	}

	function bfox_get_recent_scriptures_output($max = 1, $read = false)
	{
		global $bfox_history;

		$output = '';
		$refs_array = $bfox_history->get_refs_array($max, $read);
		if (0 < count($refs_array))
		{
			$read_str = $read? 'Read' : 'Viewed';
			$output .= "<h3>Recently $read_str Scriptures</h3>";
			foreach ($refs_array as $refs)
			{
				$output .= bfox_get_bible_link($refs->get_string()) . '<br/>';
			}
		}
		return $output;
	}

	function bfox_get_special_page_plan()
	{
		$content = '';

		// Get the recently read scriptures
		$content .= bfox_get_recent_scriptures_output(10, true);

		// Get the recently viewed scriptures
		$content .= bfox_get_recent_scriptures_output(10, false);

		$special_page = array();
		$special_page['post_title'] = 'Reading Plan';
		$special_page['post_content'] = $content;
		$special_page['post_type'] = 'page';
		return $special_page;
	}

?>
