<?php

namespace Wikibase\Test;

use DataTypes\DataType;
use DataTypes\Message;
use PHPUnit_Framework_TestCase;
use Wikibase\DataTypeSelector;

/**
 * @covers Wikibase\DataTypeSelector
 *
 * @group Wikibase
 * @group WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class DataTypeSelectorTest extends PHPUnit_Framework_TestCase {

	protected function setUp() {
		parent::setUp();

		Message::registerTextFunction( function( $key, $languageCode ) {
			return '(' . implode( '|', func_get_args() ) . ')';
		} );
	}

	/**
	 * @param string $propertyType
	 *
	 * @return DataType
	 */
	private function newDataType( $propertyType ) {
		$dataType = $this->getMockBuilder( 'DataTypes\DataType' )
			->disableOriginalConstructor()
			->getMock();

		$dataType->expects( $this->any() )
			->method( 'getId' )
			->will( $this->returnValue( $propertyType ) );

		$dataType->expects( $this->any() )
			->method( 'getLabel' )
			->will( $this->returnValue( '(datatypes-type-' . $propertyType . ')' ) );

		return $dataType;
	}

	/**
	 * @param DataType[]|null $dataTypes
	 *
	 * @return DataTypeSelector
	 */
	private function newInstance( array $dataTypes = null ) {
		return new DataTypeSelector(
			$dataTypes !== null ? $dataTypes : array( $this->newDataType( '<PT>' ) ),
			'qqx'
		);
	}

	/**
	 * @dataProvider invalidConstructorArgumentsProvider
	 */
	public function testConstructorThrowsException( array $dataTypes, $languageCode ) {
		$this->setExpectedException( 'MWException' );
		new DataTypeSelector( $dataTypes, $languageCode );
	}

	public function invalidConstructorArgumentsProvider() {
		return array(
			array( array(), null ),
			array( array(), false ),
			array( array( null ), '' ),
			array( array( false ), '' ),
			array( array( '' ), '' ),
		);
	}

	/**
	 * @dataProvider getHtmlProvider
	 */
	public function testGetHtml( array $dataTypes, $selectedTypeId, $expected ) {
		$selector = $this->newInstance( $dataTypes );
		$html = $selector->getHtml( '<ID>', '<NAME>', $selectedTypeId );
		$this->assertSame( $expected, $html );
	}

	public function getHtmlProvider() {
		return array(
			array(
				array(),
				'',
				'<select name="&lt;NAME&gt;" id="&lt;ID&gt;" class="wb-select">'
				. '</select>'
			),
			array(
				array( $this->newDataType( '<PT>' ) ),
				'',
				'<select name="&lt;NAME&gt;" id="&lt;ID&gt;" class="wb-select">'
				. '<option value="&lt;PT&gt;">(datatypes-type-&lt;PT>|qqx)</option>'
				. '</select>'
			),
			array(
				array( $this->newDataType( 'PT1' ), $this->newDataType( 'PT2' ) ),
				'PT2',
				'<select name="&lt;NAME&gt;" id="&lt;ID&gt;" class="wb-select">'
				. '<option value="PT1">(datatypes-type-PT1|qqx)</option>'
				. '<option value="PT2" selected="">(datatypes-type-PT2|qqx)</option>'
				. '</select>'
			),
		);
	}

	public function testGetOptionsArray() {
		$selector = $this->newInstance();
		$options = $selector->getOptionsArray();
		$this->assertSame( array( '<PT>' => '(datatypes-type-<PT>|qqx)' ), $options );
	}

	/**
	 * @dataProvider getOptionsHtmlProvider
	 */
	public function testGetOptionsHtml( $selectedTypeId, $expected ) {
		$selector = $this->newInstance();
		$html = $selector->getOptionsHtml( $selectedTypeId );
		$this->assertSame( $expected, $html );
	}

	public function getOptionsHtmlProvider() {
		return array(
			array(
				'',
				'<option value="&lt;PT&gt;">(datatypes-type-&lt;PT>|qqx)</option>'
			),
			array(
				'<PT>',
				'<option value="&lt;PT&gt;" selected="">(datatypes-type-&lt;PT>|qqx)</option>'
			),
		);
	}

}
