<?php

if (!defined('BFOX_TRANS_DIR')) define('BFOX_TRANS_DIR', dirname(__FILE__));
define('BFOX_TRANS_URL', WP_PLUGIN_URL . '/biblefox-translations');

// TODO: remove this define
define('BFOX_TABLE_PREFIX', $GLOBALS['wpdb']->base_prefix . 'bfox_');

require_once BFOX_TRANS_DIR . '/formatter.php';
require_once BFOX_TRANS_DIR . '/bible-search.php';

/**
 * A class for individual bible translations
 *
 */
class BfoxTrans {
	public $id, $short_name, $long_name, $table, $installed;

	const default_id = 1;
	const option_enabled = 'bfox_enabled_trans';

	private static $meta = array(
		1 =>
		array('ESV', 'English Standard Version'),
		array('WEB', 'World English Bible'),
		array('HNV', 'Hebrew Names Version'),
		array('KJV', 'King James Version'),
		array('ASV', 'American Standard Version')
	);

	/**
	 * Constructor
	 *
	 * @param $id
	 * @param $quick When true, the SQL check for the table is not performed
	 * @return unknown_type
	 */
	public function __construct($id = 0, $quick = FALSE) {
		if (empty($id) || !isset(self::$meta[$id])) $id = self::default_id;

		$this->id = $id;
		list($this->short_name, $this->long_name) = self::$meta[$id];

		// Set the translation table if it exists
		$this->table = BFOX_TABLE_PREFIX . "trans_{$this->short_name}_verses";

		if ('ESV' == $this->short_name) $this->installed = true;
		else {
			if ($quick) $this->installed = FALSE;
			else $this->installed = $this->does_table_exist();
		}
	}

	public function does_table_exist() {
		global $wpdb;
		return !empty($this->table) && ($wpdb->get_var("SHOW TABLES LIKE '$this->table'") == $this->table);
	}

	public static function get_ids_by_short_name() {
		$arr = array();
		foreach (self::$meta as $id => $meta) $arr[$meta[0]] = $id;
		return $arr;
	}

	/**
	 * Get the verse content for some bible references
	 *
	 * @param string $ref_where SQL WHERE statement as returned from BfoxRef::sql_where()
	 * @return string Formatted bible verse output
	 */
	public function get_verses($ref_where, BfoxVerseFormatter $formatter = NULL) {
		$verses = array();

		// We can only grab verses if the verse data exists
		if ($this->installed) {
			global $wpdb;
			$verses = $wpdb->get_results("SELECT unique_id, book_id, chapter_id, verse_id, verse FROM $this->table WHERE $ref_where");
		}

		if (!is_null($formatter)) return $formatter->format($verses);
		return $verses;
	}

	public function get_verses_in_books($ref_where, BfoxVerseFormatter $formatter = NULL) {
		$books = array();

		// We can only grab verses if the verse data exists
		if ($this->installed) {
			global $wpdb;
			$verses = $wpdb->get_results("SELECT unique_id, book_id, chapter_id, verse_id, verse FROM $this->table WHERE $ref_where");

			foreach ($verses as $verse) if ($verse->chapter_id) $books[$verse->book_id][$verse->chapter_id] []= $verse;
		}

		return $books;
	}

	/**
	 * Get the verse content for a sequence of chapters
	 *
	 * @param integer $book
	 * @param integer $chapter1
	 * @param integer $chapter2
	 * @param string $visible SQL WHERE statement to determine which scriptures are visible (ex. as returned from BfoxRef::sql_where())
	 * @return string Formatted bible verse output
	 */
	public function get_chapter_verses($book, $chapter1, $chapter2, $visible, BfoxVerseFormatter $formatter = NULL) {
		$chapters = array();

		// We can only grab verses if the verse data exists
		if ($this->installed) {
			global $wpdb;
			$verses = (array) $wpdb->get_results($wpdb->prepare("
				SELECT unique_id, chapter_id, verse_id, verse, ($visible) as visible
				FROM $this->table
				WHERE book_id = %d AND chapter_id >= %d AND chapter_id <= %d",
				$book, $chapter1, $chapter2));

			foreach ($verses as $verse) $chapters[$verse->chapter_id] []= $verse;
		}

		if (!is_null($formatter)) {
			$formatter->only_visible = TRUE;
			return $formatter->format_cv($chapters);
		}
		return $chapters;
	}

	/**
	 * Returns all the enabled BfoxTrans in an array
	 *
	 * @return array of BfoxTrans
	 */
	public static function get_enabled($quick = TRUE) {
		$translations = array();
		$update = FALSE;

		foreach ((array) get_site_option(self::option_enabled) as $id) {
			$trans = new BfoxTrans($id, $quick);
			if ($quick || $trans->installed) $translations[$id] = $trans;
			else $update = TRUE;
		}
		if ($update) self::set_enabled($translations);

		return $translations;
	}

	public static function set_enabled($translations) {
		$ids = array_keys($translations);
		sort($ids);
		update_site_option(self::option_enabled, $ids);
	}

	/**
	 * Returns all the installed BfoxTrans in an array
	 *
	 * @return array of BfoxTrans
	 */
	public static function get_installed() {
		$translations = array();
		foreach (self::$meta as $id => $meta) {
			$trans = new BfoxTrans($id);
			if ($trans->installed) $translations[$id] = $trans;
		}
		return $translations;
	}

	/**
	 * Return verse content for the given bible ref with minimum formatting
	 *
	 * @param BfoxRef $ref
	 * @return string
	 */
	public function get_verse_content(BfoxRef $ref) {
		// Get the verse data from the bible translation
		$formatter = new BfoxVerseFormatter();
		return $this->get_verses($ref->sql_where(), $formatter);
	}

	public function get_verse_content_foot(BfoxRef $ref, $delete_footnotes = FALSE) {
		// TODO3: This is pretty hacky, if the shortcode regex ever changes, this regex has to change as well!

		// Get the verse content, and filter it using the <footnote> tags as if they were [footnote] shortcodes
		// The regex being used here should mirror the regex returned by get_shortcode_regex() and is being used similarly to do_shortcode(),
		//  the only difference being that we only need to look for <footnote> shortcodes (and using chevrons instead of brackets)
		if ($delete_footnotes) return preg_replace('/<(footnote)\b(.*?)(?:(\/))?>(?:(.+?)<\/footnote>)?/s', '', $this->get_verse_content($ref));
		else $content = preg_replace_callback('/(.?)<(footnote)\b(.*?)(?:(\/))?>(?:(.+?)<\/\2>)?(.?)/s', 'do_shortcode_tag', $this->get_verse_content($ref));
		return array($content, shortfoot_get_list());
	}

	/**
	 * Return verse content for the given bible ref formatted for email output
	 *
	 * @param BfoxRef $ref
	 * @param BfoxTrans $trans
	 * @return string
	 */
	public function get_verse_content_email(BfoxRef $ref) {
		// Pre formatting is for when we can't use CSS (ie. in an email)
		// We just replace the tags which would have been formatted by css with tags that don't need formatting
		// We also need to run the shortcode function to correctly output footnotes

		$mods = array(
			'<span class="bible_poetry_indent_2"></span>' => '<span style="margin-left: 20px"></span>',
			'<span class="bible_poetry_indent_1"></span>' => '',
			'<span class="bible_end_poetry"></span>' => "<br/>\n",
			'<span class="bible_end_p"></span>' => "<br/><br/>\n",
			'</footnote>' => '[/foot]',
			'<footnote>' => '[foot]'
		);

		return do_shortcode(str_replace(array_keys($mods), array_values($mods), $this->get_verse_content($ref)));
	}

	private $_javascript_ref_array;

	/**
	 * Returns placeholder text for loading translation data by javascript
	 *
	 * @param string $ref_str
	 * @param string $text
	 * @return string
	 */
	public function get_javascript_placeholder($ref_str, $text = '') {
		if (!isset($this->_javascript_ref_array)) {
			$this->_javascript_ref_array = array();
			add_action('wp_footer', array($this, 'javascript_loader'));
		}
		$this->_javascript_ref_array[$ref_str] = true;
		$ref_str = strtolower(str_replace(array(' ', ':'), array('_', '_'), $ref_str));
		return '<div class="esv-ref esv-ref-' . $ref_str . '">' . $text . '</div>';
	}

	/**
	 * Adds javascript that retrieves the translation data and puts the retrieved data where it needs to be
	 * @return unknown_type
	 */
	public function javascript_loader() {
		$passage = urlencode(implode(';', array_keys($this->_javascript_ref_array)));
		// Insert the translation data onto the page
		echo '<div style="display: none;"><script type="text/javascript" src="http://www.gnpcb.org/esv/share/js/?action=doPassageQuery&passage=' . $passage . '&include-footnotes=0&include-audio-link=0&include-headings=0&include-subheadings=0"></script></div>';

		// Put the translation data where it needs to go
		echo '<script type="text/javascript" src="' . BFOX_TRANS_URL . '/trans-loader.js"></script>';
	}
}

/**
 * Checks if we want to display the Bible Print Page, displays it and exits
 */
function bfox_trans_check_for_bible_print_page() {
	if (!empty($_REQUEST['bfoxp'])) {
		require_once BFOX_TRANS_DIR . '/theme/print.php';
		exit;
	}
}
add_action('init', 'bfox_trans_check_for_bible_print_page', 1000); // Add after other things have finished init

function bfox_trans_admin_install_url() {
	if (is_multisite()) $url = menu_page_url('bfox-ms', false);
	else $url = menu_page_url('bfox-blog-settings', false);
	return add_query_arg('bfox_trans_install', true, $url);
}

function bfox_trans_admin() {
	?>
		<h3 id="trans-install"><?php _e('Install Bible Translations', 'bfox') ?></h3>
		<p><?php _e('Run this to install any Bible translation files you have stored in', 'bfox') ?> <?php echo BFOX_TRANS_DIR . '/data' ?></p>
		<p><?php _e('Installed translations can be viewed using this URL:', 'bfox') ?> <?php echo add_query_arg(array('bfoxp' => '%ref%', 'trans' => '%trans%'), site_url('/')) ?></p>
		<p><a class="button-primary" href="<?php echo bfox_trans_admin_install_url() ?>"><?php _e('Install Bible Translations', 'bfox') ?></a> <?php _e('Bible Translations are big files, so this will take a few minutes', 'bfox') ?></p>
		<br/>
	<?php
}
if (is_multisite()) add_action('bfox_ms_admin_page', 'bfox_trans_admin', 32);
else add_action('bfox_blog_admin_page', 'bfox_trans_admin', 32);

function bfox_trans_admin_install($show_settings) {
	if ($show_settings && $_GET['bfox_trans_install']) {
		?>
		<h3><?php _e('Installing Bible Translations', 'bfox') ?></h3>
		<?php

		include BFOX_TRANS_DIR . '/installer.php';
		$msgs = BfoxTransInstaller::run();
		echo implode("<br/>", $msgs);

		?>
		<p><a class="button-primary" href="<?php echo bfox_trans_admin_install_url() ?>"><?php _e('Continue installing Bible Translations', 'bfox') ?></a> <?php _e('Bible Translations are big files, so this will take a few minutes', 'bfox') ?></p>
		<?php

		$show_settings = false;
	}
	return $show_settings;
}
if (is_multisite()) add_filter('bfox_ms_show_admin_page', 'bfox_trans_admin_install');
else add_filter('bfox_blog_show_admin_page', 'bfox_trans_admin_install');

?>