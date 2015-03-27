<?php

namespace Wikibase\Client\Scribunto;

use Language;
use Wikibase\Client\Usage\UsageAccumulator;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\PropertyDataTypeLookup;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\Lib\Serializers\Serializer;
use Wikibase\Lib\Serializers\SerializerFactory;
use Wikibase\Lib\Store\EntityLookup;

/**
 * Functionality needed to expose Entities to Lua.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Jens Ohlig < jens.ohlig@wikimedia.de >
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
	 * @var SerializationOptions|null
	 */
	private $serializationOptions = null;

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
		foreach( $entityArr as &$value ) {
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
	 * @return array
	 */
	public function getEntity( $prefixedEntityId ) {
		$prefixedEntityId = trim( $prefixedEntityId );

		$entityId = $this->entityIdParser->parse( $prefixedEntityId );

		$entityObject = $this->entityLookup->getEntity( $entityId );

		if ( $entityObject === null ) {
			return null;
		}

		$serializer = $this->getEntitySerializer( $entityObject );

		$entityArr = $serializer->getSerialized( $entityObject );

		// Renumber the entity as Lua uses 1-based array indexing
		$this->renumber( $entityArr );
		$entityArr['schemaVersion'] = 2;

		$this->usageAccumulator->addAllUsage( $entityId );
		return $entityArr;
	}

	/**
	 * @param EntityDocument $entityObject
	 *
	 * @return Serializer
	 */
	private function getEntitySerializer( EntityDocument $entityObject ) {
		$options = $this->getSerializationOptions();
		$serializerFactory = new SerializerFactory( $options, $this->dataTypeLookup );

		return $serializerFactory->newSerializerForObject( $entityObject, $options );
	}

	/**
	 * @return SerializationOptions
	 */
	private function getSerializationOptions() {
		if ( $this->serializationOptions === null ) {
			$this->serializationOptions = $this->newSerializationOptions();
		}

		return $this->serializationOptions;
	}

	/**
	 * @return SerializationOptions
	 */
	private function newSerializationOptions() {
		$options = new SerializationOptions();

		// See mw.wikibase.lua. This is the only way to inject values into mw.wikibase.label( ),
		// so any customized Lua modules can access labels of another entity written in another variant,
		// unless we give them the ability to getEntity() any entity by specifying its ID, not just self.
		$languages = $this->termsLanguages->getLanguages() +
			array( $this->language->getCode() => $this->fallbackChain );

		// SerializationOptions accepts mixed types of keys happily.
		$options->setLanguages( $languages );

		$options->setIdKeyMode( SerializationOptions::ID_KEYS_UPPER );

		return $options;
	}

}
