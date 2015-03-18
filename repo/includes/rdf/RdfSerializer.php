<?php

namespace Wikibase;

use EasyRdf_Exception;
use EasyRdf_Format;
use EasyRdf_Graph;
use SiteList;
use Wikibase\Lib\Store\EntityLookup;
use Wikibase\DataModel\Entity\PropertyDataTypeLookup;

/**
 * RDF serialization for wikibase data model.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Anja Jentzsch < anja.jentzsch@wikimedia.de >
 * @author Thomas Pellissier Tanon
 * @author Daniel Kinzler
 */
class RdfSerializer implements RdfProducer {

	/**
	 * @var string
	 */
	private $baseUri;

	/**
	 * @var string
	 */
	private $dataUri;

	/**
	 * @var EasyRdf_Format
	 */
	private $format;

	/**
	 * @var SiteList
	 */
	private $sites;

	/**
	 * @var String
	 */
	private $flavor;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $propertyLookup;

	/**
	 * Hash to store seen references/values for deduplication
	 * @var BagOStuff
	 */
	private $dedupBag;

	/**
	 * @param EasyRdf_Format $format
	 * @param string $baseUri
	 * @param string $dataUri
	 * @param SiteList $sites;
	 * @param EntityLookup $entityLookup
	 * @param PropertyDataTypeLookup $propertyLookup
	 * @param integer flavor
	 */
	public function __construct(
		EasyRdf_Format $format,
		$baseUri,
		$dataUri,
		SiteList $sites,
		PropertyDataTypeLookup $propertyLookup,
		EntityLookup $entityLookup,
		$flavor,
		\BagOStuff $dedupBag = null
	) {
		$this->baseUri = $baseUri;
		$this->dataUri = $dataUri;
		$this->format = $format;
		$this->sites = $sites;
		$this->entityLookup = $entityLookup;
		$this->propertyLookup = $propertyLookup;
		$this->flavor = $flavor;
		$this->dedupBag = $dedupBag;
	}

	/**
	 * Returns an EasyRdf_Format object for the given format name.
	 * The name may be a MIME type or a file extension (or a format URI
	 * or canonical name).
	 *
	 * If no format is found for $name, this method returns null.
	 *
	 * @param string $name the name (file extension, mime type) of the desired format.
	 *
	 * @return EasyRdf_Format|null the format object, or null if not found.
	 */
	public static function getFormat( $name ) {
		try {
			$format = EasyRdf_Format::getFormat( $name );
			return $format;
		} catch ( EasyRdf_Exception $ex ) {
			// noop
		}

		return null;
	}

	public function getNamespaces() {
		return $this->newRdfBuilder()->getNamespaces(); //XXX: nasty hack!
	}

	/**
	 * Creates a new builder
	 *
	 * @return RdfBuilder
	 */
	public function newRdfBuilder() {
		//TODO: language filter

		$builder = new RdfBuilder(
			$this->sites,
			$this->baseUri,
			$this->dataUri,
			$this->propertyLookup,
			$this->flavor,
			$this->dedupBag
		);

		return $builder;
	}

	/**
	 * Generates an RDF graph representing the given entity
	 *
	 * @param EntityRevision $entityRevision the entity to output.
	 *
	 * @return EasyRdf_Graph
	 */
	public function buildGraphForEntityRevision( EntityRevision $entityRevision ) {
		$builder = $this->newRdfBuilder();

		$builder->addEntityRevisionInfo(
			$entityRevision->getEntity()->getId(),
			$entityRevision->getRevisionId(),
			$entityRevision->getTimestamp()
		);

		$builder->addEntity( $entityRevision->getEntity() );

		$builder->resolveMentionedEntities( $this->entityLookup ); //TODO: optional

		$graph = $builder->getGraph();
		return $graph;
	}

	/**
	 * Create dump header for RDF dump
	 * @param int $ts Timestamp (for testing)
	 * @return string
	 */
	public function dumpHeader( $ts = 0) {
		$builder = $this->newRdfBuilder();

		$builder->addDumpHeader( $ts );

		return $this->serializeRdf( $builder->getGraph() );
	}

	/**
	 * Returns the serialized graph
	 *
	 * @param EasyRdf_Graph $graph the graph to serialize
	 *
	 * @return string
	 */
	public function serializeRdf( EasyRdf_Graph $graph ) {
		$serialiser = $this->format->newSerialiser();
		$data = $serialiser->serialise( $graph, $this->format->getName() );
		return $data;
	}

	/**
	 * Returns the serialized entity.
	 * Shorthand for $this->serializeRdf( $this->buildGraphForEntity( $entity ) ).
	 *
	 * @param EntityRevision $entityRevision   the entity to serialize
	 *
	 * @return string
	 */
	public function serializeEntityRevision( EntityRevision $entityRevision ) {
		$graph = $this->buildGraphForEntityRevision( $entityRevision );
		$data = $this->serializeRdf( $graph );
		return $data;
	}

	/**
	 * @return string
	 */
	public function getDefaultMimeType() {
		return $this->format->getDefaultMimeType();
	}

}
