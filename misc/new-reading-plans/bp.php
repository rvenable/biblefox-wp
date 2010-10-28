<?php

function bfox_bp_plans_register() {
	if (function_exists('bpcp_register_post_type')) {
		$labels = Array(
			'my_posts' 		=> __( 'My Reading Plans (%s)' ),
			'posts_directory'	=> __( 'Reading Plans Directory' ),
			'name'			=> __( 'Plans' ),
			'all_posts'		=> __( 'All Reading Plans (%s)' ),
			'type_creator'		=> __( 'Reading Plan Creator' ),
			'activity_tab'		=> __( 'Reading Plans' ),
			'show_created'		=> __( 'Show New Reading Plans' ),
			'my_posts_public_activity' => __( 'My Reading Plans - Public Activity' )
		);

		$activity = Array(
			'create_posts' => true,
			'edit_posts' => true
		);

		$args = Array(
			'id'		=> 'bfox_plan',
			'nav'		=> true,
			'theme_nav'	=> true,
			'labels'	=> $labels,
//			'format_notifications' => Array( &$ep_views['template'], 'activity_notifications' ),
			'theme_dir'	=> '',//EP_THEMES_DIR . '/bp',
//			'activity'	=> $activity,
			'forum'		=> false
		);

		$args = apply_filters( 'bfox_ep_event_post_type_bp', $args );

		bpcp_register_post_type($args);

		add_action('bpcp_edit_add_metaboxes', 'bfox_plans_register_meta_box_cb_simple');
	}
}
add_action('plugins_loaded', 'bfox_bp_plans_register');

function bfox_plans_register_meta_box_cb_simple() {
	wp_enqueue_style('bfox-plan-reading-lists');
	wp_enqueue_script('bfox-plan-ajax');

	add_meta_box('bfox-plan-view', __('View Readings', 'bfox'), 'bfox_plans_view_meta_box_cb', 'bfox_plan', 'normal', 'low');
	add_meta_box('bfox-plan-schedule1', __('Edit Schedule', 'bfox'), 'bfox_plans_edit_schedule_meta_box_cb', 'bfox_plan', 'normal', 'high');
}

function bfox_bp_plans_setup_nav() {
	global $bp;

	$slug = 'plans';
	$link = (!empty($bp->displayed_user->domain) ? $bp->displayed_user->domain : $bp->loggedin_user->domain) . $slug . '/';

	bp_core_new_nav_item(array(
		'name' => __('Reading Plans', 'bfox'),
		'slug' => $slug,
		'position' => 21,
		'show_for_displayed_user' => true,
		'screen_function' => 'bfox_bp_plans_my_plans_screen_view',
		'default_subnav_slug' => 'my-plans',
	));

/*	bp_core_new_subnav_item( array(
		'name' => __('Calendar', 'bfox'),
		'slug' => 'calendar',
		'parent_slug' => $slug,
		'parent_url' => $link,
		'screen_function' => 'bfox_bp_bible_study_calendar_screen_view',
		'position' => 130,
	) );
*/
	bp_core_new_subnav_item( array(
		'name' => __('My Plans', 'bfox'),
		'slug' => 'my-plans',
		'parent_slug' => $slug,
		'parent_url' => $link,
		'screen_function' => 'bfox_bp_plans_my_plans_screen_view',
		'position' => 130,
	) );

	/*
	if ($slug == $bp->current_component && ('schedule' == $bp->current_action || 'delete-schedule' == $bp->current_action)) {
		$schedule = bfox_bp_schedule(BfoxReadingSchedule::schedule($bp->action_variables[0]));
		if ($schedule->id) {
			if ($schedule->is_user_member($bp->displayed_user->id)) {
				if ('schedule' == $bp->current_action) {
					bfox_bp_plans_reading_redirect($schedule, $bp->action_variables[1], $bp->action_variables[2]);

					$bp->current_action = "schedule/$schedule->id";
					bp_core_new_subnav_item( array(
						'name' => __('View Schedule', 'bfox'),
						'slug' => $bp->current_action,
						'parent_slug' => $slug,
						'parent_url' => $link,
						'screen_function' => 'bfox_bp_bible_study_edit_schedule_screen_view',
						'position' => 130,
					));
				}
				elseif ('delete-schedule' == $bp->current_action) {
					$bp->current_action = "delete-schedule/$schedule->id";
					bp_core_new_subnav_item( array(
						'name' => __('Delete Schedule', 'bfox'),
						'slug' => $bp->current_action,
						'parent_slug' => $slug,
						'parent_url' => $link,
						'screen_function' => 'bfox_bp_bible_study_edit_schedule_screen_view',
						'position' => 130,
					) );
				}
			}
			else bp_core_redirect($schedule->url());
		}
		else bp_core_redirect($link);
	}
	*/

	do_action('bfox_bp_plans_setup_nav', $slug, $link);
}
//add_action('wp', 'bfox_bp_plans_setup_nav', 2);
//add_action('admin_menu', 'bfox_bp_plans_setup_nav', 2);

function bfox_bp_plans_my_plans_screen_view() {
	add_action('bp_template_content', 'bfox_bp_plans_my_plans_screen_view_content');
	bfox_bp_core_load_template(apply_filters('bfox_bp_plans_my_plans_screen_template', 'members/single/plugins'));
}

function bfox_bp_plans_my_plans_screen_view_content() {
	global $bp, $post;
	query_posts(bfox_plan_query_for_user($bp->displayed_user->id));
	?>
	<?php if ( have_posts() ) : ?>

		<?php while (have_posts()) : the_post(); ?>

			<?php do_action( 'bp_before_blog_post' ) ?>

			<div class="post" id="post-<?php the_ID(); ?>">

				<div class="author-box">
					<?php echo get_avatar( get_the_author_meta( 'user_email' ), '50' ); ?>
					<p><?php printf( __( 'by %s', 'buddypress' ), bp_core_get_userlink( $post->post_author ) ) ?></p>
				</div>

				<div class="post-content">
					<h2 class="posttitle"><a href="<?php the_permalink() ?>" rel="bookmark" title="<?php _e( 'Permanent Link to', 'buddypress' ) ?> <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
					<p><a href=""><?php printf( __( 'View %s\'s reading progress', 'buddypress' ), bp_core_get_user_displayname( $post->post_author ) ) ?></a></p>

					<p class="date"><?php the_time() ?> <em><?php _e( 'in', 'buddypress' ) ?> <?php the_category(', ') ?> <?php printf( __( 'by %s', 'buddypress' ), bp_core_get_userlink( $post->post_author ) ) ?></em></p>

					<div class="entry">
						<?php //the_content( __( 'Read the rest of this entry &rarr;', 'buddypress' ) ); ?>
					</div>

					<p class="postmetadata"><span class="tags"><?php the_tags( __( 'Tags: ', 'buddypress' ), ', ', '<br />'); ?></span> <span class="comments"><?php comments_popup_link( __( 'No Comments &#187;', 'buddypress' ), __( '1 Comment &#187;', 'buddypress' ), __( '% Comments &#187;', 'buddypress' ) ); ?></span></p>
				</div>

			</div>

			<?php do_action( 'bp_after_blog_post' ) ?>

		<?php endwhile; ?>

		<div class="navigation">

			<div class="alignleft"><?php next_posts_link( __( '&larr; Previous Entries', 'buddypress' ) ) ?></div>
			<div class="alignright"><?php previous_posts_link( __( 'Next Entries &rarr;', 'buddypress' ) ) ?></div>

		</div>

	<?php else : ?>

		<h2 class="center"><?php _e( 'Not Found', 'buddypress' ) ?></h2>
		<p class="center"><?php _e( 'Sorry, but you are looking for something that isn\'t here.', 'buddypress' ) ?></p>

		<?php locate_template( array( 'searchform.php' ), true ) ?>

	<?php endif; ?>
	<?php
}


?>