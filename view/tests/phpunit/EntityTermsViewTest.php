<?php

namespace Wikibase\View\Tests;

use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\View\EditSectionGenerator;
use Wikibase\View\EntityTermsView;
use Wikibase\View\DummyLocalizedTextProvider;
use Wikibase\View\LanguageDirectionalityLookup;
use Wikibase\View\LocalizedTextProvider;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\View\TableTermsListView;

/**
 * @covers Wikibase\View\EntityTermsView
 *
 * @uses Wikibase\View\Template\Template
 * @uses Wikibase\View\Template\TemplateFactory
 * @uses Wikibase\View\Template\TemplateRegistry
 * @uses Wikibase\View\TableTermsListView
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @license GPL-2.0+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Thiemo Mättig
 */
class EntityTermsViewTest extends PHPUnit_Framework_TestCase {

	private function getEntityTermsView(
		$editSectionCalls = 0,
		$languageNameCalls = 1,
		LocalizedTextProvider $textProvider = null
	) {
		$editSectionGenerator = $this->getMock( EditSectionGenerator::class );
		$editSectionGenerator->expects( $this->exactly( $editSectionCalls ) )
			->method( 'getLabelDescriptionAliasesEditSection' )
			->will( $this->returnValue( '<EDITSECTION>' ) );

		$languageNameLookup = $this->getMock( LanguageNameLookup::class );
		$languageNameLookup->expects( $this->exactly( $languageNameCalls ) )
			->method( 'getName' )
			->will( $this->returnCallback( function( $languageCode ) {
				return "<LANGUAGENAME-$languageCode>";
			} ) );

		$languageDirectionalityLookup = $this->getMock( LanguageDirectionalityLookup::class );
		$languageDirectionalityLookup->expects( $this->any() )
			->method( 'getDirectionality' )
			->will( $this->returnCallback( function( $languageCode ) {
				return [
					'en' => 'ltr',
					'arc' => 'rtl',
					'lkt' => 'ltr'
				][ $languageCode ];
			} ) );

		$textProvider = $textProvider ?: new DummyLocalizedTextProvider( 'lkt' );

		return new EntityTermsView(
			TemplateFactory::getDefaultInstance(),
			$editSectionGenerator,
			new TableTermsListView(
				TemplateFactory::getDefaultInstance(),
				$languageNameLookup,
				$textProvider,
				$languageDirectionalityLookup
			),
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

	public function testGetHtml_containsDescriptionAndAliases() {
		$entityTermsView = $this->getEntityTermsView( 1 );
		$fingerprint = $this->getFingerprint();
		$html = $entityTermsView->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, null, '' );

		$this->assertContains( '&lt;DESCRIPTION&gt;', $html );
		$this->assertContains( '&lt;ALIAS1&gt;', $html );
		$this->assertContains( '&lt;ALIAS2&gt;', $html );
	}

	public function entityFingerprintProvider() {
		$fingerprint = $this->getFingerprint();

		return array(
			'empty' => array( new Fingerprint(), new ItemId( 'Q42' ), 'en' ),
			'other language' => array( $fingerprint, new ItemId( 'Q42' ), 'de' ),
			'other id' => array( $fingerprint, new ItemId( 'Q12' ), 'en' ),
		);
	}

	/**
	 * @dataProvider entityFingerprintProvider
	 */
	public function testGetHtml_isEditable( Fingerprint $fingerprint, ItemId $entityId, $languageCode ) {
		$entityTermsView = $this->getEntityTermsView( 1 );
		$html = $entityTermsView->getHtml( $languageCode, $fingerprint, $fingerprint, $fingerprint, $entityId, '' );

		$this->assertContains( '<EDITSECTION>', $html );
	}

	public function testGetHtml_valuesAreEscaped() {
		$fingerprint = new Fingerprint();
		$fingerprint->setDescription( 'en', '<script>alert( "xss" );</script>' );
		$fingerprint->setAliasGroup( 'en', array( '<a href="#">evil html</a>', '<b>bold</b>', '<i>italic</i>' ) );

		$view = $this->getEntityTermsView( 1 );
		$html = $view->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, null, '' );

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
		$html = $entityTermsView->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, null, '' );

		$this->assertContains( 'wb-empty', $html );
		$this->assertContains( '(wikibase-description-empty)', $html );
		$this->assertContains( '(wikibase-aliases-empty)', $html );
	}

	public function testGetHtml_isNotMarkedAsEmpty() {
		$entityTermsView = $this->getEntityTermsView( 1 );
		$fingerprint = $this->getFingerprint();
		$html = $entityTermsView->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, null, '' );

		$this->assertNotContains( 'wb-empty', $html );
		$this->assertNotContains( '(wikibase-description-empty)', $html );
		$this->assertNotContains( '(wikibase-aliases-empty)', $html );
	}

	public function testGetHtml_containsEmptyDescriptionPlaceholder() {
		$fingerprint = $this->getFingerprint();
		$fingerprint->removeDescription( 'en' );

		$view = $this->getEntityTermsView( 1 );
		$html = $view->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, null, '' );

		$this->assertContains( 'wb-empty', $html );
		$this->assertContains( '(wikibase-description-empty)', $html );
		$this->assertNotContains( '(wikibase-aliases-empty)', $html );
	}

	public function testGetHtml_containsEmptyAliasesPlaceholder() {
		$fingerprint = $this->getFingerprint();
		$fingerprint->removeAliasGroup( 'en' );

		$view = $this->getEntityTermsView( 1 );
		$html = $view->getHtml( 'en', $fingerprint, $fingerprint, $fingerprint, null, '' );

		$this->assertContains( 'wb-empty', $html );
		$this->assertNotContains( '(wikibase-description-empty)', $html );
		$this->assertContains( '(wikibase-aliases-empty)', $html );
	}

	public function testGetTitleHtml_containsLabel() {
		$entityTermsView = $this->getEntityTermsView( 0, 0 );
		$fingerprint = $this->getFingerprint();
		$html = $entityTermsView->getTitleHtml( 'en', $fingerprint, null );

		$this->assertContains( '&lt;LABEL&gt;', $html );
	}

	/**
	 * @dataProvider entityFingerprintProvider
	 */
	public function testGetTitleHtml_withEntityId( Fingerprint $fingerprint, ItemId $entityId ) {
		$entityTermsView = $this->getEntityTermsView( 0, 0 );
		$html = $entityTermsView->getTitleHtml( 'en', $fingerprint, $entityId );
		$idString = $entityId->getSerialization();

		$this->assertContains( '(parentheses: ' . $idString . ')', $html );
	}

	public function testGetTitleHtml_withoutEntityId() {
		$entityTermsView = $this->getEntityTermsView( 0, 0 );
		$html = $entityTermsView->getTitleHtml( 'en', new Fingerprint(), null );

		$this->assertNotContains( '(parentheses', $html );
	}

	public function testGetTitleHtml_labelIsEscaped() {
		$entityTermsView = $this->getEntityTermsView( 0, 0 );
		$fingerprint = new Fingerprint();
		$fingerprint->setLabel( 'en', '<a href="#">evil html</a>' );
		$html = $entityTermsView->getTitleHtml( 'en', $fingerprint, null );

		$this->assertContains( 'evil html', $html, 'make sure it works' );
		$this->assertNotContains( 'href="#"', $html );
		$this->assertNotContains( '&amp;', $html, 'no double escaping' );
	}

	public function testGetTitleHtml_isMarkedAsEmpty() {
		$fingerprint = $this->getFingerprint();
		$fingerprint->removeLabel( 'en' );

		$entityTermsView = $this->getEntityTermsView( 0, 0 );
		$html = $entityTermsView->getTitleHtml( 'en', $fingerprint, null );

		$this->assertContains( 'wb-empty', $html );
		$this->assertContains( '(wikibase-label-empty)', $html );
	}

	public function testGetTitleHtml_isNotMarkedAsEmpty() {
		$fingerprint = $this->getFingerprint();

		$entityTermsView = $this->getEntityTermsView( 0, 0 );
		$html = $entityTermsView->getTitleHtml( 'en', $fingerprint, null );

		$this->assertNotContains( 'wb-empty', $html );
		$this->assertNotContains( '(wikibase-label-empty)', $html );
	}

}
