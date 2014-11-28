<?php

namespace Wikibase\Test;

use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\EntityInfoTermLookup;
use Wikibase\Lib\Store\LanguageLabelLookup;
use Wikibase\Repo\View\EntityViewFactory;

/**
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class EntityViewFactoryTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider newEntityViewProvider
	 */
	public function testNewEntityView( $expectedClass, $entityType ) {
		$entityViewFactory = $this->getEntityViewFactory();

		$languageFallback = new LanguageFallbackChain( array() );
		$labelLookup = new LanguageLabelLookup( new EntityInfoTermLookup( array() ), 'de' );

		$entityView = $entityViewFactory->newEntityView(
			$entityType,
			'de',
			$languageFallback,
			$labelLookup
		);

		$this->assertInstanceOf( $expectedClass, $entityView );
	}

	public function newEntityViewProvider() {
		return array(
			array( 'Wikibase\Repo\View\ItemView', 'item' ),
			array( 'Wikibase\Repo\View\PropertyView', 'property' )
		);
	}

	public function testNewEntityView_withInvalidType() {
		$entityViewFactory = $this->getEntityViewFactory();

		$this->setExpectedException( 'InvalidArgumentException' );

		$entityViewFactory->newEntityView(
			'kittens',
			'de'
		);
	}

	private function getEntityViewFactory() {
		return new EntityViewFactory(
			$this->getEntityTitleLookup(),
			new MockRepository(),
			$this->getSnakFormatterFactory(),
			array()
		);
	}

	private function getEntityTitleLookup() {
		$entityTitleLookup = $this->getMock( 'Wikibase\Lib\Store\EntityTitleLookup' );

		$entityTitleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback( function( EntityId $id ) {
				$name = $id->getEntityType() . ':' . $id->getSerialization();
				return Title::makeTitle( NS_MAIN, $name );
			} ) );

		return $entityTitleLookup;
	}

	private function getSnakFormatterFactory() {
		$snakFormatter = $this->getMock( 'Wikibase\Lib\SnakFormatter' );

		$snakFormatter->expects( $this->any() )
			->method( 'getFormat' )
			->will( $this->returnValue( SnakFormatter::FORMAT_HTML ) );

		$snakFormatterFactory = $this->getMockBuilder( 'Wikibase\Lib\OutputFormatSnakFormatterFactory' )
			->disableOriginalConstructor()
			->getMock();

		$snakFormatterFactory->expects( $this->any() )
			->method( 'getSnakFormatter' )
			->will( $this->returnValue( $snakFormatter ) );

		return $snakFormatterFactory;
	}

}
