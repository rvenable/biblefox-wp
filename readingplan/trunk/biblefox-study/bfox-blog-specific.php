<?php

	/*
	 This include file is for functions related to tables which correspond to a specific blog.
	 (As opposed to tables which are for an entire WPMU installation)
	 */
	
	function bfox_get_verses_table_name($id)
	{
		if (!isset($id))
			$id = bfox_get_default_version();
		
		return BFOX_BASE_TABLE_PREFIX . "trans{$id}_verses";
	}
	
	?>
