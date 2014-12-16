<?php

namespace Wikibase\Repo\Hook;

use ChangesList;
use RequestContext;
use ResultWrapper;
use Title;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\Store\TermBuffer;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Store\EntityIdLookup;
use Wikibase\Term;


/**
 * Hook handlers for triggering preloading of labels.
 *
 * Wikibase uses the LinkBegin hook handler
 *
 * @see LinkBeginHookHandler
 *
 * @since 0.5.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class LabelPrefetchHookHandlers {

	/**
	 * @var TermBuffer
	 */
	private $buffer;

	/**
	 * @var EntityIdLookup
	 */
	private $idLookup;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var string[]
	 */
	private $termTypes;

	/**
	 * @var string[]
	 */
	private $languageCodes;

	/**
	 * @return null|LabelPrefetchHookHandlers
	 */
	public static function newFromGlobalState() {
		$termBuffer = WikibaseRepo::getDefaultInstance()->getTermBuffer();

		if ( $termBuffer === null ) {
			return null;
		}

		$idLookup = WikibaseRepo::getDefaultInstance()->getEntityIdLookup();

		// NOTE: keep in sync with fallback chain construction in LinkBeginHookHandler::newFromGlobalState
		$context = RequestContext::getMain();
		$languageFallbackChainFactory = WikibaseRepo::getDefaultInstance()->getLanguageFallbackChainFactory();
		$languageFallbackChain = $languageFallbackChainFactory->newFromContext( $context );

		return new LabelPrefetchHookHandlers(
			$termBuffer,
			$idLookup,
			new TitleFactory(),
			array( Term::TYPE_LABEL, Term::TYPE_DESCRIPTION ),
			$languageFallbackChain->getFetchLanguageCodes()
		);
	}

	/**
	 * Static handler for the ChangesListInitRows hook.
	 *
	 * @param ChangesList $list
	 * @param ResultWrapper|array $rows
	 *
	 * @return bool
	 */
	public static function onChangesListInitRows(
		ChangesList $list,
		$rows
	) {
		$handler = self::newFromGlobalState();

		if ( !$handler ) {
			return true;
		}

		return $handler->doChangesListInitRows( $list, $rows );
	}

	public function __construct(
		TermBuffer $buffer,
		EntityIdLookup $idLookup,
		TitleFactory $titleFactory,
		array $termTypes,
		array $languageCodes
	) {

		$this->buffer = $buffer;
		$this->idLookup = $idLookup;
		$this->titleFactory = $titleFactory;
		$this->termTypes = $termTypes;
		$this->languageCodes = $languageCodes;
	}

	/**
	 * @param ChangesList $list
	 * @param ResultWrapper|array $rows
	 *
	 * @return bool
	 */
	public function doChangesListInitRows( ChangesList $list, array $rows ) {
		try {
			$titles = $this->getChangedTitles( $rows );
			$entityIds = $this->idLookup->getEntityIds( $titles );
			$this->buffer->prefetchTerms( $entityIds, $this->termTypes, $this->languageCodes );
		} catch ( StorageException $ex ) {
			wfLogWarning( __METHOD__ . ': ' . $ex->getMessage() );
		}

		return true;
	}

	/**
	 * @param object[] $rows
	 *
	 * @return Title[]
	 */
	private function getChangedTitles( array $rows ) {
		$titles = array();

		foreach ( $rows as $row ) {
			$titles[] = $this->titleFactory->makeTitle( $row->rc_namespace, $row->rc_title );
		}

		return $titles;
	}
}
