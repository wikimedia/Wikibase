<?php

namespace Wikibase\Lib\Store\Sql\Terms;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikibase\Lib\Store\Sql\Terms\Util\ReplicaMasterAwareRecordIdsAcquirer;
use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * A {@link TermIdsAcquirer} implementation using the database tables
 * wbt_term_in_lang, wbt_text_in_lang, and wbt_text.
 *
 * Because the wbt_text.wbx_text column can only hold up to 255 bytes,
 * terms longer than that (typically non-Latin descriptions)
 * will be truncated, and different terms that only differ after the first
 * 255 bytes will get the same ID.
 *
 * @see @ref md_docs_storage_terms
 * @license GPL-2.0-or-later
 */
class DatabaseTermIdsAcquirer implements TermIdsAcquirer {

	/**
	 * @var ILBFactory
	 */
	private $lbFactory;

	/**
	 * @var TypeIdsAcquirer
	 */
	private $typeIdsAcquirer;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param ILBFactory $lbFactory
	 * @param TypeIdsAcquirer $typeIdsAcquirer
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(
		ILBFactory $lbFactory,
		TypeIdsAcquirer $typeIdsAcquirer,
		LoggerInterface $logger = null
	) {
		$this->lbFactory = $lbFactory;
		$this->typeIdsAcquirer = $typeIdsAcquirer;
		$this->logger = $logger ?? new NullLogger();
	}

	public function acquireTermIds( array $termsArray, $callback = null ): array {
		if ( $termsArray === [] ) {
			if ( $callback !== null ) {
				( $callback )( [] );
			}
			return [];
		}

		$termIds = $this->mapTermsArrayToTermIds( $termsArray );

		if ( $callback !== null ) {
			( $callback )( $termIds );
		}

		$this->restoreCleanedUpIds( $termsArray, $termIds );

		return $termIds;
	}

	private function getLoadBalancer(): ILoadBalancer {
		return $this->lbFactory->getMainLB();
	}

	/**
	 * replace root keys containing type names in termsArray
	 * with their respective ids in wbt_type table
	 *
	 * @param array $termsArray terms per type per language:
	 *	[
	 *		'type1' => [ ... ],
	 *		'type2' => [ ... ],
	 *		...
	 *	]
	 *
	 * @return array
	 *	[
	 *		<typeId1> => [ ... ],
	 *		<typeId2> => [ ... ],
	 *		...
	 *	]
	 */
	private function mapToTypeIds( array $termsArray ) {
		$typeIds = $this->typeIdsAcquirer->acquireTypeIds( array_keys( $termsArray ) );

		$termsArrayByTypeId = [];
		foreach ( $typeIds as $type => $typeId ) {
			$termsArrayByTypeId[$typeId] = $termsArray[$type];
		}

		return $termsArrayByTypeId;
	}

	/**
	 * replace text at termsArray leaves with their ids in wbt_text table
	 * and return resulting array
	 *
	 * @param array $termsArray terms per type per language:
	 *	[
	 *		'type' => [
	 *			[ 'language' => 'term' | [ 'term1', 'term2', ... ] ], ...
	 *		], ...
	 *	]
	 *
	 * @return array
	 *	[
	 *		'type' => [
	 *			[ 'language' => [ <textId1>, <textId2>, ... ] ], ...
	 *		], ...
	 *	]
	 */
	private function mapToTextIds(
		array $termsArray,
		ReplicaMasterAwareRecordIdsAcquirer $textIdsAcquirer
	) {
		$texts = [];

		array_walk_recursive( $termsArray, function ( $text ) use ( &$texts ) {
			$texts[] = $text;
		} );

		$textIds = $this->acquireTextIds( $texts, $textIdsAcquirer );

		array_walk_recursive( $termsArray, function ( &$text ) use ( $textIds ) {
			$text = $textIds[$text];
		} );

		return $termsArray;
	}

	/*
	 * Since the wbx_text column can hold at most 255 bytes, we truncate the
	 * the texts to that length before sending them to the acquirer.
	 * Additional mappings ensure that we can still return a map from full,
	 * untruncated texts to text IDs (though multiple texts may share the same
	 * ID if they only differ after more than 255 bytes).
	 */
	private function acquireTextIds(
		array $texts,
		ReplicaMasterAwareRecordIdsAcquirer $textIdsAcquirer
	) {
		global $wgContLang;

		$truncatedTexts = [];
		foreach ( $texts as $text ) {
			$truncatedText = $wgContLang->truncateForDatabase( $text, 255, '' );
			$truncatedTexts[$text] = $truncatedText;
		}

		$truncatedTextRecords = [];
		foreach ( $truncatedTexts as $truncatedText ) {
			$truncatedTextRecords[] = [ 'wbx_text' => $truncatedText ];
		}
		$truncatedTextRecords = $this->filterUniqueRecords( $truncatedTextRecords );

		$truncatedTextRecordsWithIds = $textIdsAcquirer->acquireIds( $truncatedTextRecords );
		$truncatedTextIds = [];
		foreach ( $truncatedTextRecordsWithIds as $truncatedTextRecordWithId ) {
			$truncatedText = $truncatedTextRecordWithId['wbx_text'];
			$truncatedTextId = $truncatedTextRecordWithId['wbx_id'];
			$truncatedTextIds[$truncatedText] = $truncatedTextId;
		}

		$textIds = [];
		foreach ( $truncatedTexts as $text => $truncatedText ) {
			$textIds[$text] = $truncatedTextIds[$truncatedText];
		}

		return $textIds;
	}

	/**
	 * replace ( lang => [ textId, ... ] ) entries with their respective ids
	 * in wbt_text_in_lang table and return resulting array
	 *
	 * @param array $termsArray text ids per type per language
	 *	[
	 *		'type' => [
	 *			[ 'language' => [ <textId1>, <textId2>, ... ] ], ...
	 *		], ...
	 *	]
	 *
	 * @return array
	 *	[
	 *		'type' => [ <textInLangId1>, <textInLangId2>, ... ],
	 *		...
	 *	]
	 */
	private function mapToTextInLangIds(
		array $termsArray,
		ReplicaMasterAwareRecordIdsAcquirer $textInLangIdsAcquirer
	) {
		$flattenedLangTextIds = [];
		foreach ( $termsArray as $langTextIds ) {
			foreach ( $langTextIds as $lang => $textIds ) {
				if ( !isset( $flattenedLangTextIds[$lang] ) ) {
					$flattenedLangTextIds[$lang] = [];
				}

				$flattenedLangTextIds[$lang] = array_unique(
					array_merge(
						(array)$textIds,
						(array)$flattenedLangTextIds[$lang]
					)
				);

			}
		}

		$textInLangIds = $this->acquireTextInLangIds(
			$flattenedLangTextIds,
			$textInLangIdsAcquirer
		);

		$newTermsArray = [];
		foreach ( $termsArray as $type => $langTextIds ) {
			$newTermsArray[$type] = [];
			foreach ( $langTextIds as $lang => $textIds ) {
				foreach ( (array)$textIds as $textId ) {
					$newTermsArray[$type][] = $textInLangIds[$lang][$textId];
				}
			}
		}

		return $newTermsArray;
	}

	private function acquireTextInLangIds(
		array $langTextIds,
		ReplicaMasterAwareRecordIdsAcquirer $textInLangIdsAcquirer
	) {
		$textInLangRecords = [];
		foreach ( $langTextIds as $lang => $textIds ) {
			foreach ( $textIds as $textId ) {
				$textInLangRecords[] = [ 'wbxl_text_id' => $textId, 'wbxl_language' => $lang ];
			}
		}
		$textInLangRecords = $this->filterUniqueRecords( $textInLangRecords );

		$acquiredIds = $textInLangIdsAcquirer->acquireIds( $textInLangRecords );

		$textInLangIds = [];
		foreach ( $acquiredIds as $acquiredId ) {
			$textInLangIds[$acquiredId['wbxl_language']][$acquiredId['wbxl_text_id']]
				= $acquiredId['wbxl_id'];
		}

		return $textInLangIds;
	}

	/**
	 * replace root ( type => [ textInLangId, ... ] ) entries with their respective ids
	 * in wbt_term_in_lang table and return resulting array
	 *
	 * @param array $termsArray text in lang ids per type
	 *	[
	 *		'type' => [ <textInLangId1>, <textInLangId2>, ... ],
	 *		...
	 *	]
	 *
	 * @return array
	 *	[
	 *		<termInLang1>,
	 *		<termInLang2>,
	 *		...
	 *	]
	 */
	private function mapToTermInLangIds(
		array $termsArray,
		ReplicaMasterAwareRecordIdsAcquirer $termInLangIdsAcquirer,
		array $idsToRestore = []
	) {
		$flattenedTypeTextInLangIds = [];
		foreach ( $termsArray as $typeId => $textInLangIds ) {
			if ( !isset( $flattenedTypeTextInLangIds[$typeId] ) ) {
				$flattenedTypeTextInLangIds[$typeId] = [];
			}

			$flattenedTypeTextInLangIds[$typeId] = array_unique(
				array_merge(
					(array)$textInLangIds,
					(array)$flattenedTypeTextInLangIds[$typeId]
				)
			);
		}

		$termInLangIds = $this->acquireTermInLangIds(
			$flattenedTypeTextInLangIds,
			$termInLangIdsAcquirer,
			$idsToRestore
		);

		$newTermsArray = [];
		foreach ( $termsArray as $typeId => $textInLangIds ) {
			foreach ( $textInLangIds as $textInLangId ) {
				$newTermsArray[] = $termInLangIds[$typeId][$textInLangId];
			}
		}

		return $newTermsArray;
	}

	private function acquireTermInLangIds(
		array $typeTextInLangIds,
		ReplicaMasterAwareRecordIdsAcquirer $termInLangIdsAcquirer,
		array $idsToRestore = []
	) {
		$termInLangRecords = [];
		foreach ( $typeTextInLangIds as $typeId => $textInLangIds ) {
			foreach ( $textInLangIds as $textInLangId ) {
				$termInLangRecords[] = [
					'wbtl_text_in_lang_id' => $textInLangId,
					'wbtl_type_id' => (string)$typeId
				];
			}
		}
		$termInLangRecords = $this->filterUniqueRecords( $termInLangRecords );
		$fname = __METHOD__;

		$acquiredIds = $termInLangIdsAcquirer->acquireIds(
			$termInLangRecords,
			function ( $recordsToInsert ) use ( $idsToRestore, $fname ) {
				if ( count( $idsToRestore ) <= 0 ) {
					return $recordsToInsert;
				}

				if ( count( $idsToRestore ) !== count( $recordsToInsert ) ) {
					$exception = new Exception(
						'Fail-safe exception. Number of ids to be restored is not equal to'
						. ' the number of records that are about to be inserted into master.'
						. ' This should never happen, except for an edge-case that was not'
						. ' detected during development or due to a race-condition that is'
						. ' not covered by this implementation.'
					);

					$this->logger->warning(
						'{method}: Restoring record term in lang ids failed: {exception}',
						[
							'method' => $fname,
							'exception' => $exception,
							'idsToRestore' => $idsToRestore,
							'recordsToInsert' => $recordsToInsert,
						]
					);

					throw $exception;
				}

				return array_map(
					function ( $record, $idToRestore ) {
						$record['wbtl_id'] = $idToRestore;
						return $record;
					},
					$recordsToInsert,
					$idsToRestore
				);
			} );

		$termInLangIds = [];
		foreach ( $acquiredIds as $acquiredId ) {
			$termInLangIds[$acquiredId['wbtl_type_id']][$acquiredId['wbtl_text_in_lang_id']]
				= $acquiredId['wbtl_id'];
		}

		return $termInLangIds;
	}

	private function restoreCleanedUpIds( array $termsArray, array $termIds = [] ) {
		$uniqueTermIds = array_values( array_unique( $termIds ) );

		$dbMaster = $this->getLoadBalancer()->getConnection( ILoadBalancer::DB_MASTER );
		$persistedTermIds = $dbMaster->selectFieldValues(
			'wbt_term_in_lang',
			'wbtl_id',
			[ 'wbtl_id' => $termIds ],
			__METHOD__
		);

		sort( $uniqueTermIds );
		sort( $persistedTermIds );
		$idsToRestore = array_diff( $uniqueTermIds, $persistedTermIds );

		if ( !empty( $idsToRestore ) ) {
			$this->mapTermsArrayToTermIds( $termsArray, $idsToRestore, true );
		}
	}

	private function mapTermsArrayToTermIds(
		array $termsArray,
		array $termIdsToRestore = [],
		$ignoreReplica = false
	): array {
		$textIdsAcquirer = new ReplicaMasterAwareRecordIdsAcquirer(
			$this->lbFactory, 'wbt_text', 'wbx_id', $this->logger,
			$ignoreReplica ? ReplicaMasterAwareRecordIdsAcquirer::FLAG_IGNORE_REPLICA : 0x0 );
		$textInLangIdsAcquirer = new ReplicaMasterAwareRecordIdsAcquirer(
			$this->lbFactory, 'wbt_text_in_lang', 'wbxl_id', $this->logger,
			$ignoreReplica ? ReplicaMasterAwareRecordIdsAcquirer::FLAG_IGNORE_REPLICA : 0x0 );
		$termInLangIdsAcquirer = new ReplicaMasterAwareRecordIdsAcquirer(
			$this->lbFactory, 'wbt_term_in_lang', 'wbtl_id', $this->logger,
			$ignoreReplica ? ReplicaMasterAwareRecordIdsAcquirer::FLAG_IGNORE_REPLICA : 0x0 );

		$termsArray = $this->mapToTextIds( $termsArray, $textIdsAcquirer );
		$termsArray = $this->mapToTextInLangIds( $termsArray, $textInLangIdsAcquirer );
		$termsArray = $this->mapToTypeIds( $termsArray );

		return $this->mapToTermInLangIds( $termsArray, $termInLangIdsAcquirer, $termIdsToRestore );
	}

	private function calcRecordHash( array $record ) {
		ksort( $record );
		return md5( serialize( $record ) );
	}

	private function filterUniqueRecords( array $records ) {
		$uniqueRecords = [];
		foreach ( $records as $record ) {
			$recordHash = $this->calcRecordHash( $record );
			$uniqueRecords[$recordHash] = $record;
		}

		return array_values( $uniqueRecords );
	}

}
