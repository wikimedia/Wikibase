<?php

namespace Wikibase\Repo\ParserOutput;

use DataValues\StringValue;
use ParserOutput;
use Wikibase\DataModel\Services\Entity\PropertyDataTypeMatcher;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * Add url data values as external links in ParserOutput.
 *
 * @license GPL-2.0-or-later
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Thiemo Kreuz
 */
class ExternalLinksDataUpdater implements StatementDataUpdater {

	/**
	 * @var PropertyDataTypeMatcher
	 */
	private $propertyDataTypeMatcher;

	/**
	 * @var null[] Hash set of the URL strings found while processing statements. Only the array
	 * keys are used for performance reasons, the values are meaningless.
	 */
	private $urls = [];

	public function __construct( PropertyDataTypeMatcher $propertyDataTypeMatcher ) {
		$this->propertyDataTypeMatcher = $propertyDataTypeMatcher;
	}

	/**
	 * Add DataValue to list of used urls, if Snak property has 'url' data type.
	 */
	public function updateParserOutput( ParserOutput $parserOutput, Statement $statement ) {
		// TODO: $this->urls is no longer needed, code can be simplified
		foreach ( $statement->getAllSnaks() as $snak ) {
			$this->processSnak( $snak );
		}

		foreach ( $this->urls as $url => $null ) {
			$parserOutput->addExternalLink( $url );
		}
	}

	private function processSnak( Snak $snak ) {
		if ( $snak instanceof PropertyValueSnak ) {
			$id = $snak->getPropertyId();
			$value = $snak->getDataValue();

			if ( $value instanceof StringValue
				&& $this->propertyDataTypeMatcher->isMatchingDataType( $id, 'url' )
			) {
				$url = $value->getValue();

				if ( $url !== '' ) {
					$this->urls[$url] = null;
				}
			}
		}
	}



}
