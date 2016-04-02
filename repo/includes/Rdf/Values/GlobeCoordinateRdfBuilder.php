<?php

namespace Wikibase\Rdf\Values;

use DataValues\Geo\Values\GlobeCoordinateValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Rdf\ValueSnakRdfBuilder;
use Wikibase\Rdf\RdfVocabulary;
use Wikimedia\Purtle\RdfWriter;

/**
 * RDF mapping for GlobeCoordinateValue.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 * @author Stas Malyshev
 */
class GlobeCoordinateRdfBuilder implements ValueSnakRdfBuilder {

	/**
	 * @var ComplexValueRdfHelper|null
	 */
	private $complexValueHelper;

	/**
	 * @param ComplexValueRdfHelper|null $complexValueHelper
	 */
	public function __construct( ComplexValueRdfHelper $complexValueHelper = null ) {
		$this->complexValueHelper = $complexValueHelper;
	}

	/**
	 * Adds specific value
	 *
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
		/** @var GlobeCoordinateValue $value */
		$value = $snak->getDataValue();
		$point = "Point({$value->getLongitude()} {$value->getLatitude()})";
		$globe = $value->getGlobe();

		if ( $globe && $globe !== GlobeCoordinateValue::GLOBE_EARTH ) {
			$globe = str_replace( '>', '%3E', $globe );
			// Add coordinate system according to http://www.opengeospatial.org/standards/geosparql
			// Per https://portal.opengeospatial.org/files/?artifact_id=47664 sec 8.5.1
			//    All RDFS Literals of type geo:wktLiteral shall consist of an optional URI
			//    identifying the coordinate reference system followed by Simple Features Well Known
			//   Text (WKT) describing a geometric value.
			// Example: "<http://www.opengis.net/def/crs/EPSG/0/4326> Point(33.95 -83.38)"^^<http://www.opengis.net/ont/geosparql#wktLiteral>
			$point = "<$globe> $point";
		}

		$writer->say( $propertyValueNamespace, $propertyValueLName )
			->value( $point, RdfVocabulary::NS_GEO, "wktLiteral" );

		if ( $this->complexValueHelper !== null ) {
			$this->addValueNode( $writer, $propertyValueNamespace, $propertyValueLName, $dataType, $value );
		}
	}

	/**
	 * Adds a value node representing all details of $value
	 *
	 * @param RdfWriter $writer
	 * @param string $propertyValueNamespace Property value relation namespace
	 * @param string $propertyValueLName Property value relation name
	 * @param string $dataType Property data type
	 * @param GlobeCoordinateValue $value
	 */
	private function addValueNode(
		RdfWriter $writer,
		$propertyValueNamespace,
		$propertyValueLName,
		$dataType,
		GlobeCoordinateValue $value
	) {
		$valueLName = $this->complexValueHelper->attachValueNode(
			$writer,
			$propertyValueNamespace,
			$propertyValueLName,
			$dataType,
			$value
		);

		if ( $valueLName === null ) {
			// The value node is already present in the output, don't create it again!
			return;
		}

		$valueWriter = $this->complexValueHelper->getValueNodeWriter();

		$valueWriter->say( RdfVocabulary::NS_ONTOLOGY, 'geoLatitude' )
			->value( $value->getLatitude(), 'xsd', 'decimal' );

		$valueWriter->say( RdfVocabulary::NS_ONTOLOGY, 'geoLongitude' )
			->value( $value->getLongitude(), 'xsd', 'decimal' );

		// Disallow nulls in precision, see T123392
		$precision = $value->getPrecision();
		if ( is_null( $precision ) ) {
			$valueWriter->a( RdfVocabulary::NS_ONTOLOGY, 'GeoAutoPrecision' );
			// 1/3600 comes from GeoCoordinateFormatter.php default value for no prcision
			$precision = 1 / 3600;
		}
		$valueWriter->say( RdfVocabulary::NS_ONTOLOGY, 'geoPrecision' )
			->value( $precision, 'xsd', 'decimal' );

		$valueWriter->say( RdfVocabulary::NS_ONTOLOGY, 'geoGlobe' )
			->is( trim( $value->getGlobe() ) );
	}

}
