<?php

namespace Wikibase\Client\DataAccess\Scribunto;

use Language;
use Wikibase\Client\Serializer\ClientEntitySerializer;
use Wikibase\Client\Usage\UsageAccumulator;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\Store\UnresolvedRedirectException;

/**
 * Functionality needed to expose Entities to Lua.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Jens Ohlig < jens.ohlig@wikimedia.de >
 * @author Adam Shorland
 */
class EntityAccessor {

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var UsageAccumulator
	 */
	private $usageAccumulator;

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $dataTypeLookup;

	/**
	 * @var LanguageFallbackChain
	 */
	private $fallbackChain;

	/**
	 * @var Language
	 */
	private $language;

	/**
	 * @var ContentLanguages
	 */
	private $termsLanguages;

	/**
	 * @param EntityIdParser $entityIdParser
	 * @param EntityLookup $entityLookup
	 * @param UsageAccumulator $usageAccumulator
	 * @param PropertyDataTypeLookup $dataTypeLookup
	 * @param LanguageFallbackChain $fallbackChain
	 * @param Language $language
	 * @param ContentLanguages $termsLanguages
	 */
	public function __construct(
		EntityIdParser $entityIdParser,
		EntityLookup $entityLookup,
		UsageAccumulator $usageAccumulator,
		PropertyDataTypeLookup $dataTypeLookup,
		LanguageFallbackChain $fallbackChain,
		Language $language,
		ContentLanguages $termsLanguages
	) {
		$this->entityIdParser = $entityIdParser;
		$this->entityLookup = $entityLookup;
		$this->usageAccumulator = $usageAccumulator;
		$this->dataTypeLookup = $dataTypeLookup;
		$this->fallbackChain = $fallbackChain;
		$this->language = $language;
		$this->termsLanguages = $termsLanguages;
	}

	/**
	 * Recursively renumber a serialized array in place, so it is indexed at 1, not 0.
	 * Just like Lua wants it.
	 *
	 * @param array &$entityArr
	 */
	private function renumber( array &$entityArr ) {
		foreach ( $entityArr as &$value ) {
			if ( !is_array( $value ) ) {
				continue;
			}
			if ( array_key_exists( 0, $value ) ) {
				$value = array_combine( range( 1, count( $value ) ), array_values( $value ) );
			}
			$this->renumber( $value );
		}
	}

	/**
	 * Get entity from prefixed ID (e.g. "Q23") and return it as serialized array.
	 *
	 * @since 0.5
	 *
	 * @param string $prefixedEntityId
	 *
	 * @return array|null
	 */
	public function getEntity( $prefixedEntityId ) {
		$prefixedEntityId = trim( $prefixedEntityId );

		$entityId = $this->entityIdParser->parse( $prefixedEntityId );

		try {
			$entityObject = $this->entityLookup->getEntity( $entityId );
		} catch ( UnresolvedRedirectException $ex ) {
			// We probably hit a double redirect
			wfLogWarning(
				'Encountered a UnresolvedRedirectException when trying to load ' . $prefixedEntityId
			);

			return null;
		}

		if ( $entityObject === null ) {
			return null;
		}

		$entityArr = $this->newClientEntitySerializer()->serialize( $entityObject );

		// Renumber the entity as Lua uses 1-based array indexing
		$this->renumber( $entityArr );
		$entityArr['schemaVersion'] = 2;

		$this->usageAccumulator->addAllUsage( $entityId );
		return $entityArr;
	}

	private function newClientEntitySerializer() {
		return new ClientEntitySerializer(
			$this->dataTypeLookup,
			array_unique( array_merge(
				$this->termsLanguages->getLanguages(),
				$this->fallbackChain->getFetchLanguageCodes(),
				array( $this->language->getCode() )
			) ),
			array( $this->language->getCode() => $this->fallbackChain )
		);
	}

}
