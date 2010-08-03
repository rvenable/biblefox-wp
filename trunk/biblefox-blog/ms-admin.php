<?php

function bfox_ms_admin_page() {
	?>
	<div class="wrap">
		<h2><?php _e('Biblefox for WordPress - Network Admin Settings', 'bfox') ?></h2>
		<?php settings_errors() ?>
		<p><?php _e('Biblefox for WordPress finds Bible references in all your blog posts, indexing your blog by the Bible verses you write about.', 'bfox')?></p>
		<?php
			if (apply_filters('bfox_ms_show_admin_page', true)) do_action('bfox_ms_admin_page');
		?>
	</div>
	<?php
}

function bfox_ms_admin_settings() {
	?>
	<form action="ms-edit.php?action=bfox-ms" method="post" class="standard-form" id="settings-form">
		<?php settings_fields('bfox-ms-admin-settings') ?>
		<?php do_settings_sections('bfox-ms-admin-settings') ?>
		<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Settings', 'bfox') ?>" />
		</p>
	</form>
	<?php
}
add_action('bfox_ms_admin_page', 'bfox_ms_admin_settings');

function bfox_ms_admin_settings_main() {
	//echo '<p>Intro text for our settings section</p>';
}

function bfox_ms_admin_setting_allow_blog_options() {
	?>
	<input id="bfox-ms-allow-blog-options" name="bfox-ms-allow-blog-options" type="checkbox" value="1" <?php checked(1, get_site_option('bfox-ms-allow-blog-options')) ?>/>
	<p class="description"><?php _e('When checked, individual blogs can edit their own Biblefox settings.', 'bfox') ?></p>
	<?php
}

function bfox_ms_admin_page_save() {
	if ('bfox-ms' == $_GET['action']) {
		check_admin_referer('bfox-ms-admin-settings-options');

		update_site_option('bfox-ms-allow-blog-options', $_POST['bfox-ms-allow-blog-options']);
		update_site_option('bfox-blog-options', $_POST['bfox-blog-options']);

		/**
		 *  Handle settings errors and return to options page
		 */
		// If no settings errors were registered add a general 'updated' message.
		if ( !count( get_settings_errors() ) )
			add_settings_error('general', 'settings_updated', __('Settings saved.', 'bfox'), 'updated');
		set_transient('settings_errors', get_settings_errors(), 30);

		/**
		 * Redirect back to the settings page that was submitted
		 */
		$goback = add_query_arg( 'updated', 'true',  wp_get_referer() );
		wp_redirect( $goback );
		exit;
	}
}
add_action('wpmuadminedit', 'bfox_ms_admin_page_save');

?>