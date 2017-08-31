<?php

namespace Wikibase\DataAccess;

use Serializers\Serializer;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\DataModel\Services\Term\TermBuffer;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Interactors\TermSearchInteractorFactory;
use Wikibase\Lib\Store\EntityInfoBuilderFactory;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStoreWatcher;
use Wikibase\Lib\Store\PropertyInfoLookup;
use Wikibase\StringNormalizer;

/**
 * Interface of the top-level container/factory of data access services.
 *
 * @license GPL-2.0+
 */
interface WikibaseServices {

	/**
	 * @return EntityInfoBuilderFactory
	 */
	public function getEntityInfoBuilderFactory();

	/**
	 * @return EntityNamespaceLookup
	 */
	public function getEntityNamespaceLookup();

	/**
	 * @return EntityPrefetcher
	 */
	public function getEntityPrefetcher();

	/**
	 * Note: Instance returned is not guaranteed to be a caching decorator.
	 * Callers should take care of caching themselves.
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup();

	/**
	 * Returns the entity serializer instance that includes snak hashes in the serialization.
	 *
	 * @return Serializer
	 */
	public function getEntitySerializer();

	/**
	 * Returns the entity serializer instance that omits snak hashes in the serialization.
	 *
	 * @return Serializer
	 */
	public function getCompactEntitySerializer();

	/**
	 * Returns a service that can be registered as a watcher to changes to entity data.
	 * Such watcher gets notified when entity is updated or deleted, or when the entity
	 * redirect is updated.
	 *
	 * @return EntityStoreWatcher
	 */
	public function getEntityStoreWatcher();

	/**
	 * @return LanguageFallbackChainFactory
	 */
	public function getLanguageFallbackChainFactory();

	/**
	 * Note: Instance returned is not guaranteed to be a caching decorator.
	 * Callers should take care of caching themselves.
	 *
	 * @return PropertyInfoLookup
	 */
	public function getPropertyInfoLookup();

	/**
	 * @return StringNormalizer
	 */
	public function getStringNormalizer();

	/**
	 * @return TermBuffer
	 */
	public function getTermBuffer();

	/**
	 * @return TermSearchInteractorFactory
	 */
	public function getTermSearchInteractorFactory();

}
