<?php

namespace Wikibase\Repo\Tests\Diff;

use DerivativeContext;
use Language;
use RequestContext;
use Title;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\ItemContent;
use Wikibase\Repo\Diff\EntityContentDiffView;

/**
 * @covers Wikibase\Repo\Diff\EntityContentDiffView
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group Database
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Thiemo Mättig
 */
class EntityContentDiffViewTest extends \MediaWikiTestCase {

	public function testConstructor() {
		new EntityContentDiffView( RequestContext::getMain() );
		$this->assertTrue( true );
	}

	public function testDiffingEmptyObjects() {
		$emptyItem = new Item( new ItemId( 'Q1' ) );
		$emptyContent = ItemContent::newFromItem( $emptyItem );

		$html = $this->newDiffView()->generateContentDiffBody( $emptyContent, $emptyContent );

		$this->assertSame( '', $html );
	}

	public function testDiffingSameObjects() {
		$item = new Item( new ItemId( 'Q1' ) );
		$item->setLabel( 'en', 'Not empty any more' );
		$itemContent = ItemContent::newFromItem( $item );

		$html = $this->newDiffView()->generateContentDiffBody( $itemContent, $itemContent );

		$this->assertSame( '', $html );
	}

	public function itemProvider() {
		$emptyItem = new Item( new ItemId( 'Q1' ) );

		$item = new Item( new ItemId( 'Q11' ) );
		$item->setDescription( 'en', 'ohi there' );
		$item->setLabel( 'de', 'o_O' );
		$item->setAliases( 'nl', array( 'foo', 'bar' ) );

		$item2 = new Item( new ItemId( 'Q12' ) );
		$item2->setLabel( 'de', 'o_O' );
		$item2->setLabel( 'en', 'O_o' );
		$item2->setAliases( 'nl', array( 'daaaah' ) );

		$redirect = new EntityRedirect( new ItemId( 'Q11' ), new ItemId( 'Q21' ) );
		$redirect2 = new EntityRedirect( new ItemId( 'Q11' ), new ItemId( 'Q22' ) );

		$insTags = array(
			'has <td>label / de</td>' => '>label / de</td>',
			'has <ins>foo</ins>' => '>foo</ins>',
			'has <td>aliases / nl / 0</td>' => '>aliases / nl / 0</td>',
			'has <ins>bar</ins>' => '>bar</ins>',
			'has <td>description / en</td>' => '>description / en</td>',
			'has <ins>ohi there</ins>' => '>ohi there</ins>',
		);

		$delTags = array(
			'has <td>label / de</td>' => '>label / de</td>',
			'has <del>foo</del>' => '>foo</del>',
			'has <td>aliases / nl / 0</td>' => '>aliases / nl / 0</td>',
			'has <del>bar</del>' => '>bar</del>',
			'has <td>description / en</td>' => '>description / en</td>',
			'has <del>ohi there</del>' => '>ohi there</del>',
		);

		$changeTags = array(
			'has <td>label / en</td>' => '>label / en</td>',
			'has <ins>O_o</ins>' => '>O_o</ins>',
			'has <td>aliases / nl / 0</td>' => '>aliases / nl / 0</td>',
			'has <ins>daaaah</ins>' => '>daaaah</ins>',
			'has <td>aliases / nl / 1</td>' => '>aliases / nl / 1</td>',
			'has <del>foo</del>' => '>foo</del>',
			'has <td>aliases / nl / 2</td>' => '>aliases / nl / 2</td>',
			'has <del>bar</del>' => '>bar</del>',
			'has <td>description / en</td>' => '>description / en</td>',
			'has <del>ohi there</del>' => '>ohi there</del>',
		);

		$fromRedirTags = array(
			'has <td>label / de</td>' => '>label / de</td>',
			'has <ins>foo</ins>' => '>foo</ins>',

			'has <td>redirect</td>' => '>redirect</td>',
			'has <del>Q21</del>' => '>Q21</del>',
		);

		$toRedirTags = array(
			'has <td>label / de</td>' => '>label / de</td>',
			'has <del>foo</del>' => '>foo</del>',

			'has <td>redirect</td>' => '>redirect</td>',
			'has <ins>Q21</ins>' => '>Q21</ins>',
		);

		$changeRedirTags = array(
			'has <td>redirect</td>' => '>redirect</td>',
			'has <del>Q21</del>' => '>Q21</del>',
			'has <ins>Q22</del>' => '>Q22</ins>',
		);

		$empty = ItemContent::newFromItem( $emptyItem );
		$itemContent = ItemContent::newFromItem( $item );
		$itemContent2 = ItemContent::newFromItem( $item2 );

		$redirectContent = ItemContent::newFromRedirect(
			$redirect,
			$this->getMock( Title::class )
		);
		$redirectContent2 = ItemContent::newFromRedirect(
			$redirect2,
			$this->getMock( Title::class )
		);

		return array(
			'from emtpy' => array( $empty, $itemContent, $insTags ),
			'to empty' => array( $itemContent, $empty, $delTags ),
			'changed' => array( $itemContent, $itemContent2, $changeTags ),
			'to redirect' => array( $itemContent, $redirectContent, $toRedirTags ),
			'from redirect' => array( $redirectContent, $itemContent, $fromRedirTags ),
			'redirect changed' => array( $redirectContent, $redirectContent2, $changeRedirTags ),
		);
	}

	/**
	 * @dataProvider itemProvider
	 */
	public function testGenerateContentDiffBody( ItemContent $itemContent, ItemContent $itemContent2, array $matchers ) {
		$html = $this->newDiffView()->generateContentDiffBody( $itemContent, $itemContent2 );

		$this->assertInternalType( 'string', $html );
		foreach ( $matchers as $name => $matcher ) {
			$this->assertContains( $matcher, $html, $name );
		}
	}

	/**
	 * @return EntityContentDiffView
	 */
	private function newDiffView() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setLanguage( Language::factory( 'en' ) );

		return new EntityContentDiffView( $context );
	}

}
