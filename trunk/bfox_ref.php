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

	private $linkDefaults = array();
	private $currentDefaults = array();

	function pushLinkDefaults($defaults = array()) {
		if (!empty($this->currentDefaults)) $this->linkDefaults []= $this->currentDefaults;
		$this->currentDefaults = $defaults;
	}

	function popLinkDefaults() {
		$this->currentDefaults = array_pop($this->linkDefaults);
	}

	function currentDefaults() {
		return $this->currentDefaults;
	}
}

?>