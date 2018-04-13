<?php

namespace Wikibase\View\Tests;

use PHPUnit4And6Compat;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\View\EntityTermsView;
use Wikibase\View\ItemView;
use Wikibase\View\LanguageDirectionalityLookup;
use Wikibase\View\LocalizedTextProvider;
use Wikibase\View\SiteLinksView;
use Wikibase\View\StatementSectionsView;
use Wikibase\View\Template\TemplateFactory;

/**
 * @covers Wikibase\View\ItemView
 * @covers Wikibase\View\EntityView
 *
 * @uses Wikibase\View\Template\Template
 * @uses Wikibase\View\Template\TemplateFactory
 * @uses Wikibase\View\Template\TemplateRegistry
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class ItemViewTest extends EntityViewTestCase {
	use PHPUnit4And6Compat;

	/**
	 * @param EntityId|ItemId $id
	 * @param Statement[] $statements
	 *
	 * @return Item
	 */
	protected function makeEntity( EntityId $id, array $statements = [] ) {
		$item = new Item( $id );

		$item->setLabel( 'en', "label:$id" );
		$item->setDescription( 'en', "description:$id" );

		$item->setStatements( new StatementList( $statements ) );

		return $item;
	}

	/**
	 * Generates a suitable entity ID based on $n.
	 *
	 * @param int|string $n
	 *
	 * @return ItemId
	 */
	protected function makeEntityId( $n ) {
		return new ItemId( "Q$n" );
	}

	public function provideTestGetHtml() {
		$templateFactory = TemplateFactory::getDefaultInstance();
		$itemView = new ItemView(
			$templateFactory,
			$this->getMock( EntityTermsView::class ),
			$this->getMock( LanguageDirectionalityLookup::class ),
			$this->getMockBuilder( StatementSectionsView::class )
				->disableOriginalConstructor()
				->getMock(),
			'en',
			$this->getMockBuilder( SiteLinksView::class )
				->disableOriginalConstructor()
				->getMock(),
			[],
			$this->getMock( LocalizedTextProvider::class )
		);

		return [
			[
				$itemView,
				$this->newEntityForStatements( [] ),
				'/wb-item/'
			]
		];
	}

}
