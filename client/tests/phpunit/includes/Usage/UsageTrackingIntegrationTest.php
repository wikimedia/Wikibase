<?php

namespace Wikibase\Client\Tests\Usage;

use JobRunner;
use MediaWikiTestCase;
use Title;
use Wikibase\Client\Hooks\DataUpdateHookHandlers;
use Wikibase\Client\Hooks\UpdateRepoHookHandlers;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\WikibaseSettings;
use WikiPage;
use WikitextContent;

/**
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseUsageTracking
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class UsageTrackingIntegrationTest extends MediaWikiTestCase {

	/**
	 * @var Title
	 */
	private $articleTitle;

	/**
	 * @var Title
	 */
	private $templateTitle;

	/**
	 * @var bool
	 */
	private $oldAllowDataTransclusion;

	/**
	 * @var int[]
	 */
	private $oldEntityNamespaces;

	protected function setUp() {
		if ( !WikibaseSettings::isRepoEnabled() ) {
			$this->markTestSkipped( 'Integration test requires repo and client extension to be active on the same wiki.' );
		}

		parent::setUp();

		$wikibaseClient = WikibaseClient::getDefaultInstance();

		$settings = $wikibaseClient->getSettings();
		$this->oldAllowDataTransclusion = $settings->getSetting( 'allowDataTransclusion' );
		$this->oldEntityNamespaces = $wikibaseClient->getRepositoryDefinitions()->getEntityNamespaces();
		$settings->setSetting( 'allowDataTransclusion', true );
		$settings->setSetting( 'entityNamespaces', [ 'item' => 0 ] );

		$ns = $this->getDefaultWikitextNS();
		$this->articleTitle = Title::makeTitle( $ns, 'UsageTrackingIntegrationTest_Article' );
		$this->templateTitle = Title::makeTitle( NS_TEMPLATE, 'UsageTrackingIntegrationTest_Template' );

		// Register the necessary hook handlers. Registration of these handlers is normally skipped for unit test runs.
		$this->mergeMwGlobalArrayValue( 'wgHooks', [
			'ArticleDeleteComplete' => [
				DataUpdateHookHandlers::class . '::onArticleDeleteComplete',
				UpdateRepoHookHandlers::class . '::onArticleDeleteComplete',
			],
			'LinksUpdateComplete' => [
				DataUpdateHookHandlers::class . '::onLinksUpdateComplete',
			],
			'ParserCacheSaveComplete' => [
				DataUpdateHookHandlers::class . '::onParserCacheSaveComplete',
			],
			'TitleMoveComplete' => [
				UpdateRepoHookHandlers::class . '::onTitleMoveComplete',
			],
		] );
	}

	protected function tearDown() {
		parent::tearDown();

		WikibaseClient::getDefaultInstance()->getSettings()->setSetting(
			'allowDataTransclusion',
			$this->oldAllowDataTransclusion
		);
		WikibaseClient::getDefaultInstance()->getSettings()->setSetting(
			'entityNamespaces',
			$this->oldEntityNamespaces
		);
	}

	private function runJobs() {
		$runner = new JobRunner();

		$runner->run( [
			'type'     => 'refreshLinks',
			'maxJobs'  => false,
			'maxTime'  => false,
			'throttle' => false,
		] );
	}

	private function deletePage( Title $title ) {
		$page = WikiPage::factory( $title );
		$page->doDeleteArticle( 'TEST' );

		$this->runJobs();

		$title->resetArticleID( false );
	}

	private function updatePage( Title $title, $text ) {
		$content = new WikitextContent( $text );

		$flags = $title->exists() ? EDIT_UPDATE : EDIT_NEW;

		$page = WikiPage::factory( $title );
		$page->doEditContent( $content, 'TEST', $flags );

		$this->runJobs();

		$title->resetArticleID( false );
	}

	public function testUpdateUsageOnCreation() {
		// Create a new page that uses Q11.
		$text = "Just some text\n";
		$text .= "using a property: {{#property:doesNotExist|from=Q11}}\n";
		$this->updatePage( $this->articleTitle, $text );

		// Check that the usage of Q11 is tracked.
		$expected = [
			new EntityUsage( new ItemId( 'Q11' ), EntityUsage::OTHER_USAGE ),
		];

		$this->assertTrackedUsages( $expected, $this->articleTitle );
	}

	private function waitForNextTimestamp() {
		$timestamp = wfTimestampNow();

		do {
			usleep( 100 * 1000 );
		} while ( wfTimestampNow() === $timestamp );
	}

	/**
	 * @depends testUpdateUsageOnCreation
	 */
	public function testUpdateUsageOnEdit() {
		$this->waitForNextTimestamp(); // make sure we don't get the same timestamp as the edit before!

		// Create the template we'll use below.
		$text = "{{#property:doesNotExist|from=Q22}}\n";
		$this->updatePage( $this->templateTitle, $text );

		// Assume the state created by testUpdateUsageOnCreation().
		// Change page content to use the template instead of {{#property}} directly.
		$text = "Just some text\n";
		$text .= "using a template: {{" . $this->templateTitle->getPrefixedText() . "}}\n";
		$this->updatePage( $this->articleTitle, $text );

		// Check that Q22, used via the template, is now tracked.
		// Check that Q11 is no longer tracked, due to timestamp-based pruning.
		$expected = [
			new EntityUsage( new ItemId( 'Q22' ), EntityUsage::OTHER_USAGE ),
		];

		$this->assertTrackedUsages( $expected, $this->articleTitle );
	}

	/**
	 * @depends testUpdateUsageOnEdit
	 */
	public function testUpdateUsageOnTemplateChange() {
		$this->waitForNextTimestamp(); // Make sure we don't get the same timestamp as the edit before!

		// Assume the state created by testUpdateUsageOnEdit().
		// Change the template to use Q33.
		$text = "{{#property:doesNotExist|from=Q33}}\n";

		$this->updatePage( $this->templateTitle, $text );

		// Check that Q33, now used via the template, is tracked.
		// Check that Q22 is no longer tracked, due to timestamp-based pruning.
		$expected = [
			new EntityUsage( new ItemId( 'Q33' ), EntityUsage::OTHER_USAGE ),
		];

		$this->assertTrackedUsages( $expected, $this->articleTitle );
	}

	/**
	 * @depends testUpdateUsageOnTemplateChange
	 */
	public function testUpdateUsageOnDelete() {
		$this->waitForNextTimestamp(); // make sure we don't get the same timestamp as the edit before!

		// Assume the state created by testUpdateUsageOnTemplateChange().
		// Delete the page.
		$this->deletePage( $this->articleTitle );

		// Make sure tracking has been removed for all usages on the deleted page.
		$this->assertTrackedUsages( [], $this->articleTitle );
	}

	/**
	 * @param EntityUsage[] $expected
	 * @param Title $title
	 * @param string $msg
	 */
	private function assertTrackedUsages( array $expected, Title $title, $msg = '' ) {
		$lookup = WikibaseClient::getDefaultInstance()->getStore()->getUsageLookup();
		$actual = $lookup->getUsagesForPage( $title->getArticleID() );

		$expectedUsageStrings = $this->getUsageStrings( $expected );
		$actualUsageStrings = $this->getUsageStrings( $actual );

		$this->assertEquals( $expectedUsageStrings, $actualUsageStrings, $msg );
	}

	/**
	 * @param EntityUsage[] $usages
	 *
	 * @return string[]
	 */
	private function getUsageStrings( array $usages ) {
		$strings = array_map( function ( EntityUsage $usage ) {
			return $usage->getIdentityString();
		}, $usages );

		sort( $strings );
		return $strings;
	}

}
