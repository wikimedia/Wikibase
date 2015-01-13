<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityTermLookup;
use Wikibase\Lib\Store\LanguageLabelLookup;

/**
 * @covers Wikibase\Lib\Store\LanguageLabelLookup
 *
 * @group Wikibase
 * @group WikibaseLib
 * @group WikibaseStore
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class LanguageLabelLookupTest extends \MediaWikiTestCase {

	public function testGetLabel() {
		$termLookup = $this->getTermLookup();
		$labelLookup = new LanguageLabelLookup( $termLookup, 'en' );

		$term = $labelLookup->getLabel( new ItemId( 'Q116' ) );

		$this->assertEquals( 'New York City', $term->getText() );
		$this->assertEquals( 'en', $term->getLanguageCode() );
	}

	public function testGetLabel_entityNotFound() {
		$termLookup = $this->getTermLookup();
		$labelLookup = new LanguageLabelLookup( $termLookup, 'en' );

		$this->setExpectedException( 'OutOfBoundsException' );
		$labelLookup->getLabel( new ItemId( 'Q120' ) );
	}

	public function testGetLabel_notFound() {
		$termLookup = $this->getTermLookup();
		$labelLookup = new LanguageLabelLookup( $termLookup, 'fa' );

		$this->setExpectedException( 'OutOfBoundsException' );

		$labelLookup->getLabel( new ItemId( 'Q116' ) );
	}

	private function getTermLookup() {
		return new EntityTermLookup( $this->getTermIndex() );
	}

	private function getTermIndex() {
		$terms = array(
			new \Wikibase\Term( array(
				'entityId' => 116,
				'entityType' => 'item',
				'termType' => 'label',
				'termLanguage' => 'en',
				'termText' => 'New York City'
			) ),
			new \Wikibase\Term( array(
				'entityId' => 116,
				'entityType' => 'item',
				'termType' => 'label',
				'termLanguage' => 'es',
				'termText' => 'New York City'
			) ),
			new \Wikibase\Term( array(
				'entityId' => 116,
				'entityType' => 'item',
				'termType' => 'description',
				'termLanguage' => 'en',
				'termText' => 'Big Apple'
			) ),
			new \Wikibase\Term( array(
				'entityId' => 117,
				'entityType' => 'item',
				'termType' => 'label',
				'termLanguage' => 'en',
				'termText' => 'Berlin'
			) ),
			new \Wikibase\Term( array(
				'entityId' => 118,
				'entityType' => 'item',
				'termType' => 'label',
				'termLanguage' => 'zh-cn',
				'termText' => '测试'
			) ),
		);

		return new MockTermIndex( $terms );
	}

}
