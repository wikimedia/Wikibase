<?php

namespace Wikibase\View\Tests;

use PHPUnit4And6Compat;
use Wikibase\Lib\DataTypeFactory;
use HashSiteStore;
use InvalidArgumentException;
use ValueFormatters\BasicNumberLocalizer;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Services\Statement\Grouper\NullStatementGrouper;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\PropertyOrderProvider;
use Wikibase\View\EditSectionGenerator;
use Wikibase\View\EntityTermsView;
use Wikibase\View\HtmlSnakFormatterFactory;
use Wikibase\View\ItemView;
use Wikibase\View\LanguageDirectionalityLookup;
use Wikibase\View\LocalizedTextProvider;
use Wikibase\View\PropertyView;
use Wikibase\View\StatementSectionsView;
use Wikibase\View\ViewFactory;
use Wikibase\View\EntityIdFormatterFactory;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\View\Template\TemplateRegistry;

/**
 * @covers Wikibase\View\ViewFactory
 *
 * @uses Wikibase\View\StatementHtmlGenerator
 * @uses Wikibase\View\EditSectionGenerator
 * @uses Wikibase\View\EntityTermsView
 * @uses Wikibase\View\EntityView
 * @uses Wikibase\View\ItemView
 * @uses Wikibase\View\PropertyView
 * @uses Wikibase\View\SiteLinksView
 * @uses Wikibase\View\SnakHtmlGenerator
 * @uses Wikibase\View\StatementGroupListView
 * @uses Wikibase\View\StatementSectionsView
 * @uses Wikibase\View\Template\Template
 * @uses Wikibase\View\Template\TemplateFactory
 * @uses Wikibase\View\Template\TemplateRegistry
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Thiemo Kreuz
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class ViewFactoryTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	private function newViewFactory(
		EntityIdFormatterFactory $htmlFactory = null,
		EntityIdFormatterFactory $plainFactory = null
	) {
		$templateFactory = new TemplateFactory( new TemplateRegistry( [] ) );

		$languageNameLookup = $this->getMock( LanguageNameLookup::class );
		$languageNameLookup->expects( $this->never() )
			->method( 'getName' );

		return new ViewFactory(
			$htmlFactory ?: $this->getEntityIdFormatterFactory( SnakFormatter::FORMAT_HTML ),
			$plainFactory ?: $this->getEntityIdFormatterFactory( SnakFormatter::FORMAT_PLAIN ),
			$this->getSnakFormatterFactory(),
			new NullStatementGrouper(),
			$this->getMock( PropertyOrderProvider::class ),
			new HashSiteStore(),
			new DataTypeFactory( [] ),
			$templateFactory,
			$languageNameLookup,
			$this->getMock( LanguageDirectionalityLookup::class ),
			new BasicNumberLocalizer(),
			[],
			[],
			[],
			$this->getMock( LocalizedTextProvider::class )
		);
	}

	/**
	 * @dataProvider invalidConstructorArgumentsProvider
	 */
	public function testConstructorThrowsException(
		EntityIdFormatterFactory $htmlFormatterFactory,
		EntityIdFormatterFactory $plainFormatterFactory
	) {
		$this->setExpectedException( InvalidArgumentException::class );
		$this->newViewFactory( $htmlFormatterFactory, $plainFormatterFactory );
	}

	public function invalidConstructorArgumentsProvider() {
		$htmlFactory = $this->getEntityIdFormatterFactory( SnakFormatter::FORMAT_HTML );
		$plainFactory = $this->getEntityIdFormatterFactory( SnakFormatter::FORMAT_PLAIN );
		$wikiFactory = $this->getEntityIdFormatterFactory( SnakFormatter::FORMAT_WIKI );

		return [
			[ $wikiFactory, $plainFactory ],
			[ $htmlFactory, $wikiFactory ],
		];
	}

	public function testNewItemView() {
		$factory = $this->newViewFactory();
		$editSectionGenerator = $this->getMock( EditSectionGenerator::class );
		$itemView = $factory->newItemView(
			'de',
			$this->getMock( LabelDescriptionLookup::class ),
			new LanguageFallbackChain( [] ),
			$editSectionGenerator,
			$this->getMock( EntityTermsView::class )
		);

		$this->assertInstanceOf( ItemView::class, $itemView );
	}

	public function testNewPropertyView() {
		$factory = $this->newViewFactory();
		$editSectionGenerator = $this->getMock( EditSectionGenerator::class );
		$propertyView = $factory->newPropertyView(
			'de',
			$this->getMock( LabelDescriptionLookup::class ),
			new LanguageFallbackChain( [] ),
			$editSectionGenerator,
			$this->getMock( EntityTermsView::class )
		);

		$this->assertInstanceOf( PropertyView::class, $propertyView );
	}

	public function testNewStatementSectionsView() {
		$statementSectionsView = $this->newViewFactory()->newStatementSectionsView(
			'de',
			$this->getMock( LabelDescriptionLookup::class ),
			new LanguageFallbackChain( [] ),
			$this->getMock( EditSectionGenerator::class )
		);

		$this->assertInstanceOf( StatementSectionsView::class, $statementSectionsView );
	}

	/**
	 * @param string $format
	 *
	 * @return EntityIdFormatterFactory
	 */
	private function getEntityIdFormatterFactory( $format ) {
		$entityIdFormatter = $this->getMock( EntityIdFormatter::class );

		$formatterFactory = $this->getMock( EntityIdFormatterFactory::class );

		$formatterFactory->method( 'getOutputFormat' )
			->will( $this->returnValue( $format ) );

		$formatterFactory->method( 'getEntityIdFormatter' )
			->will( $this->returnValue( $entityIdFormatter ) );

		return $formatterFactory;
	}

	/**
	 * @return HtmlSnakFormatterFactory
	 */
	private function getSnakFormatterFactory() {
		$snakFormatter = $this->getMock( SnakFormatter::class );

		$snakFormatter->method( 'getFormat' )
			->will( $this->returnValue( SnakFormatter::FORMAT_HTML ) );

		$snakFormatterFactory = $this->getMock( HtmlSnakFormatterFactory::class );

		$snakFormatterFactory->method( 'getSnakFormatter' )
			->will( $this->returnValue( $snakFormatter ) );

		return $snakFormatterFactory;
	}

}
