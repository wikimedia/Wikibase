<?php

namespace Wikibase\Dumpers;

use InvalidArgumentException;
use MWContentSerializationException;
use MWException;
use SiteList;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Serializers\Serializer;
use Wikibase\Lib\Store\EntityLookup;
use Wikibase\Lib\Store\RedirectResolvingEntityLookup;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\Store\UnresolvedRedirectException;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\RdfSerializer;
use Wikibase\RdfProducer;
use Wikibase\DataModel\Entity\PropertyDataTypeLookup;

/**
 * RdfDumpGenerator generates an RDF dump of a given set of entities, excluding
 * redirects.
 *
 * @since 0.5
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 * @author Stas Malyshev
 */
class RdfDumpGenerator extends DumpGenerator {

	/**
	 *
	 * @var RdfSerializer
	 */
	private $entitySerializer;

	/**
	 *
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * List of the prefixes we've seen in the dump
	 * @var array
	 */
	private $prefixes;

	/**
	 * Fixed timestamp for tests
	 * @var int
	 */
	private $timestamp;

	/**
	 *
	 * @param resource $out
	 * @param EntityLookup $lookup Must not resolve redirects
	 * @param Serializer $entitySerializer
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $out, EntityRevisionLookup $lookup, RdfSerializer $entitySerializer ) {
		parent::__construct( $out );
		if ( $lookup instanceof RedirectResolvingEntityLookup ) {
			throw new InvalidArgumentException( '$lookup must not resolve redirects!' );
		}

		$this->entitySerializer = $entitySerializer;
		$this->entityRevisionLookup = $lookup;
	}

	/**
	 * Cleanup prefixes in the dump to avoid repetitions
	 *
	 * @param string $data
	 * @return string
	 */
	protected function cleanupPrefixes( $data ) {
		$thisVar = $this; /* hack because php 5.3 closures don't support $this */
		return preg_replace_callback( '/@prefix .+?\n/', function ( $matches ) use ($thisVar) {
			if ( !empty( $thisVar->prefixes[$matches[0]] ) ) {
				return '';
			}
			$thisVar->prefixes[$matches[0]] = true;
			return $matches[0];
		}, $data );
	}

	/**
	 * Do something before dumping data
	 */
	protected function preDump() {
		$header = $this->entitySerializer->dumpHeader( $this->timestamp );

		$this->writeToDump( $this->cleanupPrefixes( $header ) );
	}

	/**
	 * Produces RDF dump of the entity
	 * @param EntityId $entityId
	 *
	 * @throws StorageException
	 *
	 * @return string|null
	 */
	protected function generateDumpForEntityId( EntityId $entityId ) {
		try {
			$entityRevision = $this->entityRevisionLookup->getEntityRevision( $entityId );

			if ( !$entityRevision ) {
				throw new StorageException( 'Entity not found: ' . $entityId->getSerialization() );
			}
		} catch ( MWContentSerializationException $ex ) {
			throw new StorageException( 'Deserialization error for ' . $entityId->getSerialization() );
		} catch ( UnresolvedRedirectException $e ) {
			return null;
		}

		$data = $this->entitySerializer->serializeEntityRevision( $entityRevision );
		return $this->cleanupPrefixes( $data );
	}

	public function setTimestamp( $ts ) {
		$this->timestamp = (int)$ts;
	}

	/**
	 * Create dump generator
	 * @param string $format
	 * @param string $output
	 * @param string $baseUri
	 * @param string $dataUri
	 * @param SiteList $sites
	 * @param EntityLookup $entityLookup
	 * @param EntityRevisionLookup $entityRevisionLookup
	 * @param PropertyInfoDataTypeLookup $propertyLookup
	 * @param int $flavor
	 * @throws \MWException
	 * @return RdfDumpGenerator
	 */
	public static function createDumpGenerator(
			$format,
			$output,
			$baseUri,
			$dataUri,
			SiteList $sites,
			EntityLookup $entityLookup,
			EntityRevisionLookup $entityRevisionLookup,
			PropertyDataTypeLookup $propertyLookup,
			$flavor
	) {
		$rdfFormat = RdfSerializer::getFormat( $format );
		if( !$rdfFormat ) {
			throw new \MWException( "Unknown format: $format" );
		}
		$entitySerializer = new RdfSerializer( $rdfFormat,
				$baseUri,
				$dataUri,
				$sites,
				$propertyLookup,
				$entityLookup,
				$flavor,
				new \HashBagOStuff()
		);
		return new RdfDumpGenerator( $output, $entityRevisionLookup, $entitySerializer );
	}
}
