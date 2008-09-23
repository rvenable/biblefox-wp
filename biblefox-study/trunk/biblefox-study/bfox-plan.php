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
Title: <input type="text" size="10" maxlength="128" name="plan_name" value=""> <br/>
Summary: <input type="text" size="10" maxlength="128" name="plan_summary" value=""> <br/>
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

	function bfox_echo_plan_list($plan_list, $skip_read = false)
	{
		$unread_count = 0;
		foreach ($plan_list->original as $period_id => $original)
		{
			if ($skip_read && isset($plan_list->read[$period_id]) && !isset($plan_list->unread[$period_id])) continue;
			$index = $period_id + 1;
			echo "Reading $index: " . $original->get_link();
			if (isset($plan_list->unread[$period_id]))
			{
				if (isset($plan_list->read[$period_id]))
					echo " (You still need to read " . $plan_list->unread[$period_id]->get_link() . ")";
				else
					echo " (Unread)";
				$unread_count++;
				if ($unread_count == $max_unread) break;
			}
			else
			{
				if (isset($plan_list->read[$period_id]))
					echo " (Finished!)";
			}
			echo "<br/>";
		}
	}
	
	function bfox_blog_reading_plans($plans, $can_edit = false)
	{
		global $bfox_plan;
		foreach ($plans as $plan)
		{
			$page = BFOX_PLAN_SUBPAGE;
			$delete_url = "admin.php?page=$page&amp;action=delete&amp;plan_id=$plan->id";
			$track_url = "admin.php?page=$page&amp;action=track&amp;plan_id=$plan->id";
			$plan_list = $bfox_plan->get_plan_list($plan->id);

			echo "<h3>$plan->name</h3><p>";
			if (isset($plan->summary) && ('' != $plan->summary)) echo $plan->summary . '<br/>';
			if ($can_edit) echo "(<a href=\"$delete_url\">remove</a>) ";
			if (!isset($plan_list->read) && !isset($plan_list->unread))
				echo "(<a href=\"$track_url\">track your progress</a>)";
			echo '</p>';
			bfox_echo_plan_list($plan_list);
			echo "<br/>";
		}
	}
	
	function bfox_user_reading_plans($blogs)
	{
		global $bfox_plan_progress;
		$plan_url_base = 'admin.php?page=' . BFOX_PLAN_SUBPAGE . '&amp;';

		foreach ($blogs as $blog_id => $blog_info)
		{
			$blog_url = $blog_info->siteurl . '/wp-admin/admin.php?';
			$plan_url = $blog_url . 'page=' . BFOX_PLAN_SUBPAGE;
			echo "<strong><a href=\"$plan_url\">$blog_info->blogname</a></strong><br/>";
			$blog_plan = new PlanBlog($blog_id);
			$blog_plans = $blog_plan->get_plans();
			if (0 < count($blog_plans))
			{
				foreach ($blog_plans as $plan)
				{
					$plan_url = $plan_url_base . 'plan_id=' . $plan->id . '&amp;';
					echo "<strong>$plan->name</strong> (<a href=\"$plan_url\">view plan</a>)<br/><i>$plan->summary</i><br/>";
					$progress_plan_id = $bfox_plan_progress->get_plan_id($blog_id, $plan->id);
					if (isset($progress_plan_id))
					{
						$refs_object = $bfox_plan_progress->get_plan_refs($progress_plan_id);
						if (isset($refs_object->last_read))
							echo 'The furthest you have read is ' . $refs_object->read[$refs_object->last_read]->get_link() . '.<br/>';
						if (isset($refs_object->first_unread))
							echo 'You should read ' . $refs_object->unread[$refs_object->first_unread]->get_link() . ' next.<br/>';
					}
					else
					{
						$track_url = $plan_url . 'action=track&amp;';
						echo "Not tracked. You can choose to <a href=\"$track_url\">follow this reading plan</a>.<br/>";
					}
					echo '<br/>';
				}
			}
			else
			{
				echo "This Bible Study Blog currently has no reading plans.<br/>";
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
				echo "<li><a href=\"{$blog_info->siteurl}/wp-admin/\">$blog_info->blogname</a></li>";
			echo "</ul>";
		}
		$home_dir = get_option('home');
		echo "You can always <a href=\"{$home_dir}/wp-signup.php\">create a new Bible Study Blog</a>. <br/>";
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
		global $bfox_plan, $bfox_plan_progress, $blog_id;

		// Only level 7 users can edit/create plans
		$can_edit = current_user_can(7);

		if($can_edit && ($_GET['hidden_field'] == 'Y'))
		{
			$text = (string) $_GET['books'];
			$period_length = (string) $_GET['frequency'];
			$section_size = (int) $_GET['num_chapters'];
			if ($section_size == 0) $section_size = 1;
			
			$refs = new BibleRefs($text);
			$plan = array();
			$plan['refs_array'] = $refs->get_sections($section_size);
			$plan['name'] = (string) $_GET['plan_name'];
			$plan['summary'] = (string) $_GET['plan_summary'];
			$bfox_plan->add_new_plan((object) $plan);
			//			$sections = bfox_get_sections_slow($text, $section_size);
		}
		
		if (isset($_GET['plan_id']))
		{
			if ($_GET['action'] == 'delete')
			{
				if ($can_edit) $bfox_plan->delete($_GET['plan_id']);
			}
			else if ($_GET['action'] == 'track')
				$bfox_plan_progress->track_plan($blog_id, $_GET['plan_id']);

			$display_plans = $bfox_plan->get_plans($_GET['plan_id']);
		}

		echo "<div class=\"wrap\">";
		if (0 < count($display_plans))
		{
			$plan_url_base = 'admin.php?page=' . BFOX_PLAN_SUBPAGE . '&amp;';
			echo "<h2>View Reading Plan</h2><br/>";
			echo "You have selected the following Reading Plan: (<a href=\"$plan_url_base\">view all</a>)";
			bfox_blog_reading_plans($display_plans, $can_edit);
		}
		else
		{
			$display_plans = $bfox_plan->get_plans();

			echo "<h2>Available Reading Plans</h2><br/>";
			if (0 < count($display_plans))
			{
				echo "This Bible Study Blog has the following Reading Plans:";
				bfox_blog_reading_plans($display_plans, $can_edit);
			}
			else
			{
				echo "This Bible Study Blog has no bible reading plans.<br/>";
			}
		}
		echo "</div>";

		if ($can_edit) bfox_create_plan_menu();
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
