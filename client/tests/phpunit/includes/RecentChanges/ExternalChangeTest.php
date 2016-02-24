<?php

namespace Wikibase\Client\Tests\RecentChanges;

use PHPUnit_Framework_TestCase;
use Wikibase\Client\RecentChanges\ExternalChange;
use Wikibase\Client\RecentChanges\RevisionData;
use Wikibase\DataModel\Entity\ItemId;

/**
 * @covers Wikibase\Client\RecentChanges\ExternalChange
 *
 * @group WikibaseClient
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class ExternalChangeTest extends PHPUnit_Framework_TestCase {

	public function testValueObject() {
		$entityId = new ItemId( 'Q1' );
		$revisionData = new RevisionData( '', '', '', null, '<SITE>', array() );
		$instance = new ExternalChange( $entityId, $revisionData, '<TYPE>' );
		$this->assertSame( $entityId, $instance->getEntityId() );
		$this->assertSame( $revisionData, $instance->getRev() );
		$this->assertSame( '<TYPE>', $instance->getChangeType() );
		$this->assertSame( '<SITE>', $instance->getSiteId() );
	}

}
