<?php

class BfoxPlanEdit
{
	const var_submit = 'submit';

	const var_plan_id = 'plan_id';
	const var_plan_name = 'plan_name';
	const var_plan_description = 'plan_description';
	const var_plan_readings = 'plan_readings';
	const var_plan_passages = 'plan_passages';
	const var_plan_chunk_size = 'plan_chunk_size';
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
	const action_copy = 'copy';

	private $user_id;
	private $user_type;
	private $url;

	public function __construct($user_id, $user_type, $url) {
		$this->user_id = $user_id;
		$this->user_type = $user_type;
		$this->url = $url;
	}

	public static function add_head($base_url = '') {
		if (empty($base_url)) $base_url = get_option('siteurl');

		?>
		<link rel="stylesheet" href="<?php echo $base_url ?>/wp-content/mu-plugins/biblefox/plans/plans.css" type="text/css"/>
		<?php
	}

	public function page_load() {

		$messages = array();
		$redirect = '';

		if (!empty($_POST[self::var_submit]) && isset($_POST[self::var_plan_id]) && isset($_POST[self::var_action])) {
			$plan = BfoxPlans::get_plan($_POST[self::var_plan_id]);

			switch ($_POST[self::var_action]) {
				case self::action_edit:
					if (isset($_POST[self::var_plan_name])) {

						// If this is a new plan, create a new subscription where we are the owner
						// Otherwise, get the current subscription
						if (empty($plan->id)) {
							$sub = new BfoxReadingSub(NULL, 0, $this->user_id, $this->user_type);
							$sub->is_subscribed = TRUE;
							$sub->is_owned = TRUE;
						}
						else $sub = BfoxPlans::get_sub($plan, $this->user_id, $this->user_type);

						// We can only edit the plan if we are an owner
						if ($sub->is_owned) {
							$plan->name = stripslashes($_POST[self::var_plan_name]);
							$plan->description = stripslashes($_POST[self::var_plan_description]);
							$plan->set_readings_by_strings(stripslashes($_POST[self::var_plan_readings]));
							$plan->add_passages(stripslashes($_POST[self::var_plan_passages]), $_POST[self::var_plan_chunk_size]);
							$plan->set_start_date($_POST[self::var_plan_start]);
							$plan->frequency = $_POST[self::var_plan_frequency];
							$plan->set_freq_options((array) $_POST[self::var_plan_freq_options]);

							BfoxPlans::save_plan($plan);
							$messages []= "Reading Plan ($plan->name) Saved!";
							$redirect = $this->plan_url($plan->id);

							// If the subscription plan is not set, this must be a new subscription that we need to save
							if (empty($sub->plan_id)) {
								$sub->plan_id = $plan->id;
								BfoxPlans::save_sub($sub);
							}
						}
					}
					break;
				case self::action_delete:
					$sub = BfoxPlans::get_sub($plan, $this->user_id, $this->user_type);

					// We can only delete plans that we own
					if ($sub->is_owned) {
						BfoxPlans::delete_plan($plan);
						$messages []= "Reading Plan ($plan->name) Deleted!";
						$redirect = $this->url;
					}
					break;
				case self::action_copy:
					$plan->set_as_copy();
					BfoxPlans::save_plan($plan);
					$sub = new BfoxReadingSub(NULL, $plan->id, $this->user_id, $this->user_type);
					$sub->is_subscribed = TRUE;
					$sub->is_owned = TRUE;
					BfoxPlans::save_sub($sub);
					$messages []= "Reading Plan ($plan->name) Saved!";
					$redirect = $this->url;
					break;
				case self::action_subscribe:
					$sub = BfoxPlans::get_sub($plan, $this->user_id, $this->user_type);
					$sub->is_subscribed = TRUE;
					BfoxPlans::save_sub($sub);
					$messages []= "Subscribed to Reading Plan ($plan->name)!";
					$redirect = $this->url;
					break;
				case self::action_unsubscribe:
					$sub = BfoxPlans::get_sub($plan, $this->user_id, $this->user_type);
					$sub->is_subscribed = FALSE;
					BfoxPlans::save_sub($sub);
					$messages []= "Unsubscribed to Reading Plan ($plan->name)!";
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
					$this->find_plans();
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
				echo "<h2>Manage Reading Plans</h2>";
				$this->view_user_plans($this->user_id, $this->user_type);
			}
		}
	}

	private function plan_url($plan_id) {
		return add_query_arg(self::var_plan_id, $plan_id, $this->url);
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

	private function is_owned(BfoxReadingSub $sub) {
		return (($sub->user_id == $this->user_id) && ($sub->user_type == $this->user_type));
	}

	private function user_name(BfoxReadingSub $sub, $me = '') {
		if (!empty($me) && $this->is_owned($sub)) return $me;
		return $sub->user_name();
	}

	private function user_link(BfoxReadingSub $sub, $str = '', $me = 'me') {
		if (empty($str)) $str = $this->user_name($sub, $me);

		if ($this->is_owned($sub)) $url = $this->url;
		else {
			if (BfoxPlans::user_type_blog == $sub->user_type) $var = self::var_blog;
			elseif (BfoxPlans::user_type_user == $sub->user_type) $var = self::var_user;
			$url = add_query_arg($var, $sub->user_id, $this->url);
		}

		return "<a href='$url'>$str</a>";

	}

	private function schedule_desc(BfoxReadingPlan $plan) {
		$desc = $plan->start_str() . ' - ' . $plan->end_str();
		if ($plan->is_recurring) $desc .= ' (recurring)';
		$desc .= " (" . $plan->frequency_desc() . ")";
		return $desc;
	}

	private function get_plan_options(BfoxReadingPlan $plan, $is_subscribed, $is_owned) {
		$options = array();

		// Subscribe/Unsubscribe
		if ($is_subscribed) $options []= $this->plan_action_link($plan->id, self::action_unsubscribe, __('Unsubscribe'));
		else $options []= $this->plan_action_link($plan->id, self::action_subscribe, __('Subscribe'));

		// Edit
		if ($is_owned) {
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
		<form action='<?php echo $this->url ?>' method='post'>
		<p><?php echo $confirm . $hiddens ?></p>
		<p><input type='submit' name='<?php echo self::var_submit ?>' value='<?php echo __('Confirm') ?>' class='button'/></p>
		</form>

		<?php
	}

	private function view_user_plans($user_id, $user_type) {

		$is_user = (($user_id == $this->user_id) && ($user_type == $this->user_type));

		$subs = BfoxPlans::get_user_subs($user_id, $user_type);

		$my_subs = array();
		if (!$is_user) $my_subs = BfoxPlans::get_user_subs($this->user_id, $this->user_type);

		foreach ($subs as $sub) $plan_ids []= $sub->plan_id;

		$plans = BfoxPlans::get_plans($plan_ids);

		$plans_table = new BfoxHtmlTable("id='reading_plans' class='widefat'");
		$plans_table->add_header_row('', 3, 'Reading Plan', 'Schedule', 'Options');
		if ($is_user) $plans_table->add_footer_row('', 3, $this->plan_link(0, __('Create a new reading plan')));

		// Fill the plans table with all the plans this user is subscribed to
		foreach ($subs as $sub) if (isset($plans[$sub->plan_id])) {
			$plan = $plans[$sub->plan_id];

			$sub_is_subscribed = FALSE;
			$sub_is_owned = FALSE;

			if ($is_user) {
				$sub_is_subscribed = $sub->is_subscribed;
				$sub_is_owned = $sub->is_owned;
			}
			elseif (isset($my_subs[$sub->plan_id])) {
				$sub_is_subscribed = $my_subs[$sub->plan_id]->is_subscribed;
				$sub_is_owned = $my_subs[$sub->plan_id]->is_owned;
			}

			$plans_table->add_row('', 3,
				$this->plan_link($plan->id, $plan->name) . "<br/>$plan->description",
				$this->schedule_desc($plan),
				implode('<br/>', $this->get_plan_options($plan, $sub_is_subscribed, $sub_is_owned)));
		}

		?>

		<p>Reading plans allow you to organize how you read the Bible. You can create your own reading plans, or subscribe to someone else's plans.</p>

		<?php echo $plans_table->content() ?>
		<?php $this->find_plans() ?>
		<?php
	}

	private function find_plans() {
		list($post_url, $hiddens) = BfoxUtility::get_post_url($this->url);

		?>
		<h3>Find Reading Plans</h3>
		<p>You can look up reading plans that others have created so that you can subscribe them.</p>

		<form action='<?php echo $post_url ?>' method='get'>
		<?php echo $hiddens ?>
		<p>
		<input type='text' name='<?php echo self::var_user ?>' value='<?php echo $_GET[self::var_user] ?>'/>
		<input type='submit' value='User Search' class='button'/>
		</p>
		</form>

		<form action='<?php echo $post_url ?>' method='get'>
		<?php echo $hiddens ?>
		<p>
		<input type='text' name='<?php echo self::var_blog ?>' value='<?php echo $_GET[self::var_blog] ?>'/>
		<input type='submit' value='Blog Search' class='button'/>
		</p>
		</form>

		<?php
	}

	private function plan_chart(BfoxReadingPlan $plan, $is_subscribed, $is_owned, $max_cols = 3) {

		$reading_count = $plan->reading_count();

		$dates = array();
		$unread_readings = array();

		// Get the date information
		$dates = $plan->get_dates($reading_count + 1);
		$current_date_index = BfoxReadingPlan::current_date_index($dates);
		if ($reading_count == $current_date_index) $current_date_index = -1;

		// Get the history information
		// TODO2: Implement user reading history
		/*$history_refs = BfoxHistory::get_from($plan->start_date);
		if ($history_refs->is_valid()) {
			foreach ($plan->readings as $reading_id => $reading) {
				$unread = new BibleRefs();
				$unread->add_seqs($reading->get_seqs());
				$unread->sub_seqs($history_refs->get_seqs());
				$unread_readings[$reading_id] = $unread;
			}
		}*/

		$sub_table = new BfoxHtmlTable("class='reading_plan_col'");

		// Create the table header
		$header = new BfoxHtmlRow();
		$header->add_header_col('', '');
		$header->add_header_col('Passage', '');
		if (!empty($dates)) $header->add_header_col('Date', '');
		if (!empty($unread_readings)) $header->add_header_col('My Progress', '');
		$sub_table->add_header_row($header);

		foreach ($plan->readings as $reading_id => $reading) {
			// Create the row for this reading
			if ($reading_id == $current_date_index) $row = new BfoxHtmlRow("class='current'");
			else $row = new BfoxHtmlRow();

			// Add the reading index column and the bible reference column
			$row->add_col($reading_id + 1);
			$row->add_col(BfoxBlog::ref_link($reading->get_string(BibleMeta::name_short)));

			// Add the Date column
			if (!empty($dates)) {
				if (isset($dates[$reading_id])) $row->add_col(date('M d', $dates[$reading_id]));
				else $row->add_col();
			}

			// Add the History column
			if (!empty($unread_readings)) {
				if (isset($unread_readings[$reading_id])) $row->add_col($unread_readings[$reading_id]->get_string(BibleMeta::name_short));
				else $row->add_col();
			}

			// Add the row to the table
			$sub_table->add_row($row);
		}

		$table = new BfoxHtmlTable("class='reading_plan'",
			"<b>$plan->name</b><br/>
			<small>Description: $plan->description<br/>
			Schedule: " . $this->schedule_desc($plan) . "<br/>
			Options: " . implode(', ', $this->get_plan_options($plan, $is_subscribed, $is_owned)) . "</small>");
		$table->add_row($sub_table->get_split_row($max_cols, 5));

		return $table->content();
	}

	private function view_plan(BfoxReadingPlan $plan) {

		if (empty($plan->id)) {
			$is_owned = TRUE;

			echo "<h2>Create Reading Plan</h2>";
		}
		else {
			$is_owned = FALSE;
			$is_subscribed = FALSE;

			// Users for this plan table
			$subs = BfoxPlans::get_plan_subs($plan);

			$user_table = new BfoxHtmlTable("class='widefat'");
			$user_table->add_header_row('', 2, 'Type', 'User');
			foreach ($subs as $sub) {
				$type = '';
				if (BfoxPlans::user_type_user == $sub->user_type) $type = 'User';
				elseif (BfoxPlans::user_type_blog == $sub->user_type) $type = 'Blog';
				$user_table->add_row('', 2, $type, $this->user_link($sub) . (($sub->is_owned) ? ' (manager)' : ''));
				if ($this->is_owned($sub)) {
					$is_owned = $sub->is_owned;
					$is_subscribed = $sub->is_subscribed;
				}
			}

			echo "<h2>View Reading Plan</h2>";
			echo $this->plan_chart($plan, $is_subscribed, $is_owned);
			echo "<h3>Subscribers</h3>\n";
			echo "<p>These are the subscribers using this reading plan:</p>";
			echo $user_table->content();
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

		$passage_help_text = __('<p>This allows you to add passages of the Bible to your reading plan in big chunks.</p>
			<p>You can type passages of the bible in the box, and then set how many chapters you want to read at a time. The passages will be cut into sections and added to your reading plan.</p>
			<p>Type any passages in the box above. For instance, to make a reading plan of all the gospels you could type "Matthew, Mark, Luke, John".<br/>
			You can use bible abbreviations (ie. "gen" instead of "Genesis"), and even specify chapters and verses (ie. "gen 1-3").<br/>
			Separate passages can be separated with a comma (\',\'), semicolon (\';\'), or on separate lines.</p>');

		// Name
		$table->add_option(__('Reading Plan Name'), '', $table->option_text(self::var_plan_name, $plan->name, "size = '40'"), '');

		// Description
		$table->add_option(__('Description'), '',
			$table->option_textarea(self::var_plan_description, $plan->description, 2, 50, ''),
			'<br/>' . __('Add an optional description of this reading plan.'));

		// Readings
		$table->add_option(__('Readings'), '',
			$table->option_textarea(self::var_plan_readings, implode("\n", $plan->reading_strings()), 15, 50),
			'<br/>' . $reading_help_text);

		// Groups of Passages
		$table->add_option(__('Add Groups of Passages'), '',
			$table->option_textarea(self::var_plan_passages, '', 3, 50),
			"<br/><input name='" . self::var_plan_chunk_size . "' id='" . self::var_plan_chunk_size . "' type='text' value='1' size='4' maxlength='4'/><br/>$passage_help_text");

		// Start Date
		$table->add_option(__('Start Date'), '',
			$table->option_text(self::var_plan_start, $plan->start_str(), "size='10' maxlength='20'"),
			'<br/>' . __('Set the date at which this plan schedule will begin.'));

		// Frequency
		$frequency_array = BfoxReadingPlan::frequency_array();
		$table->add_option(__('How often will this plan be read?'), '',
			$table->option_array(self::var_plan_frequency, array_map('ucfirst', $frequency_array[BfoxReadingPlan::frequency_array_daily]), $plan->frequency),
			'<br/>' . __('Will this plan be read daily, weekly, or monthly?'));

		// Frequency Options
		$days_week_array = BfoxReadingPlan::days_week_array();
		$table->add_option(__('Days of the Week'), '',
			$table->option_array(self::var_plan_freq_options, array_map('ucfirst', $days_week_array[BfoxReadingPlan::days_week_array_normal]), $plan->freq_options_array()),
			'<br/>' . __('Which days of the week will you be reading?'));

		echo $table->content();
	}
}

?>