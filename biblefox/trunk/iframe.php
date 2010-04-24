<?php

class BfoxIframe {

	/**
	 * Array of site information for Bible translation websites
	 * @var array
	 */
	public static $sites = array(
		'biblefox' => array(
			'name' => 'Biblefox.com',
			'site' => 'http://biblefox.com',
			'template' => '%site%/?bible_print_ref=%ref%&trans=%trans%&show_options=1',
		),
		'biblegateway' => array(
			'name' => 'BibleGateway.com',
			'site' => 'http://www.biblegateway.com',
			'template' => '%site%/passage/?search=%ref%&version=%trans%&interface=print',
			'mobile_site' => 'http://mobile.biblegateway.com',
			'mobile_template' => '%site%/passage/index.php?search=%ref%&version=%trans%',
		),
		'blueletter' => array(
			'name' => 'Blue Letter Bible',
			'site' => 'http://www.blueletterbible.org',
			'template' => '%site%/tools/printerFriendly.cfm?b=%book%&c=%chapter%&v=%verse%&t=%trans%',
			'mobile_site' => 'http://m.blb.org',
			'mobile_template' => '%site%/bible.cfm?b=%book%&c=%chapter%&v=%verse%&t=%trans%&type=1',
		),
	);

	/**
	 * Array of Bible translations, grouped by the site that hosts them
	 * @var array
	 */
	public static $translations = array(
		'biblefox' => array (
			array('WEB', 'World English Bible'),
			array('HNV', 'Hebrew Names Version'),
			array('KJV', 'King James Version'),
			array('ASV', 'American Standard Version'),
		),
		'biblegateway' => array(
			array('NIV', 'New International Version'),
			array('NASB', 'New American Standard Bible'),
			array('ESV', 'English Standard Version'),
			array('MSG', 'The Message'),
			array('NLT', 'New Living Translation'),
			array('NKJV', 'New King James Version'),
			array('TNIV', 'Today\'s New International Version'),
		),
		'blueletter' => array(
			array('NIV', 'New International Version'),
			array('ESV', 'English Standard Version'),
			array('RSV', 'Revised Standard Version'),
			array('NLT', 'New Living Translation'),
		),
	);

	/**
	 * @var BfoxRefs
	 */
	private $refs;

	private $url;
	private $options;

	public function __construct(BfoxRefs $refs, $is_mobile = false) {
		$this->refs = $refs;
		self::create_options($is_mobile);
	}

	/**
	 * Create an array of values to use in place of variables in template strings
	 *
	 * @param BfoxRefs $refs
	 * @return array
	 */
	private static function template_vars(BfoxRefs $refs) {
		$book_name = '';
		$ch1 = $vs1 = 0;

		if ($refs->is_valid()) {
			$bcvs = BfoxRefs::get_bcvs($refs->get_seqs());
			$books = array_keys($bcvs);
			$book_name = BibleMeta::get_book_name($books[0]);

			$cvs = array_shift($bcvs);
			$cv = array_shift($cvs);
			list($ch1, $vs1) = $cv->start;
		}

		return array(
			'%ref%' => urlencode($refs->get_string()),
			'%book%' => urlencode($book_name),
			'%chapter%' => $ch1,
			'%verse%' => $vs1,
		);
	}

	/**
	 * Creates the $this->options array
	 */
	private function create_options($is_mobile) {
		// Create template variables for this Bible reference
		$template_vars = self::template_vars($this->refs);

		// Get the previously used Bible translation from cookies
		$cookied_key = $_COOKIE['bfox-blog-iframe-select'];

		$this->url = '';
		$this->options = array();
		foreach (self::$translations as $site_id => $list) {
			$site = self::$sites[$site_id];

			if ($is_mobile && !empty($site['mobile_site'])) $template_vars['%site%'] = $site['mobile_site'];
			else $template_vars['%site%'] = $site['site'];

			foreach ($list as $trans_key => $translation) {
				$template_vars['%trans%'] = $translation[0];

				$iframe_key = 'bfox-iframe-key-' . $site_id . '-' . $translation[0];

				// Create the URL from the site's URL template, replacing the template variables
				if ($is_mobile && !empty($site['mobile_template'])) $template = $site['mobile_template'];
				else $template = $site['template'];
				$url = str_replace(array_keys($template_vars), $template_vars, $template);

				// Set the currently selected URL using the cookied key (or set it to the first available URL)
				if ($iframe_key == $cookied_key || empty($this->url)) $this->url = $url;

				$this->options[$site_id][$trans_key] = array($iframe_key, $url);
			}
		}
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
		foreach ($this->options as $site_id => $trans_options) {
			$site = self::$sites[$site_id];

			$label = $site['name'];
			$content .= "<option value=''>From $label</option>";

			foreach ($trans_options as $trans_key => $option) {
				$translation = self::$translations[$site_id][$trans_key];
				list($iframe_key, $url) = $option;
				$name = urlencode($iframe_key);
				$selected = ($url == $this->url) ? ' selected' : '';
				$label = $translation[1];
				//$translation[1] . ' (' . $site['name'] . ')',
				$content .= "<option name='$name' value='$url'$selected> - $label</option>";
			}
		}
		return $content;
	}
}

?>