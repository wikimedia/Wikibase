<?php


namespace Wikibase\Client\Tests\Specials;

use Wikibase\Client\Specials\SpecialPagesWithBadges;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Test\SpecialPageTestBase;

/**
 * @covers Wikibase\Client\Specials\SpecialPagesWithBadges
 *
 * @group WikibaseClient
 * @group SpecialPage
 * @group WikibaseSpecialPage
 * @group Wikibase
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class SpecialPagesWithBadgesTest extends SpecialPageTestBase {

	/**
	 * @return LabelDescriptionLookup
	 */
	private function getLabelLookup() {
		$labelLookup = $this->getMock( 'Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup' );
		$labelLookup->expects( $this->any() )
			->method( 'getLabel' )
			->will( $this->returnCallback( function( ItemId $itemId ) {
				return new Term( 'en', 'Label of ' . $itemId->getSerialization() );
			} ) );

		return $labelLookup;
	}

	/**
	 * @return LanguageFallbackLabelDescriptionLookupFactory
	 */
	private function getLabelDescriptionLookupFactory() {
		$itemIds = array(
			new ItemId( 'Q123' ),
			new ItemId( 'Q456' )
		);

		$labelDescriptionLookupFactory = $this->getMockBuilder( '\Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory' )
			->disableOriginalConstructor()
			->getMock();
		$labelDescriptionLookupFactory->expects( $this->once() )
			->method( 'newLabelDescriptionLookup' )
			->with( $this->anything(), $this->equalTo( $itemIds ) )
			->will( $this->returnValue( $this->getLabelLookup() ) );

		return $labelDescriptionLookupFactory;
	}

	protected function newSpecialPage() {
		$specialPage = new SpecialPagesWithBadges();
		$specialPage->initServices( $this->getLabelDescriptionLookupFactory(), array( 'Q123', 'Q456' ), 'enwiki' );

		return $specialPage;
	}

	public function testExecuteWithoutAnyParams() {
		list( $result, ) = $this->executeSpecialPage( '' );

		$this->assertContains( '<select name="badge"', $result );
		$this->assertContains( '<option value="Q123"', $result );
		$this->assertContains( '<option value="Q456"', $result );

		$this->assertContains( 'Label of Q123', $result );
		$this->assertContains( 'Label of Q456', $result );
	}

	public function testExecuteWithValidParam() {
		list( $result, ) = $this->executeSpecialPage( 'Q456' );

		$this->assertContains( '<option value="Q456" selected=""', $result );
	}

	public function testExecuteWithInvalidParam() {
		list( $result, ) = $this->executeSpecialPage( 'FooBar' );

		$this->assertContains( '<p class="error"', $result );
		$this->assertContains( 'FooBar is not a valid item id', $result );
	}

}
