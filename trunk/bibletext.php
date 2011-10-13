<?php

	/*
	 AJAX function for sending the bible text
	 */
	function bfox_ajax_send_bible_text() {
		sleep(1);

		set_bfox_ref(new BfoxRef($_POST['ref_str']));

		ob_start();

		load_bfox_template('admin-bfox_tool');

		$content = ob_get_clean();
		$content = addslashes(str_replace("\n", '', $content));

		$script = "bfox_quick_view_loaded('$ref_str', '$content');";
		die($script);
	}
	add_action('wp_ajax_bfox_ajax_send_bible_text', 'bfox_ajax_send_bible_text');

?>
