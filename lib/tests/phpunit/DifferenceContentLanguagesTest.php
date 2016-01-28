<?php

namespace Wikibase\Lib\Test;

use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\DifferenceContentLanguages;

/**
 * @covers Wikibase\Lib\DifferenceContentLanguages
 *
 * @group WikibaseLib
 * @group Wikibase
 *
 * @license GNU GPL v2+
 * @author Adrian Heine < adrian.heine@wikimedia.de >
 */
class DifferenceContentLanguagesTest extends \MediaWikiTestCase {

	/**
	 * @dataProvider provideTestGetLanguages
	 */
	public function testGetLanguages( ContentLanguages $a, ContentLanguages $b, array $expected ) {
		$contentLanguages = new DifferenceContentLanguages( $a, $b );
		$result = $contentLanguages->getLanguages();

		$this->assertSame( $expected, $result );
	}

	public function provideTestGetLanguages() {
		$empty = $this->getMockContentLanguages( array() );
		$one = $this->getMockContentLanguages( array( 'one' ) );
		$two = $this->getMockContentLanguages( array( 'one', 'two' ) );
		$otherTwo = $this->getMockContentLanguages( array( 'three', 'four' ) );

		return array(
			array( $empty, $empty, array() ),
			array( $empty, $one, array() ),
			array( $one, $empty, array( 'one' ) ),
			array( $one, $two, array() ),
			array( $two, $one, array( 'two' ) ),
			array( $two, $otherTwo, array( 'one', 'two' ) ),
		);
	}

	/**
	 * @dataProvider provideTestHasLanguage
	 */
	public function testHasLanguage( ContentLanguages $a, ContentLanguages $b, $lang, $expected ) {
		$contentLanguages = new DifferenceContentLanguages( $a, $b );
		$result = $contentLanguages->hasLanguage( $lang );

		$this->assertSame( $expected, $result );
	}

	public function provideTestHasLanguage() {
		$empty = $this->getMockContentLanguages( array() );
		$one = $this->getMockContentLanguages( array( 'one' ) );
		$two = $this->getMockContentLanguages( array( 'one', 'two' ) );
		$otherTwo = $this->getMockContentLanguages( array( 'three', 'four' ) );

		return array(
			array( $empty, $empty, 'one', false ),
			array( $empty, $one, 'one', false ),
			array( $empty, $one, 'two', false ),
			array( $two, $one, 'one', false ),
			array( $two, $one, 'two', true ),
			array( $two, $one, 'three', false ),
			array( $two, $otherTwo, 'one', true ),
			array( $two, $otherTwo, 'two', true ),
			array( $two, $otherTwo, 'three', false ),
			array( $two, $otherTwo, 'four', false ),
		);
	}

	private function getMockContentLanguages( $languages ) {
		$contentLanguages = $this->getMock( 'Wikibase\Lib\ContentLanguages' );
		$contentLanguages->expects( $this->any() )
			->method( 'getLanguages' )
			->will( $this->returnValue( $languages ) );
		return $contentLanguages;
	}

}
