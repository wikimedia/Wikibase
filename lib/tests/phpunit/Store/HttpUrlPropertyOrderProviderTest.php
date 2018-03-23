<?php

namespace Wikibase\Lib\Tests\Store;

use Wikibase\Lib\Store\HttpUrlPropertyOrderProvider;

/**
 * @covers Wikibase\Lib\Store\HttpUrlPropertyOrderProvider
 * @covers Wikibase\Lib\Store\WikiTextPropertyOrderProvider
 *
 * @group Wikibase
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 */
class HttpUrlPropertyOrderProviderTest extends \PHPUnit\Framework\TestCase {

	public function provideGetPropertyOrder() {
		$cases = WikiTextPropertyOrderProviderTestHelper::provideGetPropertyOrder();
		$cases[] = [ false, null ];

		return $cases;
	}

	/**
	 * @dataProvider provideGetPropertyOrder
	 */
	public function testGetPropertyOrder( $text, $expected ) {
		$instance = new HttpUrlPropertyOrderProvider(
			'page-url',
			$this->getHttp( $text )
		);
		$propertyOrder = $instance->getPropertyOrder();
		$this->assertSame( $expected, $propertyOrder );
	}

	private function getHttp( $text ) {
		HttpUrlPropertyOrderProviderTestMockHttp::$response = $text;
		return new HttpUrlPropertyOrderProviderTestMockHttp();
	}

}
