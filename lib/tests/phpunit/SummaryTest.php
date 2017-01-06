<?php

namespace Wikibase\Lib\Tests;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Summary;

/**
 * @covers Wikibase\Summary
 *
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Daniel Kinzler
 */
class SummaryTest extends \MediaWikiTestCase {

	public function testAddAutoCommentArgs() {
		$summary = new Summary( 'summarytest' );
		$summary->addAutoCommentArgs( "one" );
		$summary->addAutoCommentArgs( 2, new ItemId( 'Q3' ) );
		$summary->addAutoCommentArgs( array( "four", "five" ) );

		$expected = array( 'one', 2, new ItemId( 'Q3' ), 'four', 'five' );
		$this->assertEquals( $expected, $summary->getCommentArgs() );
	}

	public function testSetLanguage() {
		$summary = new Summary( 'summarytest' );
		$summary->setLanguage( "xyz" );

		$this->assertEquals( 'xyz', $summary->getLanguageCode() );
	}

	public function testSetUserSummary() {
		$summary = new Summary( 'summarytest' );
		$summary->setUserSummary( "xyz" );

		$this->assertEquals( 'xyz', $summary->getUserSummary() );
	}

	public function testAddAutoSummaryArgs() {
		$summary = new Summary( 'summarytest' );
		$summary->addAutoSummaryArgs( "one" );
		$summary->addAutoSummaryArgs( 2, new ItemId( 'Q3' ) );
		$summary->addAutoSummaryArgs( array( "four", "five" ) );

		$expected = array( 'one', 2, new ItemId( 'Q3' ), 'four', 'five' );
		$this->assertEquals( $expected, $summary->getAutoSummaryArgs() );
	}

	public function testSetAction() {
		$summary = new Summary( 'summarytest' );

		$summary->setAction( "testing" );
		$this->assertEquals( "testing", $summary->getActionName() );

		$summary->setAction( "besting" );
		$this->assertEquals( "besting", $summary->getActionName() );

		$this->assertEquals( "summarytest-besting", $summary->getMessageKey() );
	}

	public function testGetMessageKey() {
		$summary = new Summary( 'summarytest' );
		$this->assertEquals( "summarytest", $summary->getMessageKey() );

		$summary->setAction( "testing" );
		$this->assertEquals( "summarytest-testing", $summary->getMessageKey() );

		$summary->setModuleName( "" );
		$this->assertEquals( "testing", $summary->getMessageKey() );
	}

}
