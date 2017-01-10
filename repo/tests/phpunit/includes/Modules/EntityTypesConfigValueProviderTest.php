<?php

namespace Wikibase\Repo\Tests\Modules;

use PHPUnit_Framework_TestCase;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Repo\Modules\EntityTypesConfigValueProvider;
use Wikibase\Repo\Modules\MediaWikiConfigValueProvider;

/**
 * @covers Wikibase\Repo\Modules\EntityTypesConfigValueProvider
 *
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Thiemo Mättig
 */
class EntityTypesConfigValueProviderTest extends PHPUnit_Framework_TestCase {

	public function testConstructor_returnsMediaWikiConfigValueProviderInterface() {
		$instance = $this->newInstance();
		$this->assertInstanceOf( MediaWikiConfigValueProvider::class, $instance );
	}

	public function testGetKey() {
		$this->assertSame( 'wbEntityTypes', $this->newInstance()->getKey() );
	}

	public function testGetValue() {
		$expected = [
			'types' => [],
			'deserializer-factory-functions' => [],
		];
		$this->assertSame( $expected, $this->newInstance()->getValue() );
	}

	private function newInstance() {
		return new EntityTypesConfigValueProvider( new EntityTypeDefinitions( [] ) );
	}

}
