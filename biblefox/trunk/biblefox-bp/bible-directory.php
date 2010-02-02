<?php

/* Define the slug for the component */
if (!defined('BFOX_BIBLE_SLUG')) define(BFOX_BIBLE_SLUG, 'bible');

function bfox_bp_bible_directory_setup_root_component() {
	bp_core_add_root_component(BFOX_BIBLE_SLUG);
}
add_action('plugins_loaded', 'bfox_bp_bible_directory_setup_root_component', 2);

function bfox_bp_bible_directory_setup() {
	global $bp, $biblefox;

	if ($bp->current_component == BFOX_BIBLE_SLUG && empty($bp->displayed_user->id)) {
		$bp->is_directory = true;

		$biblefox->set_refs(new BfoxRefs(urldecode($bp->current_action)));
		//add_filter('bp_ajax_querystring', 'bfox_bp_ajax_querystring', 1, 1);

		add_action('bp_before_directory_activity_content', 'bfox_bp_bible_directory_before_directory_activity_content');

		do_action('bfox_bp_bible_directory_setup');

		bfox_bp_core_load_template(apply_filters('bfox_bp_bible_directory_template', /*'activity/index'*/'bible/index'));
	}
}
add_action('wp', 'bfox_bp_bible_directory_setup', 2);

function bfox_bp_bible_directory_ajax_filter() {
	global $bp;

	if (0 < $_POST['page']) $_POST['page']--;

	// See bp_dtheme_object_filter()
	$object = esc_attr( $_POST['object'] );
	$filter = esc_attr( $_POST['filter'] );
	$page = esc_attr( $_POST['page'] );
	$ref_str = esc_attr( $_POST['search_terms'] );
	$scope = esc_attr( $_POST['scope'] );

	bfox_bp_dtheme_activity_loop($scope, $filter, false, 20, $page);
	if (!empty($ref_str)) $bp->ajax_querystring .= '&bfox_refs=' . urlencode($ref_str);

	bfox_bp_locate_template(array("activity/activity-loop.php"), true);
}
add_action('wp_ajax_bible_filter', 'bfox_bp_bible_directory_ajax_filter');

/**
 * Copy of bp_dtheme_activity_loop() but without echoing anything.
 *
 * We use this just to set $bp->ajax_querystring
 *
 * @param $scope
 * @param $filter
 * @param $query_string
 * @param $per_page
 * @param $page
 * @return unknown_type
 */
function bfox_bp_dtheme_activity_loop( $scope = 'all', $filter = false, $query_string = false, $per_page = 20, $page = 1 ) {
	global $bp;

	if ( !$query_string ) {
		/* If we are on a profile page we only want to show that users activity */
		if ( $bp->displayed_user->id ) {
			$query_string = 'user_id=' . $bp->displayed_user->id;
		} else {
			/* Make sure a scope is set. */
			if ( empty($scope) )
				$type = 'all';

			$feed_url = site_url( BP_ACTIVITY_SLUG . '/feed/' );

			switch ( $scope ) {
				case 'friends':
					$friend_ids = implode( ',', friends_get_friend_user_ids( $bp->loggedin_user->id ) );
					$query_string = 'user_id=' . $friend_ids;
					$feed_url = $bp->loggedin_user->domain . BP_ACTIVITY_SLUG . '/my-friends/feed/';
					break;
				case 'groups':
					$groups = groups_get_user_groups( $bp->loggedin_user->id );
					$group_ids = implode( ',', $groups['groups'] );
					$query_string = 'object=groups&primary_id=' . $group_ids . '&show_hidden=1';
					$feed_url = $bp->loggedin_user->domain . BP_ACTIVITY_SLUG . '/my-groups/feed/';
					break;
				case 'favorites':
					$favs = bp_activity_get_user_favorites( $bp->loggedin_user->id );

					if ( empty( $favs ) )
						$favorite_ids = false;

					$favorite_ids = implode( ',', (array)$favs );
					$query_string = 'include=' . $favorite_ids;
					$feed_url = $bp->loggedin_user->domain  . BP_ACTIVITY_SLUG . '/favorites/feed/';
					break;
				case 'atme':
					$query_string = 'search_terms=@' . bp_core_get_username( $bp->loggedin_user->id, $bp->loggedin_user->userdata->user_nicename, $bp->loggedin_user->userdata->user_login );
					$feed_url = $bp->loggedin_user->domain . BP_ACTIVITY_SLUG . '/mentions/feed/';

					/* Reset the number of new @ mentions for the user */
					delete_usermeta( $bp->loggedin_user->id, 'bp_new_mention_count' );
					break;
			}
		}

		/* Build the filter */
		if ( $filter && $filter != '-1' )
			$query_string .= '&action=' . $filter;

		/* If we are viewing a group then filter the activity just for this group */
		if ( $bp->groups->current_group ) {
			$query_string .= '&object=' . $bp->groups->id . '&primary_id=' . $bp->groups->current_group->id;

			/* If we're viewing a non-private group and the user is a member, show the hidden activity for the group */
			if ( 'public' != $bp->groups->current_group->status && groups_is_user_member( $bp->loggedin_user->id, $bp->groups->current_group->id ) )
				$query_string .= '&show_hidden=1';
		}

		/* Add the per_page param */
		$query_string .= '&per_page=' . $per_page;

		/* Add the comments param */
		if ( $bp->displayed_user->id || 'atme' == $scope )
			$query_string .= '&display_comments=stream';
		else
			$query_string .= '&display_comments=threaded';
	}

	/* Add the new page param */
	$args = explode( '&', trim( $query_string ) );
	foreach( $args as $arg ) {
		if ( false === strpos( $arg, 'page' ) )
			$new_args[] = $arg;
	}
	$query_string = implode( '&', $new_args ) . '&page=' . $page;

	$bp->ajax_querystring = apply_filters( 'bp_dtheme_ajax_querystring_activity_filter', $query_string, $scope );
}

function bfox_bp_bible_directory_add_nav_item() {
	?>
	<li<?php if ( bp_is_page( BFOX_BIBLE_SLUG ) ) : ?> class="selected"<?php endif; ?>>
		<a href="<?php echo get_option('home') ?>/<?php echo BFOX_BIBLE_SLUG ?>" title="<?php _e( 'Bible Reader', 'biblefox' ) ?>"><?php _e( 'Bible', 'biblefox' ) ?></a>
	</li>
	<?php
}
add_action('bp_nav_items', 'bfox_bp_bible_directory_add_nav_item');

function bfox_bp_bible_directory_before_directory_activity_content() {
	global $biblefox;
	$refs = $biblefox->refs();

	?>
			<h4><?php echo $refs->get_string() ?></h4>
			<?php $iframe = new BfoxIframe($refs) ?>
			<div class="bfox-iframe-wrap bfox-passage-iframe-wrap">
				<select class="bfox-iframe-select bfox-passage-iframe-select">
					<?php echo $iframe->select_options() ?>
				</select>
				<iframe class="bfox-iframe bfox-passage-iframe" src="<?php echo $iframe->url() ?>"></iframe>
			</div>
	<?php
}

function bfox_bp_bible_directory_search_form() {
	global $biblefox;

	$refs = $biblefox->refs();
	$search_value = $refs->get_string(BibleMeta::name_short);

?>
	<form action="" method="get" id="search-bible-form">
		<label><input type="text" name="s" id="bible_search" value="<?php echo attribute_escape($search_value) ?>" /></label>
		<input type="submit" id="groups_search_submit" name="groups_search_submit" value="<?php _e( 'Go to passage', 'buddypress' ) ?>" />
	</form>
<?php
}

?>