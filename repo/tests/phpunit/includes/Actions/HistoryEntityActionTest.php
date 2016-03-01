<?php

namespace Wikibase\Test;

use HashConfig;
use MWContentSerializationException;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Title;
use User;
use WebRequest;
use Wikibase\HistoryEntityAction;

/**
 * @covers Wikibase\HistoryEntityAction
 *
 * @group Action
 * @group Wikibase
 * @group WikibaseAction
 * @group WikibaseRepo
 *
 * @license GPL-2.0+
 * @author Thiemo Mättig
 */
class HistoryEntityActionTest extends PHPUnit_Framework_TestCase {

	private function getPage( $title ) {
		$page = $this->getMockBuilder( 'Article' )
			->disableOriginalConstructor()
			->getMock();
		$page->expects( $this->any() )
			->method( 'getTitle' )
			->will( $this->returnValue( Title::newFromText( $title ) ) );

		return $page;
	}

	private function getOutput() {
		$output = $this->getMockBuilder( 'OutputPage' )
			->disableOriginalConstructor()
			->getMock();

		return $output;
	}

	private function getContext( PHPUnit_Framework_MockObject_MockObject $output ) {
		$context = $this->getMock( 'IContextSource' );
		$context->expects( $this->once() )
			->method( 'getConfig' )
			->will( $this->returnValue( new HashConfig( array(
				'UseFileCache' => false,
				'UseMediaWikiUIEverywhere' => false,
			) ) ) );
		$context->expects( $this->any() )
			->method( 'getRequest' )
			->will( $this->returnValue( new WebRequest() ) );
		$context->expects( $this->any() )
			->method( 'getUser' )
			->will( $this->returnValue( new User() ) );
		$context->expects( $this->any() )
			->method( 'msg' )
			->will( $this->returnCallback( function() {
				return call_user_func_array( 'wfMessage', func_get_args() )->inLanguage( 'qqx' );
			} ) );

		$context->expects( $this->any() )
			->method( 'getOutput' )
			->will( $this->returnValue( $output ) );

		$output->expects( $this->once() )
			->method( 'getContext' )
			->will( $this->returnValue( $context ) );

		return $context;
	}

	public function testGivenUnDeserializableRevision_historyActionDoesNotFail() {
		$page = $this->getPage( 'Page title' );
		$page->expects( $this->once() )
			->method( 'getPage' )
			->will( $this->throwException( new MWContentSerializationException() ) );

		$output = $this->getOutput();
		$output->expects( $this->once() )
			->method( 'setPageTitle' )
			->with( '(history-title: Page title)' );

		$action = new HistoryEntityAction( $page, $this->getContext( $output ) );
		$action->show();
	}

}
