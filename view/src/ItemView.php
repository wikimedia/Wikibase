<?php

namespace Wikibase\View;

use InvalidArgumentException;
use Language;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\EntityRevision;
use Wikibase\View\Template\TemplateFactory;

/**
 * Class for creating views for Item instances.
 * For the Item this basically is what the Parser is for WikitextContent.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 * @author Daniel Werner
 */
class ItemView extends EntityView {

	/**
	 * @var StatementSectionsView
	 */
	private $statementSectionsView;

	/**
	 * @var SiteLinksView
	 */
	private $siteLinksView;

	/**
	 * @var string[]
	 */
	private $siteLinkGroups;

	/**
	 * @see EntityView::__construct
	 *
	 * @param TemplateFactory $templateFactory
	 * @param EntityTermsView $entityTermsView
	 * @param StatementSectionsView $statementSectionsView
	 * @param Language $language
	 * @param SiteLinksView $siteLinksView
	 * @param string[] $siteLinkGroups
	 */
	public function __construct(
		TemplateFactory $templateFactory,
		EntityTermsView $entityTermsView,
		StatementSectionsView $statementSectionsView,
		Language $language,
		SiteLinksView $siteLinksView,
		array $siteLinkGroups
	) {
		parent::__construct( $templateFactory, $entityTermsView, $language );

		$this->statementSectionsView = $statementSectionsView;
		$this->siteLinksView = $siteLinksView;
		$this->siteLinkGroups = $siteLinkGroups;
	}

	/**
	 * @see EntityView::getMainHtml
	 *
	 * @param EntityRevision $entityRevision
	 *
	 * @throws InvalidArgumentException
	 * @return string HTML
	 */
	protected function getMainHtml( EntityRevision $entityRevision ) {
		$item = $entityRevision->getEntity();

		if ( !( $item instanceof Item ) ) {
			throw new InvalidArgumentException( '$entityRevision must contain an Item.' );
		}

		$html = $this->getHtmlForFingerprint( $entityRevision )
			. $this->templateFactory->render( 'wikibase-toc' )
			. $this->statementSectionsView->getHtml( $item->getStatements() );

		return $html;
	}

	/**
	 * @see EntityView::getSideHtml
	 *
	 * @param EntityDocument $entity
	 *
	 * @return string HTML
	 */
	protected function getSideHtml( EntityDocument $entity ) {
		return $this->getHtmlForSiteLinks( $entity );
	}

	/**
	 * Builds and returns the HTML representing a WikibaseEntity's site-links.
	 *
	 * @since 0.1
	 *
	 * @param Item $item the entity to render
	 *
	 * @return string HTML
	 */
	protected function getHtmlForSiteLinks( Item $item ) {
		return $this->siteLinksView->getHtml(
			$item->getSiteLinks(),
			$item->getId(),
			$this->siteLinkGroups
		);
	}

}
