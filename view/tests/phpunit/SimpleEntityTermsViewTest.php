<?php

namespace Wikibase\View\Tests;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\View\EditSectionGenerator;
use Wikibase\View\DummyLocalizedTextProvider;
use Wikibase\View\HtmlTermRenderer;
use Wikibase\View\SimpleEntityTermsView;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\View\TermsListView;

/**
 * @covers Wikibase\View\SimpleEntityTermsView
 *
 * @uses Wikibase\View\Template\Template
 * @uses Wikibase\View\Template\TemplateFactory
 * @uses Wikibase\View\Template\TemplateRegistry
 * @uses Wikibase\View\TermsListView
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @license GPL-2.0+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Thiemo Mättig
 */
class SimpleEntityTermsViewTest extends PHPUnit_Framework_TestCase {

	private function getEntityTermsView( $editSectionCalls = 0, TermsListView $termsListView = null ) {
		$editSectionGenerator = $this->getMock( EditSectionGenerator::class );
		$editSectionGenerator->expects( $this->exactly( $editSectionCalls ) )
			->method( 'getLabelDescriptionAliasesEditSection' )
			->will( $this->returnValue( '<EDITSECTION>' ) );

		$textProvider = new DummyLocalizedTextProvider( 'lkt' );

		$termsListView = $termsListView ?: $this->getMockBuilder( TermsListView::class )
			->disableOriginalConstructor()
			->getMock();

		$htmlTermRenderer = $this->getMock( HtmlTermRenderer::class );
		$htmlTermRenderer->expects( $this->any() )
			->method( 'renderTerm' )
			->will( $this->returnCallback( function( Term $term ) {
				return htmlspecialchars( $term->getText() );
			} ) );

		$labelDescriptionLookup = $this->getMock( LabelDescriptionLookup::class );
		$labelDescriptionLookup->expects( $this->any() )
			->method( 'getLabel' )
			->will( $this->returnCallback( function( EntityId $entityId ) {
				$terms = [
					'Q111' => new Term( 'language', '<LABEL>' ),
					'Q666' => new Term( 'language', '<a href="#">evil html</a>' ),
				];
				return isset( $terms[ $entityId->getSerialization() ] ) ? $terms[ $entityId->getSerialization() ] : null;
			} ) );
		$labelDescriptionLookup->expects( $this->any() )
			->method( 'getDescription' )
			->will( $this->returnCallback( function( EntityId $entityId ) {
				return $entityId->getSerialization() === 'Q111' ? new Term( 'language', '<DESCRIPTION>' ) : null;
			} ) );

		return new SimpleEntityTermsView(
			$htmlTermRenderer,
			$labelDescriptionLookup,
			TemplateFactory::getDefaultInstance(),
			$editSectionGenerator,
			$termsListView,
			$textProvider
		);
	}

	private function getFingerprint( $languageCode = 'en' ) {
		$fingerprint = new Fingerprint();
		$fingerprint->setLabel( $languageCode, '<LABEL>' );
		$fingerprint->setDescription( $languageCode, '<DESCRIPTION>' );
		$fingerprint->setAliasGroup( $languageCode, [ '<ALIAS1>', '<ALIAS2>' ] );
		return $fingerprint;
	}

	public function testGetHtml_containsAliases() {
		$entityTermsView = $this->getEntityTermsView( 1 );
		$fingerprint = $this->getFingerprint();
		$html = $entityTermsView->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, null );

		$this->assertContains( '&lt;ALIAS1&gt;', $html );
		$this->assertContains( '&lt;ALIAS2&gt;', $html );
	}

	public function entityFingerprintProvider() {
		$fingerprint = $this->getFingerprint();

		return [
			'empty' => [ new Fingerprint(), new ItemId( 'Q42' ), 'en' ],
			'other language' => [ $fingerprint, new ItemId( 'Q42' ), 'de' ],
			'other id' => [ $fingerprint, new ItemId( 'Q12' ), 'en' ],
		];
	}

	/**
	 * @dataProvider entityFingerprintProvider
	 */
	public function testGetHtml_isEditable( Fingerprint $fingerprint, ItemId $entityId, $languageCode ) {
		$entityTermsView = $this->getEntityTermsView( 1 );
		$html = $entityTermsView->getHtml( $languageCode, $fingerprint, $fingerprint, $fingerprint, $entityId );

		$this->assertContains( '<EDITSECTION>', $html );
	}

	public function testGetHtml_valuesAreEscaped() {
		$fingerprint = new Fingerprint();
		$fingerprint->setDescription( 'en', '<script>alert( "xss" );</script>' );
		$fingerprint->setAliasGroup( 'en', [ '<a href="#">evil html</a>', '<b>bold</b>', '<i>italic</i>' ] );

		$view = $this->getEntityTermsView( 1 );
		$html = $view->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, null );

		$this->assertContains( 'evil html', $html, 'make sure it works' );
		$this->assertNotContains( 'href="#"', $html );
		$this->assertNotContains( '<script>', $html );
		$this->assertNotContains( '<b>', $html );
		$this->assertNotContains( '<i>', $html );
		$this->assertNotContains( '&amp;', $html, 'no double escaping' );
	}

	public function testGetHtml_isMarkedAsEmptyValue() {
		$entityTermsView = $this->getEntityTermsView( 1 );
		$fingerprint = new Fingerprint();
		$html = $entityTermsView->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, null );

		$this->assertContains( 'wb-empty', $html );
		$this->assertContains( '(wikibase-description-empty)', $html );
		$this->assertContains( '<div class="wikibase-entitytermsview-heading-aliases wb-empty"></div>', $html );
	}

	public function testGetHtml_isNotMarkedAsEmpty() {
		$entityTermsView = $this->getEntityTermsView( 1 );
		$fingerprint = $this->getFingerprint();
		$html = $entityTermsView->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, new ItemId( 'Q111' ) );

		$this->assertNotContains( 'wb-empty', $html );
		$this->assertNotContains( '(wikibase-description-empty)', $html );
		$this->assertContains( 'wikibase-entitytermsview-aliases', $html );
	}

	public function testGetHtml_containsEmptyDescriptionPlaceholder() {
		$fingerprint = $this->getFingerprint();

		$view = $this->getEntityTermsView( 1 );
		$html = $view->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, null );

		$this->assertContains( 'wb-empty', $html );
		$this->assertContains( '(wikibase-description-empty)', $html );
		$this->assertContains( 'wikibase-entitytermsview-aliases', $html );
	}

	public function testGetHtml_containsEmptyAliasesList() {
		$fingerprint = $this->getFingerprint();
		$fingerprint->removeAliasGroup( 'en' );

		$view = $this->getEntityTermsView( 1 );
		$html = $view->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint );

		$this->assertContains( 'wb-empty', $html );
		$this->assertContains( '<div class="wikibase-entitytermsview-heading-aliases wb-empty"></div>', $html );
	}

	/**
	 * @dataProvider entityFingerprintProvider
	 */
	public function testGetHtml_containsAllTerms( Fingerprint $fingerprint, ItemId $entityId, $languageCode ) {
		$termsListView = $this->getMockBuilder( TermsListView::class )
			->disableOriginalConstructor()
			->getMock();
		$termsListView->expects( $this->once() )
			->method( 'getHtml' )
			->with(
				$fingerprint,
				$fingerprint,
				$fingerprint,
				$this->equalTo( $languageCode === 'de' ? [ 'de', 'en' ] : [ 'en' ] )
			)
			->will( $this->returnValue( '<TERMSLISTVIEW>' ) );
		$entityTermsView = $this->getEntityTermsView( 1, $termsListView );
		$html = $entityTermsView->getHtml( $languageCode, $fingerprint, $fingerprint, $fingerprint, $entityId );

		$this->assertContains( '<TERMSLISTVIEW>', $html );
	}

	public function testGetTitleHtml_withEntityId() {
		$entityTermsView = $this->getEntityTermsView( 0 );
		$html = $entityTermsView->getTitleHtml( new ItemId( 'Q111' ) );

		$this->assertContains( '(parentheses: Q111)', $html );
		$this->assertContains( '&lt;LABEL&gt;', $html );
	}

	public function testGetTitleHtml_withoutEntityId() {
		$entityTermsView = $this->getEntityTermsView( 0 );
		$html = $entityTermsView->getTitleHtml( null );

		$this->assertNotContains( '(parentheses', $html );
		$this->assertNotContains( '&lt;LABEL&gt;', $html );
	}

	public function testGetTitleHtml_labelIsEscaped() {
		$entityTermsView = $this->getEntityTermsView( 0 );
		$html = $entityTermsView->getTitleHtml( new ItemId( 'Q666' ) );

		$this->assertContains( 'evil html', $html, 'make sure it works' );
		$this->assertNotContains( 'href="#"', $html );
		$this->assertNotContains( '&amp;', $html, 'no double escaping' );
	}

	public function testGetTitleHtml_isMarkedAsEmpty() {
		$entityTermsView = $this->getEntityTermsView( 0 );
		$html = $entityTermsView->getTitleHtml( null );

		$this->assertContains( 'wb-empty', $html );
		$this->assertContains( '(wikibase-label-empty)', $html );
	}

	public function testGetTitleHtml_isNotMarkedAsEmpty() {
		$entityTermsView = $this->getEntityTermsView( 0 );
		$html = $entityTermsView->getTitleHtml( new ItemId( 'Q111' ) );

		$this->assertNotContains( 'wb-empty', $html );
		$this->assertNotContains( '(wikibase-label-empty)', $html );
	}

}
