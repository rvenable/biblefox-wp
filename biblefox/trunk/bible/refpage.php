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

	/**
	 * The bible translation being used
	 *
	 * @var Translation
	 */
	protected $translation;

	public function __construct(BibleRefs $refs, Translation $translation)
	{
		$this->refs = $refs;
		$this->translation = $translation;
	}
}

?>