<?php

namespace Wikibase\Repo\Tests;

use PHPUnit4And6Compat;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\LanguageFallbackChain;
use Wikibase\Repo\Content\EntityHandler;
use Wikibase\View\EditSectionGenerator;
use Wikibase\View\EntityTermsView;
use Wikibase\View\EntityView;

/**
 * @group Wikibase
 *
 * @license GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class EntityTypesTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	private function getRegistry() {
		return require __DIR__  . '/../../../WikibaseRepo.entitytypes.php';
	}

	public function provideEntityTypes() {
		return array_map(
			function( $entityType ) {
				return [ $entityType ];
			},
			array_keys( $this->getRegistry() )
		);
	}

	public function testKnownEntityTypesSupported() {
		$entityTypes = $this->provideEntityTypes();

		$this->assertContains( [ 'item' ], $entityTypes );
		$this->assertContains( [ 'property' ], $entityTypes );
	}

	/**
	 * @dataProvider provideEntityTypes
	 */
	public function testViewFactoryCallback( $entityType ) {
		$registry = $this->getRegistry();

		$this->assertArrayHasKey( $entityType, $registry );
		$this->assertArrayHasKey( 'view-factory-callback', $registry[$entityType] );

		$callback = $registry[$entityType]['view-factory-callback'];

		$this->assertInternalType( 'callable', $callback );

		$entityTermsView = $this->getMock( EntityTermsView::class );

		$entityView = call_user_func(
			$callback,
			'en',
			$this->getMock( LabelDescriptionLookup::class ),
			new LanguageFallbackChain( [] ),
			$this->getMock( EditSectionGenerator::class ),
			$entityTermsView
		);

		$this->assertInstanceOf( EntityView::class, $entityView );
	}

	/**
	 * @dataProvider provideEntityTypes
	 */
	public function testContentModelId( $entityType ) {
		$registry = $this->getRegistry();

		$this->assertArrayHasKey( $entityType, $registry );
		$this->assertArrayHasKey( 'content-model-id', $registry[$entityType] );
		$this->assertSame( 'wikibase-' . $entityType, $registry[$entityType]['content-model-id'] );
	}

	/**
	 * @dataProvider provideEntityTypes
	 */
	public function testContentHandlerFactoryCallback( $entityType ) {
		$registry = $this->getRegistry();

		$this->assertArrayHasKey( $entityType, $registry );
		$this->assertArrayHasKey( 'content-handler-factory-callback', $registry[$entityType] );

		$callback = $registry[$entityType]['content-handler-factory-callback'];

		$this->assertInternalType( 'callable', $callback );

		/** @var EntityHandler $entityHandler */
		$entityHandler = call_user_func( $callback );

		$this->assertInstanceOf( EntityHandler::class, $entityHandler );
		$this->assertSame( $entityType, $entityHandler->getEntityType() );
	}

}
