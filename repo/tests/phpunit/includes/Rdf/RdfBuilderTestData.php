<?php

namespace Wikibase\Repo\Tests\Rdf;

use InvalidArgumentException;
use Site;
use SiteList;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Lib\Tests\MockRepository;
use Wikimedia\Purtle\BNodeLabeler;
use Wikimedia\Purtle\NTriplesRdfWriter;

/**
 * Helper class for accessing data files for RdfBuilder related tests.
 *
 * @group Wikibase
 * @group WikibaseRdf
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 * @author Stas Malyshev
 */
class RdfBuilderTestData {

	const URI_BASE = 'http://acme.test/';
	const URI_DATA = 'http://data.acme.test/';

	/**
	 * @var EntityContentDataCodec|null
	 */
	private $codec = null;

	/**
	 * @var string
	 */
	private $dataDir;

	/**
	 * @var string
	 */
	private $entityDir;

	/**
	 * @param string $entityDir directory containing entity data (JSON files)
	 * @param string $dataDir directory containing RDF data (n-triples files)
	 */
	public function __construct( $entityDir, $dataDir ) {
		$this->entityDir = $entityDir;
		$this->dataDir = $dataDir;
	}

	/**
	 * @return EntityContentDataCodec
	 */
	private function getCodec() {
		if ( $this->codec === null ) {
			$wikibaseRepo = WikibaseRepo::getDefaultInstance();
			$this->codec = $wikibaseRepo->getEntityContentDataCodec();
		}

		return $this->codec;
	}

	/**
	 * Load entity from JSON
	 *
	 * @param string $idString
	 *
	 * @return EntityDocument
	 */
	public function getEntity( $idString ) {
		return $this->getCodec()->decodeEntity(
			file_get_contents( "{$this->entityDir}/$idString.json" ),
			CONTENT_FORMAT_JSON
		);
	}

	/**
	 * @param $dataSetName
	 * @return string
	 */
	private function getDataSetFileName( $dataSetName ) {
		return $filename = "{$this->dataDir}/$dataSetName.nt";
	}

	/**
	 * @param $dataSetName
	 * @return bool
	 */
	public function hasDataSet( $dataSetName ) {
		$filename = $this->getDataSetFileName( $dataSetName );
		return file_exists( $filename );
	}

	/**
	 * Load serialized ntriples.
	 *
	 * @param string|string[] $dataSetName one or more data set names
	 * @param string ... more data set names
	 * @return string N-Triples
	 */
	public function getNTriples( $dataSetName ) {
		$dataSets = is_array( $dataSetName ) ? $dataSetName : func_get_args();
		$triples = [];

		foreach ( $dataSets as $dataSetName ) {
			$filename = $this->getDataSetFileName( $dataSetName );

			if ( !file_exists( $filename ) || !is_readable( $filename ) ) {
				throw new InvalidArgumentException( 'No such file: ' . $filename );
			}

			$lines = file( $filename );
			$lines = array_map( 'trim', $lines );
			$triples = array_merge( $triples,  $lines );
		}

		$triples = array_unique( $triples );

		return $triples;
	}

	/**
	 * Writes the given ntriples lines to the test data file with the given name.
	 * Existing files will not be overwritten.
	 *
	 * @note This is intended as a helper function for building test cases, it should
	 *       not be used while testing.
	 *
	 * @param string $dataSetName
	 * @param string[]|string $lines
	 * @param string $suffix File name suffix
	 *
	 * @return string The filename the data was written to, or false if no data was written.
	 */
	public function putTestData( $dataSetName, $lines, $suffix = '' ) {
		$filename = $this->getDataSetFileName( $dataSetName ) . $suffix;

		$data = join( "\n", (array)$lines );
		file_put_contents( $filename, $data );

		return $filename;
	}

	/**
	 * Returns the vocabulary to use with the test data.
	 *
	 * @return RdfVocabulary
	 */
	public function getVocabulary() {
		return new RdfVocabulary( self::URI_BASE, self::URI_DATA );
	}

	/**
	 * Returns a new NTriplesRdfWriter, with vocabulary namespaces registered.
	 *
	 * @param bool $start whether to call start() on the writer.
	 *
	 * @return NTriplesRdfWriter
	 */
	public function getNTriplesWriter( $start = true ) {
		$writer = new NTriplesRdfWriter();

		foreach ( $this->getVocabulary()->getNamespaces() as $ns => $uri ) {
			$writer->prefix( $ns, $uri );
		}

		if ( $start ) {
			$writer->start();
		}

		return $writer;
	}

	/**
	 * Get site definitions matching the test data.
	 *
	 * @return SiteList
	 */
	public function getSiteList() {
		$list = new SiteList();

		$wiki = new Site();
		$wiki->setGlobalId( 'enwiki' );
		$wiki->setLanguageCode( 'en' );
		$wiki->setLinkPath( 'http://enwiki.acme.test/$1' );
		$wiki->setGroup( 'wikipedia' );
		$list['enwiki'] = $wiki;

		$wiki = new Site();
		$wiki->setGlobalId( 'ruwiki' );
		$wiki->setLanguageCode( 'ru' );
		$wiki->setLinkPath( 'http://ruwiki.acme.test/$1' );
		$list['ruwiki'] = $wiki;

		return $list;
	}

	/**
	 * Define a set of fake properties
	 * @return array[] A list of properties used in the test data. Each element is a pair
	 *         of an PropertyId and a data type ID.
	 */
	public static function getTestProperties() {
		return array(
			array( new PropertyId( 'P2' ), 'wikibase-item' ),
			array( new PropertyId( 'P3' ), 'commonsMedia' ),
			array( new PropertyId( 'P4' ), 'globe-coordinate' ),
			array( new PropertyId( 'P5' ), 'monolingualtext' ),
			array( new PropertyId( 'P6' ), 'quantity' ),
			array( new PropertyId( 'P7' ), 'string' ),
			array( new PropertyId( 'P8' ), 'time' ),
			array( new PropertyId( 'P9' ), 'url' ),
			array( new PropertyId( 'P10' ), 'geo-shape' ),
		);
	}

	/**
	 * Construct mock repository matching the test data.
	 *
	 * @return MockRepository
	 */
	public function getMockRepository() {
		static $repo;

		if ( !empty( $repo ) ) {
			return $repo;
		}

		$repo = new MockRepository();

		foreach ( self::getTestProperties() as list( $id, $type ) ) {
			$fingerprint = new Fingerprint();
			$fingerprint->setLabel( 'en', 'Property' . $id->getNumericId() );
			$entity = new Property( $id, $fingerprint, $type );
			$repo->putEntity( $entity );
		}

		$q42 = new ItemId( 'Q42' );
		$fingerprint = new Fingerprint();
		$fingerprint->setLabel( 'en', 'Item42' );
		$entity = new Item( $q42, $fingerprint );
		$repo->putEntity( $entity );

		$repo->putRedirect( new EntityRedirect( new ItemId( 'Q4242' ), $q42 ) );

		return $repo;
	}

}
