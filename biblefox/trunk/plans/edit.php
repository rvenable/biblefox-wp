<?php

class BfoxPlanEdit
{
	const var_submit = 'submit';

	const var_plan_id = BfoxQuery::var_plan_id;
	const var_plan_name = 'plan_name';
	const var_plan_description = 'plan_description';
	const var_plan_readings = 'plan_readings';
	const var_plan_passages = 'plan_passages';
	const var_plan_chunk_size = 'plan_chunk_size';
	const var_plan_is_private = 'plan_is_private';
	const var_plan_is_scheduled = 'plan_is_scheduled';
	const var_plan_start = 'plan_start';
	const var_plan_frequency = 'plan_frequency';
	const var_plan_freq_options = 'plan_freq_options';

	const var_user = 'user';
	const var_blog = 'blog';

	const var_action = 'plan_action';
	const action_edit = 'edit';
	const action_delete = 'delete';
	const action_subscribe = 'subscribe';
	const action_unsubscribe = 'unsubscribe';
	const action_mark_finished = 'mark_finished';
	const action_mark_unfinished = 'mark_unfinished';
	const action_copy = 'copy';

	private $user_id;
	private $user_type;
	private $url;

	public function __construct($user_id, $user_type, $url) {
		$this->user_id = $user_id;
		$this->user_type = $user_type;
		$this->url = $url;

		BfoxUtility::enqueue_style('bfox_plans', 'plans/plans.css');
	}

	public function page_load() {

		$messages = array();
		$redirect = '';

		if (!empty($_POST[self::var_submit]) && isset($_POST[self::var_plan_id]) && isset($_POST[self::var_action])) {

			// Get the passed in plan and this user's subscription to it
			$plan = BfoxPlans::get_plan($_POST[self::var_plan_id]);
			$my_sub = BfoxPlans::get_sub($plan, $this->user_id, $this->user_type);

			if ($my_sub->is_visible($plan)) switch ($_POST[self::var_action]) {
				case self::action_edit:
					if (isset($_POST[self::var_plan_name])) {

						// If this is a new plan, create a new subscription where we are the owner
						// Otherwise, get the current subscription
						if (empty($plan->id)) {
							$my_sub = new BfoxReadingSub(NULL, 0, $this->user_id, $this->user_type);
							$my_sub->is_subscribed = TRUE;
							$my_sub->is_owned = TRUE;
						}

						// We can only edit the plan if we are an owner
						if ($my_sub->is_owned) {
							$plan->name = strip_tags(stripslashes($_POST[self::var_plan_name]));
							$plan->description = strip_tags(stripslashes($_POST[self::var_plan_description]));
							$plan->set_readings_by_strings(stripslashes($_POST[self::var_plan_readings]));
							$plan->add_passages(stripslashes($_POST[self::var_plan_passages]), $_POST[self::var_plan_chunk_size]);
							$plan->is_private = $_POST[self::var_plan_is_private];
							$plan->is_scheduled = $_POST[self::var_plan_is_scheduled];
							$plan->set_start_date($_POST[self::var_plan_start]);
							$plan->frequency = $_POST[self::var_plan_frequency];
							$plan->set_freq_options((array) $_POST[self::var_plan_freq_options]);
							$plan->finish_setting_plan();

							BfoxPlans::save_plan($plan);
							$messages []= "Reading Plan ($plan->name) Saved!";
							$redirect = $this->plan_url($plan->id);

							// If the subscription plan is not set, this must be a new subscription that we need to save
							if (empty($my_sub->plan_id)) {
								$my_sub->plan_id = $plan->id;
								BfoxPlans::save_sub($my_sub);
							}
						}
					}
					break;
				case self::action_delete:
					// We can only delete plans that we own
					if ($my_sub->is_owned) {
						BfoxPlans::delete_plan($plan);
						$messages []= "Reading Plan ($plan->name) Deleted!";
						$redirect = $this->url;
					}
					break;
				case self::action_copy:
					$plan->set_as_copy();
					BfoxPlans::save_plan($plan);
					$my_sub->plan_id = $plan->id;
					$my_sub->is_subscribed = TRUE;
					$my_sub->is_owned = TRUE;
					BfoxPlans::save_sub($my_sub);
					$messages []= "Reading Plan ($plan->name) Saved!";
					$redirect = $this->url;
					break;
				case self::action_subscribe:
					$my_sub->is_subscribed = TRUE;
					BfoxPlans::save_sub($my_sub);
					$messages []= "Subscribed to Reading Plan ($plan->name)!";
					$redirect = $this->url;
					break;
				case self::action_unsubscribe:
					$my_sub->is_subscribed = FALSE;
					BfoxPlans::save_sub($my_sub);
					$messages []= "Unsubscribed to Reading Plan ($plan->name)!";
					$redirect = $this->url;
					break;
				case self::action_mark_finished:
					$my_sub->is_finished = TRUE;
					BfoxPlans::save_sub($my_sub);
					$messages []= "Marked Reading Plan ($plan->name) as Finished!";
					$redirect = $this->url;
					break;
				case self::action_mark_unfinished:
					$my_sub->is_finished = FALSE;
					BfoxPlans::save_sub($my_sub);
					$messages []= "Marked Reading Plan ($plan->name) as Unfinished!";
					$redirect = $this->url;
					break;
			}
		}
		$message = implode('<br/>', $messages);

		if (!empty($redirect)) wp_redirect(add_query_arg(BfoxQuery::var_message, urlencode($message), $redirect));
	}

	public function content() {
		if (!empty($_GET[BfoxQuery::var_message])) {
			?>
			<div id="page_message"><?php echo strip_tags(stripslashes(urldecode($_GET[BfoxQuery::var_message])), '<br/>') ?></div>
			<?php
			$_SERVER['REQUEST_URI'] = remove_query_arg(array(BfoxQuery::var_message), $_SERVER['REQUEST_URI']);
		}


		if (isset($_GET[self::var_plan_id])) {
			$plan = BfoxPlans::get_plan($_GET[self::var_plan_id]);
			$confirm = '';
			if (isset($_GET[self::var_action])) {
				switch ($_GET[self::var_action]) {
					case self::action_delete:
						$confirm = __('Are you sure you want to delete ') . $this->plan_link($plan->id, $plan->name) .
							__('?<br/><b>This will delete it for all of its other subscribers as well.</b><br/>If you don\'t want to delete it, you can just ') .
							$this->plan_action_link($plan->id, self::action_unsubscribe, __('unsubscribe')) . __(' from it.');
						break;
					case self::action_copy:
						$confirm = __('Are you sure you want to copy ') . $this->plan_link($plan->id, $plan->name) . __('?');
						break;
					case self::action_subscribe:
						$confirm = __('Are you sure you want to subscribe to ') . $this->plan_link($plan->id, $plan->name) . __('?');
						break;
					case self::action_unsubscribe:
						$confirm = __('Are you sure you want to unsubscribe from ') . $this->plan_link($plan->id, $plan->name) . __('?');
						break;
					case self::action_mark_finished:
						$confirm = __('Are you sure you want to mark ') . $this->plan_link($plan->id, $plan->name) . __(' as finished? This is for when you have finished reading everything in this plan.');
						break;
					case self::action_mark_unfinished:
						$confirm = __('Are you sure you want to mark ') . $this->plan_link($plan->id, $plan->name) . __(' as unfinished?');
						break;
				}
			}
			if (empty($confirm)) $this->view_plan($plan);
			else $this->confirm_page($confirm, $plan);
		}
		else {
			if (!empty($_GET[self::var_user])) {
				$user_var = $_GET[self::var_user];

				$user = get_userdata($user_var);
				if (!$user) $user = get_userdatabylogin($user_var);
				if (!$user) $user = get_user_by_email($user_var);
				if (!$user) {
					echo "<h2>User Search Failed</h2><p>No user could be found for '$user_var'.</p>";
					$this->find_plans($user_var);
				}
				else {
					echo "<h2>Reading Plans for User: $user->display_name</h2>";
					$this->view_user_plans($user->ID, BfoxPlans::user_type_user);
				}
			}
			elseif (!empty($_GET[self::var_blog])) {
				$blog_var = $_GET[self::var_blog];

				$blog_id = get_id_from_blogname($blog_var);
				if (!$blog_id) $blog_id = (int) $blog_var;
				if (!$blog_id) {
					echo "<h2>Blog Search Failed</h2><p>No blog could be found for '$blog_var'.</p>";
					$this->find_plans();
				}
				else {
					echo "<h2>Reading Plans for blog: <a href='" . get_blogaddress_by_id($blog_id) . "'>" . get_blog_option($blog_id, 'blogname') . "</a></h2>";
					$this->view_user_plans($blog_id, BfoxPlans::user_type_blog);
				}
			}
			else {
				echo "<h2>My Reading Plans</h2>";
				$this->view_user_plans($this->user_id, $this->user_type);
			}
		}
	}

	private function plan_url($plan_id) {
		return BfoxQuery::reading_plan_url($plan_id, $this->url);
	}

	private function plan_link($plan_id, $str) {
		return "<a href='" . $this->plan_url($plan_id) . "'>$str</a>";
	}

	private function edit_plan_link($plan_id, $str) {
		return "<a href='" . $this->plan_url($plan_id) . "#edit'>$str</a>";
	}

	private function plan_action_url($plan_id, $action) {
		return add_query_arg(array(self::var_plan_id => $plan_id, self::var_action => $action), $this->url);
	}

	private function plan_action_link($plan_id, $action, $str) {
		return "<a href='" . $this->plan_action_url($plan_id, $action) . "'>$str</a>";
	}

	private function return_link($str = '') {
		if (empty($str)) $str = __('My Reading Plan List');
		return "<a href='$this->url'>$str</a>";
	}

	private function user_url($user_id, $user_type) {
		if (BfoxPlans::user_type_blog == $user_type) $var = self::var_blog;
		elseif (BfoxPlans::user_type_user == $user_type) $var = self::var_user;

		return add_query_arg($var, $user_id, $this->url);
	}

	private function is_user_sub(BfoxReadingSub $sub) {
		return (($sub->user_id == $this->user_id) && ($sub->user_type == $this->user_type));
	}

	private function user_name(BfoxReadingSub $sub, $me = '') {
		if (!empty($me) && $this->is_user_sub($sub)) return $me;
		return $sub->user_name();
	}

	private function user_link(BfoxReadingSub $sub, $str = '', $me = 'me') {
		if (empty($str)) $str = $this->user_name($sub, $me);

		if ($this->is_user_sub($sub)) $url = $this->url;
		else $url = $this->user_url($sub->user_id, $sub->user_type);

		return "<a href='$url'>$str</a>";

	}

	private function schedule_desc(BfoxReadingPlan $plan) {
		if ($plan->is_scheduled) {
			$desc = $plan->start_date('M j, Y') . ' - ' . $plan->end_date('M j, Y');
			if ($plan->is_recurring) $desc .= ' (recurring)';
			$desc .= " (" . $plan->frequency_desc() . ")";
		}
		else $desc = 'Unscheduled';
		return $desc;
	}

	private function get_plan_options(BfoxReadingPlan $plan, BfoxReadingSub $my_sub) {
		$options = array();

		// Subscribe/Unsubscribe
		if ($my_sub->is_subscribed) {
			if ($my_sub->is_finished) $options []= $this->plan_action_link($plan->id, self::action_mark_unfinished, __('Mark as Unfinished'));
			else {
				$options []= $this->plan_action_link($plan->id, self::action_mark_finished, __('Mark as Finished'));
				$options []= $this->plan_action_link($plan->id, self::action_unsubscribe, __('Unsubscribe'));
			}
		}
		else $options []= $this->plan_action_link($plan->id, self::action_subscribe, __('Subscribe'));

		// Edit
		if ($my_sub->is_owned) {
			$options []= $this->edit_plan_link($plan->id, __('Edit'));
			$options []= $this->plan_action_link($plan->id, self::action_delete, __('Delete'));
		}

		// Copy
		$options []= $this->plan_action_link($plan->id, self::action_copy, __('Copy'));

		return $options;
	}

	private function confirm_page($confirm) {
		$hiddens = '';
		if (!empty($_GET[self::var_plan_id])) $hiddens .= BfoxUtility::hidden_input(self::var_plan_id, $_GET[self::var_plan_id]);
		if (!empty($_GET[self::var_action])) $hiddens .= BfoxUtility::hidden_input(self::var_action, $_GET[self::var_action]);

		?>
		<h2>Confirm Action</h2>
		<p><?php echo $this->return_link() ?></p>
		<form action='<?php echo $this->url ?>' method='post'>
		<p><?php echo $confirm . $hiddens ?></p>
		<p><input type='submit' name='<?php echo self::var_submit ?>' value='<?php echo __('Confirm') ?>' class='button'/></p>
		</form>

		<?php
	}

	private function view_user_plans($user_id, $user_type) {

		$is_user = (($user_id == $this->user_id) && ($user_type == $this->user_type));

		list($plans, $subs) = BfoxPlans::get_user_plans($user_id, $user_type);

		$my_subs = array();
		if (!$is_user) $my_subs = BfoxPlans::get_user_subs($this->user_id, $this->user_type);

		$create_link = $this->plan_link(0, __('Create a new reading plan'));

		$plans_table = new BfoxHtmlTable("id='reading_plans' class='widefat'");
		$plans_table->add_header_row('', 3, 'Reading Plan', 'Schedule', 'Options');
		if ($is_user) $plans_table->add_footer_row('', 3, $create_link);

		$finished_table = new BfoxHtmlTable("id='reading_plans' class='widefat'");
		$finished_table->add_header_row('', 3, 'Reading Plan', 'Schedule', 'Options');

		// Fill the plans table with all the plans this user is subscribed to
		foreach ($subs as $sub) if (isset($plans[$sub->plan_id])) {
			$plan = $plans[$sub->plan_id];

			// Determine the subscription for the current user
			if ($is_user) $my_sub = $sub;
			elseif (isset($my_subs[$sub->plan_id])) $my_sub = $my_subs[$sub->plan_id];
			else $my_sub = new BfoxReadingSub(NULL, $plan->id);

			// If the subscription is visible to this user, add it to the table
			if ($my_sub->is_visible($plan)) {
				$row = new BfoxHtmlRow('',
				$this->plan_link($plan->id, $plan->name) . ($plan->is_private ? ' (private)' : '') . '<small>' . $plan->desc_html() . '</small>',
				$this->schedule_desc($plan),
				implode('<br/>', $this->get_plan_options($plan, $my_sub)));

				if ($my_sub->is_finished) $finished_table->add_row($row);
				else $plans_table->add_row($row);
			}
		}

		if (!$is_user) echo '<p>' . $this->return_link() . '</p>';

		?>

		<p>Reading plans allow you to organize how you read the Bible. You can create your own reading plans, or subscribe to someone else's plans.</p>

		<h3>Current Plans</h3>
		<?php if ($plans_table->row_count()): ?>
		<?php echo $plans_table->content() ?>
		<?php else: ?>
		<p>No current reading plans<?php if ($is_user) echo ": $create_link" ?></p>
		<?php endif ?>

		<?php if ($finished_table->row_count()): ?>
		<h3>Finished Plans</h3>
		<p>These are the plans you have already finished reading.</p>
		<?php echo $finished_table->content() ?>
		<?php endif ?>
		<?php $this->find_plans() ?>
		<?php
	}

	private function find_plans($user_search = '') {

		list($post_url, $hiddens) = BfoxUtility::get_post_url($this->url);

		global $user_ID;
		$your_blogs = (array) get_blogs_of_user($user_ID);

		?>
		<h3>Find Reading Plans</h3>
		<p>You can look up reading plans that others have created so that you can subscribe to them or copy them to use as a start for your own custom reading plan.</p>

		<?php if (!empty($your_blogs)): ?>
		<h4>Your Blogs</h4>
		<p>You can also use reading plans from blogs. Begin with your blogs:</p>
		<ul>
			<?php foreach ($your_blogs as $blog): ?>
			<li><a href="<?php echo $this->user_url($blog->userblog_id, BfoxPlans::user_type_blog) ?>"><?php echo $blog->blogname ?></a></li>
			<?php endforeach ?>
		</ul>
		<p>Also check out the reading plans on the main <a href='<?php echo $this->user_url(1, BfoxPlans::user_type_blog) ?>'>Biblefox.com blog</a>.</p>
		<?php endif ?>

		<h4>User Search</h4>
		<p>Type in anyone's username to view their reading plans.</p>
		<form action='<?php echo $post_url ?>' method='get'>
		<?php echo $hiddens ?>
		<p>
		<input type='text' name='<?php echo self::var_user ?>' value='<?php echo $user_search ?>'/>
		<input type='submit' value='User Search' class='button'/>
		</p>
		</form>

		<?php
	}

	private function plan_chart(BfoxReadingPlan $plan, BfoxReadingSub $my_sub, $max_cols = 3) {

		// If this plan is scheduled, not finished, and this is a user, not a blog, then use the history information
		$use_history = ($plan->is_scheduled && !$my_sub->is_finished && ($my_sub->user_type == BfoxPlans::user_type_user));

		$unread_readings = array();
		if ($use_history) {
			$use_history = $plan->set_history(BfoxHistory::get_history(0, $plan->history_start_date(), NULL, TRUE));
			$crossed_out = '<br/>' . __('*Note: Crossed out passages indicate that you have finished reading that passage');
		}

		$sub_table = new BfoxHtmlTable("class='reading_plan_col'");

		// Create the table header
		$header = new BfoxHtmlRow();
		$header->add_header_col('', '');
		$header->add_header_col('Passage', '');
		if ($plan->is_scheduled) $header->add_header_col('Date', '');
		//if (!empty($unread_readings)) $header->add_header_col('Unread', '');
		$sub_table->add_header_row($header);

		$total_refs = new BfoxRefs;

		foreach ($plan->readings as $reading_id => $reading) {
			$total_refs->add($reading);

			// Create the row for this reading
			if ($reading_id == $plan->current_reading_id) $row = new BfoxHtmlRow("class='current'");
			else $row = new BfoxHtmlRow();

			// Add the reading index column
			$row->add_col($reading_id + 1);

			// Add the bible reference column
			$attrs = '';
			if ($use_history) {
				// Calculate how much of this reading is unread
				$unread = $plan->get_unread($reading);

				// If this reading is 'read', then mark it as such
				if (!$unread->is_valid()) $attrs = "class='finished'";
			}
			$row->add_col(Biblefox::ref_link($reading->get_string(BibleMeta::name_short)), $attrs);

			// Add the Date column
			if ($plan->is_scheduled) $row->add_col($plan->date($reading_id, 'M d'));

			// Add the History column
			/*if (!empty($unread_readings)) {
				if (isset($unread_readings[$reading_id])) $row->add_col(Biblefox::ref_link($unread_readings[$reading_id]->get_string(BibleMeta::name_short)));
				else $row->add_col();
			}*/

			// Add the row to the table
			$sub_table->add_row($row);
		}

		if ($plan->is_private) $is_private = ' (private)';

		$table = new BfoxHtmlTable("class='reading_plan'",
			"<b>$plan->name$is_private</b><br/>
			<small>" . $plan->desc_html() . "<br/>
			Schedule: " . $this->schedule_desc($plan) . "<br/>
			Options: " . implode(', ', $this->get_plan_options($plan, $my_sub)) .
			"$crossed_out</small>");
		$table->add_row($sub_table->get_split_row($max_cols, 5));
		$table->add_row('', 1, array("<small>Combined passages covered by this plan: " . $total_refs->get_string(BibleMeta::name_short) . "</small>", "colspan='$max_cols'"));

		return $table->content();
	}

	private function view_plan(BfoxReadingPlan $plan) {

		if (empty($plan->id)) {
			$is_owned = TRUE;

			echo "<h2>Create Reading Plan</h2>";
			echo '<p>' . $this->return_link() . '</p>';
			echo "<p>Here you can create your own custom reading plan. If you don't really want to create a plan from scratch, try copying some of our plans from the main <a href='" . $this->user_url(1, BfoxPlans::user_type_blog) . "'>Biblefox.com blog</a>.</p>";
			echo "<p>Creating a reading plan is easy. You just give it a name and description, then add a bunch of bible passages, and finally add an optional schedule.</p>";
		}
		else {
			$is_owned = FALSE;

			// Users for this plan table
			$subs = BfoxPlans::get_plan_subs($plan);

			$user_table = new BfoxHtmlTable("class='widefat'");
			$user_table->add_header_row('', 2, 'Type', 'User');
			foreach ($subs as $sub) {
				$type = '';
				if (BfoxPlans::user_type_user == $sub->user_type) $type = 'User';
				elseif (BfoxPlans::user_type_blog == $sub->user_type) $type = 'Blog';
				$user_table->add_row('', 2, $type, $this->user_link($sub) . (($sub->is_owned) ? ' (manager)' : ''));
				if ($this->is_user_sub($sub)) $my_sub = $sub;
			}

			if (isset($my_sub) && $my_sub->is_visible($plan)) {
				$is_owned = $my_sub->is_owned;

				echo "<h2>View Reading Plan</h2>";
				echo '<p>' . $this->return_link() . '</p>';
				echo $this->plan_chart($plan, $my_sub);
				echo "<h3>Subscribers</h3>\n";
				echo "<p>These are the subscribers using this reading plan:</p>";
				echo $user_table->content();
			}
			else echo "<h2>Cannot find reading plan</h2><p>The reading plan you are looking for either does not exist or is set as private.</p>";
		}

		if ($is_owned) {
			echo "<h3 id='edit'>Edit Plan</h3>";
			$this->edit_plan($plan);
		}
	}

	private function edit_plan(BfoxReadingPlan $plan) {

		$table = new BfoxHtmlOptionTable("class='form-table'", "action='$this->url' method='post'",
			BfoxUtility::hidden_input(self::var_plan_id, $plan->id) . BfoxUtility::hidden_input(self::var_action, self::action_edit),
			"<p><input type='submit' name='" . self::var_submit . "' value='" . __('Save') . "' class='button'/></p>");

		$passage_help = __('<p>This allows you to add passages of the Bible to your reading plan in big chunks.</p>
			<p>You can type passages of the bible in the box, and then set how many chapters you want to read at a time. The passages will be cut into sections and added to your reading plan.</p>
			<p>Type any passages in the box above. For instance, to make a reading plan of all the gospels you could type "Matthew, Mark, Luke, John". You can use bible abbreviations (ie. "gen" instead of "Genesis"), and even specify chapters and verses (ie. "gen 1-3"). Separate passages can be separated with a comma (\',\'), semicolon (\';\'), or on separate lines.</p>');

		if (empty($plan->id)) {
			$readings_label = __('Add Readings (Option 1)');
			$groups_label = __('Add Readings (Option 2)');
			$readings_help = '<p>' . __('Add scriptures to your reading plan. Each line you enter will be a different reading in the plan. If you want to automatically add bible passages, skip to the next section: ') . $groups_label . '</p>' .
				'<p>' . __('<b>Tip</b>: We will parse out any text that is not a bible reference. This means, if you found a cool reading plan online somewhere, you can just paste it straight in here. As long as each bible reading is on a separate line, we should be able to correctly parse all the bible references.') . '</p>';
			$passage_help .= __('<p>By the way, you can use both option 1 and 2 for adding passages. The passages from option 2 will be appended to the end of the passages added from option 1.</p>');
		}
		else {
			$readings_label = __('Edit Readings');
			$groups_label = __('Append More Readings');
			$reading_help = '<p>' . __('This is a list of all the current readings: edit these passages to modify your reading plan. Each line is a different reading in the plan.') . '</p>';
			$passage_help .= __('<p><b>Note</b>: Any passages you add here will be appended to the end of the current readings (in the "') . $readings_label . __('" box).</p>');
		}

		// Name
		$table->add_option(__('Reading Plan Name'), '', $table->option_text(self::var_plan_name, $plan->name, "size = '40'"),
			'<p>' . __('Give the reading plan a cool name.') . '</p>');

		// Description
		$table->add_option(__('Description'), '',
			$table->option_textarea(self::var_plan_description, $plan->description, 2, 50, ''),
			'<p>' . __('Add an optional description of this reading plan.') . '</p>');

		// Readings
		$table->add_option($readings_label, '',
			$table->option_textarea(self::var_plan_readings, implode("\n", $plan->reading_strings()), 15, 50),
			$readings_help);

		// Groups of Passages
		$table->add_option($groups_label, '',
			$table->option_textarea(self::var_plan_passages, '', 3, 50),
			"<br/><input name='" . self::var_plan_chunk_size . "' id='" . self::var_plan_chunk_size . "' type='text' value='1' size='4' maxlength='4'/> " . __('Chapters Per Reading') .
			$passage_help);

		// Private
		$table->add_option(__('Privacy'), '',
			$table->option_check(self::var_plan_is_private, __('Set as private'), $plan->is_private),
			'<p>' . __('Check this to set this reading plan as private. Private reading plans will not be shown to other readers looking at your reading plans. Other users will not be able to subscribe to this plan.') . '</p>');

		// Is Scheduled?
		$table->add_option(__('Use a reading schedule?'), '',
			$table->option_check(self::var_plan_is_scheduled, __('Yes, use a reading schedule'), $plan->is_scheduled),
			'<p>' . __('Check this to use a reading schedule for this plan. If unchecked, you can skip past the rest of the options and save your plan.') . '</p>');

		// Start Date
		$table->add_option(__('Start Date'), '',
			$table->option_text(self::var_plan_start, $plan->start_date('M j, Y'), "size='10' maxlength='20'"),
			'<p>' . __('Set the date at which this plan schedule will begin.') . '</p>');

		// Frequency
		$frequency_array = BfoxReadingPlan::frequency_array();
		$table->add_option(__('How often will this plan be read?'), '',
			$table->option_array(self::var_plan_frequency, array_map('ucfirst', $frequency_array[BfoxReadingPlan::frequency_array_daily]), $plan->frequency),
			'<p>' . __('Will this plan be read daily, weekly, or monthly?') . '</p>');

		// Frequency Options
		$days_week_array = BfoxReadingPlan::days_week_array();
		$table->add_option(__('Days of the Week'), '',
			$table->option_array(self::var_plan_freq_options, array_map('ucfirst', $days_week_array[BfoxReadingPlan::days_week_array_normal]), $plan->freq_options_array()),
			'<p>' . __('Which days of the week will you be reading? This only applies to plans that are read daily.') . '</p>');

		echo $table->content();
	}
}

?>