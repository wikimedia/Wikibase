<?php

namespace Wikibase\Repo\ParserOutput;

use InvalidArgumentException;
use ParserOutput;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Statement\StatementListProvider;

/**
 * @todo have ItemParserOutputDataUpdate, etc. instead.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Thiemo Mättig
 */
class EntityParserOutputDataUpdater {

	/**
	 * @var ParserOutput
	 */
	private $parserOutput;

	/**
	 * @var ParserOutputDataUpdater[]
	 */
	private $dataUpdaters;

	/**
	 * @var StatementDataUpdater[]
	 */
	private $statementDataUpdaters = array();

	/**
	 * @var SiteLinkDataUpdater[]
	 */
	private $siteLinkDataUpdaters = array();

	/**
	 * @param ParserOutput $parserOutput
	 * @param ParserOutputDataUpdater[] $dataUpdaters
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( ParserOutput $parserOutput, array $dataUpdaters ) {
		foreach ( $dataUpdaters as $dataUpdater ) {
			if ( $dataUpdater instanceof StatementDataUpdater ) {
				$this->statementDataUpdaters[] = $dataUpdater;
			} elseif ( $dataUpdater instanceof SiteLinkDataUpdater ) {
				$this->siteLinkDataUpdaters[] = $dataUpdater;
			} else {
				throw new InvalidArgumentException( 'Each $dataUpdaters element must be a '
					. 'StatementDataUpdater, SiteLinkDataUpdater or both' );
			}
		}

		$this->parserOutput = $parserOutput;
		$this->dataUpdaters = $dataUpdaters;
	}

	/**
	 * @param EntityDocument $entity
	 */
	public function processEntity( EntityDocument $entity ) {
		if ( $entity instanceof StatementListProvider ) {
			$this->processStatementListProvider( $entity );
		}

		if ( $entity instanceof Item ) {
			$this->processItem( $entity );
		}
	}

	/**
	 * @param StatementListProvider $entity
	 */
	private function processStatementListProvider( StatementListProvider $entity ) {
		if ( empty( $this->statementDataUpdaters ) ) {
			return;
		}

		foreach ( $entity->getStatements() as $statement ) {
			foreach ( $this->statementDataUpdaters as $dataUpdater ) {
				$dataUpdater->processStatement( $statement );
			}
		}
	}

	/**
	 * @param Item $item
	 */
	private function processItem( Item $item ) {
		if ( empty( $this->siteLinkDataUpdaters ) ) {
			return;
		}

		foreach ( $item->getSiteLinkList() as $siteLink ) {
			foreach ( $this->siteLinkDataUpdaters as $dataUpdater ) {
				$dataUpdater->processSiteLink( $siteLink );
			}
		}
	}

	public function finish() {
		foreach ( $this->dataUpdaters as $dataUpdater ) {
			$dataUpdater->updateParserOutput( $this->parserOutput );
		}
	}

}
