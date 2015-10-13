<?php

namespace Wikibase\Repo\DataUpdates;

use DataValues\StringValue;
use ParserOutput;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Store\PropertyDataTypeMatcher;

/**
 * Add url data values as external links in ParserOutput.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Thiemo Mättig
 */
class ExternalLinksDataUpdate implements StatementDataUpdate {

	/**
	 * @var PropertyDataTypeMatcher
	 */
	private $propertyDataTypeMatcher;

	/**
	 * @var null[] Hash set of the URL strings found while processing statements. Only the array
	 * keys are used for performance reasons, the values are meaningless.
	 */
	private $urls = array();

	/**
	 * @param PropertyDataTypeMatcher $propertyDataTypeMatcher
	 */
	public function __construct( PropertyDataTypeMatcher $propertyDataTypeMatcher ) {
		$this->propertyDataTypeMatcher = $propertyDataTypeMatcher;
	}

	/**
	 * @param StatementList $statements
	 *
	 * @return string[]
	 */
	public function getExternalLinks( StatementList $statements ) {
		$this->urls = array();

		foreach ( $statements as $statement ) {
			$this->processStatement( $statement );
		}

		return array_keys( $this->urls );
	}

	/**
	 * Add DataValue to list of used urls, if Snak property has 'url' data type.
	 *
	 * @param Statement $statement
	 */
	public function processStatement( Statement $statement ) {
		foreach ( $statement->getAllSnaks() as $snak ) {
			$this->processSnak( $snak );
		}
	}

	/**
	 * @param Snak $snak
	 */
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

	/**
	 * @param ParserOutput $parserOutput
	 */
	public function updateParserOutput( ParserOutput $parserOutput ) {
		foreach ( $this->urls as $url => $null ) {
			$parserOutput->addExternalLink( $url );
		}
	}

}
