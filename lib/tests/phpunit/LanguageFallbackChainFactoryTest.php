<?php

namespace Wikibase\Lib\Tests;

use Language;
use MediaWiki\Languages\LanguageFallback;
use MediaWikiIntegrationTestCase;
use MWException;
use RequestContext;
use User;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\LanguageWithConversion;
use Wikibase\Lib\TermLanguageFallbackChain;

/**
 * @covers \Wikibase\Lib\LanguageFallbackChainFactory
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Liangent < liangent@gmail.com >
 */
class LanguageFallbackChainFactoryTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param array $expectedItems
	 * @param LanguageWithConversion[] $chain
	 */
	private function assertChainEquals( array $expectedItems, array $chain ) {
		$this->assertSame( count( $expectedItems ), count( $chain ) );

		foreach ( $expectedItems as $i => $expected ) {
			if ( is_array( $expected ) ) {
				$this->assertSame( $expected[0], $chain[$i]->getLanguage()->getCode() );
				$this->assertSame( $expected[1], $chain[$i]->getSourceLanguage()->getCode() );
			} else {
				$this->assertSame( $expected, $chain[$i]->getLanguage()->getCode() );
				$this->assertNull( $chain[$i]->getSourceLanguage() );
			}
		}
	}

	/**
	 * @param string[] $disabledVariants
	 */
	private function setupDisabledVariants( array $disabledVariants ) {
		$this->setMwGlobals( [
			'wgDisabledVariants' => $disabledVariants,
		] );
	}

	private function getLanguageFallbackChainFactory() {
		$languageFallback = $this->createMock( LanguageFallback::class );
		$languageFallback->method( 'getAll' )
			->willReturnCallback( function( $code ) {
				return $this->getLanguageFallbacksForCallback( $code );
			} );
		return new LanguageFallbackChainFactory( null, null, null, $languageFallback );
	}

	/**
	 * This captures the state of language fallbacks from 2016-08-17.
	 * There's no need for this to be exactly up to date with MediaWiki,
	 * we just need a data base to test with.
	 *
	 * @param string $code
	 *
	 * @return string[]
	 */
	private function getLanguageFallbacksForCallback( $code ) {
		switch ( $code ) {
			case 'en':
				return [];
			case 'de':
				return [ 'en' ];
			case 'de-formal':
				return [ 'de', 'en' ];
			case 'ii':
				return [ 'zh-cn', 'zh-hans', 'en' ];
			case 'kk':
				return [ 'kk-cyrl', 'en' ];
			case 'kk-cn':
				return [ 'kk-arab', 'kk-cyrl', 'en' ];
			case 'lzh':
				return [ 'en' ];
			case 'zh':
				return [ 'zh-hans', 'en' ];
			case 'zh-cn':
				return [ 'zh-hans', 'en' ];
			case 'zh-hk':
				return [ 'zh-hant', 'zh-hans', 'en' ];
			default:
				// Language::getFallbacksFor returns [ 'en' ] if $code is unknown and conforms to /^[a-z0-9-]{2,}$/
				return preg_match( '/^[a-z0-9-]{2,}$/', $code ) ? [ 'en' ] : [];
		}
	}

	/**
	 * @dataProvider providerNewFromLanguage
	 */
	public function testNewFromLanguage(
		$languageCode,
		array $expected,
		array $disabledVariants = []
	) {
		$this->setupDisabledVariants( $disabledVariants );
		$factory = $this->getLanguageFallbackChainFactory();
		$chain = $factory->newFromLanguage( Language::factory( $languageCode ) )->getFallbackChain();
		$this->assertChainEquals( $expected, $chain );
	}

	/**
	 * @dataProvider providerNewFromLanguage
	 */
	public function testNewFromLanguageCode(
		$languageCode,
		array $expected,
		array $disabledVariants = []
	) {
		$this->setupDisabledVariants( $disabledVariants );
		$factory = $this->getLanguageFallbackChainFactory();
		$chain = $factory->newFromLanguageCode( $languageCode )->getFallbackChain();
		$this->assertChainEquals( $expected, $chain );
	}

	public function providerNewFromLanguage() {
		return [
			[
				'languageCode' => 'en',
				'expected' => [ 'en' ]
			],

			[
				'languageCode' => 'zh-classical',
				'expected' => [ 'lzh', 'en' ],
			],

			[
				'languageCode' => 'de-formal',
				'expected' => [ 'de-formal', 'de', 'en' ]
			],
			// Repeated to test caching
			[
				'languageCode' => 'de-formal',
				'expected' => [ 'de-formal', 'de', 'en' ]
			],

			[
				'languageCode' => 'zh',
				'expected' => [
					'zh',
					[ 'zh', 'zh-hans' ],
					[ 'zh', 'zh-hant' ],
					[ 'zh', 'zh-cn' ],
					[ 'zh', 'zh-tw' ],
					[ 'zh', 'zh-hk' ],
					[ 'zh', 'zh-sg' ],
					[ 'zh', 'zh-mo' ],
					[ 'zh', 'zh-my' ],
					'en',
				]
			],
			[
				'languageCode' => 'zh',
				'expected' => [
					'zh',
					[ 'zh', 'zh-hans' ],
					[ 'zh', 'zh-hant' ],
					[ 'zh', 'zh-cn' ],
					[ 'zh', 'zh-tw' ],
					[ 'zh', 'zh-hk' ],
					[ 'zh', 'zh-sg' ],
					'en',
				],
				'disabledVariants' => [ 'zh-mo', 'zh-my' ]
			],
			[
				'languageCode' => 'zh-cn',
				'expected' => [
					'zh-cn',
					[ 'zh-cn', 'zh-hans' ],
					[ 'zh-cn', 'zh-sg' ],
					[ 'zh-cn', 'zh-my' ],
					[ 'zh-cn', 'zh' ],
					[ 'zh-cn', 'zh-hant' ],
					[ 'zh-cn', 'zh-hk' ],
					[ 'zh-cn', 'zh-mo' ],
					[ 'zh-cn', 'zh-tw' ],
					'en',
				]
			],
			[
				'languageCode' => 'zh-cn',
				'expected' => [
					'zh-cn',
					[ 'zh-cn', 'zh-sg' ],
					[ 'zh-cn', 'zh' ],
					[ 'zh-cn', 'zh-hant' ],
					[ 'zh-cn', 'zh-hk' ],
					[ 'zh-cn', 'zh-tw' ],
					'zh-hans',
					'en',
				],
				'disabledVariants' => [ 'zh-mo', 'zh-my', 'zh-hans' ]
			],

			[
				'languageCode' => 'ii',
				'expected' => [
					'ii',
					'zh-cn',
					[ 'zh-cn', 'zh-hans' ],
					[ 'zh-cn', 'zh-sg' ],
					[ 'zh-cn', 'zh-my' ],
					[ 'zh-cn', 'zh' ],
					[ 'zh-cn', 'zh-hant' ],
					[ 'zh-cn', 'zh-hk' ],
					[ 'zh-cn', 'zh-mo' ],
					[ 'zh-cn', 'zh-tw' ],
					'en',
				]
			],

			[
				'languageCode' => 'kk',
				'expected' => [
					'kk',
					[ 'kk', 'kk-cyrl' ],
					[ 'kk', 'kk-latn' ],
					[ 'kk', 'kk-arab' ],
					[ 'kk', 'kk-kz' ],
					[ 'kk', 'kk-tr' ],
					[ 'kk', 'kk-cn' ],
					'en',
				]
			],
			[
				'languageCode' => '⧼Lang⧽',
				'expected' => [ 'en' ]
			],
		];
	}

	/**
	 * @dataProvider provideNewFromLanguageCodeException
	 */
	public function testNewFromLanguageCodeException( $languageCode ) {
		$factory = $this->getLanguageFallbackChainFactory();
		$this->expectException( MWException::class );
		$factory->newFromLanguageCode( $languageCode );
	}

	public function provideNewFromLanguageCodeException() {
		return [
			[ ':' ],
			[ '/' ],
		];
	}

	public function testNewFromContext() {
		$factory = $this->getLanguageFallbackChainFactory();
		$languageFallbackChain = $factory->newFromContext( RequestContext::getMain() );
		$this->assertTrue( $languageFallbackChain instanceof TermLanguageFallbackChain );
	}

	public function testNewFromContextAndLanguageCode() {
		$factory = $this->getLanguageFallbackChainFactory();
		$languageFallbackChain = $factory->newFromContextAndLanguageCode( RequestContext::getMain(), 'en' );
		$this->assertTrue( $languageFallbackChain instanceof TermLanguageFallbackChain );
	}

	/**
	 * @dataProvider providerNewFromLanguage
	 */
	public function testNewFromUserAndLanguageCode(
		$languageCode,
		array $expected,
		array $disabledVariants = []
	) {
		$this->setupDisabledVariants( $disabledVariants );
		$factory = $this->getLanguageFallbackChainFactory();
		$anon = new User();
		$chain = $factory->newFromUserAndLanguageCode( $anon, $languageCode )->getFallbackChain();
		$this->assertChainEquals( $expected, $chain );
	}

	/**
	 * @dataProvider provideTestFromBabel
	 */
	public function testBuildFromBabel( array $babel, array $expected, array $disabledVariants = [] ) {
		$this->setupDisabledVariants( $disabledVariants );
		$factory = $this->getLanguageFallbackChainFactory();
		$chain = $factory->buildFromBabel( $babel );
		$this->assertChainEquals( $expected, $chain );
	}

	public function provideTestFromBabel() {
		return [
			[
				'babel' => [ 'N' => [ 'de-formal' ] ],
				'expected' => [ 'de-formal', 'de', 'en' ]
			],
			[
				'babel' => [ 'N' => [ '/' ] ],
				'expected' => []
			],
			[
				'babel' => [ 'N' => [ ':', 'en' ] ],
				'expected' => [ 'en' ]
			],
			[
				'babel' => [ 'N' => [ 'unknown' ] ],
				'expected' => [ 'unknown', 'en' ]
			],
			[
				'babel' => [ 'N' => [ 'zh-classical' ] ],
				'expected' => [ 'lzh', 'en' ]
			],
			[
				'babel' => [ 'N' => [ 'en', 'de-formal' ] ],
				'expected' => [ 'en', 'de-formal', 'de' ]
			],
			[
				'babel' => [ 'N' => [ 'de-formal' ], '3' => [ 'en' ] ],
				'expected' => [ 'de-formal', 'en', 'de' ]
			],
			[
				'babel' => [ 'N' => [ 'zh' ] ],
				'expected' => [
					'zh',
					[ 'zh', 'zh-hans' ],
					[ 'zh', 'zh-hant' ],
					[ 'zh', 'zh-cn' ],
					[ 'zh', 'zh-tw' ],
					[ 'zh', 'zh-hk' ],
					[ 'zh', 'zh-sg' ],
					[ 'zh', 'zh-mo' ],
					[ 'zh', 'zh-my' ],
					'en',
				],
			],
			[
				'babel' => [ 'N' => [ 'zh' ] ],
				'expected' => [
					'zh',
					[ 'zh', 'zh-hans' ],
					[ 'zh', 'zh-hant' ],
					[ 'zh', 'zh-cn' ],
					[ 'zh', 'zh-tw' ],
					[ 'zh', 'zh-hk' ],
					[ 'zh', 'zh-sg' ],
					'en',
				],
				'disabledVariants' => [ 'zh-mo', 'zh-my' ],
			],
			[
				'babel' => [ 'N' => [ 'zh-cn', 'de-formal' ], '3' => [ 'en', 'de' ] ],
				'expected' => [
					'zh-cn',
					'de-formal',
					[ 'zh-cn', 'zh-hans' ],
					[ 'zh-cn', 'zh-sg' ],
					[ 'zh-cn', 'zh-my' ],
					[ 'zh-cn', 'zh' ],
					[ 'zh-cn', 'zh-hant' ],
					[ 'zh-cn', 'zh-hk' ],
					[ 'zh-cn', 'zh-mo' ],
					[ 'zh-cn', 'zh-tw' ],
					'en',
					'de',
				]
			],
			[
				'babel' => [ 'N' => [ 'zh-cn', 'zh-hk' ], '3' => [ 'en', 'de-formal' ] ],
				'expected' => [
					'zh-cn',
					'zh-hk',
					[ 'zh-cn', 'zh-hans' ],
					[ 'zh-cn', 'zh-sg' ],
					[ 'zh-cn', 'zh-my' ],
					[ 'zh-cn', 'zh' ],
					[ 'zh-cn', 'zh-hant' ],
					[ 'zh-cn', 'zh-mo' ],
					[ 'zh-cn', 'zh-tw' ],
					'en',
					'de-formal',
					'de',
				]
			],
			[
				'babel' => [
					'N' => [ 'en', 'de-formal', 'zh', 'zh-cn' ],
					'4' => [ 'kk-cn' ],
					'2' => [ 'zh-hk', 'kk' ],
				],
				'expected' => [
					'en',
					'de-formal',
					'zh',
					'zh-cn',
					[ 'zh', 'zh-hans' ],
					[ 'zh', 'zh-hant' ],
					[ 'zh', 'zh-tw' ],
					[ 'zh', 'zh-hk' ],
					[ 'zh', 'zh-sg' ],
					[ 'zh', 'zh-mo' ],
					[ 'zh', 'zh-my' ],
					'kk-cn',
					[ 'kk-cn', 'kk' ],
					[ 'kk-cn', 'kk-cyrl' ],
					[ 'kk-cn', 'kk-latn' ],
					[ 'kk-cn', 'kk-arab' ],
					[ 'kk-cn', 'kk-kz' ],
					[ 'kk-cn', 'kk-tr' ],
					'de',
				]
			],
		];
	}

}
