<?php

namespace Wikibase\View;

use InvalidArgumentException;
use Wikibase\DataModel\Services\Statement\Grouper\StatementGrouper;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\View\Template\TemplateFactory;

/**
 * @license GPL-2.0+
 * @author Thiemo Mättig
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
class StatementSectionsView {

	/**
	 * @var TemplateFactory
	 */
	private $templateFactory;

	/**
	 * @var StatementGrouper
	 */
	private $statementGrouper;

	/**
	 * @var StatementGroupListView
	 */
	private $statementListView;

	/**
	 * @var LocalizedTextProvider
	 */
	private $textProvider;

	public function __construct(
		TemplateFactory $templateFactory,
		StatementGrouper $statementGrouper,
		StatementGroupListView $statementListView,
		LocalizedTextProvider $textProvider
	) {
		$this->templateFactory = $templateFactory;
		$this->statementGrouper = $statementGrouper;
		$this->statementListView = $statementListView;
		$this->textProvider = $textProvider;
	}

	/**
	 * @param StatementList $statementList
	 *
	 * @throws InvalidArgumentException
	 * @return string HTML
	 */
	public function getHtml( StatementList $statementList ) {
		return $this->getStatementSectionHtml( $statementList, false );
	}

	/**
	 * @param StatementList $statementList
	 *
	 * @throws InvalidArgumentException
	 * @return string HTML
	 */
	public function getHtmlWithStatementDataAttribute( StatementList $statementList ) {
		return $this->getStatementSectionHtml( $statementList, true );
	}

	/**
	 * @param string $key
	 *
	 * @return string HTML
	 */
	private function getHtmlForSectionHeading( $key ) {
		/**
		 * Message keys:
		 * wikibase-statementsection-statements
		 * wikibase-statementsection-identifiers
		 */
		$messageKey = 'wikibase-statementsection-' . strtolower( $key );
		$className = 'wikibase-statements';

		if ( $key === 'statements' ) {
			$id = 'claims';
		} else {
			$id = $key;
			$className .= ' wikibase-statements-' . $key;
		}

		// TODO: Add link to SpecialPage that allows adding a new statement.
		return $this->templateFactory->render(
			'wb-section-heading',
			htmlspecialchars( $this->textProvider->get( $messageKey ) ),
			$id,
			$className
		);
	}

	/**
	 * @param StatementList $statementList
	 * @return string
	 */
	private function getStatementSectionHtml( StatementList $statementList, $includeStatementSerialization ) {
		$statementLists = $this->statementGrouper->groupStatements( $statementList );
		$html = '';

		foreach ( $statementLists as $key => $statements ) {
			if ( !is_string( $key ) || !( $statements instanceof StatementList ) ) {
				throw new InvalidArgumentException(
					'$statementLists must be an associative array of StatementList objects'
				);
			}

			if ( $key !== 'statements' && $statements->isEmpty() ) {
				continue;
			}

			$html .= $this->getHtmlForSectionHeading( $key );
			$html .= $this->statementListView->getHtml( $statements->toArray(), $includeStatementSerialization );
		}

		return $html;
	}

}
