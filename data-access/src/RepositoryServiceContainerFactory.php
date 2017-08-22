<?php

namespace Wikibase\DataAccess;

use Wikibase\DataModel\Services\EntityId\PrefixMappingEntityIdParserFactory;
use Wikibase\DataModel\Services\Lookup\UnknownForeignRepositoryException;
use Wikibase\Lib\EntityIdComposer;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Lib\Serialization\RepositorySpecificDataValueDeserializerFactory;

/**
 * A factory providing RepositoryServiceContainer objects configured for given repository.
 * RepositoryServiceContainers are initialized using wiring files provided in the constructor.
 *
 * @license GPL-2.0+
 */
class RepositoryServiceContainerFactory {

	/**
	 * @var PrefixMappingEntityIdParserFactory
	 */
	private $idParserFactory;

	/**
	 * @var EntityIdComposer
	 */
	private $idComposer;

	/**
	 * @var RepositorySpecificDataValueDeserializerFactory
	 */
	private $dataValueDeserializerFactory;

	/**
	 * Associative array mapping repository names to database names (string or false)
	 *
	 * @var array
	 */
	private $databaseNames;

	/**
	 * @var string[]
	 */
	private $wiringFiles;

	/**
	 * @var GenericServices
	 */
	private $genericServices;

	/**
	 * @var DataAccessSettings
	 */
	private $settings;


	private $entityTypeDefinitions;

	/**
	 * @param PrefixMappingEntityIdParserFactory $idParserFactory
	 * @param EntityIdComposer $idComposer
	 * @param RepositorySpecificDataValueDeserializerFactory $dataValueDeserializerFactory
	 * @param array $repositoryDatabaseNames
	 * @param string[] $wiringFiles
	 * @param GenericServices $genericServices
	 * @param DataAccessSettings $settings
	 * @param EntityTypeDefinitions $entityTypeDefinitions
	 */
	public function __construct(
		PrefixMappingEntityIdParserFactory $idParserFactory,
		EntityIdComposer $idComposer, //  TODO: change ID Composer and pass a factory of prefixing composer (T165589)
		RepositorySpecificDataValueDeserializerFactory $dataValueDeserializerFactory,
		array $repositoryDatabaseNames,
		array $wiringFiles,
		GenericServices $genericServices,
		DataAccessSettings $settings,
		EntityTypeDefinitions $entityTypeDefinitions
	) {
		$this->idParserFactory = $idParserFactory;
		$this->idComposer = $idComposer;
		$this->dataValueDeserializerFactory = $dataValueDeserializerFactory;
		$this->databaseNames = $repositoryDatabaseNames;
		$this->wiringFiles = $wiringFiles;
		$this->genericServices = $genericServices;
		$this->settings = $settings;
		$this->entityTypeDefinitions = $entityTypeDefinitions;
	}

	/**
	 * @param string $repositoryName
	 *
	 * @return RepositoryServiceContainer
	 *
	 * @throws UnknownForeignRepositoryException
	 */
	public function newContainer( $repositoryName ) {
		if ( !array_key_exists( $repositoryName, $this->databaseNames ) ) {
			throw new UnknownForeignRepositoryException( $repositoryName );
		}

		$container = new RepositoryServiceContainer(
			$this->databaseNames[$repositoryName],
			$repositoryName,
			$this->idParserFactory->getIdParser( $repositoryName ),
			$this->idComposer,
			$this->dataValueDeserializerFactory->getDeserializer( $repositoryName ),
			$this->genericServices,
			$this->settings,
			$this->entityTypeDefinitions->getDeserializerFactoryCallbacks()
		);
		$container->loadWiringFiles( $this->wiringFiles );

		return $container;
	}

}
