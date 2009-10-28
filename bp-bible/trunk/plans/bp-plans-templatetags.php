<?php

/**
 * In this file you should define template tag functions that end users can add to their template files.
 * Each template tag function should echo the final data so that it will output the required information
 * just by calling the function name.
 */

function bp_plans_header_tabs() {
	global $bp;
?>
	<li<?php if ( !isset($bp->action_variables[0]) || 'active' == $bp->action_variables[0] ) : ?> class="current"<?php endif; ?>><a href="<?php echo $bp->displayed_user->domain . $bp->plans->slug ?>/my-plans"><?php _e( 'Active', 'bp-plans' ) ?></a></li>
	<li<?php if ( 'inactive' == $bp->action_variables[0] ) : ?> class="current"<?php endif; ?>><a href="<?php echo $bp->displayed_user->domain . $bp->plans->slug ?>/my-plans/inactive"><?php _e( 'Inactive', 'bp-plans' ) ?></a></li>
	<li<?php if ( 'friends' == $bp->action_variables[0] ) : ?> class="current"<?php endif; ?>><a href="<?php echo $bp->displayed_user->domain . $bp->plans->slug ?>/my-plans/friends"><?php _e( 'Friends', 'bp-plans' ) ?></a></li>
<?php
	do_action( 'bp_plans_header_tabs' );
}

function bp_plan_search_form() {
	global $bp;

	if ('friends' != $bp->action_variables[0]) return false;

?>
	<form action="<?php echo $bp->displayed_user->domain . $bp->plans->slug . '/my-plans/friends/' ?>" id="plan-search-form" method="post">
		<label for="plan-filter-box" id="plan-filter-box-label"><?php _e('Filter Plans', 'bp-plans') ?></label>
		<input type="text" name="plan-filter-box" id="plan-filter-box" value="" />

		<?php wp_nonce_field( 'plan-filter-box', '_wpnonce_plan_filter' ) ?>
	</form>
<?php
}

function bp_plans_filter_title() {
	global $bp;

	$current_filter = $bp->action_variables[0];

	switch ( $current_filter ) {
		case 'active': default:
			_e( 'Active', 'bp-plans' );
			break;
		case 'inactive':
			_e( 'Inactive', 'bp-plans' );
			break;
		case 'friends':
			_e( 'Friends', 'bp-plans' );
			break;
	}
	do_action( 'bp_plans_filter_title' );
}

/**
 * If you want to go a step further, you can create your own custom WordPress loop for your component.
 * By doing this you could output a number of items within a loop, just as you would output a number
 * of blog posts within a standard WordPress loop.
 *
 * The example template class below would allow you do the following in the template file:
 *
 * 	<?php if ( bp_get_plans_has_items() ) : ?>
 *
 *		<?php while ( bp_get_plans_items() ) : bp_get_plans_the_item(); ?>
 *
 *			<p><?php bp_get_plans_item_name() ?></p>
 *
 *		<?php endwhile; ?>
 *
 *	<?php else : ?>
 *
 *		<p class="error">No items!</p>
 *
 *	<?php endif; ?>
 *
 * Obviously, you'd want to be more specific than the word 'item'.
 *
 */

class BP_Plans_Template extends BP_Loop_Template {
	public function __construct( $user_id, $type, $per_page, $max ) {
		global $bp;

		$this->set_user_id($user_id);
		$this->set_per_page($per_page);

		$args = array(
			'user_id' => $this->user_id,
			'limit' => $this->pag_num,
			'page' => $this->pag_page
		);

		if ('single-plan' == $type) {
			$this->items = array($bp->plans->current_plan);
			$this->total_item_count = 1;
		}
		else {
			switch ( $type ) {
				case 'current':
					$args['is_finished'] = FALSE;
					break;
				case 'finished':
					$args['is_finished'] = TRUE;
					break;
				case 'friends':
					$args['user_id'] = friends_get_friend_user_ids($this->user_id);
					break;
			}

			$plans = BfoxPlans::get_plans_using_args($args);
			$this->items = array();
			foreach ((array)$plans as $plan) $this->items []= $plan;

			$this->total_item_count = BfoxPlans::count_plans($args);
		}

		BfoxPlans::add_history_to_plans($this->items);

		$this->item_count = count($this->items);
		$this->set_max($max);
		$this->set_page_links();
	}
}

function bp_has_plans( $args = '' ) {
	global $bp, $plans_template, $bp_plans;

	/***
	 * This function should accept arguments passes as a string, just the same
	 * way a 'query_posts()' call accepts parameters.
	 * At a minimum you should accept 'per_page' and 'max' parameters to determine
	 * the number of plans to show per page, and the total number to return.
	 *
	 * e.g. bp_has_plans( 'per_page=10&max=50' );
	 */

	/***
	 * Set the defaults for the parameters you are accepting via the "bp_has_plans()"
	 * function call
	 */
	$defaults = array(
		'user_id' => false,
		'per_page' => 10,
		'max' => false,
		'type' => 'current'
	);

	/***
	 * This function will extract all the parameters passed in the string, and turn them into
	 * proper variables you can use in the code - $per_page, $max
	 */
	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	/* The following code will auto set parameters based on the page being viewed.
	 * for example on example.com/members/andy/plans/my-plans/inactive/
	 * $type = 'inactive'
	 */
	if ( 'my-plans' == $bp->current_action ) {
		$page = $bp->action_variables[0];
		if ( 'inactive' == $page )
			$type = 'finished';
		else if ( 'friends' == $page )
			$type = 'friends';
	}
	elseif ( $bp->plans->current_plan->slug ) {
		$type = 'single-plan';
	}

	$plans_template = new BP_Plans_Template( $user_id, $type, $per_page, $max );

	return $plans_template->has_items();
}

function bp_plans() {
	global $plans_template;
	return $plans_template->items();
}

function bp_the_plan() {
	global $plans_template;
	return $plans_template->the_item();
}

/**
 * Returns the current reading plan if $plan is NULL
 *
 * @param BfoxReadingPlan $plan
 * @return BfoxReadingPlan
 */
function bp_get_plan(BfoxReadingPlan $plan = NULL) {
	if (empty($plan)) {
		global $bp, $plans_template;
		if (!empty($plans_template->item)) $plan = $plans_template->item;
		elseif (!empty($bp->plans->current_plan)) $plan = $bp->plans->current_plan;
	}

	return $plan;
}

function bp_plan_id() {
	echo bp_get_plan_id();
}
	function bp_get_plan_id(BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);
		return apply_filters( 'bp_get_plan_id', $plan->id );
	}

function bp_plan_name() {
	echo bp_get_plan_name();
}
	function bp_get_plan_name(BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);
		return apply_filters( 'bp_get_plan_name', $plan->name );
	}

function bp_plan_schedule_description() {
	echo bp_get_plan_schedule_description();
}
	function bp_get_plan_schedule_description(BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);
		return apply_filters( 'bp_get_plan_schedule_description', $plan->schedule_desc() );
	}

function bp_plan_description() {
	echo bp_get_plan_description();
}
	function bp_get_plan_description(BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);
		return apply_filters( 'bp_get_plan_description', $plan->desc_html() );
	}

function bp_plan_description_editable() {
	echo bp_get_plan_description_editable();
}
	function bp_get_plan_description_editable(BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);
		return apply_filters( 'bp_get_plan_description_editable', $plan->description );
	}

function bp_plan_readings_editable() {
	echo bp_get_plan_readings_editable();
}
	function bp_get_plan_readings_editable(BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);
		return apply_filters( 'bp_get_plan_readings_editable', implode("\n", $plan->reading_strings()) );
	}

function bp_plan_start_date($format = '') {
	echo bp_get_plan_start_date($format);
}
	function bp_get_plan_start_date($format = '', BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);
		return apply_filters( 'bp_get_plan_start_date', $plan->start_date($format) );
	}

function bp_plan_day_of_week_setting($day, BfoxReadingPlan $plan = NULL) {
	$plan = bp_get_plan($plan);
	$days = $plan->freq_options_array();
	if ( $days[$day] ) echo ' checked="checked"';
}

function bp_plan_privacy_setting($is_private, BfoxReadingPlan $plan = NULL) {
	$plan = bp_get_plan($plan);
	if ( $is_private == $plan->is_private ) echo ' checked="checked"';
}

function bp_plan_schedule_setting($schedule, BfoxReadingPlan $plan = NULL) {
	$plan = bp_get_plan($plan);
	if ((!$schedule && !$plan->is_scheduled) || (($schedule - 1) == $plan->frequency)) echo ' checked="checked"';
}

function bp_plan_pagination() {
	echo bp_get_plan_pagination();
}
	function bp_get_plan_pagination() {
		global $plans_template;
		return apply_filters( 'bp_get_plan_pagination', $plans_template->pag_links );
	}

function bp_plan_pagination_count() {
	global $plans_template;
	echo sprintf( __( 'Viewing plan %d to %d (of %d plans)', 'bp-plans' ), $plans_template->from_num, $plans_template->to_num, $plans_template->total_item_count ); ?> &nbsp;
	<span class="ajax-loader"></span><?php
}

function bp_plan_pag_id() {
	echo bp_get_plan_pag_id();
}
	function bp_get_plan_pag_id() {
		return apply_filters( 'bp_get_plan_pag_id', 'pag' );
	}

function bp_plan_avatar( $args = '' ) {
	echo bp_get_plan_avatar( $args );
}
	function bp_get_plan_avatar( $args = '' ) {
		$defaults = array(
			'type' => 'full',
			'width' => false,
			'height' => false,
			'class' => 'avatar',
			'id' => false,
			'alt' => __( 'Plan Avatar', 'bp-plans' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$plan = bp_get_plan($plan);

		// Fetch the avatar from the folder, if not provide backwards compat.
		$nargs = array( 'item_id' => $plan->owner_id, 'type' => $type, 'alt' => $alt, 'css_id' => $id, 'class' => $class, 'width' => $width, 'height' => $height );
		$avatar = bp_core_fetch_avatar($nargs);

		return apply_filters( 'bp_get_plan_avatar', $avatar );
	}

function bp_plan_avatar_thumb() {
	echo bp_get_plan_avatar_thumb();
}
	function bp_get_plan_avatar_thumb(BfoxReadingPlan $plan = NULL) {
		return bp_get_plan_avatar( array('plan' => $plan, 'type' => 'thumb' ) );
	}

function bp_plan_owner() {
	echo bp_get_plan_owner();
}
	function bp_get_plan_owner(BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);

		if (empty($plan->owner)) {
			if (BfoxPlans::user_type_user == $plan->owner_type) $plan->owner = get_userdata($plan->owner_id);
			elseif (BfoxPlans::user_type_group == $plan->owner_type) $plan->owner = new BP_Groups_Group( $plan->owner_id, false, false );
		}

		return apply_filters( 'bp_get_plan_owner', $plan->owner );
	}

function bp_plan_owner_permalink() {
	echo bp_get_plan_owner_permalink();
}
	function bp_get_plan_owner_permalink(BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);
		$owner = bp_get_plan_owner($plan);

		if (BfoxPlans::user_type_user == $plan->owner_type) $url = bp_core_get_user_domain($plan->owner_id);
		elseif (BfoxPlans::user_type_group == $plan->owner_type) $url = bp_get_group_permalink(bp_get_plan_owner());

		return apply_filters( 'bp_get_plan_owner_permalink', $url );
	}

function bp_plan_owner_name() {
	echo bp_get_plan_owner_name();
}
	function bp_get_plan_owner_name(BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);
		if (BfoxPlans::user_type_user == $plan->owner_type) $name = bp_core_get_user_displayname($plan->owner_id);
		elseif (BfoxPlans::user_type_group == $plan->owner_type) $name = bp_get_group_name(bp_get_plan_owner());

		return apply_filters( 'bp_get_plan_owner_name', $name );
	}

function bp_plan_owner_link() {
	echo bp_get_plan_owner_link();
}
	function bp_get_plan_owner_link(BfoxReadingPlan $plan = NULL) {
		$plan = bp_get_plan($plan);
		return apply_filters( 'bp_get_plan_owner_link', "<a href='" . bp_get_plan_owner_permalink($plan) . "'>" . bp_get_plan_owner_name($plan) . '</a>' );
	}

function bp_plan_permalink() {
	echo bp_get_plan_permalink();
}
	function bp_get_plan_permalink(BfoxReadingPlan $plan = NULL) {
		global $bp;
		$plan = bp_get_plan($plan);
		return apply_filters( 'bp_get_plan_permalink', bp_get_plan_owner_permalink($plan) . $bp->plans->slug . '/' . $plan->slug );
	}

function bp_plans_show_no_plans_message() {
	global $bp;
	return !BfoxPlans::count_plans(array('user_id' => $bp->displayed_user->id));
}

function bp_add_plan_button() {
	echo bp_get_add_plan_button();
}
	function bp_get_add_plan_button(BfoxReadingPlan $plan = NULL) {
		global $bp;

		$button = false;
		$plan = bp_get_plan($plan);

		if (!empty($plan->id)) {

			$class = '';
			if (bp_plan_is_owned($plan)) {
				if ($plan->is_finished) {
					$link = '<a href="' . bp_get_plan_permalink($plan) . '/mark" title="' . __('Activate', 'bp-plans') . '" id="plan-' . $potential_plan_id . '" rel="mark-active">' . __('Activate', 'bp-plans') . '</a>';
					//$class = 'accept';
				}
				else {
					$link = '<a href="' . bp_get_plan_permalink($plan) . '/mark" title="' . __('Deactivate', 'bp-plans') . '" id="plan-' . $potential_plan_id . '" rel="mark-inactive">' . __('Deactivate', 'bp-plans') . '</a>';
					$class = 'reject';
				}
			}
			else $link = '<a href="' . bp_get_plan_permalink($plan) . '/copy" title="' . __('Copy plan', 'bp-plans') . '" id="plan-' . $potential_plan_id . '" rel="copy" class="add">' . __('Copy plan', 'bp-plans') . '</a>';
			$button = '<div class="generic-button ' . $class . '" id="plan-button-' . $plan->id . '">' . $link . '</div>';
		}

		return apply_filters( 'bp_get_add_plan_button', $button );
	}

function bp_plan_is_owned(BfoxReadingPlan $plan = NULL) {
	global $bp;
	$plan = bp_get_plan($plan);

	// New plans ($plan->id == 0) are owned
	if (empty($plan->id)) return TRUE;

	return (($bp->loggedin_user->id == $plan->owner_id) && (BfoxPlans::user_type_user == $plan->owner_type));
}

function bp_plan_is_finished(BfoxReadingPlan $plan = NULL) {
	$plan = bp_get_plan($plan);
	return apply_filters( 'bp_plan_is_finished', $plan->is_finished );
}

function bp_plan_is_private(BfoxReadingPlan $plan = NULL) {
	$plan = bp_get_plan($plan);
	return apply_filters( 'bp_plan_is_private', $plan->is_private );
}

function bp_plan_view_tabs(BfoxReadingPlan $plan = NULL) {
	global $bp;

	$plan = bp_get_plan($plan);
	$plan_link = bp_get_plan_permalink($plan);
	$finished = $plan->is_finished ? __('Activate', 'bp-plans') : __('Deactivate', 'bp-plans');
	$is_owned = bp_plan_is_owned($plan);

	$current_tab = $bp->action_variables[0];
?>
	<li<?php if ( 'overview' == $current_tab ) : ?> class="current"<?php endif; ?>><a href="<?php echo $plan_link ?>/overview"><?php _e('Overview', 'bp-plans') ?></a></li>
	<?php if ( $is_owned ): ?>
		<li<?php if ( 'edit-details' == $current_tab ) : ?> class="current"<?php endif; ?>><a href="<?php echo $plan_link ?>/edit-details"><?php _e('Edit Settings', 'bp-plans') ?></a></li>
		<li<?php if ( 'edit-readings' == $current_tab ) : ?> class="current"<?php endif; ?>><a href="<?php echo $plan_link ?>/edit-readings"><?php _e('Edit Readings', 'bp-plans') ?></a></li>
	<?php endif ?>
	<li<?php if ( 'copy' == $current_tab ) : ?> class="current"<?php endif; ?>><a href="<?php echo $plan_link ?>/copy"><?php _e('Copy', 'bp-plans') ?></a></li>
	<?php if ( $is_owned ): ?>
		<li<?php if ( 'mark' == $current_tab ) : ?> class="current"<?php endif; ?>><a href="<?php echo $plan_link ?>/mark"><?php echo $finished ?></a></li>
		<li<?php if ( 'delete' == $current_tab ) : ?> class="current"<?php endif; ?>><a href="<?php echo $plan_link ?>/delete"><?php _e('Delete', 'bp-plans') ?></a></li>
	<?php endif ?>
	<li<?php if ( 'print' == $current_tab ) : ?> class="current"<?php endif; ?>><a target="_blank" href="<?php echo $plan_link ?>/print?cols=6"><?php _e('Print', 'bp-plans') ?></a></li>

<?php
	do_action( 'plans_admin_tabs', $current_tab, $plan->slug );
}

function bp_plan_view_form_action( $page = false ) {
	echo bp_get_plan_view_form_action( $page );
}
	function bp_get_plan_view_form_action( $page = false, BfoxReadingPlan $plan = NULL) {
		global $bp;

		$plan = bp_get_plan($plan);
		if ( !$page ) $page = $bp->action_variables[0];

		return apply_filters( 'bp_plan_view_form_action', bp_get_plan_permalink( $plan ) . '/' . $page );
	}

function bp_is_plan_view_screen( $slug ) {
	global $bp;

	if ( $bp->current_component != BP_PLANS_SLUG || 'overview' != $bp->current_action )
		return false;

	if ( $bp->action_variables[0] == $slug )
		return true;

	return false;
}

function bp_plan_chart(BfoxReadingPlan $plan = NULL) {
	echo bp_get_plan_chart($plan, $_GET['cols']);
}
	function bp_get_plan_chart(BfoxReadingPlan $plan = NULL, $max_cols = 0) {
		if (empty($max_cols)) $max_cols = 3;

		$plan = bp_get_plan($plan);

		// If this plan is scheduled, not finished, and this is a user, not a blog, then use the history information
		$use_history = FALSE;//($plan->is_scheduled && !$my_sub->is_finished && ($my_sub->user_type == BfoxPlans::user_type_user));

		$unread_readings = array();
		if ($use_history) {
			$use_history = $plan->set_history(BfoxHistory::get_history(0, $plan->history_start_date(), NULL, TRUE));
			$crossed_out = '<br/>' . __('*Note: Crossed out passages indicate that you have finished reading that passage');
		}

		$sub_table = new BfoxHtmlTable("class='reading_plan_col'");

		// Create the table header
		$header = new BfoxHtmlRow();
		$header->add_header_col('#', '');
		if ($plan->is_scheduled) $header->add_header_col('Date', '');
		$header->add_header_col('Passage', '');
		//if (!empty($unread_readings)) $header->add_header_col('Unread', '');
		$sub_table->add_header_row($header);

		$total_refs = new BfoxRefs;

		foreach ($plan->readings as $reading_id => $reading) {
			$total_refs->add_refs($reading);

			// Create the row for this reading
			if ($reading_id == $plan->current_reading_id) $row = new BfoxHtmlRow("class='current'");
			else $row = new BfoxHtmlRow();

			// Add the reading index column
			$row->add_col($reading_id + 1);

			// Add the Date column
			if ($plan->is_scheduled) $row->add_col($plan->date($reading_id, 'M d'));

			// Add the bible reference column
			$attrs = '';
			if ($use_history) {
				// Calculate how much of this reading is unread
				$unread = $plan->get_unread($reading);

				// If this reading is 'read', then mark it as such
				if (!$unread->is_valid()) $attrs = "class='finished'";
			}
			$row->add_col(Biblefox::ref_link($reading->get_string(BibleMeta::name_short)), $attrs);

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
			Schedule: " . $plan->schedule_desc() .
			"$crossed_out</small>");
		$table->add_row($sub_table->get_split_row($max_cols, 5));
		$table->add_row('', 1, array("<small>Combined passages covered by this plan: " . $total_refs->get_string(BibleMeta::name_short) . "</small>", "colspan='$max_cols'"));

		return $table->content();
	}

function bp_plan_current_readings($args = array(), $plans = array()) {
	global $plans_template;

	$defaults = array(
		'max' => 5,
		'ref_name' => BibleMeta::name_short
	);

	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	if (empty($plans)) $plans = $plans_template->items;

	if (1 > $max) $max = 5;

	if (!empty($plans)) {
		$dates = array();
		$lis = array();

		foreach ($plans as $plan) if ($plan->is_current()) {
			// Show any unread readings before the current reading
			// And any readings between the current reading and the first unread reading after it
			foreach ($plan->readings as $reading_id => $reading) {
				$unread = $plan->get_unread($reading);
				$is_unread = $unread->is_valid();

				// If the passage is unread or current, add it
				if ($is_unread || ($reading_id >= $plan->current_reading_id)) {
					$ref_str = $plan->readings[$reading_id]->get_string($ref_name);
					$url = Biblefox::ref_url($ref_str);

					if (!$is_unread) $finished = " class='finished'";
					else $finished = '';

					$lis []= BfoxUtility::nice_date($plan->time($reading_id)) . ": <a href='$url'$finished>$ref_str</a>";
					$dates []= $plan->date($reading_id);
				}
				// Break after the first unread reading > current_reading
				//if ($is_unread && ($reading_id > $plan->current_reading_id)) break;
			}
		}

		array_multisort($dates, $lis);
		$readings = array_slice($lis, 0, $max);
	}

	$content = '';
	if (!empty($readings)) {
		$content = '<ul>';
		foreach ($readings as $reading) $content .= "<li>$reading</li>\n";
		$content .= '</ul>';
	}
	return $content;
}

/***************************************************************************
 * Reading Plan Creation Process Template Tags
 **/

function bp_plan_creation_tabs() {
	global $bp;

	if ( !is_array( $bp->plans->plan_creation_steps ) )
		return false;

	if ( !$bp->plans->current_create_step )
		$bp->plans->current_create_step = array_shift( array_keys( $bp->plans->plan_creation_steps ) );

	$counter = 1;
	foreach ( $bp->plans->plan_creation_steps as $slug => $step ) {
		$is_enabled = bp_are_previous_plan_creation_steps_complete( $slug ); ?>

		<li<?php if ( $bp->plans->current_create_step == $slug ) : ?> class="current"<?php endif; ?>><?php if ( $is_enabled ) : ?><a href="<?php echo $bp->loggedin_user->domain . $bp->plans->slug ?>/create/step/<?php echo $slug ?>"><?php endif; ?><?php echo $counter ?>. <?php echo $step['name'] ?><?php if ( $is_enabled ) : ?></a><?php endif; ?></li><?php
		$counter++;
	}

	unset( $is_enabled );

	do_action( 'plans_creation_tabs' );
}

function bp_plan_creation_stage_title() {
	global $bp;

	echo apply_filters( 'bp_plan_creation_stage_title', '<span>&mdash; ' . $bp->plans->plan_creation_steps[$bp->plans->current_create_step]['name'] . '</span>' );
}

function bp_plan_creation_form_action() {
	echo bp_get_plan_creation_form_action();
}
	function bp_get_plan_creation_form_action() {
		global $bp;

		if ( empty( $bp->action_variables[1] ) )
			$bp->action_variables[1] = array_shift( array_keys( $bp->plans->plan_creation_steps ) );

		return apply_filters( 'bp_get_plan_creation_form_action', $bp->loggedin_user->domain . $bp->plans->slug . '/create/step/' . $bp->action_variables[1] );
	}

function bp_is_plan_creation_step( $step_slug ) {
	global $bp;

	/* Make sure we are in the plans component */
	if ( $bp->current_component != BP_PLANS_SLUG || 'create' != $bp->current_action )
		return false;

	/* If this the first step, we can just accept and return true */
	if ( !$bp->action_variables[1] && array_shift( array_keys( $bp->plans->plan_creation_steps ) ) == $step_slug )
		return true;

	/* Before allowing a user to see a plan creation step we must make sure previous steps are completed */
	if ( !bp_is_first_plan_creation_step() ) {
		if ( !bp_are_previous_plan_creation_steps_complete( $step_slug ) )
			return false;
	}

	/* Check the current step against the step parameter */
	if ( $bp->action_variables[1] == $step_slug )
		return true;

	return false;
}

function bp_is_plan_creation_step_complete( $step_slugs ) {
	global $bp;

	if ( !$bp->plans->completed_create_steps )
		return false;

	if ( is_array( $step_slugs ) ) {
		$found = true;

		foreach ( $step_slugs as $step_slug ) {
			if ( !in_array( $step_slug, $bp->plans->completed_create_steps ) )
				$found = false;
		}

		return $found;
	} else {
		return in_array( $step_slugs, $bp->plans->completed_create_steps );
	}

	return true;
}

function bp_are_previous_plan_creation_steps_complete( $step_slug ) {
	global $bp;

	/* If this is the first plan creation step, return true */
	if ( array_shift( array_keys( $bp->plans->plan_creation_steps ) ) == $step_slug )
		return true;

	reset( $bp->plans->plan_creation_steps );
	unset( $previous_steps );

	/* Get previous steps */
	foreach ( $bp->plans->plan_creation_steps as $slug => $name ) {
		if ( $slug == $step_slug )
			break;

		$previous_steps[] = $slug;
	}

	return bp_is_plan_creation_step_complete( $previous_steps );
}

function bp_plan_creation_previous_link() {
	echo bp_get_plan_creation_previous_link();
}
	function bp_get_plan_creation_previous_link() {
		global $bp;

		foreach ( $bp->plans->plan_creation_steps as $slug => $name ) {
			if ( $slug == $bp->action_variables[1] )
				break;

			$previous_steps[] = $slug;
		}

		return apply_filters( 'bp_get_plan_creation_previous_link', $bp->loggedin_user->domain . $bp->plans->slug . '/create/step/' . array_pop( $previous_steps ) );
	}

function bp_is_last_plan_creation_step() {
	global $bp;

	$last_step = array_pop( array_keys( $bp->plans->plan_creation_steps ) );

	if ( $last_step == $bp->plans->current_create_step )
		return true;

	return false;
}

function bp_is_first_plan_creation_step() {
	global $bp;

	$first_step = array_shift( array_keys( $bp->plans->plan_creation_steps ) );

	if ( $first_step == $bp->plans->current_create_step )
		return true;

	return false;
}

?>