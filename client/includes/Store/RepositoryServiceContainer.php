<?php

namespace Wikibase\Client\Store;

use DataValues\Deserializers\DataValueDeserializer;
use Deserializers\Deserializer;
use Deserializers\DispatchingDeserializer;
use MediaWiki\Services\ServiceContainer;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\InternalSerialization\DeserializerFactory as InternalDeserializerFactory;

/**
 * @license GPL-2.0+
 */
class RepositoryServiceContainer extends ServiceContainer {

	/**
	 * @var string|false
	 */
	private $databaseName;

	/**
	 * @var string
	 */
	private $repositoryName;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var DataValueDeserializer
	 */
	private $dataValueDeserializer;

	/**
	 * @var callable[]
	 */
	private $deserializerFactoryCallbacks;

	/**
	 * @param string|false $databaseName
	 * @param string $repositoryName
	 * @param EntityIdParser $entityIdParser
	 * @param WikibaseClient $client Top-level factory passed to service instantiators
	 */
	public function __construct(
		$databaseName,
		$repositoryName,
		EntityIdParser $entityIdParser,
		DataValueDeserializer $dataValueDeserializer,
		WikibaseClient $client
	) {
		parent::__construct( [ $client ] );

		$this->databaseName = $databaseName;
		$this->repositoryName = $repositoryName;
		$this->entityIdParser = $entityIdParser;
		$this->dataValueDeserializer = $dataValueDeserializer;
		$this->deserializerFactoryCallbacks = $client->getEntityDeserializerFactoryCallbacks();
	}

	/**
	 * @return string
	 */
	public function getRepositoryName() {
		return $this->repositoryName;
	}

	/**
	 * @return string|false
	 */
	public function getDatabaseName() {
		return $this->databaseName;
	}

	/**
	 * @return EntityIdParser
	 */
	public function getEntityIdParser() {
		return $this->entityIdParser;
	}

	/**
	 * @return DataValueDeserializer
	 */
	public function getDataValueDeserializer() {
		return $this->dataValueDeserializer;
	}

	/**
	 * @return Deserializer
	 */
	public function getEntityDeserializer() {
		$deserializerFactory = new DeserializerFactory(
			$this->dataValueDeserializer,
			$this->entityIdParser
		);

		$deserializers = [];
		foreach ( $this->deserializerFactoryCallbacks as $callback ) {
			$deserializers[] = call_user_func( $callback, $deserializerFactory );
		}

		$internalDeserializerFactory = new InternalDeserializerFactory(
			$this->dataValueDeserializer,
			$this->entityIdParser,
			new DispatchingDeserializer( $deserializers )
		);

		return $internalDeserializerFactory->newEntityDeserializer();
	}

}
