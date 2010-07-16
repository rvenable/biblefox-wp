<?php

function bfox_blog_admin_page() {
	?>
	<div class="wrap">
		<h2><?php _e('Bible Blog Settings', 'bfox') ?></h2>
		<?php settings_errors() ?>
		<p><?php _e('Biblefox finds Bible references in all your blog posts, indexing your blog by the Bible verses you write about.', 'bfox')?></p>
	<?php
		if (apply_filters('bfox_blog_show_admin_page', true)) do_action('bfox_blog_admin_page');
	?>
	</div>
	<?php
}

function bfox_blog_admin_settings() {
	?>
	<form action="options.php" method="post" class="standard-form" id="settings-form">
		<?php settings_fields('bfox-blog-admin-settings') ?>
		<?php do_settings_sections('bfox-blog-admin-settings') ?>
		<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Settings', 'bfox') ?>" />
		</p>
	</form>
	<?php
}
add_action('bfox_blog_admin_page', 'bfox_blog_admin_settings', 9);

function bfox_blog_admin_settings_main() {
	//echo '<p>Intro text for our settings section</p>';
}

function bfox_blog_admin_setting_tooltips() {
	?>
	<input id="bfox-tooltips" name="bfox-blog-options[disable-tooltips]" type="checkbox" value="1" <?php checked(1, bfox_blog_option('disable-tooltips')) ?>/>
	<p class="description"><?php _e('Bible tooltips are javascript popups that display Scripture when clicking on a Bible reference link.', 'bfox')?></p>
	<?php
}

?>