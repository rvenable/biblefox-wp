<?php

class BfoxIframe {

	/**
	 * @var BfoxRef
	 */
	private $ref;

	private $url;

	public function __construct(BfoxRef $ref) {
		$this->ref = $ref;
		$translations = BfoxTranslations::replace_vars(BfoxTranslations::translations(), $this->ref);

		// Get the previously used Bible translation from cookies
		foreach ($translations as $id => $trans) if (empty($this->url) || $id == $_COOKIE['bfox-blog-iframe-select']) $this->url = $trans->url;

		$this->domains = BfoxTranslations::group_by_domain($translations);
	}

	/**
	 * Returns the currently selected URL
	 * @return string
	 */
	public function url() {
		return $this->url;
	}

	/**
	 * Returns a string of HTML option elements for these translations
	 * @return string
	 */
	public function select_options() {
		$content = '';

		foreach ($this->domains as $domain => $translations) {
			$content .= "<option value=''>From http://$domain</option>";
			foreach ($translations as $id => $trans) {
				if ($this->url == $trans->url) $selected = "selected='selected'";
				else $selected = '';

				$content .= "<option name='$id' value='$trans->url'$selected> - $trans->long_name</option>";
			}
		}

		return $content;
	}
}

function bfox_tool_iframe_select($query = false) {
	if (!$query) $query = bfox_tool_query();

	$selected_post_id = selected_bfox_tool_post_id();
	while ($query->have_posts()) {
		$query->the_post();

		$post_id = get_the_ID();
		$url = bfox_tool_source_url();
		$title = get_the_title();

		if ($post_id == $selected_post_id) $selected = " selected='selected'";
		else $selected = '';

		$content .= "<option name='$post_id' value='$url'$selected>$title</option>";
	}

	return '<select class="bfox-iframe-select">' . $content . '</select>';
}

?>