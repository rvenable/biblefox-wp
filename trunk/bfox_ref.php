<?php

/*
 * Classes
 */

class BfoxRefController {
	private static $_sharedInstance = NULL;

	/**
	 * @return BfoxRefController
	 */
	static function sharedInstance() {
		if (is_null(self::$_sharedInstance)) {
			self::$_sharedInstance = new BfoxRefController();
		}
		return self::$_sharedInstance;
	}

	private $linkActions = array();
	private $currentAction = '';

	function pushLinkAction($action) {
		if (!empty($this->currentAction)) $this->linkActions []= $this->currentAction;
		$this->currentAction = $action;
	}

	function popLinkAction() {
		$this->currentAction = array_pop($this->linkActions);
	}

	function currentAction($refStr = '') {
		return str_replace('%ref%', $refStr, $this->currentAction);
	}
}

?>