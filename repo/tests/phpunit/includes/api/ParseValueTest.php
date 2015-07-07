<?php

namespace Wikibase\Test\Repo\Api;

use ApiMain;
use DataTypes\DataType;
use DataTypes\DataTypeFactory;
use FauxRequest;
use Language;
use ValueParsers\NullParser;
use Wikibase\Api\ApiErrorReporter;
use Wikibase\Api\ParseValue;
use Wikibase\Repo\ValueParserFactory;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Repo\Api\ParseValue
 *
 * @group API
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class ParseValueTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @param string[] $params
	 *
	 * @return ParseValue
	 */
	private function newApiModule( array $params ) {

		$request = new FauxRequest( $params, true );
		$main = new ApiMain( $request );

		$module = new ParseValue( $main, 'wbparsevalue' );

		$exceptionLocalizer = WikibaseRepo::getDefaultInstance()->getExceptionLocalizer();
		$errorReporter = new ApiErrorReporter(
			$module,
			$exceptionLocalizer,
			Language::factory( 'qqq' )
		);

		$dataTypeFactory = new DataTypeFactory( array(
			'string' => array( $this, 'newStringDataType' ),
			'url' => array( $this, 'newStringDataType' ),
			'globe-coordinate' => array( $this, 'newCoordinateDataType' ),
		) );

		$valueParserFactory = new ValueParserFactory( array(
			'null' => array( $this, 'newNullParser' ),
			'string' => array( $this, 'newNullParser' ),
			'url' => array( $this, 'newNullParser' ),
			'globe-coordinate' => 'DataValues\Geo\Parsers\GlobeCoordinateParser',
		) );

		$module->setServices(
			$dataTypeFactory,
			$valueParserFactory,
			$exceptionLocalizer,
			$errorReporter
		);

		return $module;
	}

	public function newStringDataType( $name ) {
		return new DataType( $name, 'string', array() );
	}

	public function newCoordinateDataType( $name ) {
		return new DataType( $name, 'globecoordinate', array() );
	}

	public function newNullParser() {
		return new NullParser();
	}

	private function callApiModule( array $params ) {
		$module = $this->newApiModule( $params );

		$module->execute();
		$result = $module->getResult();

		$data = $result->getResultData( null, array(
			'BC' => array(),
			'Types' => array(),
			'Strip' => 'all',
		) );
		return $data;
	}

	/**
	 * @return array[]
	 */
	public function provideValid() {
		return array(
			'datatype=string' => array(
				array(
					'values' => 'foo',
					'datatype' => 'string',
				),
				array(
					'0/raw' => 'foo',
					'0/type' => 'unknown',
					'0/value' => 'foo',
				),
			),

			'datatype=url' => array(
				array(
					'values' => 'foo',
					'datatype' => 'string',
				),
				array(
					'0/raw' => 'foo',
					'0/type' => 'unknown',
					'0/value' => 'foo',
				),
			),

			'parser=string (deprecated param)' => array(
				array(
					'values' => 'foo',
					'parser' => 'string',
				),
				array(
					'0/raw' => 'foo',
					'0/type' => 'unknown',
					'0/value' => 'foo',
				),
			),

			'values=foo|bar' => array(
				array(
					'values' => 'foo|bar',
					'datatype' => 'string',
				),
				array(
					'0/raw' => 'foo',
					'0/type' => 'unknown',
					'0/value' => 'foo',

					'1/raw' => 'bar',
					'1/type' => 'unknown',
					'1/value' => 'bar',
				),
			),

			'datatype=globe-coordinate' => array(
				array(
					'values' => '5.5S,37W',
					'datatype' => 'globe-coordinate',
				),
				array(
					'0/raw' => '5.5S,37W',
					'0/type' => 'globecoordinate',
				),
			),

			'malformed coordinate' => array(
				array(
					'values' => 'XYZ',
					'datatype' => 'globe-coordinate',
				),
				array(
					'0/raw' => 'XYZ',
					'0/error' => 'ValueParsers\ParseException',
					'0/error-info' => '/^.+$/',
					'0/messages/0/html/*' => '/^.+$/',
				),
			),

			'good and bad' => array(
				array(
					'values' => 'XYZ|5.5S,37W',
					'datatype' => 'globe-coordinate',
				),
				array(
					'0/error' => 'ValueParsers\ParseException',
					'1/type' => 'globecoordinate',
				),
			),

		);
	}

	protected function assertValueAtPath( $expected, $path, $data ) {
		$name = '';
		foreach ( $path as $step ) {
			$this->assertArrayHasKey( $step, $data );
			$data = $data[$step];
			$name .= '/' . $step;
		}

		if ( is_string( $expected ) && preg_match( '/^([^\s\w\d]).*\1[a-zA-Z]*$/', $expected ) ) {
			$this->assertInternalType( 'string', $data, $name );
			$this->assertRegExp( $expected, $data, $name );
		} else {
			$this->assertEquals( $expected, $data, $name );
		}
	}

	/**
	 * @dataProvider provideValid
	 */
	public function testParse( array $params, array $expected ) {

		$result = $this->callApiModule( $params );

		$this->assertArrayHasKey( 'results', $result );

		foreach ( $expected as $path => $value ) {
			$path = explode( '/', $path );
			$this->assertValueAtPath( $value, $path, $result['results'] );
		}
	}

	/**
	 * @return array[]
	 */
	public function provideInvalid() {
		return array(
			'no datatype' => array(
				array(
					'values' => 'foo',
				)
			),
			'bad datatype (valid parser name)' => array(
				array(
					'values' => 'foo',
					'datatype' => 'null',
				)
			),
			'bad parser' => array(
				array(
					'values' => 'foo',
					'parser' => 'foo',
				)
			),
		);
	}

	/**
	 * @dataProvider provideInvalid
	 */
	public function testParse_failure( array $params ) {
		$this->setExpectedException( 'UsageException' );
		$this->callApiModule( $params );
	}

}
