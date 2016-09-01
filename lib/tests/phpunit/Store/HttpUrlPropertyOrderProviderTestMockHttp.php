<?php

namespace Wikibase\Tests;

use Http;
use PHPUnit_Framework_Assert;

/**
 * Http mock for the HttpUrlPropertyOrderProviderTest.
 *
 * @private
 * @see Http
 *
 * @license GPL-2.0+
 * @author Marius Hoch
 */
class HttpUrlPropertyOrderProviderTestMockHttp extends Http {

	/**
	 * @var mixed
	 */
	public static $response;

	public static function get( $url, $options = [], $caller = __METHOD__ ) {
		PHPUnit_Framework_Assert::assertSame( 'page-url', $url );
		PHPUnit_Framework_Assert::assertInternalType( 'array', $options );
		PHPUnit_Framework_Assert::assertInternalType( 'string', $caller );

		return self::$response;
	}

}
