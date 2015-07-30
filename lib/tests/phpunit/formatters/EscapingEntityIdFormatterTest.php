<?php

namespace Wikibase\Lib\Test;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\EscapingEntityIdFormatter;

/**
 * @covers Wikibase\Lib\EscapingEntityIdFormatter
 *
 * @group ValueFormatters
 * @group DataValueExtensions
 * @group WikibaseLib
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EscapingEntityIdFormatterTest extends PHPUnit_Framework_TestCase {

	public function testFormat() {
		$entityIdFormatter = $this->getMock( 'Wikibase\Lib\EntityIdFormatter' );
		$entityIdFormatter->expects( $this->once() )
			->method( 'formatEntityId' )
			->will( $this->returnValue( 'Q1 is &%$;§ > Q2' ) );

		$formatter = new EscapingEntityIdFormatter( $entityIdFormatter, 'htmlspecialchars' );
		$value = new ItemId( 'Q1' );

		$this->assertEquals( 'Q1 is &amp;%$;§ &gt; Q2', $formatter->formatEntityId( $value ) );
	}

}
