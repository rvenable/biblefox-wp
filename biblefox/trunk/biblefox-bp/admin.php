<?php

function bfox_bp_admin_page() {
	?>
	<div class="wrap">
		<h2><?php _e('Biblefox for BuddyPress Settings', 'bfox') ?></h2>
		<?php settings_errors() ?>
		<p><?php _e('Biblefox for BuddyPress finds Bible references in all BuddyPress activities, indexing your site by the Bible verses being discussed.', 'bfox') ?></p>
	<?php
		if (apply_filters('bfox_bp_show_admin_page', true)) do_action('bfox_bp_admin_page');
	?>
	</div>
	<?php
}

function bfox_bp_admin_settings() {
	?>
	<form action="options.php" method="post" class="standard-form" id="settings-form">
		<?php settings_fields('bfox-bp-admin-settings') ?>
		<?php do_settings_sections('bfox-bp-admin-settings') ?>
		<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Settings', 'bfox') ?>" />
		</p>
	</form>
	<?php
}
add_action('bfox_bp_admin_page', 'bfox_bp_admin_settings', 9);

function bfox_bp_admin_settings_main() {
	//echo '<p>Intro text for our settings section</p>';
}

function bfox_bp_admin_setting_enable_bible_directory() {
	// Have to include bible directory so that we can call bfox_bp_bible_directory_url()
	require_once BFOX_BP_DIR . '/bible-directory.php';

	?>
	<input id="bfox-enable-bible-directory" name="bfox-enable-bible-directory" type="checkbox" value="1" <?php checked(1, bfox_bp_get_option('bfox-enable-bible-directory')) ?>/>
	<p class="description"><?php _e('The Bible Directory is a page that displays a Bible passage and all BuddyPress activity that corresponds to that passage. It is the exact same as the default Activity directory but it displays a Bible passage at the top and filters the activities by the Bible reference.', 'bfox')?></p>
	<p class="description"><?php printf(__('Bible Directory URL: %s', 'bfox'), bfox_bp_bible_directory_url()) ?></p>
	<?php
}

?>