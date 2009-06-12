<?php

class BfoxPagePlans extends BfoxPage {
	private $editor;

	public function page_load() {
		global $user_ID;

		require_once BFOX_PLANS_DIR . '/edit.php';
		$this->editor = new BfoxPlanEdit($user_ID, BfoxPlans::user_type_user, BfoxQuery::page_url(BfoxQuery::page_plans));
		$this->editor->page_load();
	}

	public function content() {
		$this->editor->content();
	}
}

?>