<?php

namespace Wikibase\Repo\Tests\Rdf;

use DataValues\StringValue;
use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Rdf\RdfVocabulary;

/**
 * @covers Wikibase\Rdf\RdfVocabulary
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseRdf
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class RdfVocabularyTest extends PHPUnit_Framework_TestCase {

	private function newInstance() {
		return new RdfVocabulary(
			'<BASE>',
			'<DATA>',
			[ 'German' => 'de' ],
			[ 'acme' => 'http://acme.test/vocab/ACME' ]
		);
	}

	public function testGetCanonicalLanguageCode_withNonStandardCode() {
		$actual = $this->newInstance()->getCanonicalLanguageCode( 'German' );
		$this->assertSame( 'de', $actual );
	}

	public function testGetCanonicalLanguageCode_withStandardCode() {
		$actual = $this->newInstance()->getCanonicalLanguageCode( 'DE-at-x-GIBBERISH' );
		$this->assertSame( 'de-AT-x-gibberish', $actual );
	}

	public function testGetCommonsURI() {
		$actual = $this->newInstance()->getCommonsURI( '!' );
		$this->assertSame( 'http://commons.wikimedia.org/wiki/Special:FilePath/%21', $actual );
	}

	public function testGetDataTypeURI() {
		$property = Property::newFromType( 'some-type' );
		$vocab = $this->newInstance();

		// test generic uri construction
		$actual = $vocab->getDataTypeURI( $property );
		$expected = $vocab->getNamespaceURI( RdfVocabulary::NS_ONTOLOGY ) . 'SomeType';
		$this->assertSame( $expected, $actual );

		// test a type for which we have explicitly defined a uri
		$property = Property::newFromType( 'acme' );
		$actual = $vocab->getDataTypeURI( $property );
		$this->assertSame( 'http://acme.test/vocab/ACME', $actual );
	}

	public function testGetEntityLName() {
		$entityId = new PropertyId( 'P1' );
		$actual = $this->newInstance()->getEntityLName( $entityId );
		$this->assertSame( 'P1', $actual );
	}

	public function testGetEntityTypeName() {
		$actual = $this->newInstance()->getEntityTypeName( 'type' );
		$this->assertSame( 'Type', $actual );
	}

	public function testGetNamespaces() {
		$actual = $this->newInstance()->getNamespaces();
		$this->assertInternalType( 'array', $actual );
		$this->assertContainsOnly( 'string', $actual );
		$this->assertContains( '<BASE>', $actual );
		$this->assertContains( '<DATA>', $actual );
	}

	public function testGetNamespaceURI() {
		$vocab = $this->newInstance();
		$all = $vocab->getNamespaces();

		$this->assertEquals( '<DATA>', $vocab->getNamespaceURI( RdfVocabulary::NS_DATA ) );
		$this->assertEquals( '<BASE>', $vocab->getNamespaceURI( RdfVocabulary::NS_ENTITY ) );

		foreach ( $all as $ns => $uri ) {
			$this->assertEquals( $uri, $vocab->getNamespaceURI( $ns ) );
		}

		$this->setExpectedException( 'OutOfBoundsException' );
		$vocab->getNamespaceURI( 'NonExistingNamespaceForGetNamespaceUriTest' );
	}

	public function testGetOntologyURI() {
		$actual = $this->newInstance()->getOntologyURI();
		$this->assertStringStartsWith( 'http://wikiba.se/ontology-', $actual );
		$this->assertStringEndsWith( '.owl', $actual );
	}

	public function testGetStatementLName() {
		$statement = new Statement( new PropertyNoValueSnak( 1 ), null, null, '<GUID>' );
		$actual = $this->newInstance()->getStatementLName( $statement );
		$this->assertSame( '-GUID-', $actual );
	}

	public function testGetValueTypeName() {
		$dataValue = new StringValue( '' );
		$actual = $this->newInstance()->getValueTypeName( $dataValue );
		$this->assertSame( 'StringValue', $actual );
	}

}
