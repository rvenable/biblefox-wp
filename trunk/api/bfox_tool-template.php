<?php

/*
Template Tags
*/

function bfox_tool_select($query = false) {
	$bfoxTools = BfoxBibleToolController::sharedInstance();
	return $bfoxTools->select();
}

?>