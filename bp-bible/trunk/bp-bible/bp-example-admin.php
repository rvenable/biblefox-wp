<?php

/***
 * This file is used to add site administration menus to the WordPress backend.
 *
 * If you need to provide configuration options for your component that can only
 * be modified by a site administrator, this is the best place to do it.
 *
 * However, if your component has settings that need to be configured on a user
 * by user basis - it's best to hook into the front end "Settings" menu.
 */

/**
 * bp_bible_add_admin_menu()
 *
 * Registers the component admin menu into the admin menu array.
 */
function bp_bible_add_admin_menu() {
	global $wpdb, $bp;

	if ( !is_site_admin() )
		return false;

	/**
	 * Add the component's administration tab under the "BuddyPress" menu for site administrators
	 *
	 * Use 'bp-core.php' as the first parameter to add your submenu to the "BuddyPress" menu.
	 * Use 'wpmu-admin.php' if you want it under the "Site Admin" menu.
	 */
	add_submenu_page( 'bp-core.php', __( 'Bible Admin', 'bp-bible' ), __( 'Bible Admin', 'bp-bible' ), 1, "bp_bible_settings", "bp_bible_admin" );
}
add_action( 'admin_menu', 'bp_bible_add_admin_menu' );

/**
 * bp_bible_admin()
 *
 * Checks for form submission, saves component settings and outputs admin screen HTML.
 */
function bp_bible_admin() {
	global $bp, $bbpress_live;

	/* If the form has been submitted and the admin referrer checks out, save the settings */
	if ( isset( $_POST['submit'] ) && check_admin_referer('bible-settings') ) {
		update_option( 'bible-setting-one', $_POST['bible-setting-one'] );
		update_option( 'bible-setting-two', $_POST['bible-setting-two'] );

		$updated = true;
	}

	$setting_one = get_option( 'bible-setting-one' );
	$setting_two = get_option( 'bible-setting-two' );
?>
	<div class="wrap">
		<h2><?php _e( 'Bible Admin', 'bp-bible' ) ?></h2>
		<br />

		<?php if ( isset($updated) ) : ?><?php echo "<div id='message' class='updated fade'><p>" . __( 'Settings Updated.', 'bp-bible' ) . "</p></div>" ?><?php endif; ?>

		<form action="<?php echo site_url() . '/wp-admin/admin.php?page=bp_bible_settings' ?>" name="bible-settings-form" id="bible-settings-form" method="post">

			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="target_uri"><?php _e( 'Option One', 'bp-bible' ) ?></label></th>
					<td>
						<input name="bible-setting-one" type="text" id="bible-setting-one" value="<?php echo attribute_escape( $setting_one ); ?>" size="60" />
					</td>
				</tr>
					<th scope="row"><label for="target_uri"><?php _e( 'Option Two', 'bp-bible' ) ?></label></th>
					<td>
						<input name="bible-setting-two" type="text" id="bible-setting-two" value="<?php echo attribute_escape( $setting_two ); ?>" size="60" />
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="submit" value="<?php _e( 'Save Settings', 'bp-bible' ) ?>"/>
			</p>

			<?php
			/* This is very important, don't leave it out. */
			wp_nonce_field( 'bible-settings' );
			?>
		</form>
	</div>
<?php
}
?>