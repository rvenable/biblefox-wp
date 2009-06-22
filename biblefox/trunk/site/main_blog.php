<?php

class BiblefoxMainBlog {

	const cookie_translation = 'bfox_trans_str';

	private static $active_page = '';
	private static $trans_id = 0;
	private static $search_str = '';

	public static function init() {
		if (!empty($_COOKIE[self::cookie_translation])) self::$trans_id = $_COOKIE[self::cookie_translation];

		add_action('signup_header', 'BiblefoxMainBlog::signup_header');
	}

	public static function get_trans_id() {
		return self::$trans_id;
	}

	public static function set_trans_id($trans_id) {
		setcookie(self::cookie_translation, $trans_id, /* 365 days from now: */ time() + 60 * 60 * 24 * 365);
		self::$trans_id = $trans_id;
	}

	public static function set_search_str($str) {
		self::$search_str = $str;
	}

	public static function bible($query) {
		self::$active_page = 'bible';

		require_once BFOX_DIR . '/bible/bible.php';
		$bible = new BfoxBible($query);
		$bible->page();
	}

	public static function signup_header() {
		self::$active_page = 'signup';
	}

	public static function banner() {
		global $user_ID;

		$home = get_option('home');
		$bible = BfoxQuery::url();

		$pages = array(
			'home' => array($home . '/', __('Home')),
			'bible' => array($bible, __('Bible')),
			'signup' => array("$home/wp-signup.php", __('Sign Up'))
		);
		if (empty(self::$active_page)) self::$active_page = 'home';

		if (!empty($user_ID)) unset($pages['signup']);

		list($post_url, $hiddens) = BfoxUtility::get_post_url(BfoxQuery::page_url(BfoxQuery::page_search));

		?>
		<div id='bfox_header'>
			<div id='bfox_logo'>
				<a href='<?php echo $home ?>/' title='Biblefox.com'></a>
			</div>
			<div id="bfox_search">
				<a href='<?php echo BfoxQuery::page_url(BfoxQuery::page_passage) ?>'><?php _e('Bible Reader') ?></a>
				<form id="bible_search_form" action="<?php echo $post_url ?>" method="get">
					<?php echo $hiddens ?>
					<?php BfoxTrans::output_select(self::$trans_id) ?>
					<input type="text" name="<?php echo BfoxQuery::var_search ?>" value="<?php echo self::$search_str ?>" />
					<input type="submit" value="<?php _e('Search Bible', BFOX_DOMAIN); ?>" class="button" />
				</form>
			</div>
			<ul id='bfox_nav'>
				<?php
				foreach ($pages as $name => $page) {
					if ($name == self::$active_page) $class = " class='active_page'";
					else $class = '';
					echo "<li$class><a href='$page[0]'>$page[1]</a></li>\n";
				}
				?>
			</ul>
		</div>
		<?php
	}

	public static function sidebar() {
		if ('bible' == self::$active_page) BfoxBible::sidebar();
		else dynamic_sidebar();
	}
}

BiblefoxMainBlog::init();

?>