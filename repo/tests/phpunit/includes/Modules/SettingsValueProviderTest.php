<?php

namespace Wikibase\Repo\Tests\Modules;

use PHPUnit4And6Compat;
use Prophecy\Prophecy\ObjectProphecy;
use Wikibase\Repo\Modules\SettingsValueProvider;
use Wikibase\SettingsArray;

/**
 * @covers Wikibase\Repo\Modules\SettingsValueProvider
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class SettingsValueProviderTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testGetKeyReturnsJSSettingName() {
		$settingsValueProvider = new SettingsValueProvider(
			$this->getMock( SettingsArray::class ),
			$jsSettingName = 'jsName',
			'does not matter'
		);

		self::assertEquals( $jsSettingName, $settingsValueProvider->getKey() );
	}

	public function testGetValueReturnsSettingWithGivenName() {

		/** @var SettingsArray|ObjectProphecy $settings */
		$settings = $this->prophesize( SettingsArray::class );

		$settings->getSetting( 'setting_name' )->willReturn( 'setting value' );

		$settingsValueProvider = new SettingsValueProvider(
			$settings->reveal(),
			'does not matter',
			'setting_name'
		);

		self::assertEquals( 'setting value', $settingsValueProvider->getValue() );
	}

}
