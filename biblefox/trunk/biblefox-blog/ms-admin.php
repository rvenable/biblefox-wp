<?php

function bfox_ms_admin_page() {
	?>
	<div class="wrap">
		<h2><?php _e('Biblefox for WordPress - Network Admin Settings', 'bfox') ?></h2>
		<p><?php _e('Biblefox for WordPress finds Bible references in all your blog posts, indexing your blog by the Bible verses you write about.', 'bfox')?></p>
		<?php
			if (apply_filters('bfox_ms_show_admin_page', true)) do_action('bfox_ms_admin_page');
		?>
	</div>
	<?php
}

function bfox_ms_admin_settings() {
	// TODO: Change to ms-edit for WP 3
	?>
	<form action="wpmu-edit.php?action=bfox-ms" method="post" class="standard-form" id="settings-form">
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
		update_site_option('bfox-ms-allow-blog-options', $_POST['bfox-ms-allow-blog-options']);
		update_site_option('bfox-blog-options', bfox_blog_option_defaults($_POST['bfox-blog-options']));

		wp_redirect(admin_url('wpmu-admin.php?page=bfox-ms'));
	}
}
add_action('wpmuadminedit', 'bfox_ms_admin_page_save');

?>