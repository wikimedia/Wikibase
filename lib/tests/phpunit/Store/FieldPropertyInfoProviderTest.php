<?php

namespace Wikibase\Lib\Tests\Store;

use PHPUnit4And6Compat;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\FieldPropertyInfoProvider;
use Wikibase\Lib\Store\PropertyInfoLookup;

/**
 * @covers Wikibase\Lib\Store\FieldPropertyInfoProvider
 *
 * @group Wikibase
 * @group WikibaseStore
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class FieldPropertyInfoProviderTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/**
	 * @dataProvider provideGetPropertyInfo
	 */
	public function testGetPropertyInfo( $info, $key, $expected ) {
		$propertyId = new PropertyId( 'P1' );

		$lookup = $this->getMock( PropertyInfoLookup::class );
		$lookup->expects( $this->once() )
			->method( 'getPropertyInfo' )
			->with( $propertyId )
			->will( $this->returnValue( $info ) );

		$instance = new FieldPropertyInfoProvider( $lookup, $key );
		$this->assertSame( $expected, $instance->getPropertyInfo( $propertyId ) );
	}

	public function provideGetPropertyInfo() {
		return [
			'no info array' => [ null, 'foo', null ],
			'empty info array' => [ [], 'foo', null ],
			'found info field' => [ [ 'hrmf' => 'Mitten', 'foo' => 'Kitten' ], 'foo', 'Kitten' ],
		];
	}

}
