<?php

/*
Template Tags
*/

function bfox_tool_select($query = false) {
	$bibles = bfox_bibles();
	foreach ($bibles as $bible) {
		if ($post_id == $selected_post_id) $selected = " selected='selected'";
		else $selected = '';

		$content .= "<option name='$bible->shortName' value='$bible->shortName'$selected>$bible->longName</option>";
	}

	return '<select class="bfox-tool-select">' . $content . '</select>';
}

?>