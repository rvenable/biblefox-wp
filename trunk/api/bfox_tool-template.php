<?php

/*
Template Tags
*/

function bfox_tool_select($options = array()) {
	$bfoxTools = BfoxBibleToolController::sharedInstance();
	return $bfoxTools->select($options);
}

?>