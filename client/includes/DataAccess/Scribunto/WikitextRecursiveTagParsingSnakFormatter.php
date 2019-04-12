<?php

namespace Wikibase\Client\DataAccess\Scribunto;

use Parser;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Lib\SnakFormatter;
use Wikimedia\Assert\Assert;

/**
 * SnakFormatter decorator that recursiveTagParses any Wikitext it produces.
 * This is needed in order to pass certain Wikitext constructs (extension tags,
 * like "<maplink>") to Scribunto.
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 */
class WikitextRecursiveTagParsingSnakFormatter implements SnakFormatter {

	/**
	 * @var SnakFormatter
	 */
	private $snakFormatter;

	/**
	 * @var Parser
	 */
	private $parser;

	public function __construct(
		SnakFormatter $snakFormatter,
		Parser $parser
	) {
		Assert::parameter(
			$snakFormatter->getFormat() === SnakFormatter::FORMAT_WIKI,
			'$snakFormatter',
			'$snakFormatter must produce Wikitext (SnakFormatter::FORMAT_WIKI)'
		);

		$this->snakFormatter = $snakFormatter;
		$this->parser = $parser;
	}

	/**
	 * @see SnakFormatter::formatSnak
	 *
	 * @param Snak $snak
	 *
	 * @return string preprocessed Wikitext
	 */
	public function formatSnak( Snak $snak ) {
		$wikitext = $this->snakFormatter->formatSnak( $snak );

		return $this->parser->recursiveTagParse( $wikitext );
	}

	/**
	 * Returns the format ID of the format this formatter generates.
	 * This uses the FORMAT_XXX constants defined in OutputFormatSnakFormatterFactory.
	 *
	 * @return string One of the SnakFormatter::FORMAT_... constants.
	 */
	public function getFormat() {
		return $this->snakFormatter->getFormat();
	}

}
