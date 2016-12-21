<?php

namespace Wikibase\Lib\Tests\Interactors;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Interactors\DispatchingTermSearchInteractor;
use Wikibase\Lib\Interactors\DispatchingTermSearchInteractorFactory;
use Wikibase\Lib\Interactors\TermSearchInteractor;
use Wikibase\Lib\Interactors\TermSearchInteractorFactory;
use Wikimedia\Assert\ParameterAssertionException;

/**
 * @covers Wikibase\Lib\Interactors\DispatchingTermSearchInteractorFactory
 *
 * @license GPL-2.0+
 */
class DispatchingTermSearchInteractorFactoryTest extends \PHPUnit_Framework_TestCase {

	public function provideInvalidConsructorArguments() {
		return [
			'non-string keys' => [
				[ 0 => $this->getMock( TermSearchInteractorFactory::class )]
			],
			'not a TermSearchInteractorFactory as a value' => [
				[ 'item' => new ItemId( 'Q123' ) ]
			],
		];
	}

	/**
	 * @dataProvider provideInvalidConsructorArguments
	 */
	public function testGivenInvalidArguments_constructorThrowsException( $factories ) {
		$this->setExpectedException( ParameterAssertionException::class );

		new DispatchingTermSearchInteractorFactory( $factories );
	}

	public function testGetInteractorReturnsDispatchingTermSearchInteractorInstance() {
		$fooInteractorFactory = $this->getMock( TermSearchInteractorFactory::class );
		$fooInteractorFactory->expects( $this->any() )
			->method( 'getInteractor' )
			->will(
				$this->returnValue( $this->getMock( TermSearchInteractor::class ) )
			);

		$localInteractorFactory = $this->getMock( TermSearchInteractorFactory::class );
		$localInteractorFactory->expects( $this->any() )
			->method( 'getInteractor' )
			->will(
				$this->returnValue( $this->getMock( TermSearchInteractor::class ) )
			);

		$dispatchingFactory = new DispatchingTermSearchInteractorFactory( [
			'item' => $fooInteractorFactory,
			'property' => $localInteractorFactory,
		] );

		$this->assertInstanceOf( TermSearchInteractor::class, $dispatchingFactory->getInteractor( 'en' ) );
	}

}
