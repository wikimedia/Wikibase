<?php

namespace Wikibase\Repo\Tests\Specials;

use FauxRequest;
use HashSiteStore;
use Site;
use SiteStore;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Repo\Specials\SpecialNewItem;

/**
 * @covers Wikibase\Repo\Specials\SpecialNewItem
 * @covers Wikibase\Repo\Specials\SpecialNewEntity
 * @covers Wikibase\Repo\Specials\SpecialWikibaseRepoPage
 * @covers Wikibase\Repo\Specials\SpecialWikibasePage
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group SpecialPage
 * @group WikibaseSpecialPage
 *
 * @group Database
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @author Addshore
 */
class SpecialNewItemTest extends SpecialNewEntityTest {

	/**
	 * @var SiteStore
	 */
	private $siteStore;

	protected function setUp() {
		parent::setUp();

		$this->siteStore = new HashSiteStore();
	}

	protected function newSpecialPage() {
		return new SpecialNewItem( $this->siteStore );
	}

	public function testAllNecessaryFormFieldsArePresent_WhenRendered() {

		list( $html ) = $this->executeSpecialPage();

		$this->assertHtmlContainsInputWithName( $html, SpecialNewItem::FIELD_LANG );
		$this->assertHtmlContainsInputWithName( $html, SpecialNewItem::FIELD_LABEL );
		$this->assertHtmlContainsInputWithName( $html, SpecialNewItem::FIELD_DESCRIPTION );
		$this->assertHtmlContainsInputWithName( $html, SpecialNewItem::FIELD_ALIASES );
		$this->assertHtmlContainsSubmitControl( $html );
	}

	public function testSiteAndPageInputFieldsWithPredefinedValuesPresent_WhenRenderedWithGetParametersPassed() {
		$getParameters = [
			SpecialNewItem::FIELD_SITE => 'some-site',
			SpecialNewItem::FIELD_PAGE => 'some-page'
		];

		list( $html ) = $this->executeSpecialPage( '', new FauxRequest( $getParameters ) );

		$this->assertHtmlContainsInputWithNameAndValue(
			$html,
			SpecialNewItem::FIELD_SITE,
			'some-site'
		);
		$this->assertHtmlContainsInputWithNameAndValue(
			$html,
			SpecialNewItem::FIELD_PAGE,
			'some-page'
		);
	}

	public function testLabelAndDescriptionValuesAreSetAccordingToSubpagePath_WhenRendered() {
		$subPagePart1 = 'LabelText';
		$subPagePart2 = 'DescriptionText';
		$subPage = "{$subPagePart1}/{$subPagePart2}";

		list( $html, ) = $this->executeSpecialPage( $subPage );

		$this->assertHtmlContainsInputWithNameAndValue( $html, SpecialNewItem::FIELD_LABEL, $subPagePart1 );
		$this->assertHtmlContainsInputWithNameAndValue( $html, SpecialNewItem::FIELD_DESCRIPTION, $subPagePart2 );
	}

	public function provideValidEntityCreationRequests() {
		return [
			'only label is set' => [
				[
					SpecialNewItem::FIELD_LANG => 'en',
					SpecialNewItem::FIELD_LABEL => 'label',
					SpecialNewItem::FIELD_DESCRIPTION => '',
					SpecialNewItem::FIELD_ALIASES => '',
				],
			],
			'another language' => [
				[
					SpecialNewItem::FIELD_LANG => 'fr',
					SpecialNewItem::FIELD_LABEL => 'label',
					SpecialNewItem::FIELD_DESCRIPTION => '',
					SpecialNewItem::FIELD_ALIASES => '',
				],
			],
			'only description is set' => [
				[
					SpecialNewItem::FIELD_LANG => 'en',
					SpecialNewItem::FIELD_LABEL => '',
					SpecialNewItem::FIELD_DESCRIPTION => 'desc',
					SpecialNewItem::FIELD_ALIASES => '',
				],
			],
			'single alias' => [
				[
					SpecialNewItem::FIELD_LANG => 'en',
					SpecialNewItem::FIELD_LABEL => '',
					SpecialNewItem::FIELD_DESCRIPTION => '',
					SpecialNewItem::FIELD_ALIASES => 'alias',
				],
			],
			'multiple aliases' => [
				[
					SpecialNewItem::FIELD_LANG => 'en',
					SpecialNewItem::FIELD_LABEL => '',
					SpecialNewItem::FIELD_DESCRIPTION => '',
					SpecialNewItem::FIELD_ALIASES => 'alias1|alias2|alias3',
				],
			],
			'nontrimmed label' => [
				[
					SpecialNewItem::FIELD_LANG => 'en',
					SpecialNewItem::FIELD_LABEL => '  some text with spaces on the sides    ',
					SpecialNewItem::FIELD_DESCRIPTION => '',
					SpecialNewItem::FIELD_ALIASES => '',
				],
			],
			'nontrimmed description' => [
				[
					SpecialNewItem::FIELD_LANG => 'en',
					SpecialNewItem::FIELD_LABEL => '',
					SpecialNewItem::FIELD_DESCRIPTION => ' description with spaces on the sides ',
					SpecialNewItem::FIELD_ALIASES => '',
				],
			],
			'all input is present' => [
				[
					SpecialNewItem::FIELD_LANG => 'en',
					SpecialNewItem::FIELD_LABEL => 'label',
					SpecialNewItem::FIELD_DESCRIPTION => 'desc',
					SpecialNewItem::FIELD_ALIASES => 'a1|a2',
				],
			],
		];
	}

	public function provideInvalidEntityCreationRequests() {
		return [
			'unknown language' => [
				[
					SpecialNewItem::FIELD_LANG => 'some-weird-language',
					SpecialNewItem::FIELD_LABEL => 'label',
					SpecialNewItem::FIELD_DESCRIPTION => '',
					SpecialNewItem::FIELD_ALIASES => '',
				],
				'language code was not recognized',
			],
			'unknown site identifier' => [
				[
					SpecialNewItem::FIELD_LANG => 'en',
					SpecialNewItem::FIELD_LABEL => 'label',
					SpecialNewItem::FIELD_DESCRIPTION => '',
					SpecialNewItem::FIELD_ALIASES => '',
					SpecialNewItem::FIELD_SITE => 'unknown',
					SpecialNewItem::FIELD_PAGE => 'some page'
				],
				'site identifier was not recognized',
			],
			'all fields are empty' => [
				[
					SpecialNewItem::FIELD_LANG => 'en',
					SpecialNewItem::FIELD_LABEL => '',
					SpecialNewItem::FIELD_DESCRIPTION => '',
					SpecialNewItem::FIELD_ALIASES => '',
				],
				'you need to fill'
			],
			'empty label and description, aliases contain only spaces and pipe symbols' => [
				[
					SpecialNewItem::FIELD_LANG => 'en',
					SpecialNewItem::FIELD_LABEL => '',
					SpecialNewItem::FIELD_DESCRIPTION => '',
					SpecialNewItem::FIELD_ALIASES => ' | || | ',
				],
				'you need to fill'
			],
		];
	}

	public function testErrorBeingDisplayed_WhenItemWithTheSameLabelAndDescriptionInThisLanguageAlreadyExists() {
		if ( $this->db->getType() === 'mysql' ) {
			$this->markTestSkipped( 'MySQL doesn\'t support self-joins on temporary tables' );
		}

		$formData = [
			SpecialNewItem::FIELD_LANG => 'en',
			SpecialNewItem::FIELD_LABEL => 'label1',
			SpecialNewItem::FIELD_DESCRIPTION => 'description1',
			SpecialNewItem::FIELD_ALIASES => '',
		];
		$this->executeSpecialPage( '', new FauxRequest( $formData, true ) );

		list( $html ) = $this->executeSpecialPage( '', new FauxRequest( $formData, true ) );

		$this->assertHtmlContainsErrorMessage( $html, 'already has label' );
	}

	public function testErrorAboutNonExistentPageIsDisplayed_WhenSiteExistsButPageDoesNot() {
		$existingSiteId = 'existing-site';
		$formData = [
			SpecialNewItem::FIELD_LANG => 'en',
			SpecialNewItem::FIELD_LABEL => 'some label',
			SpecialNewItem::FIELD_DESCRIPTION => 'some description',
			SpecialNewItem::FIELD_ALIASES => '',
			SpecialNewItem::FIELD_SITE => $existingSiteId,
			SpecialNewItem::FIELD_PAGE => 'nonexistent-page'
		];
		$this->givenSiteWithNoPagesExists( $existingSiteId );

		list( $html ) = $this->executeSpecialPage( '', new FauxRequest( $formData, true ) );

		$this->assertHtmlContainsErrorMessage( $html, 'could not be found on' );
	}

	/**
	 * @param string $itemUrl
	 * @return ItemId
	 */
	protected function extractEntityIdFromUrl( $itemUrl ) {
		$itemIdSerialization = preg_replace( '@^.*(Q\d+)$@', '$1', $itemUrl );
		$itemId = new ItemId( $itemIdSerialization );

		return $itemId;
	}

	/**
	 * @param array $form
	 * @param EntityDocument $entity
	 */
	protected function assertEntityMatchesFormData( array $form, EntityDocument $entity ) {
		$this->assertInstanceOf( Item::class, $entity );
		/** @var Item $entity */

		$language = $form[SpecialNewItem::FIELD_LANG];
		if ( $form[SpecialNewItem::FIELD_LABEL] !== '' ) {
			$this->assertSame(
				trim( $form[SpecialNewItem::FIELD_LABEL] ),
				$entity->getLabels()->getByLanguage( $language )->getText()
			);
		}
		if ( $form[SpecialNewItem::FIELD_DESCRIPTION] !== '' ) {
			$this->assertSame(
				trim( $form[SpecialNewItem::FIELD_DESCRIPTION] ),
				$entity->getDescriptions()->getByLanguage( $language )->getText()
			);
		}
		if ( $form[SpecialNewItem::FIELD_ALIASES] !== '' ) {
			$this->assertArrayEquals(
				explode( '|', $form[SpecialNewItem::FIELD_ALIASES] ),
				$entity->getAliasGroups()->getByLanguage( $language )->getAliases()
			);
		}
	}

	/**
	 * @param string $existingSiteId
	 */
	private function givenSiteWithNoPagesExists( $existingSiteId ) {
		/** @var \PHPUnit_Framework_MockObject_MockObject|Site $siteMock */
		$siteMock = $this->getMock( Site::class, [ 'normalizePageName' ] );
		$siteMock->setGlobalId( $existingSiteId );
		$siteMock->method( 'normalizePageName' )->willReturn( false );

		$this->siteStore->saveSite( $siteMock );
	}

}
