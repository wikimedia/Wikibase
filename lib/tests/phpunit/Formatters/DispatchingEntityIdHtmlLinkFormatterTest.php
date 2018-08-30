<?php

namespace Wikibase\Lib\Tests\Formatters;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit4And6Compat;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\EntityIdHtmlLinkFormatter;
use Wikibase\Lib\Formatters\DispatchingEntityIdHtmlLinkFormatter;

/**
 * @covers \Wikibase\Lib\DispatchingEntityIdHtmlLinkFormatter
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class DispatchingEntityIdHtmlLinkFormatterTest extends TestCase {

	use PHPUnit4And6Compat;

	/**
	 * @var EntityIdHtmlLinkFormatter|MockObject
	 */
	private $defaultFormatter;

	public function setUp() {
		$this->defaultFormatter = $this->newMockFormatter();
	}

	public function testGivenFormatterMissing_UseDefaultFormatter() {
		$this->defaultFormatter->expects( $this->once() )
			->method( 'formatEntityId' );
		$formatter = new DispatchingEntityIdHtmlLinkFormatter( [], $this->defaultFormatter );
		$formatter->formatEntityId( $this->createMock( EntityId::class ) );
	}

	public function testGivenFormatterExists_FormatterUsed() {
		$this->defaultFormatter->expects( $this->once() )
			->method( 'formatEntityId' );
		$formatters = [ 'foo' => $this->defaultFormatter ];

		$mockEntityId = $this->createMock( EntityId::class );
		$mockEntityId->expects( $this->any() )
			->method( 'getEntityType' )
			->willReturn( 'foo' );

		$mockDefaultFormatter = $this->createMock( EntityIdHtmlLinkFormatter::class );
		$formatter = new DispatchingEntityIdHtmlLinkFormatter( $formatters, $mockDefaultFormatter );
		$formatter->formatEntityId( $mockEntityId );
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testGivenInvalidFormatter() {
		$formatters = [ 'foo' => 'aStringIsNotAFormatter' ];

		$mockEntityId = $this->createMock( EntityId::class );
		$mockEntityId->expects( $this->any() )
			->method( 'getEntityType' )
			->willReturn( 'foo' );

		$mockDefaultFormatter = $this->createMock( EntityIdHtmlLinkFormatter::class );

		new DispatchingEntityIdHtmlLinkFormatter( $formatters, $mockDefaultFormatter );
	}

	private function newMockFormatter(): EntityIdHtmlLinkFormatter {
		return $this->createMock( EntityIdHtmlLinkFormatter::class );
	}

}
