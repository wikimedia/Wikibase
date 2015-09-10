<?php

namespace Wikibase\Client\Tests\RecentChanges;

use ChangesList;
use DerivativeContext;
use Language;
use RecentChange;
use RequestContext;
use Title;
use User;
use Wikibase\Client\RecentChanges\ChangeLineFormatter;
use Wikibase\Client\RecentChanges\ExternalChangeFactory;
use Wikibase\Client\RecentChanges\ExternalRecentChange;
use Wikibase\Client\RepoLinker;

/**
 * @covers Wikibase\Client\RecentChanges\ChangeLineFormatter
 *
 * @group WikibaseClient
 * @group Wikibase
 * @group Database
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class ChangeLineFormatterTest extends \MediaWikiTestCase {

	protected $repoLinker;

	protected function setUp() {
		parent::setUp();

		// these are required because Linker is used in ChangeLineFormatter
		// @todo eliminate Linker or at least use of Linker in Wikibase :)
		$this->setMwGlobals( array(
			'wgLang' => Language::factory( 'en' ),
			'wgScriptPath' => '',
			'wgScript' => '/index.php',
			'wgArticlePath' => '/wiki/$1'
		) );

		$this->repoLinker = new RepoLinker(
			'http://www.wikidata.org',
			'/wiki/$1',
			'/w',
			array(
				'wikibase-item' => '',
				'wikibase-property' => 'Property'
			)
		);
	}

	/**
	 * @dataProvider formatProvider
	 */
	public function testFormat( array $expectedTags, array $patterns, RecentChange $recentChange ) {
		$context = $this->getTestContext();

		$changesList = ChangesList::newFromContext( $context );
		$changeFactory = new ExternalChangeFactory( 'testrepo', Language::factory( 'en' ) );
		$externalChange = $changeFactory->newFromRecentChange( $recentChange );

		$formatter = new ChangeLineFormatter(
			$changesList->getUser(),
			Language::factory( 'en' ),
			$this->repoLinker
		);

		$formattedLine = $formatter->format(
			$externalChange,
			$recentChange->getTitle(),
			$recentChange->counter,
			$changesList->recentChangesFlags( array( 'wikibase-edit' => true ), '' )
		);

		foreach ( $expectedTags as $key => $tag ) {
			$this->assertTag( $tag, $formattedLine, $key . "\n\t" . $formattedLine );
		}

		foreach ( $patterns as $pattern ) {
			$this->assertRegExp( $pattern, $formattedLine );
		}
	}

	private function getTestContext() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setLanguage( Language::factory( 'en' ) );
		$context->setUser( User::newFromId( 0 ) );

		return $context;
	}

	public function formatProvider() {
		return array(
			'edit-change' => array(
				$this->getEditSiteLinkChangeTagMatchers(),
				$this->getEditSiteLinkPatterns(),
				$this->getEditSiteLinkRecentChange()
			),
			'log-change' => array(
				$this->getLogChangeTagMatchers(),
				array(
					'/Log Change Comment/',
				),
				$this->getLogRecentChange()
			),
			'comment-override' => array(
				array(),
				array(
					'/\(Associated .*? item deleted\. Language links removed\.\)/'
				),
				$this->getEditSiteLinkRecentChange(
					array(
						'message' => 'wikibase-comment-remove',
					),
					null
				)
			),
		);
	}

	public function getEditSiteLinkPatterns() {
		return array(
			'/title=Q4&amp;curid=5&amp;action=history/',
			'/title=Q4&amp;curid=5&amp;diff=92&amp;oldid=90/',
			'/<span class="comment">\('
				. '‎<span dir="auto"><span class="autocomment">Changed claim: <\/span><\/span> '
				. '<a .*?>Property:P213<\/a>: <a .*?>Q850<\/a>'
				. '\)<\/span>/',
		);
	}

	public function getEditSiteLinkChangeTagMatchers() {
		return array(
			'edit-difflink' => array(
				'tag' => 'a',
				'content' => 'diff'
			),
			'edit-histlink' => array(
				'tag' => 'a',
				'content' => 'hist'
			),
			'edit-changeslist-separator' => array(
				'tag' => 'span',
				'attributes' => array(
					'class' => 'mw-changeslist-separator'
				),
				'content' => '. .'
			),
			'edit-change-flag' => array(
				'tag' => 'abbr',
				'attributes' => array(
					'class' => 'wikibase-edit'
				),
				'content' => 'D'
			),
			'edit-entitylink' => array(
				'tag' => 'a',
				'attributes' => array(
					'class' => 'plainlinks wb-entity-link',
					'href' => 'http://www.wikidata.org/wiki/Q4'
				),
				'content' => 'Q4'
			),
			'edit-changeslist-date' => array(
				'tag' => 'span',
				'attributes' => array(
					'class' => 'mw-changeslist-date'
				),
				'content' => '11:17'
			),
			'edit-userlink' => array(
				'tag' => 'a',
				'attributes' => array(
					'class' => 'plainlinks mw-userlink',
					'href' => 'http://www.wikidata.org/wiki/User:Cat'
				),
				'content' => 'Cat'
			),
			'edit-usertoollinks' => array(
				'tag' => 'span',
				'attributes' => array(
					'class' => 'mw-usertoollinks'
				)
			),
			'edit-usertalk' => array(
				'tag' => 'a',
				'attributes' => array(
					'class' => 'plainlinks',
					'href' => 'http://www.wikidata.org/wiki/User_talk:Cat'
				),
				'content' => 'Talk'
			),
			'edit-usercontribs' => array(
				'tag' => 'a',
				'attributes' => array(
					'class' => 'plainlinks',
					'href' => 'http://www.wikidata.org/wiki/Special:Contributions/Cat'
				),
				'content' => 'contribs'
			),
			'edit-comment' => array(
				'tag' => 'span',
				'attributes' => array(
					'class' => 'comment'
				)
			)
		);
	}

	protected function getEditSiteLinkRecentChange( $commentOverride = null, $compositeCommentOverride = null ) {
		$params = array(
			'wikibase-repo-change' => array(
				'id' => 4,
				'type' => 'wikibase-item~update',
				'time' => '20130819111741',
				'object_id' => 'q4',
				'user_id' => 1,
				'revision_id' => 92,
				'entity_type' => 'item',
				'user_text' => 'Cat',
				'bot' => 0,
				'page_id' => 5,
				'rev_id' => 92,
				'parent_id' => 90,
			)
		);

		if ( $commentOverride ) {
			$params['wikibase-repo-change']['comment'] = $commentOverride;
		}

		if ( $compositeCommentOverride ) {
			$params['wikibase-repo-change']['composite-comment'] = $compositeCommentOverride;
		}

		$title = $this->makeTitle( NS_MAIN, 'Canada', 52, 114 );
		return $this->makeRecentChange( $params, $title, '/* wbsetclaim-update:2||1 */ [[Property:P213]]: [[Q850]]' );
	}

	protected function getLogChangeTagMatchers() {
		return array(
			'delete-deletionlog' => array(
				'tag' => 'a',
				'attributes' => array(
					'class' => 'plainlinks',
					'href' => 'http://www.wikidata.org/wiki/Special:Log/delete'
				),
				'content' => 'Deletion log'
			),
			'delete-changeslist-separator' => array(
				'tag' => 'span',
				'attributes' => array(
					'class' => 'mw-changeslist-separator'
				),
				'content' => '. .'
			),
			'delete-change-flag' => array(
				'tag' => 'abbr',
				'attributes' => array(
					'class' => 'wikibase-edit'
				),
				'content' => 'D'
			),
			'delete-titlelink' => array(
				'tag' => 'a',
				'content' => 'Canada'
			),
			'delete-changeslist-date' => array(
				'tag' => 'span',
				'attributes' => array(
					'class' => 'mw-changeslist-date'
				),
				'content' => '15:18'
			),
			'delete-userlink' => array(
				'tag' => 'a',
				'attributes' => array(
					'class' => 'plainlinks mw-userlink',
					'href' => 'http://www.wikidata.org/wiki/User:Cat'
				),
				'content' => 'Cat'
			),
			'delete-usertoollinks' => array(
				'tag' => 'span',
				'attributes' => array(
					'class' => 'mw-usertoollinks'
				)
			),
			'delete-usertalk' => array(
				'tag' => 'a',
				'attributes' => array(
					'class' => 'plainlinks',
					'href' => 'http://www.wikidata.org/wiki/User_talk:Cat'
				),
				'content' => 'Talk'
			),
			'delete-contribs' => array(
				'tag' => 'a',
				'attributes' => array(
					'class' => 'plainlinks',
					'href' => 'http://www.wikidata.org/wiki/Special:Contributions/Cat'
				),
				'content' => 'contribs'
			),
			'delete-comment' => array(
				'tag' => 'span',
				'attributes' => array(
					'class' => 'comment'
				)
			)
		);
	}

	protected function getLogRecentChange() {
		$params = array(
			'wikibase-repo-change' => array(
				'id' => 20,
				'type' => 'wikibase-item~remove',
				'time' => '20130820151835',
				'object_id' => 'q20',
				'user_id' => 1,
				'revision_id' => 0,
				'entity_type' => 'item',
				'user_text' => 'Cat',
				'page_id' => 0,
				'rev_id' => 0,
				'parent_id' => 0,
			)
		);

		$title = $this->makeTitle( NS_MAIN, 'Canada', 12, 53 );
		return $this->makeRecentChange( $params, $title, 'Log Change Comment' );
	}

	/**
	 * @param int $ns
	 * @param string $text
	 * @param int $pageId
	 * @param int $currentRevision
	 *
	 * @return Title
	 */
	private function makeTitle( $ns, $text, $pageId, $currentRevision ) {
		$title = $this->getMock( 'Title' );

		$title->expects( $this->any() )
			->method( 'getNamespace' )
			->will( $this->returnValue( $ns ) );

		$title->expects( $this->any() )
			->method( 'getText' )
			->will( $this->returnValue( $text ) );

		$title->expects( $this->any() )
			->method( 'getDBKey' )
			->will( $this->returnValue( str_replace( ' ', '_', $text ) ) );

		$title->expects( $this->any() )
			->method( 'getArticleID' )
			->will( $this->returnValue( $pageId ) );

		$title->expects( $this->any() )
			->method( 'getLatestRevID' )
			->will( $this->returnValue( $currentRevision ) );

		$title->expects( $this->any() )
			->method( 'getLength' )
			->will( $this->returnValue( 1234 ) );

		return $title;
	}

	private function makeRecentChange( array $params, Title $title, $comment ) {
		$recentChange = new RecentChange();
		$recentChange->counter = 1;

		$externalChange = ExternalRecentChange::newFromAttribs( $params, $title );
		$attribs = $externalChange->getAttributes();

		$attribs['rc_comment'] = $comment;
		$recentChange->setAttribs( $attribs );

		return $recentChange;
	}

}
