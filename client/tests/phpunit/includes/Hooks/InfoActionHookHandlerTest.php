<?php

namespace Wikibase\Client\Tests\Hooks;

use IContextSource;
use RequestContext;
use Title;
use Wikibase\Client\Hooks\InfoActionHookHandler;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\Usage\Sql\SqlUsageTracker;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\NamespaceChecker;

/**
 * @covers Wikibase\Client\Hooks\InfoActionHookHandler
 *
 * @group WikibaseClient
 * @group InfoActionHookHandler
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class InfoActionHookHandlerTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider handleProvider
	 */
	public function testHandle( $expected, $context, $pageInfo, $enabled, $entityId, $message ) {
		$hookHandler = $this->newHookHandler( $enabled, $entityId );
		$pageInfo = $hookHandler->handle( $context, $pageInfo );

		$this->assertEquals( $expected, $pageInfo, $message );
	}

	public function handleProvider() {
		$context = $this->getContext();

		$cases = array();

		$cases[] = array(
			array(
				'header-basic' => array(
					array(
						$context->msg( 'wikibase-pageinfo-entity-id' )->escaped(),
						'https://www.wikidata.org/wiki/Q4'
					),
					array(
						$context->msg( 'wikibase-pageinfo-entity-usage' )->escaped(),
						"<ul><li>https://www.wikidata.org/wiki/Q4</li><ul><li>Sitelink</li></ul></ul>",
					),
				)
			),
			$context, array( 'header-basic' => array() ), true, new ItemId( 'Q4' ),
			'item id link'
		);

		$cases[] = array(
			array( 'header-basic' => array(
					array(
						$context->msg( 'wikibase-pageinfo-entity-usage' )->escaped(),
						"<ul><li>https://www.wikidata.org/wiki/Q4</li><ul><li>Sitelink</li></ul></ul>",
					),
				)
			),
			$context,
			array( 'header-basic' => array() ),
			false,
			new ItemId( 'Q4' ),
			'namespace does not have wikibase enabled'
		);

		$cases[] = array(
			array(
				'header-basic' => array(
					array(
						$context->msg( 'wikibase-pageinfo-entity-id' )->escaped(),
						$context->msg( 'wikibase-pageinfo-entity-id-none' )->escaped()
					)
				)
			),
			$context, array( 'header-basic' => array() ), true, false,
			'page is not connected to an item'
		);

		return $cases;
	}

	/**
	 * @param bool $enabled
	 * @param ItemId $entityId
	 *
	 * @return InfoActionHookHandler
	 */
	private function newHookHandler( $enabled, $entityId ) {
		$namespaceChecker = $this->getMockBuilder( NamespaceChecker::class )
			->disableOriginalConstructor()
			->getMock();

		$namespaceChecker->expects( $this->any() )
			->method( 'isWikibaseEnabled' )
			->will( $this->returnValue( $enabled ) );

		$repoLinker = $this->getMockBuilder( RepoLinker::class )
			->disableOriginalConstructor()
			->getMock();

		$repoLinker->expects( $this->any() )
			->method( 'buildEntityLink' )
			->will( $this->returnValue( 'https://www.wikidata.org/wiki/Q4' ) );

		$siteLinkLookup = $this->getMockBuilder( SiteLinkLookup::class )
			->disableOriginalConstructor()
			->getMock();

		$siteLinkLookup->expects( $this->any() )
			->method( 'getItemIdForLink' )
			->will( $this->returnValue( $entityId ) );

		$sqlUsageTracker = $this->getMockBuilder( SqlUsageTracker::class )
			->disableOriginalConstructor()
			->getMock();

		if ( $entityId ) {
			$entityUsage = array( new EntityUsage( $entityId, 'S' ) );
			$sqlUsageTracker->expects( $this->any() )
				->method( 'getUsagesForPage' )
				->will( $this->returnValue( $entityUsage ) );
		}

		$hookHandler = new InfoActionHookHandler(
			$namespaceChecker,
			$repoLinker,
			$siteLinkLookup,
			'enwiki',
			$sqlUsageTracker
		);

		return $hookHandler;
	}

	/**
	 * @return IContextSource
	 */
	private function getContext() {
		$title = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->getMock();

		$title->expects( $this->any() )
			->method( 'exists' )
			->will( $this->returnValue( true ) );

		$title->expects( $this->any() )
			->method( 'getNamespace' )
			->will( $this->returnValue( NS_MAIN ) );

		$title->expects( $this->any() )
			->method( 'getPrefixedText' )
			->will( $this->returnValue( 'Cat' ) );

		$context = new RequestContext();
		$context->setTitle( $title );

		$context->setLanguage( 'en' );

		return $context;
	}

}
