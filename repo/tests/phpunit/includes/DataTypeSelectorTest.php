<?php

namespace Wikibase\Repo\Tests;

use DataTypes\DataType;
use MWException;
use PHPUnit_Framework_TestCase;
use Wikibase\DataTypeSelector;

/**
 * @covers Wikibase\DataTypeSelector
 *
 * @group Wikibase
 * @group WikibaseRepo
 *
 * @license GPL-2.0+
 * @author Thiemo Mättig
 */
class DataTypeSelectorTest extends PHPUnit_Framework_TestCase {

	/**
	 * @param string $propertyType
	 * @param string $label
	 *
	 * @return DataType
	 */
	private function newDataType( $propertyType, $label ) {
		$dataType = $this->getMockBuilder( DataType::class )
			->disableOriginalConstructor()
			->getMock();

		$dataType->expects( $this->any() )
			->method( 'getId' )
			->will( $this->returnValue( $propertyType ) );

		$dataType->expects( $this->any() )
			->method( 'getLabel' )
			->with( 'en' )
			->will( $this->returnValue( $label ) );

		return $dataType;
	}

	/**
	 * @dataProvider invalidConstructorArgumentsProvider
	 */
	public function testConstructorThrowsException( array $dataTypes, $languageCode ) {
		$this->setExpectedException( MWException::class );
		new DataTypeSelector( $dataTypes, $languageCode );
	}

	public function invalidConstructorArgumentsProvider() {
		return [
			[ [], null ],
			[ [], false ],
			[ [ null ], '' ],
			[ [ false ], '' ],
			[ [ '' ], '' ],
		];
	}

	public function testGetOptionsArrayWithOneElement() {
		$selector = new DataTypeSelector( [
			$this->newDataType( '<PROPERTY-TYPE>', '<LABEL>' ),
		], 'en' );

		$expected = [
			'<LABEL>' => '<PROPERTY-TYPE>',
		];
		$this->assertSame( $expected, $selector->getOptionsArray() );
	}

	public function testGetOptionsArrayWithDuplicateLabels() {
		$selector = new DataTypeSelector( [
			$this->newDataType( '<PROPERTY-TYPE-B>', '<LABEL>' ),
			$this->newDataType( '<PROPERTY-TYPE-A>', '<LABEL>' ),
		], 'en' );

		$expected = [
			'<PROPERTY-TYPE-A>' => '<PROPERTY-TYPE-A>',
			'<PROPERTY-TYPE-B>' => '<PROPERTY-TYPE-B>',
		];
		$this->assertSame( $expected, $selector->getOptionsArray() );
	}

	public function testGetOptionsArraySortsLabelsInNaturalOrder() {
		$selector = new DataTypeSelector( [
			$this->newDataType( '<PROPERTY-TYPE-A>', '<LABEL-10>' ),
			$this->newDataType( '<PROPERTY-TYPE-B>', '<label-2>' ),
		], 'en' );

		$expected = [
			'<label-2>' => '<PROPERTY-TYPE-B>',
			'<LABEL-10>' => '<PROPERTY-TYPE-A>',
		];
		$this->assertSame( $expected, $selector->getOptionsArray() );
	}

}
