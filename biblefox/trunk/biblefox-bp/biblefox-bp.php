<?php

define(BFOX_BP_DIR, dirname(__FILE__));
define(BFOX_BP_URL, BFOX_URL . '/biblefox-bp');

require_once BFOX_BP_DIR . '/activity.php';

// TODO: only load if user option
require_once BFOX_BP_DIR . '/bible-directory.php';

// Add Ref Replace filters
add_filter('bp_get_activity_content_body', 'bfox_ref_replace_html');
add_filter('bp_get_the_topic_post_content', 'bfox_ref_replace_html');
add_action('bp_get_activity_action', 'bfox_ref_replace_html');

function bfox_bp_init() {
	wp_register_style('biblefox-bp', BFOX_BP_URL . '/biblefox-bp.css', array(), BFOX_VERSION);
	//wp_register_script('biblefox-bp', BFOX_BP_URL . '/biblefox-bp.js', array('jquery'), BFOX_VERSION);
}
add_action('init', 'bfox_bp_init');

// HACK: this function is a hack to get around a bug in bp_core_load_template() and bp_core_catch_no_access()
function bfox_bp_core_load_template($template) {
	bp_core_load_template($template);
	remove_action('wp', 'bp_core_catch_no_access');
}

/**
 * Function that imitates locate_template() but adds a filter so we can modify the located file name before we try to load it
 *
 * @param $template_names
 * @param $load
 * @return string located file name
 */
function bfox_bp_locate_template($template_names, $load = false) {
	if (!is_array($template_names))
		return '';

	$located = apply_filters('bfox_bp_located_template', locate_template($template_names, false), $template_names);

	if ($load && '' != $located)
		load_template($located);

	return $located;
}

/**
 * Locates theme files within the plugin if they weren't found in the theme
 *
 * @param string $located
 * @param array $template_names
 * @return string
 */
function bfox_bp_located_template($located, $template_names) {
	if (empty($located)) {
		$dir = BFOX_BP_DIR . '/theme/';
		foreach((array) $template_names as $template_name) {
			$template_name = ltrim($template_name, '/');
			list($start, $end) = explode('/', $template_name, 2);
			if (('bible' == strtolower($start)) && (file_exists($dir . $template_name))) {
				$located = $dir . $template_name;
				break;
			}
		}
	}
	return $located;
}
//add_filter('bp_located_template', 'bfox_bp_located_template', 10, 2);
//add_filter('bfox_bp_located_template', 'bfox_bp_located_template', 10, 2);

function bfox_bp_admin_menu() {
	add_submenu_page(
		'bp-general-settings',
		__('Bible Settings', 'biblefox'),
		__('Bible Settings', 'biblefox'),
		'manage_options',
		'bfox-bp-settings',
		'bfox_bp_admin_settings'
	);
}
add_action('admin_menu', 'bfox_bp_admin_menu', 20);


function bfox_bp_admin_settings() {
	?>
	<div class="wrap">
		<h2><?php _e('Biblefox for BuddyPress Settings', 'biblefox') ?></h2>
	<?php if (apply_filters('bfox_bp_admin_show_settings', true)): ?>
		<p><?php _e('Biblefox for BuddyPress finds Bible references in all BuddyPress activity, indexing your site by the Bible verses that people are discussing.', 'biblefox')?></p>
		<?php do_action('bfox_bp_admin_settings') ?>
	<?php endif ?>
	</div>
	<?php
}

do_action('bfox_bp_loaded');

?>