<?php

namespace Wikibase\Client\Tests;

use Database;
use FakeResultWrapper;
use HashSiteStore;
use Language;
use LBFactory;
use LoadBalancer;
use MediaWikiTestCase;
use ResultWrapper;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\SettingsArray;

/**
 * @group Wikibase
 * @group WikibaseClient
 *
 * @license GPL-2.0+
 */
class WikibaseClientFederationIntegrationTest extends MediaWikiTestCase {

	private function getRowObject( array $fields ) {
		$row = new \stdClass();
		foreach ( $fields as $field => $value ) {
			$row->$field = $value;
		}
		return $row;
	}

	/**
	 * @param ResultWrapper|false $returnValue
	 *
	 * @return \PHPUnit_Framework_MockObject_MockObject|Database
	 */
	private function getMockDb( $returnValue ) {
		$db = $this->getMockBuilder( Database::class )
			->disableOriginalConstructor()
			->getMock();
		$db->expects( $this->any() )
			->method( 'select' )
			->willReturn( $returnValue );
		return $db;
	}

	private function getMockLoadBalancer( Database $db ) {
		$loadBalancer = $this->getMockBuilder( LoadBalancer::class )
			->disableOriginalConstructor()
			->getMock();
		$loadBalancer->expects( $this->any() )
			->method( 'getConnection' )
			->willReturn( $db );
		return $loadBalancer;
	}

	private function getLoadBalancerFactory( array $loadBalancers ) {
		$factory = $this->getMockBuilder( LBFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$factory->expects( $this->any() )
			->method( 'getMainLB' )
			->willReturnCallback( function( $wiki ) use ( $loadBalancers ) {
				return $loadBalancers[$wiki];
			} );
		return $factory;
	}

	public function testFoo() {
		if ( !defined( 'WB_VERSION' ) ) {
			$this->markTestSkipped( 'This test requires repo and client extension to be active on the same wiki.' );
		}

		$defaultLb = $this->getMockLoadBalancer( $this->getMockDb( new FakeResultWrapper( [] ) ) );
		$lbFactory = $this->getLoadBalancerFactory( [
			'foowiki' => $this->getMockLoadBalancer(
				$this->getMockDb( new FakeResultWrapper( [ $this->getRowObject( [
					'rev_id' => 1,
					'rev_content_format' => null,
					'rev_timestamp' => '20160101010101',
					'page_title' => 'Q123',
					'page_latest' => 1,
					'page_is_redirect' => 0,
					'old_id' => 1,
					'old_text' => '{"type":"item","id":"Q123","labels":{"en":{"language":"en","value":"Foo Item"}}' .
						',"descriptions":[],"aliases":[],"claims":[],"sitelinks":[]}',
					'old_flags' => 'utf-8',
				] ) ] ) )
			),
			false => $defaultLb,
		] );
		$this->overrideMwServices( null, [
			'DBLoadBalancerFactory' => function () use ( $lbFactory ) {
				return $lbFactory;
			},
			'DBLoadBalancer' => function () use ( $defaultLb ) {
				return $defaultLb;
			},
		] );

		$client = $this->getWikibaseClient();
		$lookup = $client->getRestrictedEntityLookup();

		$this->assertTrue( $lookup->hasEntity( new ItemId( 'foo:Q123' ) ) );
		$this->assertFalse( $lookup->hasEntity( new ItemId( 'Q123' ) ) );
	}

	/**
	 * @return WikibaseClient
	 */
	private function getWikibaseClient() {
		return new WikibaseClient(
			new SettingsArray( WikibaseClient::getDefaultInstance()->getSettings()->getArrayCopy() ),
			Language::factory( 'en' ),
			new DataTypeDefinitions( [] ),
			new EntityTypeDefinitions( [
				'item' => [
					'entity-id-pattern' => ItemId::PATTERN,
					'entity-id-builder' => function( $serialization ) {
						return new ItemId( $serialization );
					},
				],
			] ),
			[
				'foo' => new SettingsArray( [ 'repoDatabase' => 'foowiki' ] ),
			],
			new HashSiteStore()
		);
	}

}
