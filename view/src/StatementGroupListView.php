<?php

namespace Wikibase\View;

use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\Store\PropertyOrderProvider;
use Wikibase\View\Template\TemplateFactory;

/**
 * Generates HTML to display statements.
 *
 * @license GPL-2.0+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Daniel Kinzler
 */
class StatementGroupListView {

	/**
	 * @var PropertyOrderProvider
	 */
	private $propertyOrderProvider;

	/**
	 * @var TemplateFactory
	 */
	private $templateFactory;

	/**
	 * @var EntityIdFormatter
	 */
	private $propertyIdFormatter;

	/**
	 * @var EditSectionGenerator
	 */
	private $editSectionGenerator;

	/**
	 * @var ClaimHtmlGenerator
	 */
	private $claimHtmlGenerator;

	/**
	 * @param PropertyOrderProvider $propertyOrderProvider
	 * @param TemplateFactory $templateFactory
	 * @param EntityIdFormatter $propertyIdFormatter
	 * @param EditSectionGenerator $sectionEditLinkGenerator
	 * @param ClaimHtmlGenerator $claimHtmlGenerator
	 */
	public function __construct(
		PropertyOrderProvider $propertyOrderProvider,
		TemplateFactory $templateFactory,
		EntityIdFormatter $propertyIdFormatter,
		EditSectionGenerator $sectionEditLinkGenerator,
		ClaimHtmlGenerator $claimHtmlGenerator
	) {
		$this->propertyOrderProvider = $propertyOrderProvider;
		$this->propertyIdFormatter = $propertyIdFormatter;
		$this->editSectionGenerator = $sectionEditLinkGenerator;
		$this->claimHtmlGenerator = $claimHtmlGenerator;
		$this->templateFactory = $templateFactory;
	}

	/**
	 * Builds and returns the HTML representing a WikibaseEntity's statements.
	 *
	 * @param Statement[] $statements
	 * @param bool $includeStatementSerialization Flag indicating whether individual statement's
	 * 			serialization should be included in HTML as a data attribute
	 * @return string HTML
	 */
	public function getHtml( array $statements, $includeStatementSerialization ) {
		$statementsByProperty = $this->orderStatementsByPropertyOrder(
			$this->groupStatementsByProperties( $statements )
		);

		$statementsHtml = '';
		foreach ( $statementsByProperty as $statements ) {
			$statementsHtml .= $this->getHtmlForStatementGroupView( $statements, $includeStatementSerialization );
		}

		return $this->templateFactory->render(
			'wikibase-statementgrouplistview',
			$this->templateFactory->render( 'wikibase-listview', $statementsHtml )
		);
	}

	/**
	 * @param Statement[] $statements
	 *
	 * @return array[]
	 */
	private function groupStatementsByProperties( array $statements ) {
		$byProperty = array();

		foreach ( $statements as $statement ) {
			$propertyId = $statement->getPropertyId();
			$byProperty[$propertyId->getSerialization()][] = $statement;
		}

		return $byProperty;
	}

	/**
	 * @param array[] $statementsByProperty The array keys are expected to be Property ID
	 *  serializations.
	 *
	 * @return array[]
	 */
	private function orderStatementsByPropertyOrder( array $statementsByProperty ) {
		$propertyOrder = $this->propertyOrderProvider->getPropertyOrder();

		if ( !$propertyOrder ) {
			return $statementsByProperty;
		}

		$ordered = [];
		$unordered = [];

		foreach ( $statementsByProperty as $propertyId => $statements ) {
			if ( isset( $propertyOrder[$propertyId] ) ) {
				$ordered[$propertyOrder[$propertyId]] = $statements;
			} else {
				$unordered[] = $statements;
			}
		}

		ksort( $ordered );
		return array_merge( $ordered, $unordered );
	}

	/**
	 * @param Statement[] $statements
	 * @param bool $includeStatementSerialization Flag indicating whether individual statement's
	 * 			serialization should be included in HTML as a data attribute
	 *
	 * @return string HTML
	 */
	private function getHtmlForStatementGroupView( array $statements, $includeStatementSerialization ) {
		$propertyId = $statements[0]->getPropertyId();
		$addStatementHtml = $this->editSectionGenerator->getAddStatementToGroupSection( $propertyId );

		return $this->templateFactory->render(
			'wikibase-statementgroupview',
			$this->propertyIdFormatter->formatEntityId( $propertyId ),
			$this->getHtmlForStatementListView( $statements, $addStatementHtml, $includeStatementSerialization ),
			$propertyId->getSerialization()
		);
	}

	/**
	 * @param Statement[] $statements
	 * @param string $addStatementHtml
	 * @param bool $includeStatementSerialization Flag indicating whether individual statement's
	 * 			serialization should be included in HTML as a data attribute
	 *
	 * @return string HTML
	 */
	private function getHtmlForStatementListView( array $statements, $addStatementHtml, $includeStatementSerialization ) {
		$statementViewsHtml = '';

		foreach ( $statements as $statement ) {
			$statementViewsHtml .= $this->claimHtmlGenerator->getHtmlForClaim(
				$statement,
				$this->editSectionGenerator->getStatementEditSection( $statement ),
				$includeStatementSerialization
			);
		}

		return $this->templateFactory->render( 'wikibase-statementlistview',
			$statementViewsHtml,
			$addStatementHtml
		);
	}

}
