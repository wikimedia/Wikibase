<?php

namespace Wikibase\Repo\Tests\Actions;

use ApiQueryInfo;
use MWException;
use Title;
use User;
use Wikibase\EditEntityAction;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\SubmitEntityAction;
use WikiPage;

/**
 * @covers \Wikibase\EditEntityAction
 * @covers \Wikibase\SubmitEntityAction
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 *
 * @group Action
 * @group Wikibase
 * @group WikibaseAction
 *
 * @group Database
 * @group medium
 */
class EditEntityActionTest extends ActionTestCase {

	protected function setUp() {
		parent::setUp();

		static $user = null;

		if ( !$user ) {
			$user = User::newFromId( 0 );
			$user->setName( '127.0.0.1' );
		}

		$this->setMwGlobals( 'wgUser', $user );

		// Remove handlers for the "OutputPageParserOutput" hook
		$this->mergeMwGlobalArrayValue( 'wgHooks', [ 'OutputPageParserOutput' => [] ] );

		$this->overrideMwServices();
	}

	public function testActionForPage() {
		$page = $this->getTestItemPage( 'Berlin' );

		$action = $this->createAction( 'edit', $page );
		$this->assertInstanceOf( EditEntityAction::class, $action );

		$action = $this->createAction( 'submit', $page );
		$this->assertInstanceOf( SubmitEntityAction::class, $action );
	}

	protected function adjustRevisionParam( $key, array &$params, WikiPage $page ) {
		if ( !isset( $params[$key] ) || ( is_int( $params[$key] ) && $params[$key] > 0 ) ) {
			return;
		}

		if ( is_array( $params[$key] ) ) {
			$page = $this->getTestItemPage( $params[$key][0] );
			$ofs = (int)$params[$key][1];

			$params[$key] = 0;
		} else {
			$ofs = (int)$params[$key];
		}

		$rev = $page->getRevision();

		if ( !$rev ) {
			return;
		}

		for ( $i = abs( $ofs ); $i > 0; $i -= 1 ) {
			$rev = $rev->getPrevious();
			if ( !$rev ) {
				throw new MWException( 'Page ' . $page->getTitle()->getPrefixedDBkey()
					. ' does not have ' . ( abs( $ofs ) + 1 ) . ' revisions' );
			}
		}

		$params[ $key ] = $rev->getId();
	}

	public function provideUndoForm() {
		// based upon well known test items defined in ActionTestCase::makeTestItemData

		$cases = [
			[ //0: edit, no parameters
				'edit', // action
				'Berlin', // handle
				[], // params
				false, // post
				null, // user
				'/id="[^"]*\bwb-item\b[^"]*"/', // htmlPattern: should show an item
			],

			[ //1: submit, no parameters
				'submit', // action
				'Berlin', // handle
				[], // params
				false, // post
				null, // user
				'/id="[^"]*\bwb-item\b[^"]*"/', // htmlPattern: should show an item
			],

			// -- show undo form -----------------------------------
			[ //2: // undo form with legal undo
				'edit', // action
				'Berlin', // handle
				[ // params
					'undo' => 0, // current revision
				],
				false, // post
				null, // user
				'/undo-success/', // htmlPattern: should be a success
			],

			[ //3: // undo form with legal undo and undoafter
				'edit', // action
				'Berlin', // handle
				[ // params
					'undo' => 0, // current revision
					'undoafter' => -1, // previous revision
				],
				false, // post
				null, // user
				'/undo-success/', // htmlPattern: should be a success
			],

			[ //4: // undo form with illegal undo == undoafter
				'edit', // action
				'Berlin', // handle
				[ // params
					'undo' => -1, // previous revision
					'undoafter' => -1, // previous revision
				],
				false, // post
				null, // user
				'/wikibase-undo-samerev/', // htmlPattern: should contain error
			],

			[ //5: // undo form with legal undoafter
				'edit', // action
				'Berlin', // handle
				[ // params
					'undoafter' => -1, // previous revision
				],
				false, // post
				null, // user
				'/undo-success/', // htmlPattern: should be a success
			],

			[ //6: // undo form with illegal undo
				'edit', // action
				'Berlin', // handle
				[ // params
					'undo' => -2, // first revision
				],
				false, // post
				null, // user
				'/wikibase-undo-firstrev/', // htmlPattern: should contain error
			],

			[ //7: // undo form with illegal undoafter
				'edit', // action
				'Berlin', // handle
				[ // params
					'undoafter' => 0, // current revision
				],
				false, // post
				null, // user
				'/wikibase-undo-samerev/', // htmlPattern: should contain error
			],

			// -- show restore form -----------------------------------
			[ //8: // restore form with legal restore
				'edit', // action
				'Berlin', // handle
				[ // params
					'restore' => -1, // previous revision
				],
				false, // post
				null, // user
				'/class="diff/', // htmlPattern: should be a success and contain a diff (undo-success is not shown for restore)
			],

			[ //9: // restore form with illegal restore
				'edit', // action
				'Berlin', // handle
				[ // params
					'restore' => 0, // current revision
				],
				false, // post
				null, // user
				'/wikibase-undo-samerev/', // htmlPattern: should contain error
			],

			// -- bad revision -----------------------------------
			[ //10: // undo bad revision
				'edit', // action
				'Berlin', // handle
				[ // params
					'undo' => 12345678, // bad revision
				],
				false, // post
				null, // user
				'/undo-norev/', // htmlPattern: should contain error
			],

			[ //11: // undoafter bad revision with good undo
				'edit', // action
				'Berlin', // handle
				[ // params
					'undo' => 0, // current revision
					'undoafter' => 12345678, // bad revision
				],
				false, // post
				null, // user
				'/undo-norev/', // htmlPattern: should contain error
			],

			[ //12: // undoafter bad revision
				'edit', // action
				'Berlin', // handle
				[ // params
					'undoafter' => 12345678, // bad revision
				],
				false, // post
				null, // user
				'/undo-norev/', // htmlPattern: should contain error
			],

			[ //13: // restore bad revision
				'edit', // action
				'Berlin', // handle
				[ // params
					'restore' => 12345678, // bad revision
				],
				false, // post
				null, // user
				'/undo-norev/', // htmlPattern: should contain error
			],

			// -- bad page -----------------------------------
			[ //14: // non-existing page
				'edit', // action
				Title::newFromText( 'XXX', $this->getItemNamespace() ),
				[ // params
					'restore' => [ 'London', 0 ], // ok revision
				],
				false, // post
				null, // user
				'/missing-article/', // htmlPattern: should contain error
			],

			[ //15: // undo revision from different pages
				'edit', // action class
				'Berlin', // handle
				[ // params
					'undo' => [ 'London', 0 ], // wrong page
				],
				false, // post
				null, // user
				'/wikibase-undo-badpage/', // htmlPattern: should contain error
			],

			[ //16: // undoafter revision from different pages
				'edit', // action class
				'Berlin', // handle
				[ // params
					'undoafter' => [ 'London', -1 ], // wrong page
				],
				false, // post
				null, // user
				'/wikibase-undo-badpage/', // htmlPattern: should contain error
			],

			[ //17: // restore revision from different pages
				'edit', // action class
				'Berlin', // handle
				[ // params
					'restore' => [ 'London', -1 ], // wrong page
				],
				false, // post
				null, // user
				'/wikibase-undo-badpage/', // htmlPattern: should contain error
			],

		];

		// -- show undo form for redirect -----------------------------------
		$cases[] = [ //18: // undo form with legal undo
			'edit', // action
			'Berlin2', // handle
			[ // params
				'undo' => 0, // current revision
			],
			false, // post
			null, // user
			'/undo-success/', // htmlPattern: should be a success
		];

		return $cases;
	}

	/**
	 * @dataProvider provideUndoForm
	 */
	public function testUndoForm(
		$action,
		$page,
		array $params,
		$post = false,
		User $user = null,
		$htmlPattern = null,
		array $expectedProps = null
	) {
		$this->tryUndoAction( $action, $page, $params, $post, $user, $htmlPattern, $expectedProps );
	}

	public function provideUndoSubmit() {
		// based upon well known test items defined in ActionTestCase::makeTestItemData
		return [
			[ //0: submit with legal undo, but don't post
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undo' => 0, // current revision
				],
				false, // post
				null, // user
				null, // htmlPattern
				[
					'redirect' => '/[&?]action=edit&undo=\d+/', // redirect to undo form
				]
			],

			[ //1: submit with legal undo, but omit wpSave
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpEditToken' => true, // automatic token
					'undo' => 0, // current revision
				],
				true, // post
				null, // user
				null, // htmlPattern
				[
					'redirect' => '/[&?]action=edit&undo=\d+/', // redirect to undo form
				]
			],

			// -- show undo form -----------------------------------
			[ //2: // undo form with legal undo
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undo' => 0, // current revision
				],
				true, // post
				null, // user
				null, // htmlPattern
				[
					'redirect' => '![:/=]Q\d+$!' // expect success and redirect to page
				],
			],

			[ //3: // undo form with legal undo and undoafter
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undo' => 0, // current revision
					'undoafter' => -1, // previous revision
				],
				true, // post
				null, // user
				null, // htmlPattern
				[
					'redirect' => '![:/=]Q\d+$!' // expect success and redirect to page
				],
			],

			[ //4: // undo form with illegal undo == undoafter
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undo' => -1, // previous revision
					'undoafter' => -1, // previous revision
				],
				true, // post
				null, // user
				'/wikibase-undo-samerev/', // htmlPattern: should contain error
			],

			[ //5: // undo form with legal undoafter
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undoafter' => -1, // previous revision
				],
				true, // post
				null, // user
				null, // htmlPattern
				[
					'redirect' => '![:/=]Q\d+$!' // expect success and redirect to page
				],
			],

			[ //6: // undo form with illegal undo
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undo' => -2, // first revision
				],
				true, // post
				null, // user
				'/wikibase-undo-firstrev/', // htmlPattern: should contain error
			],

			[ //7: // undo form with illegal undoafter
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undoafter' => 0, // current revision
				],
				true, // post
				null, // user
				'/wikibase-undo-samerev/', // htmlPattern: should contain error
			],

			// -- show restore form -----------------------------------
			[ //8: // restore form with legal restore
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'restore' => -1, // previous revision
				],
				true, // post
				null, // user
				null, // htmlPattern
				[
					'redirect' => '![:/=]Q\d+$!' // expect success and redirect to page
				],
			],

			[ //9: // restore form with illegal restore
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'restore' => 0, // current revision
				],
				true, // post
				null, // user
				'/wikibase-undo-samerev/', // htmlPattern: should contain error
			],

			// -- bad revision -----------------------------------
			[ //10: // undo bad revision
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undo' => 12345678, // bad revision
				],
				true, // post
				null, // user
				'/undo-norev/', // htmlPattern: should contain error
			],

			[ //11: // undoafter bad revision with good undo
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undo' => 0, // current revision
					'undoafter' => 12345678, // bad revision
				],
				true, // post
				null, // user
				'/undo-norev/', // htmlPattern: should contain error
			],

			[ //12: // undoafter bad revision
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undoafter' => 12345678, // bad revision
				],
				true, // post
				null, // user
				'/undo-norev/', // htmlPattern: should contain error
			],

			[ //13: // restore bad revision
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'restore' => 12345678, // bad revision
				],
				true, // post
				null, // user
				'/undo-norev/', // htmlPattern: should contain error
			],

			// -- bad page -----------------------------------
			[ //14: // non-existing page
				'submit', // action
				Title::newFromText( 'XXX', $this->getItemNamespace() ),
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'restore' => [ 'London', 0 ], // ok revision
				],
				true, // post
				null, // user
				'/missing-article/', // htmlPattern: should contain error
			],

			[ //15: // undo revision from different pages
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undo' => [ 'London', 0 ], // wrong page
				],
				true, // post
				null, // user
				'/wikibase-undo-badpage/', // htmlPattern: should contain error
			],

			[ //16: // undoafter revision from different pages
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'undoafter' => [ 'London', -1 ], // wrong page
				],
				true, // post
				null, // user
				'/wikibase-undo-badpage/', // htmlPattern: should contain error
			],

			[ //17: // restore revision from different pages
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // automatic token
					'restore' => [ 'London', -1 ], // wrong page
				],
				true, // post
				null, // user
				'/wikibase-undo-badpage/', // htmlPattern: should contain error
			],

			// -- bad token -----------------------------------
			[ //18: submit with legal undo, but wrong token
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => 'xyz', // bad token
					'undo' => 0, // current revision
				],
				true, // post
				null, // user
				'/token_suffix_mismatch/', // htmlPattern: should contain error
			],

			// -- incomplete form -----------------------------------
			[ //19: submit without undo/undoafter/restore
				'submit', // action
				'Berlin', // handle
				[ // params
					'wpSave' => 1,
					'wpEditToken' => true, // bad token
				],
				true, // post
				null, // user
				'/id="[^"]*\bwb-item\b[^"]*"/', // htmlPattern: should show item
			],

		];
	}

	/**
	 * @dataProvider provideUndoSubmit
	 */
	public function testUndoSubmit(
		$action,
		$page,
		array $params,
		$post = false,
		User $user = null,
		$htmlPattern = null,
		array $expectedProps = null
	) {
		if ( is_string( $page ) ) {
			self::resetTestItem( $page );
		}

		$this->tryUndoAction( $action, $page, $params, $post, $user, $htmlPattern, $expectedProps );

		if ( is_string( $page ) ) {
			self::resetTestItem( $page );
		}
	}

	/**
	 * @param string $action
	 * @param WikiPage|Title|string $page
	 * @param array $params
	 * @param bool $post
	 * @param User|null $user
	 * @param string|bool|null $htmlPattern
	 * @param string[]|null $expectedProps
	 */
	protected function tryUndoAction(
		$action,
		$page,
		array $params,
		$post = false,
		User $user = null,
		$htmlPattern = null,
		array $expectedProps = null
	) {
		if ( $user ) {
			$this->setUser( $user );
		}

		if ( is_string( $page ) ) {
			$page = $this->getTestItemPage( $page );
		} elseif ( $page instanceof Title ) {
			$page = WikiPage::factory( $page );
		}

		$this->adjustRevisionParam( 'undo', $params, $page );
		$this->adjustRevisionParam( 'undoafter', $params, $page );
		$this->adjustRevisionParam( 'restore', $params, $page );

		if ( isset( $params['wpEditToken'] ) && $params['wpEditToken'] === true ) {
			$params['wpEditToken'] = $this->getEditToken(); //TODO: $user
		}

		$out = $this->callAction( $action, $page, $params, $post );

		if ( $htmlPattern !== null && $htmlPattern !== false ) {
			$this->assertRegExp( $htmlPattern, $out->getHTML() );
		}

		if ( $expectedProps ) {
			foreach ( $expectedProps as $p => $pattern ) {
				$func = 'get' . ucfirst( $p );
				$act = call_user_func( [ $out, $func ] );

				if ( $pattern === true ) {
					$this->assertNotEmpty( $act, $p );
				} elseif ( $pattern === false ) {
					$this->assertEmpty( $act, $p );
				} else {
					$this->assertRegExp( $pattern, $act, $p );
				}
			}
		}
	}

	public function provideUndoRevisions() {
		// based upon well known test items defined in ActionTestCase::makeTestItemData

		return [
			[ //0: undo last revision
				'Berlin', //handle
				[
					'undo' => 0, // last revision
				],
				[ //expected
					'descriptions' => [
						'de' => 'Stadt in Brandenburg',
						'en' => 'City in Germany',
					],
				],
			],

			[ //1: undo previous revision
				'Berlin', //handle
				[
					'undo' => -1, // previous revision
				],
				[ //expected
					'descriptions' => [
						'de' => 'Hauptstadt von Deutschland',
					],
				]
			],

			[ //2: undo last and previous revision
				'Berlin', //handle
				[
					'undo' => 0, // current revision
					'undoafter' => -2, // first revision
				],
				[ //expected
					'descriptions' => [
						'de' => 'Stadt in Deutschland',
					],
				]
			],

			[ //3: undoafter first revision (conflict, no change)
				'Berlin', //handle
				[
					'undoafter' => -2, // first revision
				],
				[ //expected
					'descriptions' => [
						'de' => 'Stadt in Deutschland',
					],
				]
			],

			[ //4: restore previous revision
				'Berlin', //handle
				[
					'restore' => -1, // previous revision
				],
				[ //expected
					'descriptions' => [
						'de' => 'Stadt in Brandenburg',
						'en' => 'City in Germany',
					],
				]
			],

			[ //5: restore first revision
				'Berlin', //handle
				[
					'restore' => -2, // first revision
				],
				[ //expected
					'descriptions' => [
						'de' => 'Stadt in Deutschland',
					],
				]
			],
		];
	}

	/**
	 * @dataProvider provideUndoRevisions
	 */
	public function testUndoRevisions( $handle, array $params, array $expected ) {
		self::resetTestItem( $handle );

		$page = $this->getTestItemPage( $handle );

		$this->adjustRevisionParam( 'undo', $params, $page );
		$this->adjustRevisionParam( 'undoafter', $params, $page );
		$this->adjustRevisionParam( 'restore', $params, $page );

		if ( !isset( $params['wpEditToken'] ) ) {
			$params['wpEditToken'] = $this->getEditToken();
		}

		if ( !isset( $params['wpSave'] ) ) {
			$params['wpSave'] = 1;
		}

		$out = $this->callAction( 'submit', $page, $params, true );

		$this->assertRegExp( '![:/=]Q\d+$!', $out->getRedirect(), 'successful operation should return a redirect' );

		$item = $this->loadTestItem( $handle );

		if ( isset( $expected['labels'] ) ) {
			$this->assertArrayEquals( $expected['labels'], $item->getLabels()->toTextArray(), false, true );
		}

		if ( isset( $expected['descriptions'] ) ) {
			$this->assertArrayEquals( $expected['descriptions'], $item->getDescriptions()->toTextArray(), false, true );
		}

		if ( isset( $expected['aliases'] ) ) {
			$this->assertArrayEquals( $expected['aliases'], $item->getAliasGroups()->toTextArray(), false, true );
		}

		if ( isset( $expected['sitelinks'] ) ) {
			$actual = [];

			foreach ( $item->getSiteLinkList()->toArray() as $siteLink ) {
				$actual[$siteLink->getSiteId()] = $siteLink->getPageName();
			}

			$this->assertArrayEquals( $expected['sitelinks'], $actual, false, true );
		}

		self::resetTestItem( $handle );
	}

	public function provideUndoPermissions() {
		return [
			[ //0
				'edit',
				[
					'*' => [ 'edit' => false ],
					'user' => [ 'edit' => false ],
				],
				'/permissions-errors/'
			],

			[ //1
				'submit',
				[
					'*' => [ 'edit' => false ],
					'user' => [ 'edit' => false ],
				],
				'/permissions-errors/'
			],
		];
	}

	/**
	 * @dataProvider provideUndoPermissions
	 */
	public function testUndoPermissions( $action, array $permissions, $error ) {
		$handle = 'London';

		self::resetTestItem( $handle );

		$this->applyPermissions( $permissions );

		$page = $this->getTestItemPage( $handle );

		$params = [
			'wpEditToken' => $this->getEditToken(),
			'wpSave' => 1,
			'undo' => $page->getLatest(),
		];

		$out = $this->callAction( $action, $page, $params, true );

		if ( $error ) {
			$this->assertRegExp( $error, $out->getHTML() );

			$this->assertEmpty( $out->getRedirect(), 'operation should not trigger a redirect' );
		} else {
			$this->assertRegExp( '![:/=]Q\d+$!', $out->getRedirect(), 'successful operation should return a redirect' );
		}

		self::resetTestItem( $handle );
	}

	/**
	 * @return int
	 */
	private function getItemNamespace() {
		 $entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();
		 return $entityNamespaceLookup->getEntityNamespace( 'item' );
	}

	/**
	 * Changes wgUser and resets any associated state
	 *
	 * @param User $user the desired user
	 */
	private function setUser( User $user ) {
		global $wgUser;

		if ( $user->getName() !== $wgUser->getName() ) {
			$wgUser = $user;
			ApiQueryInfo::resetTokenCache();
		}
	}

	/**
	 * @param array[] $permissions
	 */
	private function applyPermissions( array $permissions ) {
		global $wgGroupPermissions,
			$wgUser;

		if ( $permissions === [] ) {
			return;
		}

		foreach ( $permissions as $group => $rights ) {
			if ( !isset( $wgGroupPermissions[ $group ] ) ) {
				$wgGroupPermissions[ $group ] = [];
			}

			$wgGroupPermissions[ $group ] = array_merge( $wgGroupPermissions[ $group ], $rights );
		}

		// reset rights cache
		$wgUser->clearInstanceCache();
		$this->overrideMwServices();
	}

	/**
	 * @return string
	 */
	private function getEditToken() {
		global $wgUser;

		return $wgUser->getEditToken();
	}

}
