<?php

require_once BFOX_BIBLE_DIR . '/quicknote.php';
require_once BFOX_BIBLE_DIR . '/commentaries.php';
require_once BFOX_BIBLE_DIR . '/bible.php';

global $bfox_viewer;
$bfox_viewer = new Bible();
$bfox_viewer->page_load($_GET);

?>