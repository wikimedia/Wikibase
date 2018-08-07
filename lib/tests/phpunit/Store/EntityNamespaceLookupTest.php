<?php

namespace Wikibase\Lib\Tests\Store;

use Wikibase\Lib\Store\EntityNamespaceLookup;

/**
 * @covers Wikibase\Lib\Store\EntityNamespaceLookup
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 * @author Thiemo Kreuz
 */
class EntityNamespaceLookupTest extends \PHPUnit\Framework\TestCase {

	private function newInstance() {
		return new EntityNamespaceLookup( [
			'item' => 120,
			'property' => 122,
			'mediainfo' => NS_FILE,
		], [
			'mediainfo' => 'mediainfo'
		] );
	}

	public function testGetEntityNamespaces() {
		$lookup = $this->newInstance();

		$expected = [
			'item' => 120,
			'property' => 122,
			'mediainfo' => NS_FILE,
		];
		$this->assertSame( $expected, $lookup->getEntityNamespaces() );
	}

	public function testGetEntityNamespace() {
		$lookup = $this->newInstance();

		$this->assertSame( 120, $lookup->getEntityNamespace( 'item' ), 'found' );
		$this->assertSame( NS_FILE, $lookup->getEntityNamespace( 'mediainfo' ), 'found' );
		$this->assertNull( $lookup->getEntityNamespace( 'kittens' ), 'not found' );
	}

	public function testGetEntitySlotRole() {
		$lookup = $this->newInstance();

		$this->assertSame( 'main', $lookup->getEntitySlotRole( 'item' ), 'found' );
		$this->assertSame( 'mediainfo', $lookup->getEntitySlotRole( 'mediainfo' ), 'found' );
	}

	public function testIsEntityNamespace() {
		$lookup = $this->newInstance();

		$this->assertTrue( $lookup->isEntityNamespace( 120 ), 'found' );
		$this->assertFalse( $lookup->isEntityNamespace( 120.0 ), 'must be int' );
		$this->assertFalse( $lookup->isEntityNamespace( 5 ), 'not found' );
		$this->assertFalse( $lookup->isEntityNamespace( NS_FILE ), 'not in main slot' );
	}

	public function testIsNamespaceWithEntities() {
		$lookup = $this->newInstance();

		$this->assertTrue( $lookup->isNamespaceWithEntities( 120 ), 'found' );
		$this->assertTrue( $lookup->isNamespaceWithEntities( NS_FILE ), 'not in main slot' );
	}

	public function testGetEntityType() {
		$lookup = $this->newInstance();

		$this->assertSame( 'item', $lookup->getEntityType( 120 ), 'found' );
		$this->assertNull( $lookup->getEntityType( 120.0 ), 'must be int' );
		$this->assertNull( $lookup->getEntityType( 4 ), 'not found' );
	}

}
