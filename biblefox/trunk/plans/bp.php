<?php

class BfoxBpPlans {
	const slug = 'plans';

	private static $editor;

	public static function setup_root_component() {
		bp_core_add_root_component(self::slug);
	}

	public static function add_nav() {
		global $bp;

		$plans_link = $bp->loggedin_user->domain . self::slug . '/';

		$callback = 'bfox_bp_none';

		/* Add the settings navigation item */
		bp_core_add_nav_item( __('Reading Plans'), 'plans', false);
		bp_core_add_nav_default(self::slug, $callback, 'my-plans', false);

		bp_core_add_subnav_item(self::slug, 'my-plans', __('My Reading Plans'), $plans_link, $callback, false, bp_is_home() );
		bp_core_add_subnav_item(self::slug, 'find-plans', __('Find a Plan'), $plans_link, $callback, false, bp_is_home() );
		bp_core_add_subnav_item(self::slug, 'create-plan', __('Create a Plan'), $plans_link, $callback, false, bp_is_home() );

		if (self::slug == $bp->current_component) {
			require_once BFOX_PLANS_DIR . '/plans.php';
			$_REQUEST[BfoxQuery::var_page] = BfoxQuery::page_plans;

			switch ($bp->current_action) {
				case 'create-plan':
					$_REQUEST[BfoxQuery::var_plan_id] = 0;
					$_GET[BfoxQuery::var_plan_id] = 0;
					break;
				default:
					$plan = BfoxPlans::get_plan($bp->current_action);
					if (!empty($plan->id)) {
						$_REQUEST[BfoxQuery::var_plan_id] = $plan->id;
						$_GET[BfoxQuery::var_plan_id] = $plan->id;

						$plans_link .= $plan->id . '/';
						$bp->current_item = $bp->current_action;
						$bp->current_action = 'edit-plan';

	//					bp_core_reset_subnav_items('plans');
						bp_core_add_nav_default(self::slug, $callback, 'edit-plan');
						bp_core_add_subnav_item(self::slug, 'edit-plan', __('View Plan'), $plans_link, $callback, false, bp_is_home() );
						//pre($bp);die;

/*						$bp->bp_options_title = $plan->name;
						bp_core_reset_subnav_items('plans');
						bp_core_add_nav_default(self::slug, $callback, 'overview');
						bp_core_add_subnav_item(self::slug, 'overview', __('Overview'), $plans_link, $callback, false, bp_is_home() );
						bp_core_add_subnav_item(self::slug, 'admin', __('Admin'), $plans_link, $callback, false, bp_is_home() );
						bp_core_add_subnav_item(self::slug, 'subscribers', __('Subscribers'), $plans_link, $callback, false, bp_is_home() );
						bp_core_add_subnav_item(self::slug, 'send-invites', __('Send Invites'), $plans_link, $callback, false, bp_is_home() );
						bp_core_add_subnav_item(self::slug, 'unsubscribe', __('Unsubscribe'), $plans_link, $callback, false, bp_is_home() );
*/					}
					break;
			}
		}
	}

	const page_user_plans = 'my-plans';
	const page_find_plans = 'find-plans';
	const page_create_plan = 'create-plan';
	const page_edit_plan = 'edit-plan';

	public static function screen_title() {
		global $bp;
		$titles = array(
			self::page_user_plans => 'Reading Plans',
			self::page_find_plans => 'Find Plans',
			self::page_create_plan => 'Create a Reading Plan',
			self::page_edit_plan => 'View Reading Plan'
		);

		echo $titles[$bp->current_action];
	}

	public static function screen_content() {
		global $bp, $displayed_user_id;

		switch ($bp->current_action) {
			case self::page_user_plans:
				self::$editor->view_user_plans($displayed_user_id, BfoxPlans::user_type_user);
				break;
			case self::page_create_plan:
				self::$editor->view_plan(new BfoxReadingPlan());
				break;
			case self::page_edit_plan:
				$plan = BfoxPlans::get_plan($bp->current_item);
				$confirm = '';
				if (!empty($bp->action_variables[0])) {
					switch ($bp->action_variables[0]) {
						case BfoxPlanEdit::action_delete:
							$confirm = __('Are you sure you want to delete ') . self::$editor->plan_link($plan->id, $plan->name) . __('?');
							break;
						case BfoxPlanEdit::action_copy:
							$confirm = __('Are you sure you want to copy ') . self::$editor->plan_link($plan->id, $plan->name) . __('?');
							break;
						case BfoxPlanEdit::action_subscribe:
							$confirm = __('Are you sure you want to subscribe to ') . self::$editor->plan_link($plan->id, $plan->name) . __('?');
							break;
						case BfoxPlanEdit::action_unsubscribe:
							$confirm = __('Are you sure you want to unsubscribe from ') . self::$editor->plan_link($plan->id, $plan->name) . __('?');
							break;
						case BfoxPlanEdit::action_mark_finished:
							$confirm = __('Are you sure you want to mark ') . self::$editor->plan_link($plan->id, $plan->name) . __(' as finished? This is for when you have finished reading everything in this plan.');
							break;
						case BfoxPlanEdit::action_mark_unfinished:
							$confirm = __('Are you sure you want to mark ') . self::$editor->plan_link($plan->id, $plan->name) . __(' as unfinished?');
							break;
					}
					$_GET[BfoxPlanEdit::var_action] = $bp->action_variables[0];
				}
				if (empty($confirm)) self::$editor->view_plan($plan);
				else self::$editor->confirm_page($confirm, $plan);
				break;
		}

//		pre($bp);
	}

	public static function editor_cb() {
		global $user_ID, $bp;

		require_once BFOX_PLANS_DIR . '/edit.php';
		self::$editor = new BfoxPlanEdit($user_ID, BfoxPlans::user_type_user, $bp->loggedin_user->domain . self::slug . '/');
		self::$editor->page_load();

		add_action('bp_template_title', 'BfoxBpPlans::screen_title');
		add_action('bp_template_content', 'BfoxBpPlans::screen_content');

		bp_core_load_template(apply_filters('bp_core_template_plugin', 'plugin-template'));
	}
}

function bfox_bp_none() {
	BfoxBpPlans::editor_cb();
}

add_action( 'plugins_loaded', 'BfoxBpPlans::setup_root_component', 1 );

add_action( 'wp', 'BfoxBpPlans::add_nav', 2 );
add_action( 'admin_menu', 'BfoxBpPlans::add_nav', 2 );

?>