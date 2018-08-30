<?php

namespace Wikibase\Repo;

use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\Lib\DefaultEntityIdHtmlLinkFormatter;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\View\EntityIdFormatterFactory;

/**
 * A factory for generating EntityIdHtmlLinkFormatters.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class EntityIdHtmlLinkFormatterFactory implements EntityIdFormatterFactory {

	/**
	 * @var EntityTitleLookup
	 */
	private $titleLookup;

	/**
	 * @var LanguageNameLookup
	 */
	private $languageNameLookup;

	public function __construct(
		EntityTitleLookup $titleLookup,
		LanguageNameLookup $languageNameLookup
	) {
		$this->titleLookup = $titleLookup;
		$this->languageNameLookup = $languageNameLookup;
	}

	/**
	 * @see EntityIdFormatterFactory::getOutputFormat
	 *
	 * @return string SnakFormatter::FORMAT_HTML
	 */
	public function getOutputFormat() {
		return SnakFormatter::FORMAT_HTML;
	}

	/**
	 * @see EntityIdFormatterFactory::getEntityIdFormatter
	 *
	 * @param LabelDescriptionLookup $labelDescriptionLookup
	 *
	 * @return DefaultEntityIdHtmlLinkFormatter
	 */
	public function getEntityIdFormatter( LabelDescriptionLookup $labelDescriptionLookup ) {
		return new DefaultEntityIdHtmlLinkFormatter(
			$labelDescriptionLookup,
			$this->titleLookup,
			$this->languageNameLookup
		);
	}

}
