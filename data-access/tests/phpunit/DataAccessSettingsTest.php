<?php

namespace Wikibase\DataAccess\Tests;

use Wikibase\DataAccess\DataAccessSettings;

/**
 * @covers \Wikibase\DataAccess\DataAccessSettings
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class DataAccessSettingsTest extends \PHPUnit\Framework\TestCase {

	public function testConvertsMaxSerializedEntitySizeFromKiloBytesToBytes() {
		$settings = new DataAccessSettings(
			1,
			true,
			false,
			DataAccessSettings::USE_REPOSITORY_PREFIX_BASED_FEDERATION,
			DataAccessSettings::PROPERTY_TERMS_UNNORMALIZED
		);

		$this->assertEquals( 1024, $settings->maxSerializedEntitySizeInBytes() );
	}

	/**
	 * @dataProvider provideTwoBooleans
	 */
	public function testSearchFieldsGetters( $useSearchFields, $forceWriteSearchFields ) {
		$settings = new DataAccessSettings(
			1,
			$useSearchFields,
			$forceWriteSearchFields,
			DataAccessSettings::USE_REPOSITORY_PREFIX_BASED_FEDERATION,
			DataAccessSettings::PROPERTY_TERMS_UNNORMALIZED
		);

		$this->assertSame( $useSearchFields, $settings->useSearchFields() );
		$this->assertSame( $forceWriteSearchFields, $settings->forceWriteSearchFields() );
	}

	public function provideTwoBooleans() {
		return [
			[ false, false ],
			[ false, true ],
			[ true, false ],
			[ true, true ],
		];
	}

	/**
	 * @dataProvider provideBoolean
	 */
	public function testNormalizedPropertyTerms( $useNormalizedPropertyTerms ) {
		$settings = new DataAccessSettings(
			1,
			true,
			false,
			DataAccessSettings::USE_REPOSITORY_PREFIX_BASED_FEDERATION,
			$useNormalizedPropertyTerms
		);

		$this->assertSame( $useNormalizedPropertyTerms, $settings->useNormalizedPropertyTerms() );
	}

	public function provideBoolean() {
		return [ [ false ], [ true ] ];
	}

}
