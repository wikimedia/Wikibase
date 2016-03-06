<?php

namespace Wikibase\View\Tests;

use Language;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\View\EntityTermsView;
use Wikibase\View\ItemView;
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
 * @uses Wikibase\View\TextInjector
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class ItemViewTest extends EntityViewTest {

	protected function makeEntity( EntityId $id, array $statements = array() ) {
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
	 * @return EntityId
	 */
	protected function makeEntityId( $n ) {
		return new ItemId( "Q$n" );
	}

	public function provideTestGetHtml() {
		$templateFactory = TemplateFactory::getDefaultInstance();
		$itemView = new ItemView(
			$templateFactory,
			$this->getMockBuilder( EntityTermsView::class )
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder( StatementSectionsView::class )
				->disableOriginalConstructor()
				->getMock(),
			$this->getMock( Language::class ),
			$this->getMockBuilder( SiteLinksView::class )
				->disableOriginalConstructor()
				->getMock(),
			array()
		);

		return array(
			array(
				$itemView,
				$this->newEntityRevisionForStatements( array() ),
				'/wb-item/'
			)
		);
	}

}
