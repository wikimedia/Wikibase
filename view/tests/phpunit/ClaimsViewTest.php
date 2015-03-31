<?php

namespace Wikibase\Test;

use DataValues\StringValue;
use Html;
use MediaWikiLangTestCase;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Lib\EntityIdFormatter;
use Wikibase\View\ClaimHtmlGenerator;
use Wikibase\View\StatementGroupListView;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\View\Template\TemplateRegistry;

/**
 * @covers Wikibase\View\StatementGroupListView
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class StatementGroupListViewTest extends MediaWikiLangTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgArticlePath' => '/wiki/$1'
		) );
	}

	/**
	 * @uses Wikibase\View\EditSectionGenerator
	 * @uses Wikibase\View\Template\Template
	 * @uses Wikibase\View\Template\TemplateFactory
	 * @uses Wikibase\View\Template\TemplateRegistry
	 */
	public function testGetHtml() {
		$propertyId = new PropertyId( 'P77' );
		$claims = $this->makeClaims( $propertyId );

		$propertyIdFormatter = $this->getEntityIdFormatter();
		$link = $this->getLinkForId( $propertyId );

		$statementGroupListView = $this->newStatementGroupListView( $propertyIdFormatter );

		$html = $statementGroupListView->getHtml( $claims );

		foreach ( $claims as $claim ) {
			$this->assertContains( $claim->getGuid(), $html );
		}

		$this->assertContains( $link, $html );
	}

	/**
	 * @param PropertyId $propertyId
	 *
	 * @return Claim[]
	 */
	private function makeClaims( PropertyId $propertyId ) {
		return array(
			$this->makeClaim( new PropertyNoValueSnak(
				$propertyId
			) ),
			$this->makeClaim( new PropertyValueSnak(
				$propertyId,
				new EntityIdValue( new ItemId( 'Q22' ) )
			) ),
			$this->makeClaim( new PropertyValueSnak(
				$propertyId,
				new StringValue( 'test' )
			) ),
			$this->makeClaim( new PropertyValueSnak(
				$propertyId,
				new StringValue( 'File:Image.jpg' )
			) ),
			$this->makeClaim( new PropertySomeValueSnak(
				$propertyId
			) ),
			$this->makeClaim( new PropertyValueSnak(
				$propertyId,
				new EntityIdValue( new ItemId( 'Q555' ) )
			) ),
		);
	}

	/**
	 * @param Snak $mainSnak
	 * @param string|null $guid
	 *
	 * @return Claim
	 */
	private function makeClaim( Snak $mainSnak, $guid = null ) {
		static $guidCounter = 0;

		if ( $guid === null ) {
			$guidCounter++;
			$guid = 'EntityViewTest$' . $guidCounter;
		}

		$claim = new Claim( $mainSnak );
		$claim->setGuid( $guid );

		return $claim;
	}

	/**
	 * @param EntityIdFormatter $propertyIdFormatter
	 *
	 * @return StatementGroupListView
	 */
	private function newStatementGroupListView( EntityIdFormatter $propertyIdFormatter ) {
		$templateFactory = new TemplateFactory( TemplateRegistry::getDefaultInstance() );

		return new StatementGroupListView(
			$templateFactory,
			$propertyIdFormatter,
			$this->getMock( 'Wikibase\View\EditSectionGenerator' ),
			$this->getClaimHtmlGenerator()
		);
	}

	/**
	 * @return ClaimHtmlGenerator
	 */
	private function getClaimHtmlGenerator() {
		$claimHtmlGenerator = $this->getMockBuilder( 'Wikibase\View\ClaimHtmlGenerator' )
			->disableOriginalConstructor()
			->getMock();

		$claimHtmlGenerator->expects( $this->any() )
			->method( 'getHtmlForClaim' )
			->will( $this->returnCallback( function( Claim $claim, $editSectionHtml = null ) {
				return $claim->getGuid();
			} ) );

		return $claimHtmlGenerator;
	}

	/**
	 * @param EntityId $id
	 *
	 * @return string
	 */
	public function getLinkForId( EntityId $id ) {
		$name = $id->getEntityType() . ':' . $id->getSerialization();
		$url = 'http://wiki.acme.com/wiki/' . urlencode( $name );
		return Html::element( 'a', array( 'href' => $url ), $name );
	}

	/**
	 * @return EntityIdFormatter
	 */
	private function getEntityIdFormatter() {
		$lookup = $this->getMock( 'Wikibase\Lib\EntityIdFormatter' );

		$lookup->expects( $this->any() )
			->method( 'formatEntityId' )
			->will( $this->returnCallback( array( $this, 'getLinkForId' ) ) );

		return $lookup;
	}

}
