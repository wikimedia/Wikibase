<?php

namespace Wikibase\Test\Rdf;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Rdf\Values\CommonsMediaRdfBuilder;
use Wikibase\Repo\Tests\Rdf\NTriplesRdfTestHelper;
use Wikimedia\Purtle\NTriplesRdfWriter;
use Wikibase\DataModel\Snak\PropertyValueSnak;

/**
 * @covers Wikibase\Rdf\Values\CommonsMediaRdfBuilder
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseRdf
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class CommonsMediaRdfBuilderTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var NTriplesRdfTestHelper
	 */
	private $helper;

	protected function setUp() {
		parent::setUp();

		$this->helper = new NTriplesRdfTestHelper();
	}

	public function testAddValue() {
		$vocab = new RdfVocabulary( 'http://test/item/', 'http://test/data/' );
		$builder = new CommonsMediaRdfBuilder( $vocab );

		$writer = new NTriplesRdfWriter();
		$writer->prefix( 'www', "http://www/" );
		$writer->prefix( 'acme', "http://acme/" );

		$writer->start();
		$writer->about( 'www', 'Q1' );

		$snak = new PropertyValueSnak(
			new PropertyId( 'P1' ),
			new StringValue( 'Bunny.jpg' )
		);

		$builder->addValue( $writer, 'acme', 'testing', 'DUMMY', $snak );

		$expected = '<http://www/Q1> <http://acme/testing> <http://commons.wikimedia.org/wiki/Special:FilePath/Bunny.jpg> .';
		$this->helper->assertNTriplesEquals( $expected, $writer->drain() );
	}

}
