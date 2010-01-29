<?php

define(BFOX_BP_DIR, dirname(__FILE__));

require_once BFOX_BP_DIR . '/activity.php';

function bfox_buddypress_admin_menu() {
	add_submenu_page(
		'bp-general-settings',
		__('Bible Settings', 'biblefox'),
		__('Bible Settings', 'biblefox'),
		'manage_options',
		'bfox-bp-settings',
		'bfox_bp_admin_settings'
	);
}
add_action('admin_menu', 'bfox_buddypress_admin_menu', 20);

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