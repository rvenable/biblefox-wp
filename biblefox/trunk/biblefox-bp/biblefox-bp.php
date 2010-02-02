<?php

define(BFOX_BP_DIR, dirname(__FILE__));
define(BFOX_BP_URL, BFOX_URL . '/biblefox-bp');

require_once BFOX_BP_DIR . '/activity.php';

// TODO: only load if user option
require_once BFOX_BP_DIR . '/bible-directory.php';

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

				wp_enqueue_style('biblefox-bp', BFOX_BP_URL . '/theme/_inc/css/biblefox-bp.css', array(), BFOX_VERSION);
				wp_enqueue_script('biblefox-bp', BFOX_BP_URL . '/theme/_inc/js/biblefox-bp.js', array(), BFOX_VERSION);

				break;
			}
		}
	}
	return $located;
}
add_filter('bp_located_template', 'bfox_bp_located_template', 10, 2);
add_filter('bfox_bp_located_template', 'bfox_bp_located_template', 10, 2);

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
	$refresh_url = admin_url('admin.php?page=bfox-bp-settings&refresh=1');
	?>
	<div class="wrap">
		<h2><?php _e('Biblefox for BuddyPress Settings', 'biblefox') ?></h2>

	<?php if ($_GET['refresh']): ?>
		<?php
		global $biblefox;

		// If this is the first page of refreshing, delete all the activities
		$offset = (int) $_GET['offset'];
		if (0 == $offset) $biblefox->activity_refs->delete_all();

		// Refresh this set of activities
		extract($biblefox->activity_refs->simple_refresh('id', 'content', $_GET['limit'], $offset));
		$scan_total = $_GET['scan_total'] + $scanned;
		$index_total += $_GET['index_total'] + $indexed;

		?>
		<h3><?php _e('Refreshing Bible Index...', 'biblefox') ?></h3>
		<p><?php printf(__('Scanned %d activities (out of %d total activities)<br/>%d contained bible references', 'biblefox'), $scan_total, $total, $index_total) ?></p>

		<?php
		$offset += $scanned;
		$next_url = add_query_arg(compact('offset', 'scan_total', 'index_total'), $refresh_url);

		if ($offset < $total): ?>
		<p><?php _e("If your browser doesn't start loading the next page automatically click this link:", 'biblefox'); ?> <a class="button" href="<?php echo $next_url ?>"><?php _e("Continue", 'biblefox'); ?></a></p>
		<script type='text/javascript'>
		<!--
		function nextpage() {
			location.href = "<?php echo $next_url ?>";
		}
		setTimeout( "nextpage()", 250 );
		//-->
		</script>
		<?php endif ?>

	<?php else: ?>

		<p><?php _e('Biblefox for BuddyPress finds Bible references in all BuddyPress activity, indexing your site by the Bible verses that people are discussing.', 'biblefox')?></p>

		<h3><?php _e('Refresh Bible Index', 'biblefox') ?></h3>
		<p><?php _e('You can refresh your Bible index to make sure all activity is indexed properly (this is good to do after Biblefox upgrades).', 'biblefox') ?></p>
		<p><a class="button" href="<?php echo $refresh_url ?>"><?php _e('Refresh Bible Index', 'biblefox') ?></a></p>

	<?php endif ?>

	</div>
	<?php
}

do_action('bfox_bp_loaded');

?>