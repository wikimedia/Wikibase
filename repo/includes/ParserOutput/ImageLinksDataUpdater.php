<?php

namespace Wikibase\Repo\ParserOutput;

use DataValues\StringValue;
use ParserOutput;
use Wikibase\DataModel\Services\Entity\PropertyDataTypeMatcher;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * Register commonsMedia values as used images in ParserOutput.
 *
 * @license GPL-2.0-or-later
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Thiemo Kreuz
 */
class ImageLinksDataUpdater implements StatementDataUpdater {

	/**
	 * @var PropertyDataTypeMatcher
	 */
	private $propertyDataTypeMatcher;

	/**
	 * @var null[] Hash set of the file name strings found while processing statements. Only the
	 * array keys are used for performance reasons, the values are meaningless.
	 */
	private $fileNames = [];

	public function __construct( PropertyDataTypeMatcher $propertyDataTypeMatcher ) {
		$this->propertyDataTypeMatcher = $propertyDataTypeMatcher;
	}

	/**
	 * Add DataValue to list of used images if Snak property data type is commonsMedia.
	 * Treat CommonsMedia values as file transclusions
	 */
	public function updateParserOutput( ParserOutput $parserOutput, Statement $statement ) {
		// TODO: $this->fileNames is no longer needed, code can be simplified
		foreach ( $statement->getAllSnaks() as $snak ) {
			$this->processSnak( $snak );
		}

		foreach ( $this->fileNames as $fileName => $null ) {
			$file = wfFindFile( $fileName );

			$parserOutput->addImage(
				$fileName,
				$file ? $file->getSha1() : false,
				$file ? $file->getTimestamp() : false
			);
		}
	}

	private function processSnak( Snak $snak ) {
		if ( $snak instanceof PropertyValueSnak ) {
			$id = $snak->getPropertyId();
			$value = $snak->getDataValue();

			if ( $value instanceof StringValue
				&& $this->propertyDataTypeMatcher->isMatchingDataType( $id, 'commonsMedia' )
			) {
				$fileName = str_replace( ' ', '_', $value->getValue() );

				if ( $fileName !== '' ) {
					$this->fileNames[$fileName] = null;
				}
			}
		}
	}

}
