<?php

class BfoxTranslations {

	public static function translation_domain($translation) {
		return parse_url($translation->url, PHP_URL_HOST);
	}

	public static function translations($use_default_if_empty = true) {
		$translations = get_option('bfox_translations');

		// If we don't have any translations saved used the defaults
		if ($use_default_if_empty && empty($translations)) {
			$defaults = (array) apply_filters('bfox_translation_defaults', array(
				array('ESV', 'English Standard Version', 'http://biblefox.com/?bfoxp=%ref%&trans=ESV'),
				array('WEB', 'World English Bible', 'http://biblefox.com/?bfoxp=%ref%&trans=WEB'),
				array('HNV', 'Hebrew Names Version', 'http://biblefox.com/?bfoxp=%ref%&trans=HNV'),
				array('KJV', 'King James Version', 'http://biblefox.com/?bfoxp=%ref%&trans=KJV'),
				array('ASV', 'American Standard Version', 'http://biblefox.com/?bfoxp=%ref%&trans=ASV'),
			));

			foreach ($defaults as $default) {
				$translation = new stdClass;
				$translation->short_name = array_shift($default);
				$translation->long_name = array_shift($default);
				$translation->url = array_shift($default);
				$translations []= $translation;
			}
		}

		return (array) apply_filters('bfox_translations', (array) $translations);
	}

	private static function save_translations($translations) {
		update_option('bfox_translations', $translations);
	}

	public static function add_translation($new_trans) {
		$translations = self::translations();
		$translations []= $new_trans;
		self::save_translations($translations);
	}

	public static function delete_translation($id) {
		$translations = self::translations();
		unset($translations[$id]);
		self::save_translations($translations);
	}

	public static function replace_vars($translations, BfoxRef $ref) {
		$template_vars = self::template_vars($ref);
		$keys = array_keys($template_vars);

		foreach ($translations as &$trans) $trans->url = str_replace($keys, $template_vars, $trans->url);

		return $translations;
	}

	public static function group_by_domain($translations) {
		$grouped = array();
		foreach ($translations as $index => $trans) $grouped[self::translation_domain($trans)][$index] = $trans;
		return $grouped;
	}

	/**
	 * Create an array of values to use in place of variables in template strings
	 *
	 * @param BfoxRef $ref
	 * @return array
	 */
	private static function template_vars(BfoxRef $ref) {
		$book_name = '';
		$ch1 = $vs1 = 0;

		if ($ref->is_valid()) {
			$bcvs = BfoxRef::get_bcvs($ref->get_seqs());
			$books = array_keys($bcvs);
			$book_name = BibleMeta::get_book_name($books[0]);

			$cvs = array_shift($bcvs);
			$cv = array_shift($cvs);
			list($ch1, $vs1) = $cv->start;
		}

		return array(
			'%ref%' => urlencode($ref->get_string()),
			'%book%' => urlencode($book_name),
			'%chapter%' => $ch1,
			'%verse%' => $vs1,
		);
	}
}

function bfox_translation_settings() {

	if (isset($_POST['add-translation'])) {
		check_admin_referer('bfox_translation_settings_add');

		$trans = new stdClass;
		$trans->short_name = $_POST['short-name'];
		$trans->long_name = $_POST['long-name'];
		$trans->url = $_POST['url'];

		$error = '';
		if (empty($trans->long_name)) $error = __('You must enter a Translation name', 'bfox');
		else if (empty($trans->short_name)) $error = __('You must enter a Translation abbreviation', 'bfox');
		else if (empty($trans->url)) $error = __('You must enter the URL for the Translation', 'bfox');
		else if (false === stripos($trans->url, '%ref%') && false === stripos($trans->url, '%book%')) $error = __('The URL must contain either the %ref% or %book% token', 'bfox');

		if (empty($error)) {
			BfoxTranslations::add_translation($trans);
			$message = sprintf(__('Added translation %s.', 'bfox'), $trans->long_name);
			unset($trans);
		}
	}
	else if (isset($_POST['remove-translations'])) {
		check_admin_referer('bfox_translation_settings_remove');

		$count = 0;
		foreach ((array) $_POST['trans-selected'] as $trans_id) {
			BfoxTranslations::delete_translation($trans_id);
			$count++;
		}

		if ($count) $message = sprintf(__('Deleted %d translation(s).', 'bfox'), $count);
	}

	$domains = BfoxTranslations::group_by_domain(BfoxTranslations::translations());

	?>
	<div id="bible-translations">
	<h3><?php _e('Bible Translations', 'bfox')?></h3>
	<?php if ($error): ?>
		<div id="message" class="error fade"><p><strong><?php echo $error ?></strong></p></div>
	<?php elseif ($message): ?>
		<div id="message" class="updated fade"><p><strong><?php echo $message ?></strong></p></div>
	<?php endif ?>
	<p><?php _e('Biblefox uses iframes to display Bible content, allowing you to read your favorite online Bible translations quickly from your own blog.', 'bfox')?></p>
	<form action="" method="post" class="standard-form" id="settings-form">
		<ul>
		<?php foreach ($domains as $domain => $translations): ?>
			<li>
				<h4><?php _e('From', 'bfox') ?> <a href="http://<?php echo $domain ?>">http://<?php echo $domain ?></a></h4>
				<ul>
			<?php foreach ($translations as $trans_id => $translation): ?>
					<li>
						<input type="checkbox" name="trans-selected[]" value="<?php echo $trans_id ?>" />
						<span class="trans-title"><?php echo $translation->long_name ?> (<?php echo $translation->short_name ?>)</span>
						<div class="trans-url">URL: <?php echo $translation->url ?></div>
					</li>
			<?php endforeach ?>
				</ul>
			</li>
		<?php endforeach ?>
		</ul>
		<?php wp_nonce_field('bfox_translation_settings_remove') ?>
		<p class="submit">
		<input type="submit" name="remove-translations" class="button-primary" value="<?php esc_attr_e('Remove Selected', 'bfox') ?>" />
		</p>
	</form>
	<h3><?php _e('Add a new Online Bible translation', 'bfox') ?></h3>
	<form action="" method="post" class="standard-form" id="settings-form">
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="long-name"><?php _e('Translation Name', 'bfox') ?></label></th>
				<td>
					<input type="text" name="long-name" id="long-name" value="<?php echo $trans->long_name ?>" size="40" /><br/>
					<span class="description"><?php _e('ie. King James Version', 'bfox')?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="short-name"><?php _e('Translation Abbreviation', 'bfox') ?></label></th>
				<td>
					<input type="text" name="short-name" id="short-name" value="<?php echo $trans->short_name ?>" maxlength="5" size="5" /><br/>
					<span class="description"><?php _e('ie. KJV', 'bfox')?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="url"><?php _e('URL', 'bfox') ?></label></th>
				<td>
					<input type="text" name="url" id="url" value="<?php echo $trans->url ?>" size="40" /><br/>
					<div class="description">
						<p><?php _e('This is the URL where the Bible text can be loaded by the iframe. To get it, go to your favorite online Bible website and load a Bible passage with the correct Bible translation. Copy the URL and paste it in here.', 'bfox') ?></p>
						<p><?php _e('You will need to modify the URL to be able to use it for any Bible passage. Just replace the Bible reference in the URL with "%ref%". If the URL doesn\'t support whole Bible references, you can use the "%book%", "%chapter%", and "%verse%" tokens.', 'bfox') ?></p>
						<ul>
							<li><?php _e('%ref% - Adds the current Bible reference (ie. Gen+1 for Genesis 1) to the URL', 'bfox') ?></li>
							<li><?php _e('%book% - Adds the book name of the current Bible reference (ie. Gen for Genesis 1) to the URL', 'bfox') ?></li>
							<li><?php _e('%chapter% - Adds the chapter number of the current Bible reference (ie. 1 for Genesis 1) to the URL', 'bfox') ?></li>
							<li><?php _e('%verse% - Adds the verse number of the current Bible reference (ie. 2 for Genesis 1:2) to the URL', 'bfox') ?></li>
						</ul>
						<p><?php _e('Example: When viewing Genesis 1, "http://biblefox.com/?bfoxp=%ref%&trans=ASV&opts=1" will become "http://biblefox.com/?bfoxp=Gen+1&trans=ASV&opts=1".', 'bfox') ?></p>
					</div>
				</td>
			</tr>
		</table>
		<?php wp_nonce_field('bfox_translation_settings_add') ?>
		<p class="submit">
		<input type="submit" name="add-translation" class="button-primary" value="<?php esc_attr_e('Add Translation', 'bfox') ?>" />
		</p>
	</form>
	</div>
	<?php

}
if (is_multisite()) add_action('bfox_ms_admin_page', 'bfox_translation_settings', 30);
else add_action('bfox_blog_admin_page', 'bfox_translation_settings', 30);

?>