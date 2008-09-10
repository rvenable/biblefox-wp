<?php

	/*
	 This include file is for functions related to settings.
	 */

	function bfox_get_default_version()
	{
		global $wpdb;
		return $wpdb->get_var("SELECT id FROM " . BFOX_TRANSLATIONS_TABLE . " WHERE is_default = TRUE");
	}

	?>
