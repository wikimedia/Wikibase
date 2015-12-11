<?php

namespace Wikibase\Lib\Tests\Store;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\FieldPropertyInfoProvider;

/**
 * @covers Wikibase\Lib\Store\FieldPropertyInfoProvider
 *
 * @group Wikibase
 * @group WikibaseLib
 * @group WikibaseStore
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class FieldPropertyInfoProviderTest extends PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider provideGetPropertyInfo
	 */
	public function testGetPropertyInfo( $info, $key, $expected ) {
		$propertyId = new PropertyId( 'P1' );

		$lookup = $this->getMock( 'Wikibase\PropertyInfoStore' );
		$lookup->expects( $this->once() )
			->method( 'getPropertyInfo' )
			->with( $propertyId )
			->will( $this->returnValue( $info ) );

		$instance = new FieldPropertyInfoProvider( $lookup, $key );
		$this->assertSame( $expected, $instance->getPropertyInfo( $propertyId ) );
	}

	public function provideGetPropertyInfo() {
		return array(
			'no info array' => array( null, 'foo', null ),
			'empty info array' => array( array(), 'foo', null ),
			'found info field' => array( array( 'hrmf' => 'Mitten', 'foo' => 'Kitten' ), 'foo', 'Kitten' ),
		);
	}

}
