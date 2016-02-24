<?php

namespace Wikibase\Test;

use FauxRequest;
use SpecialPageTestBase;
use Wikibase\Lib\StaticContentLanguages;
use Wikibase\Repo\Specials\SpecialEntitiesWithoutPage;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\TermIndexEntry;

/**
 * @covers Wikibase\Repo\Specials\SpecialEntitiesWithoutPage
 * @covers Wikibase\Repo\Specials\SpecialWikibaseQueryPage
 * @covers Wikibase\Repo\Specials\SpecialWikibasePage
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group SpecialPage
 * @group WikibaseSpecialPage
 *
 * @group Database
 *        ^---- needed because we rely on Title objects internally
 *
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@googlemail.com >
 * @author Addshore
 * @author Thiemo Mättig
 */
class SpecialEntitiesWithoutPageTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		return new SpecialEntitiesWithoutPage(
			'EntitiesWithoutLabel',
			TermIndexEntry::TYPE_LABEL,
			'wikibase-entitieswithoutlabel-legend',
			$wikibaseRepo->getStore()->newEntityPerPage(),
			$wikibaseRepo->getEntityFactory(),
			new StaticContentLanguages( array( 'acceptedlanguage' ) )
		);
	}

	public function testForm() {
		list( $html, ) = $this->executeSpecialPage( '', null, 'qqx' );

		$this->assertContains( '(wikibase-entitieswithoutlabel-label-language)', $html );
		$this->assertContains( 'name="language"', $html );
		$this->assertContains( 'id="wb-entitieswithoutpage-language"', $html );
		$this->assertContains( 'wb-language-suggester', $html );

		$this->assertContains( '(wikibase-entitieswithoutlabel-label-type)', $html );
		$this->assertContains( 'name="type"', $html );
		$this->assertContains( 'id="wb-entitieswithoutpage-type"', $html );
		$this->assertContains( '(wikibase-entitieswithoutlabel-label-alltypes)', $html );

		$this->assertContains( '(wikibase-entitieswithoutlabel-submit)', $html );
		$this->assertContains( 'id="wikibase-entitieswithoutpage-submit"', $html );
	}

	public function testRequestParameters() {
		$request = new FauxRequest( array(
			'language' => '<LANGUAGE>',
			'type' => '<TYPE>',
		) );
		list( $html, ) = $this->executeSpecialPage( '', $request );

		$this->assertContains( '&lt;LANGUAGE&gt;', $html );
		$this->assertContains( '&lt;TYPE&gt;', $html );
		$this->assertNotContains( '<LANGUAGE>', $html );
		$this->assertNotContains( '<TYPE>', $html );
		$this->assertNotContains( '&amp;', $html, 'no double escaping' );
	}

	public function testSubPageParts() {
		list( $html, ) = $this->executeSpecialPage( '<LANGUAGE>/<TYPE>' );

		$this->assertContains( '&lt;LANGUAGE&gt;', $html );
		$this->assertContains( '&lt;TYPE&gt;', $html );
	}

	public function testNoLanguage() {
		list( $html, ) = $this->executeSpecialPage( '', null, 'qqx' );

		$this->assertNotContains( 'class="mw-spcontent"', $html );
	}

	public function testInvalidLanguage() {
		list( $html, ) = $this->executeSpecialPage( '<INVALID>', null, 'qqx' );

		$this->assertContains(
			'(wikibase-entitieswithoutlabel-invalid-language: &lt;INVALID&gt;)',
			$html
		);
	}

	public function testValidLanguage() {
		list( $html, ) = $this->executeSpecialPage( 'acceptedlanguage', null, 'qqx' );

		$this->assertContains( 'value="acceptedlanguage"', $html );
		$this->assertContains( 'class="mw-spcontent"', $html );
	}

	public function testInvalidType() {
		list( $html, ) = $this->executeSpecialPage( 'acceptedlanguage/<INVALID>', null, 'qqx' );

		$this->assertContains(
			'(wikibase-entitieswithoutlabel-invalid-type: &lt;INVALID&gt;)',
			$html
		);
	}

}
