<?php

namespace Wikibase\Test;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpChange;
use Exception;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Services\Diff\ItemDiff;
use Wikibase\EntityChange;
use Wikibase\ItemChange;

/**
 * @covers Wikibase\ItemChange
 * @covers Wikibase\DiffChange
 *
 * @since 0.3
 *
 * @group Database
 * @group Wikibase
 * @group WikibaseLib
 * @group WikibaseChange
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class ItemChangeTest extends EntityChangeTest {

	/**
	 * @since 0.4
	 * @return string
	 */
	protected function getRowClass() {
		return 'Wikibase\ItemChange';
	}

	public function entityProvider() {
		$entities = array_filter(
			TestChanges::getEntities(),
			function( EntityDocument $entity ) {
				return ( $entity instanceof Item );
			}
		);

		$cases = array_map(
			function( Item $item ) {
				return array( $item );
			},
			$entities
		);

		return $cases;
	}

	public function itemChangeProvider() {
		$changes = array_filter(
			TestChanges::getChanges(),
			function( EntityChange $change ) {
				return ( $change instanceof ItemChange );
			}
		);

		$cases = array_map( function( ItemChange $change ) {
			return array( $change );
		},
		$changes );

		return $cases;
	}

	/**
	 * @dataProvider changeProvider
	 *
	 * @param ItemChange $change
	 */
	public function testGetSiteLinkDiff( ItemChange $change ) {
		$siteLinkDiff = $change->getSiteLinkDiff();
		$this->assertInstanceOf( 'Diff\Diff', $siteLinkDiff,
			"getSiteLinkDiff must return a Diff" );
	}

	public function changeBackwardsCompatProvider() {
		global $wgDevelopmentWarnings;

		//NOTE: Disable developer warnings that may get triggered by
		//      the B/C code path.
		$wgDevelopmentWarnings = false;
		\MediaWiki\suppressWarnings();

		try {
			$cases = array();

			// --------
			// We may hit a plain diff generated by old code.
			// Make sure we can deal with that.

			$diff = new Diff();

			$change = new ItemChange( array( 'type' => 'test' ) );
			$change->setDiff( $diff );

			$cases['plain-diff'] = array( $change );

			// --------
			// Bug T53363: As of commit ff65735a125e, MapDiffer may generate atomic diffs for
			// substructures even in recursive mode. Make sure we can handle them
			// if we happen to load them from the database or such.

			$diff = new ItemDiff( array(
				'links' => new DiffOpChange(
					array( 'foowiki' => 'X', 'barwiki' => 'Y' ),
					array( 'barwiki' => 'Y', 'foowiki' => 'X' )
				)
			) );

			// make sure we got the right key for sitelinks
			assert( $diff->getSiteLinkDiff() !== null );

			//NOTE: ItemChange's constructor may or may not already fix the bad diff.
			$change = new ItemChange( array( 'type' => 'test' ) );
			$change->setDiff( $diff );

			$cases['atomic-sitelink-diff'] = array( $change );

			$wgDevelopmentWarnings = true;
			\MediaWiki\restoreWarnings();

			return $cases;
		} finally {
			$wgDevelopmentWarnings = true;
			\MediaWiki\restoreWarnings();
		}
	}

	/**
	 * @dataProvider changeBackwardsCompatProvider
	 *
	 * @param ItemChange $change
	 * @throws Exception
	 */
	public function testGetSiteLinkDiffBackwardsCompat( ItemChange $change ) {
		//NOTE: Disable developer warnings that may get triggered by
		//      the B/C code path.
		$this->setMwGlobals( 'wgDevelopmentWarnings', false );

		// Also suppress notices that may be triggered by wfLogWarning
		\MediaWiki\suppressWarnings();
		$exception = null;

		try {
			$siteLinkDiff = $change->getSiteLinkDiff();
			$this->assertInstanceOf( 'Diff\Diff', $siteLinkDiff,
				"getSiteLinkDiff must return a Diff" );
		} finally {
			\MediaWiki\restoreWarnings();
		}
	}

}
