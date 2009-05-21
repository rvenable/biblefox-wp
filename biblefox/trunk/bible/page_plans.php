<?php

class BfoxPagePlans extends BfoxPage {
	private $editor;

	public function __construct() {
		global $user_ID;

		parent::__construct();

		require_once BFOX_PLANS_DIR . '/edit.php';
		$this->editor = new BfoxPlanEdit($user_ID, BfoxPlans::user_type_user, BfoxQuery::page_url(BfoxQuery::page_plans));
	}

	public function page_load() {
		$this->editor->page_load();
	}

	public function print_scripts($base_url) {
		parent::print_scripts($base_url);
		$this->editor->add_head($base_url);
	}

	public function content() {
		$this->editor->content();
	}
}

?>