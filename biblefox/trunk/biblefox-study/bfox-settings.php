<?php

/*
 This include file is for functions related to settings.
 */

/**
 * Hacks the user profile page so that the Settings API functions can work for it.
 *
 * See http://codex.wordpress.org/Settings_API for more information on the Settings API
 *
 */
function bfox_hack_user_profile()
{
	// Add all the settings sections to the user profile page
	// The 'profile' ID should be a unique ID that is only used for this page
	do_settings_sections('profile');
}
add_action('show_user_profile', 'bfox_hack_user_profile');
add_action('edit_user_profile', 'bfox_hack_user_profile');

/**
 * Updates the DB with any options updated on the User Profile page
 *
 * @param unknown_type $user_id
 */
function bfox_profile_update($user_id)
{
	$email_readings = 'false';
	if (isset($_POST['bfox_email_readings']))
		$email_readings = $_POST['bfox_email_readings'];
	update_user_option($user_id, 'bfox_email_readings', $email_readings, TRUE);
}
add_action('profile_update', 'bfox_profile_update');

/**
 * Called when a user is registered to save any default Biblefox settings
 *
 * @param unknown_type $user_id
 */
function bfox_user_register($user_id)
{
	$_POST['bfox_email_readings'] = 'true';
	bfox_profile_update($user_id);
}
add_action('user_register', 'bfox_user_register');

/**
 * Function for updating user options that haven't been set yet.
 *
 * @param unknown_type $user_id
 */
function bfox_user_add_defaults($user_id)
{
	if ('false' != get_user_option('bfox_email_readings', $user_id))
		update_user_option($user_id, 'bfox_email_readings', 'true', TRUE);
}

/**
 * Callback function for Bible Settings section of User Profile page
 *
 */
function bfox_bible_settings_cb()
{
	// Nothing to do...
}

/**
 * Callback function for the Email Readings user profile setting
 *
 */
function bfox_email_readings_cb()
{
	if ('true' == get_user_option('bfox_email_readings'))
		$checked = 'checked="checked"';

	echo "<label for='bfox_email_readings'><input type='checkbox' $checked name='bfox_email_readings' id='bfox_email_readings' value='true' /> " . __('Do you want to receive the scripture in your reading plans by email?') . "</label>";
}

/**
 * Setup the settings options
 *
 */
function bfox_setup_settings()
{
	// Add bible settings section to the user profile page (requires bfox_hack_user_profile() functionality)
	add_settings_section('bfox_profile_bible', 'Bible Settings', 'bfox_bible_settings_cb', 'profile');

	// Bible Settings
	add_settings_field('bfox_email_readings', 'Email Readings', 'bfox_email_readings_cb', 'profile', 'bfox_profile_bible');
}
add_action('admin_init', 'bfox_setup_settings');

?>
