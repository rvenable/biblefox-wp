<?php

require_once BFOX_BIBLE_DIR . '/page.php';

abstract class BfoxRefPage extends BfoxPage
{

	/**
	 * The bible references being used
	 *
	 * @var BibleRefs
	 */
	protected $refs;

	public function __construct(BibleRefs $refs, Translation $translation)
	{
		parent::__construct($translation);
		$this->refs = $refs;
	}
}

?>