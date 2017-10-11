<?php

namespace Wikibase\Rdf\Values;

use DataValues\StringValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\PropertyInfoProvider;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Rdf\ValueSnakRdfBuilder;
use Wikimedia\Purtle\RdfWriter;

/**
 * RDF mapping for StringValues that are interpreted as external identifiers.
 * URIs for the external identifier are generated based on a URI pattern associated with
 * the respective property.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Daniel Kinzler
 * @author Stas Malyshev
 */
class ExternalIdentifierRdfBuilder implements ValueSnakRdfBuilder {

	/**
	 * @var PropertyInfoProvider
	 */
	private $uriPatternProvider;

	/**
	 * @param PropertyInfoProvider $uriPatternProvider
	 */
	public function __construct(
		PropertyInfoProvider $uriPatternProvider
	) {
		$this->uriPatternProvider = $uriPatternProvider;
	}

	/**
	 * @param RdfWriter $writer
	 * @param string $propertyValueNamespace Property value relation namespace
	 * @param string $propertyValueLName Property value relation name
	 * @param string $dataType Property data type
	 * @param PropertyValueSnak $snak
	 */
	public function addValue(
		RdfWriter $writer,
		$propertyValueNamespace,
		$propertyValueLName,
		$dataType,
		PropertyValueSnak $snak
	) {
		$id = $this->getValueId( $snak->getDataValue() );
		$uriPattern = $this->uriPatternProvider->getPropertyInfo( $snak->getPropertyId() );

		$writer->say( $propertyValueNamespace, $propertyValueLName )->value( $id );

		$normalizedValueNamespace = RdfVocabulary::$normalizedPropertyValueNamespace[$propertyValueNamespace];
		if ( $uriPattern !== null && $normalizedValueNamespace !== null ) {
			$uri = str_replace( '$1', wfUrlencode( $id ), $uriPattern );
			$writer->say( $normalizedValueNamespace, $propertyValueLName )->is( $uri );
		}
	}

	/**
	 * @param StringValue $value
	 *
	 * @return string the external ID
	 */
	private function getValueId( StringValue $value ) {
		return trim( strval( $value->getValue() ) );
	}

}
