<?php
/**
 * The template for displaying Bible Tool iFrames.
 *
 * iFrames are used to display Bible Tool content, while allowing the user to select which tool they want to use.
 *
 */

require_once BFOX_REF_DIR . '/bfox_bible_tool_api.php';

class BfoxBible {
	/**
	 * @var BfoxBibleToolApi
	 */
	var $api;
	var $shortName;
	var $longName;

	function __construct(BfoxBibleToolApi $api, $shortName = '', $longName = '') {
		$this->api = $api;
		if (empty($shortName)) $shortName = $api->bible;
		if (empty($longName)) $longName = $shortName;
		$this->shortName = $shortName;
		$this->longName = $longName;
	}

	function echoContentForRef(BfoxRef $ref) {
		$this->api->echoContentForRef($ref);
	}
}

class BfoxLocalWPBibleToolApi extends BfoxLocalBibleToolApi {
	function rowsForRef(BfoxRef $ref) {
		global $wpdb;

		$tableName = $wpdb->escape($this->tableName);

		$indexCol = $wpdb->escape($this->indexCol);
		$indexCol2 = $wpdb->escape($this->indexCol2);

		if (empty($indexCol2)) $refWhere = $ref->sql_where($indexCol);
		else $refWhere = $ref->sql_where2($indexCol, $indexCol2);

		$sql = $wpdb->prepare("SELECT * FROM $tableName WHERE $refWhere");
		$rows = $wpdb->get_results($sql);

		return $rows;
	}
}

class BfoxWPBibleToolIframeApi extends BfoxBibleToolIframeApi {
	function echoContentForUrl($url) {
?>
	<div class="bfox-tool-iframe">
		<?php parent::echoContentForUrl($url); ?>
	</div>
<?php
	}
}

function load_bfox_bibles() {
	global $_bfox_bibles;

	load_bfox_template('load-bfox_bible');

	$_bfox_bibles = apply_filters('load_bfox_bibles', array());
}

function bfox_bibles() {
	global $_bfox_bibles;

	if (is_null($_bfox_bibles)) load_bfox_bibles();

	return $_bfox_bibles;
}

/**
 * Returns the active Bible
 *
 * @return BfoxBible
 */
function bfox_bible($shortName = '') {
	if (empty($shortName)) $shortName = $_REQUEST['tool'];

	$bibles = bfox_bibles();

	if (!empty($shortName)) {
		foreach ($bibles as $bible) {
			if ($bible->shortName == $shortName) {
				return $bible;
			}
		}
	}

	if (isset($bibles[0])) return $bibles[0];
	return NULL;
}

?>