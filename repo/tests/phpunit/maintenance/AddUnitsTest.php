<?php
namespace Wikibase\Test;

use DataValues\DecimalValue;
use DataValues\QuantityValue;
use MediaWikiLangTestCase;
use Wikibase\Lib\UnitConverter;
use Wikibase\Repo\Maintenance\SPARQLClient;
use Wikibase\Repo\Tests\Rdf\NTriplesRdfTestHelper;
use Wikibase\Test\Rdf\RdfBuilderTestData;
use Wikibase\UpdateUnits;

require_once __DIR__ . '/MockAddUnits.php';

/**
 * @covers updateUnits.php
 * @group Wikibase
 */
class AddUnitsTest extends MediaWikiLangTestCase {

	/**
	 * @var MockAddUnits
	 */
	private $script;
	/**
	 * @var SPARQLClient
	 */
	private $client;
	/**
	 * @var UnitConverter
	 */
	private $uc;
	/**
	 * @var NTriplesRdfTestHelper
	 */
	private $helper;

	public function setUp() {
		parent::setUp();
		$this->script = new MockAddUnits();
		$this->client =
			$this->getMockBuilder( SPARQLClient::class )->disableOriginalConstructor()->getMock();
		$this->script->setClient( $this->client );
		$this->script->initializeWriter( 'http://acme.test/', 'nt' );
		$this->uc =
			$this->getMockBuilder( UnitConverter::class )->disableOriginalConstructor()->getMock();
		$this->script->setUnitConverter( $this->uc );
		$this->script->initializeBuilder();
		$this->helper = new NTriplesRdfTestHelper();
	}

	private function getTestData() {
		return new RdfBuilderTestData(
			__DIR__ . '/../data/maintenance',
			__DIR__ . '/../data/maintenance'
		);
	}

	public function getUnitsData() {
		$qConverted =
			new QuantityValue( new DecimalValue( '+1234.5' ), 'Q2', new DecimalValue( '+1235.0' ),
				new DecimalValue( '+1233.9' ) );
		/*
		 * The results files are in tests/phpunit/data/maintenance/*.nt
		 */
		return [
			'base unit' => [
				// values
				[
					[
						'v' => 'http://acme.test/value/testunit',
						'amount' => '123.45',
						'upper' => '123.50',
						'lower' => '123.39'
					]
				],
				// statements
				[
					[
						's' => 'Qstatement',
						'p' => 'http://acme.test/prop/statement/value/P123',
						'v' => 'http://acme.test/value/testunit'
					],
					[
						's' => 'Qstatement',
						'p' => 'http://acme.test/prop/qualifier/value/P123',
						'v' => 'http://acme.test/value/testunit'
					],
					[
						's' => 'Qstatement-another',
						'p' => 'http://acme.test/prop/reference/value/P123',
						'v' => 'http://acme.test/value/testunit'
					],
				],
				// convert
				null,
				// ttl
				'base'
			],
			'converted unit' => [
				// values
				[
					[
						'v' => 'http://acme.test/value/testunit',
						'amount' => '123.45',
						'upper' => '123.50',
						'lower' => '123.39'
					]
				],
				// statements
				[
					[
						's' => 'Qstatement',
						'p' => 'http://acme.test/prop/statement/value/P123',
						'v' => 'http://acme.test/value/testunit'
					],
					[
						's' => 'Qstatement',
						'p' => 'http://acme.test/prop/qualifier/value/P123',
						'v' => 'http://acme.test/value/testunit'
					],
					[
						's' => 'Qstatement-another',
						'p' => 'http://acme.test/prop/reference/value/P123',
						'v' => 'http://acme.test/value/testunit'
					],
				],
				// convert
				$qConverted,
				// ttl
				'converted',
			],
			'no statements' => [
				// values
				[
					[
						'v' => 'http://acme.test/value/testunit',
						'amount' => '123.45',
						'upper' => '123.50',
						'lower' => '123.39'
					]
				],
				// statements
				[],
				// convert
				null,
				// ttl
				'onlyvalue'
			],
		];
	}

	/**
	 * @param array $values  List of values linked to unit
	 * @param array $statements List of statements using values from $values
	 * @param array|null $converted Converted value
	 * @param string $result Expected result filename, in tests/phpunit/data/maintenance/
	 * @dataProvider getUnitsData
	 */
	public function testBaseUnit( $values, $statements, $converted, $result ) {
		$this->client->expects( $this->any() )
			->method( 'query' )
			->will( $this->onConsecutiveCalls( $values, $statements ) );

		$this->uc->expects( $this->any() )->method( 'toStandardUnits' )->will( $converted
			? $this->returnValue( $converted ) : $this->returnArgument( 0 ) );

		$values = 'Q1';
		$this->script->processUnit( $values );
		$expected = $this->getTestData()->getNTriples( $result );
		if ( !$expected ) {
			$this->getTestData()->putTestData( $result, $this->script->output, '.actual' );
		} else {
			$this->helper->assertNTriplesEquals( $expected, $this->script->output );
		}
	}

}
