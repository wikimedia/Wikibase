<?php
namespace Wikibase\Test;

use User;
use WikiPage;
use Page;
use Title;

use \Wikibase\Item;
use \Wikibase\ItemContent;

/**
 * Tests for blocking of direct editing.
 *
 * @file
 * @since 0.1
 *
 * @ingroup Wikibase
 * @ingroup Test
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 *
 * @group Action
 * @group Wikibase
 * @group WikibaseAction
 *
 * The database group has as a side effect that temporal database tables are created. This makes
 * it possible to test without poisoning a production database.
 * @group Database
 *
 * Some of the tests takes more time, and needs therefor longer time before they can be aborted
 * as non-functional. The reason why tests are aborted is assumed to be set up of temporal databases
 * that hold the first tests in a pending state awaiting access to the database.
 * @group medium
 */
class EditEntityActionTest extends ActionTestCase {

	public function testActionForPage() {
		$page = $this->getTestItemPage( "Berlin" );

		$action = $this->createAction( "edit", $page );

		$this->assertInstanceOf( 'Wikibase\EditEntityAction', $action );
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

		for ( $i = abs($ofs); $i > 0; $i -= 1 ) {
			$rev = $rev->getPrevious();
			if ( !$rev ) {
				throw new \MWException( "Page " . $page->getTitle()->getPrefixedDBkey() . " does not have " . ( abs($ofs) +1 ) . " revisions" );
			}
		}

		$params[ $key ] = $rev->getId();
	}

	public function provideUndoForm() {
		return array(
			array( #0: edit, no parameters
				'edit',   # action
				'Berlin', # handle
				array(),  # params
				false,    # post
				null,     # user
				'/class="wb-property-container"/',     # htmlPattern: should show an item
			),

			array( #1: submit, no parameters
				'submit', # action
				'Berlin', # handle
				array(),  # params
				false,    # post
				null,     # user
				'/class="wb-property-container"/',     # htmlPattern: should show an item
			),

			// -- show undo form -----------------------------------
			array( #2: # undo form with legal undo
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'undo' => 0, # current revision
				),
				false,    # post
				null,     # user
				'/undo-success/', # htmlPattern: should be a success
			),

			array( #3: # undo form with legal undo and undoafter
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'undo' => 0, # current revision
					'undoafter' => -1, # previous revision
				),
				false,    # post
				null,     # user
				'/undo-success/', # htmlPattern: should be a success
			),

			array( #4: # undo form with illegal undo == undoafter
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'undo' => -1, # previous revision
					'undoafter' => -1, # previous revision
				),
				false,    # post
				null,     # user
				'/wikibase-undo-samerev/', # htmlPattern: should contain error
			),

			array( #5: # undo form with legal undoafter
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'undoafter' => -1, # previous revision
				),
				false,    # post
				null,     # user
				'/undo-success/', # htmlPattern: should be a success
			),

			array( #6: # undo form with illegal undo
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'undo' => -2, # first revision
				),
				false,    # post
				null,     # user
				'/wikibase-undo-firstrev/', # htmlPattern: should contain error
			),

			array( #7: # undo form with illegal undoafter
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'undoafter' => 0, # current revision
				),
				false,    # post
				null,     # user
				'/wikibase-undo-samerev/', # htmlPattern: should contain error
			),

			// -- show restore form -----------------------------------
			array( #8: # restore form with legal restore
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'restore' => -1, # previous revision
				),
				false,    # post
				null,     # user
				'/class="diff/', # htmlPattern: should be a success and contain a diff (undo-success is not shown for restore)
			),

			array( #9: # restore form with illegal restore
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'restore' => 0, # current revision
				),
				false,    # post
				null,     # user
				'/wikibase-undo-samerev/', # htmlPattern: should contain error
			),

			// -- bad revision -----------------------------------
			array( #10: # undo bad revision
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'undo' => 12345678, # bad revision
				),
				false,    # post
				null,     # user
				'/undo-norev/', # htmlPattern: should contain error
			),

			array( #11: # undoafter bad revision with good undo
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'undo' => 0, # current revision
					'undoafter' => 12345678, # bad revision
				),
				false,    # post
				null,     # user
				'/undo-norev/', # htmlPattern: should contain error
			),

			array( #12: # undoafter bad revision
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'undoafter' => 12345678, # bad revision
				),
				false,    # post
				null,     # user
				'/undo-norev/', # htmlPattern: should contain error
			),

			array( #13: # restore bad revision
				'edit',   # action
				'Berlin', # handle
				array(    # params
					'restore' => 12345678, # bad revision
				),
				false,    # post
				null,     # user
				'/undo-norev/', # htmlPattern: should contain error
			),

			// -- bad page -----------------------------------
			array( #14: # non-existing page
				'edit',   # action
				Title::newFromText( "XXX", WB_NS_DATA ), # non-existing page
				array(    # params
					'restore' => array( "London", 0 ), # ok revision
				),
				false,    # post
				null,     # user
				'/missing-article/', # htmlPattern: should contain error
			),

			array( #15: # undo revision from different pages
				'edit',   # action class
				"Berlin", # handle
				array(    # params
					'undo' => array( "London", 0 ), # wrong page
				),
				false,    # post
				null,     # user
				'/wikibase-undo-badpage/', # htmlPattern: should contain error
			),

			array( #16: # undoafter revision from different pages
				'edit',   # action class
				"Berlin", # handle
				array(    # params
					'undoafter' => array( "London", -1 ), # wrong page
				),
				false,    # post
				null,     # user
				'/wikibase-undo-badpage/', # htmlPattern: should contain error
			),

			array( #17: # restore revision from different pages
				'edit',   # action class
				"Berlin", # handle
				array(    # params
					'restore' => array( "London", -1 ), # wrong page
				),
				false,    # post
				null,     # user
				'/wikibase-undo-badpage/', # htmlPattern: should contain error
			),

		);
	}

	//TODO: test permission errors (edit and submit)
	//TODO: check actual revert
	//TODO: check conflict detection, empty diff

	/**
	 * @dataProvider provideUndoForm
	 */
	 public function testUndoForm( $action, $page, array $params, $post = false, User $user = null, $htmlPattern = null, $expectedProps = null ) {
		$this->tryUndoAction( $action, $page, $params, $post, $user, $htmlPattern, $expectedProps );
	}

	public function provideUndoSubmit() {
		return array(
			array( #0: submit with legal undo, but don't post
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undo' => 0,     # current revision
				),
				false,    # post
				null,     # user
				null,     # htmlPattern
				array(
					'redirect' => '/[&?]action=edit&undo=\d+/', # redirect to undo form
				)
			),

			array( #1: submit with legal undo, but omit wpSave
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpEditToken' => true, # automatic token
					'undo' => 0,     # current revision
				),
				true,    # post
				null,     # user
				null,     # htmlPattern
				array(
					'redirect' => '/[&?]action=edit&undo=\d+/', # redirect to undo form
				)
			),

			// -- show undo form -----------------------------------
			array( #2: # undo form with legal undo
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undo' => 0,     # current revision
				),
				true,    # post
				null,    # user
				null,    # htmlPattern
				array(
					'redirect' => true # expect success and redirect
				),
			),

			array( #3: # undo form with legal undo and undoafter
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undo' => 0, # current revision
					'undoafter' => -1, # previous revision
				),
				true,    # post
				null,    # user
				null,    # htmlPattern
				array(
					'redirect' => true # expect success and redirect
				),
			),

			array( #4: # undo form with illegal undo == undoafter
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undo' => -1, # previous revision
					'undoafter' => -1, # previous revision
				),
				true,    # post
				null,     # user
				'/wikibase-undo-samerev/', # htmlPattern: should contain error
			),

			array( #5: # undo form with legal undoafter
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undoafter' => -1, # previous revision
				),
				true,    # post
				null,     # user
				null,    # htmlPattern
				array(
					'redirect' => true # expect success and redirect
				),
			),

			array( #6: # undo form with illegal undo
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undo' => -2, # first revision
				),
				true,    # post
				null,     # user
				'/wikibase-undo-firstrev/', # htmlPattern: should contain error
			),

			array( #7: # undo form with illegal undoafter
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undoafter' => 0, # current revision
				),
				true,    # post
				null,     # user
				'/wikibase-undo-samerev/', # htmlPattern: should contain error
			),

			// -- show restore form -----------------------------------
			array( #8: # restore form with legal restore
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'restore' => -1, # previous revision
				),
				true,    # post
				null,     # user
				null,    # htmlPattern
				array(
					'redirect' => true # expect success and redirect
				),
			),

			array( #9: # restore form with illegal restore
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'restore' => 0, # current revision
				),
				true,    # post
				null,     # user
				'/wikibase-undo-samerev/', # htmlPattern: should contain error
			),

			// -- bad revision -----------------------------------
			array( #10: # undo bad revision
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undo' => 12345678, # bad revision
				),
				true,    # post
				null,     # user
				'/undo-norev/', # htmlPattern: should contain error
			),

			array( #11: # undoafter bad revision with good undo
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undo' => 0, # current revision
					'undoafter' => 12345678, # bad revision
				),
				true,    # post
				null,     # user
				'/undo-norev/', # htmlPattern: should contain error
			),

			array( #12: # undoafter bad revision
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undoafter' => 12345678, # bad revision
				),
				true,    # post
				null,     # user
				'/undo-norev/', # htmlPattern: should contain error
			),

			array( #13: # restore bad revision
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'restore' => 12345678, # bad revision
				),
				true,    # post
				null,     # user
				'/undo-norev/', # htmlPattern: should contain error
			),

			// -- bad page -----------------------------------
			array( #14: # non-existing page
				'submit', # action
				Title::newFromText( "XXX", WB_NS_DATA ), # non-existing page
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'restore' => array( "London", 0 ), # ok revision
				),
				true,    # post
				null,     # user
				'/missing-article/', # htmlPattern: should contain error
			),

			array( #15: # undo revision from different pages
				'submit', # action
				"Berlin", # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undo' => array( "London", 0 ), # wrong page
				),
				true,    # post
				null,     # user
				'/wikibase-undo-badpage/', # htmlPattern: should contain error
			),

			array( #16: # undoafter revision from different pages
				'submit', # action
				"Berlin", # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'undoafter' => array( "London", -1 ), # wrong page
				),
				true,    # post
				null,     # user
				'/wikibase-undo-badpage/', # htmlPattern: should contain error
			),

			array( #17: # restore revision from different pages
				'submit', # action
				"Berlin", # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # automatic token
					'restore' => array( "London", -1 ), # wrong page
				),
				true,    # post
				null,     # user
				'/wikibase-undo-badpage/', # htmlPattern: should contain error
			),

			// -- bad token -----------------------------------
			array( #18: submit with legal undo, but wrong token
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => 'xyz', # bad token
					'undo' => 0,      # current revision
				),
				true,    # post
				null,     # user
				'/session_fail_preview/',     # htmlPattern: should contain error
			),

			array( #19: submit with illegal undoafter, but wrong token
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => 'xyz',  # bad token
					'undoafter' => 0,  # current revision
				),
				true,    # post
				null,     # user
				'/session_fail_preview/',     # htmlPattern: should contain error
			),

			// -- incomplete form -----------------------------------
			array( #20: submit without undo/undoafter/restore
				'submit', # action
				'Berlin', # handle
				array(    # params
					'wpSave' => 1,
					'wpEditToken' => true, # bad token
				),
				true,    # post
				null,     # user
				'/class="wb-property-container"/',     # htmlPattern: should show item
			),

		);
	}


	/**
	 * @dataProvider provideUndoSubmit
	 */
	public function testUndoSubmit( $action, $page, array $params, $post = false, User $user = null, $htmlPattern = null, $expectedProps = null ) {
		$this->tryUndoAction( $action, $page, $params, $post, $user, $htmlPattern, $expectedProps );

		if ( is_string( $page ) ) {
			self::resetTestItem( $page );
		}
	}

	protected function tryUndoAction( $action, $page, array $params, $post = false, User $user = null, $htmlPattern = null, $expectedProps = null ) {
		if ( $user ) {
			$this->setUser( $user );
		}

		if ( is_string( $page ) ) {
			$page = $this->getTestItemPage( $page );
		} else if ( $page instanceof Title ) {
			$page = WikiPage::factory( $page );
		}

		$this->adjustRevisionParam( 'undo', $params, $page );
		$this->adjustRevisionParam( 'undoafter', $params, $page );
		$this->adjustRevisionParam( 'restore', $params, $page );

		if ( isset( $params['wpEditToken'] ) && $params['wpEditToken'] === true ) {
			$params['wpEditToken'] = $this->getToken( $page->getTitle(), 'edit' ); //TODO: $user
		}

		$out = $this->callAction( $action, $page, $params, $post );

		if ( $htmlPattern !== null && $htmlPattern !== false ) {
			$this->assertRegExp( $htmlPattern, $out->getHTML() );
		}

		if ( $expectedProps ) {
			foreach ( $expectedProps as $p => $pattern ) {
				$func = 'get' . ucfirst( $p );
				$act = call_user_func( array( $out, $func ) );

				if ( $pattern === true ) {
					$this->assertNotEmpty( $act, $p );
				} else if ( $pattern === false ) {
					$this->assertEmpty( $act, $p );
				} else {
					$this->assertRegExp( $pattern, $act, $p );
				}
			}
		}
	}

}