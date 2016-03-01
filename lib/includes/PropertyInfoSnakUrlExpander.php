<?php

namespace Wikibase\Lib;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikimedia\Assert\Assert;

/**
 * SnakUrlExpander that uses an PropertyInfoProvider to find
 * a URL pattern for expanding a Snak's value into an URL.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class PropertyInfoSnakUrlExpander implements SnakUrlExpander {

	/**
	 * @var PropertyInfoProvider
	 */
	private $infoProvider;

	/**
	 * @param PropertyInfoProvider $infoProvider
	 */
	public function __construct( PropertyInfoProvider $infoProvider ) {
		$this->infoProvider = $infoProvider;
	}

	/**
	 * @see SnakUrlExpander::expandUrl
	 *
	 * @param PropertyValueSnak $snak
	 *
	 * @return string|null A URL or URI derived from the Snak, or null if no such URL
	 *         could be determined.
	 */
	public function expandUrl( PropertyValueSnak $snak ) {
		$propertyId = $snak->getPropertyId();
		$value = $snak->getDataValue();

		Assert::parameterType( 'DataValues\StringValue', $value, '$snak->getDataValue()' );

		$pattern = $this->infoProvider->getPropertyInfo( $propertyId );

		if ( $pattern === null ) {
			return null;
		}

		$id = wfUrlencode( $value->getValue() );
		$url = str_replace( '$1', $id, $pattern );
		return $url;
	}

}
